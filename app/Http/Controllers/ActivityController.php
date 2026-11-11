<?php

namespace App\Http\Controllers;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Community;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = Activity::query()
            ->visibleTo($user)
            ->with(['assignee:id,name,role', 'team:id,name', 'type:id,name', 'community:id,name'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim((string) $request->string('q'));

                // «ID1.0012» o «1.12» buscan un folio exacto; «ID1» todo el equipo 1
                if (preg_match('/^(?:id)?\s*(\d{1,2})\.(\d+)$/i', $term, $m)) {
                    return $q->where('folio_group', (int) $m[1])->where('folio_seq', (int) $m[2]);
                }
                if (preg_match('/^id\s*(\d{1,2})$/i', $term, $m)) {
                    return $q->where('folio_group', (int) $m[1]);
                }

                return $q->where(fn ($w) => $w
                    ->where('folio', 'like', "%{$term}%")
                    ->orWhereHas('type', fn ($t) => $t->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('community', fn ($c) => $c->where('name', 'like', "%{$term}%"))
                    ->orWhere('evidence_text', 'like', "%{$term}%"));
            })
            ->when($request->filled('activity_type_id'), fn ($q) => $q->where('activity_type_id', $request->integer('activity_type_id')))
            ->when($request->filled('community_id'), fn ($q) => $q->where('community_id', $request->integer('community_id')))
            ->when($request->filled('status'), function ($q) use ($request) {
                if ($request->input('status') === 'vencida') {
                    return $q->overdue();
                }
                if ($request->input('status') === 'sin_marcar') {
                    return $q->whereIn('status', [ActivityStatus::NoIniciado->value, ActivityStatus::EnProceso->value]);
                }

                return $q->where('status', $request->input('status'));
            })
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->integer('assigned_to')))
            ->when($request->filled('team_id'), fn ($q) => $q->where('team_id', $request->integer('team_id')));

        $activities = $query
            ->orderByRaw("CASE WHEN status IN ('no_iniciado','en_proceso') THEN 0 WHEN status = 'no_completada' THEN 1 WHEN status = 'suspendida' THEN 2 ELSE 3 END")
            ->orderBy('end_date')
            ->orderBy('folio_group')
            ->orderBy('folio_seq')
            ->paginate(15)
            ->withQueryString();

        return view('activities.index', [
            'activities' => $activities,
            'people' => $user->canManageActivities() ? $this->visiblePeople($user) : collect(),
            'teams' => $this->teamsFor($user),
            'statuses' => ActivityStatus::options(),
            'types' => ActivityType::orderBy('name')->get(['id', 'name']),
            'communities' => Community::orderBy('name')->get(['id', 'name']),
            'canMarkAny' => $user->canManageActivities(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Activity::class);

        return view('activities.create', $this->formData($request->user(), new Activity([
            'status' => ActivityStatus::NoIniciado->value,
            'assigned_to' => $request->integer('assigned_to') ?: null,
            'start_date' => today(),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Activity::class);
        $user = $request->user();

        // El empleado solo se carga actividades a sí mismo
        if ($user->isEmpleado()) {
            $request->merge(['assigned_to' => $user->id]);
        }

        $data = $this->validated($request, $user);
        $data['created_by'] = $user->id;
        $data['team_id'] = User::find($data['assigned_to'])?->team_id;
        // Al crear se elige No iniciado, En proceso o Suspendida; Concluido y No completada solo los pone un superior
        $data['status'] = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(ActivityStatus::editableOptions()))],
        ], [], ['status' => 'estado'])['status'] ?? ActivityStatus::NoIniciado->value;

        if ($request->hasFile('evidence')) {
            $data['evidence_path'] = $request->file('evidence')->store('evidencias', 'local');
        }

        $activity = Activity::create($data);

        $message = $user->isEmpleado()
            ? "Actividad {$activity->code} cargada. Tu encargado la marcará cuando la revise."
            : "Actividad {$activity->code} creada y asignada.";

        return redirect()->route('activities.show', $activity)->with('status', $message);
    }

    public function show(Activity $activity): View
    {
        $this->authorize('view', $activity);
        $activity->load(['assignee', 'creator', 'team', 'marker', 'type', 'community']);

        return view('activities.show', ['activity' => $activity]);
    }

    public function edit(Request $request, Activity $activity): View
    {
        $this->authorize('update', $activity);

        return view('activities.edit', $this->formData($request->user(), $activity));
    }

    public function update(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorize('update', $activity);

        $data = $this->validated($request, $request->user(), $activity);
        if ((int) $data['assigned_to'] !== $activity->assigned_to) {
            $data['team_id'] = User::find($data['assigned_to'])?->team_id;
        }

        // A mano solo se elige No iniciado, En proceso o Suspendida; los otros dos se ponen con el checkbox
        $status = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(ActivityStatus::editableOptions()))],
        ])['status'] ?? null;

        if ($status && $status !== $activity->status->value) {
            $data['status'] = $status;
            $data['completed_at'] = null;
            $data['marked_by'] = null;
            $data['marked_at'] = null;
        }

        if ($request->hasFile('evidence')) {
            if ($activity->evidence_path) {
                Storage::disk('local')->delete($activity->evidence_path);
            }
            $data['evidence_path'] = $request->file('evidence')->store('evidencias', 'local');
        } elseif ($request->boolean('remove_photo') && $activity->evidence_path) {
            Storage::disk('local')->delete($activity->evidence_path);
            $data['evidence_path'] = null;
        }

        $activity->fill($data)->save();

        return redirect()->route('activities.show', $activity)->with('status', 'Actividad actualizada.');
    }

    /**
     * Checkbox de un superior: ✓ Concluido (verde), ✗ No completada (rojo) o quitar la marca.
     */
    public function mark(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorize('mark', $activity);

        $data = $request->validate([
            'marca' => ['required', 'in:concluido,no_completada,sin_marcar'],
            'mark_notes' => ['nullable', 'string', 'max:2000'],
        ], [], ['mark_notes' => 'comentario']);

        if ($data['marca'] === 'sin_marcar') {
            $activity->status = ActivityStatus::EnProceso;
            $activity->completed_at = null;
            $activity->marked_by = null;
            $activity->marked_at = null;
            $activity->mark_notes = null;
            $message = 'Se quitó la marca; la actividad regresó a «En proceso».';
        } else {
            $activity->status = $data['marca'] === 'concluido' ? ActivityStatus::Concluido : ActivityStatus::NoCompletada;
            $activity->completed_at = $data['marca'] === 'concluido' ? now() : null;
            $activity->marked_by = $request->user()->id;
            $activity->marked_at = now();
            if ($request->has('mark_notes')) {
                $activity->mark_notes = $data['mark_notes'] ?? null;
            }
            $message = $data['marca'] === 'concluido'
                ? 'Actividad marcada como Concluido.'
                : 'Actividad marcada como No completada.';
        }

        $activity->save();

        return back()->with('status', $message);
    }

    /** Cambio rápido de estado manual; también lo usa el empleado asignado. */
    public function updateStatus(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorize('changeStatus', $activity);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(ActivityStatus::editableOptions()))],
        ], [], ['status' => 'estado']);

        $activity->update(['status' => $data['status']]);

        return back()->with('status', 'Estado actualizado a «'.$activity->status->label().'».');
    }

    public function destroy(Activity $activity): RedirectResponse
    {
        $this->authorize('delete', $activity);

        if ($activity->evidence_path) {
            Storage::disk('local')->delete($activity->evidence_path);
        }

        $activity->delete();

        return redirect()->route('activities.index')->with('status', 'Actividad eliminada.');
    }

    /** Descarga protegida de la evidencia: solo quien puede ver la actividad. */
    public function evidence(Activity $activity): StreamedResponse
    {
        $this->authorize('view', $activity);

        abort_unless($activity->evidence_path && Storage::disk('local')->exists($activity->evidence_path), 404);

        return Storage::disk('local')->response($activity->evidence_path);
    }

    // ---------- Auxiliares ----------

    private function validated(Request $request, User $user, ?Activity $activity = null): array
    {
        $assignableIds = $user->assignableUsers()->pluck('id')->all();

        // Al editar, conservar la persona, el tipo y la comunidad aunque ya no estén disponibles
        if ($activity) {
            $assignableIds[] = $activity->assigned_to;
        }

        $typeIds = ActivityType::active()->pluck('id')->push($activity?->activity_type_id)->filter()->all();
        $communityIds = Community::active()->pluck('id')->push($activity?->community_id)->filter()->all();

        // «otro» = escribirlo a mano; se agrega al catálogo al guardar
        $data = $request->validate([
            'activity_type_id' => ['required', Rule::in([...$typeIds, 'otro'])],
            'activity_type_new' => ['nullable', 'required_if:activity_type_id,otro', 'string', 'max:120'],
            'community_id' => ['required', Rule::in([...$communityIds, 'otro'])],
            'community_new' => ['nullable', 'required_if:community_id,otro', 'string', 'max:120'],
            'assigned_to' => ['required', 'integer', Rule::in($assignableIds)],
            'evidence' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,heic,heif'],
            'evidence_text' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ], [
            'assigned_to.in' => 'No puedes asignar actividades a esa persona.',
            'activity_type_id.in' => 'Elige un tipo de actividad de la lista o escribe uno nuevo.',
            'community_id.in' => 'Elige una comunidad de la lista o escribe una nueva.',
            'activity_type_new.required_if' => 'Escribe el tipo de actividad.',
            'community_new.required_if' => 'Escribe el nombre de la comunidad.',
            'evidence.mimes' => 'La evidencia debe ser una fotografía (JPG, PNG, WEBP o HEIC).',
            'end_date.after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.',
        ], [
            'activity_type_id' => 'tipo de actividad',
            'community_id' => 'comunidad',
            'activity_type_new' => 'nuevo tipo de actividad',
            'community_new' => 'nueva comunidad',
            'assigned_to' => 'persona asignada',
            'evidence' => 'fotografía',
            'evidence_text' => 'evidencia en texto',
            'start_date' => 'fecha de inicio',
            'end_date' => 'fecha de fin',
        ]);

        $data['activity_type_id'] = $this->catalogId(ActivityType::class, $data['activity_type_id'], $data['activity_type_new'] ?? null);
        $data['community_id'] = $this->catalogId(Community::class, $data['community_id'], $data['community_new'] ?? null);

        unset($data['evidence'], $data['activity_type_new'], $data['community_new']);

        return $data;
    }

    /**
     * Id del catálogo: el elegido de la lista o el escrito a mano. Lo escrito se busca
     * sin distinguir mayúsculas para no duplicar; si no existe, se agrega al catálogo.
     *
     * @param  class-string<ActivityType|Community>  $model
     */
    private function catalogId(string $model, int|string $selected, ?string $typed): int
    {
        if ($selected !== 'otro') {
            return (int) $selected;
        }

        $name = preg_replace('/\s+/', ' ', trim((string) $typed));
        $item = $model::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            ?? $model::create(['name' => $name, 'active' => true]);

        if (! $item->active) {
            $item->update(['active' => true]);
        }

        return $item->id;
    }

    private function formData(User $user, Activity $activity): array
    {
        $people = $user->assignableUsers()->get(['id', 'name', 'role', 'team_id']);

        if ($activity->assigned_to && ! $people->contains('id', $activity->assigned_to)) {
            $people->push(User::find($activity->assigned_to, ['id', 'name', 'role', 'team_id']));
        }
        $people = $people->filter();

        // Vista previa del folio: depende del equipo de la persona asignada
        $numbers = Team::pluck('number', 'id');
        $groupOf = fn (?int $teamId) => $teamId ? (int) ($numbers[$teamId] ?? 0) : 0;
        $previews = $people->mapWithKeys(fn ($p) => [$p->id => Activity::nextCode($groupOf($p->team_id))]);
        $code = match (true) {
            $activity->exists => $activity->code,
            $user->isEmpleado() => Activity::nextCode($groupOf($user->team_id)),
            (bool) $activity->assigned_to => $previews[$activity->assigned_to] ?? Activity::nextCode(0),
            // Si todas las personas son del mismo equipo (caso del encargado), el folio ya se conoce
            $previews->unique()->count() === 1 => $previews->first(),
            default => null,
        };

        return [
            'activity' => $activity,
            'people' => $people,
            'folioPreviews' => $activity->exists ? collect() : $previews,
            'teams' => $this->teamsFor($user),
            'statuses' => ActivityStatus::editableOptions(),
            'types' => ActivityType::active()->orWhere('id', $activity->activity_type_id)->orderBy('name')->get(['id', 'name']),
            'communities' => Community::active()->orWhere('id', $activity->community_id)->orderBy('name')->get(['id', 'name']),
            'code' => $code,
        ];
    }

    private function teamsFor(User $user)
    {
        if ($user->seesEverything()) {
            return Team::orderBy('name')->get(['id', 'name']);
        }

        if ($user->isEncargado()) {
            return $user->ledTeams()->orderBy('name')->get(['id', 'name']);
        }

        return collect();
    }

    private function visiblePeople(User $user)
    {
        if ($user->seesEverything()) {
            return User::orderBy('name')->get(['id', 'name']);
        }

        return User::whereIn('id', [...$user->teamMemberIds(), $user->id])->orderBy('name')->get(['id', 'name']);
    }
}
