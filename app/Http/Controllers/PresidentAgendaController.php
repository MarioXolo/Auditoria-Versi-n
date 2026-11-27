<?php

namespace App\Http\Controllers;

use App\Enums\ActivityStatus;
use App\Enums\Role;
use App\Models\ActivityType;
use App\Models\Community;
use App\Models\PresidentActivity;
use App\Models\User;
use App\Services\PhotoStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Agenda del Presidente: administradores y gerentes. Mismos campos y reglas que Actividades
 * (tipo, comunidad, asignada a, inicio–fin, estado, evidencia, marcar Concluido/No completada),
 * pero totalmente aparte de las actividades de los demás usuarios.
 */
class PresidentAgendaController extends Controller
{
    public function index(Request $request): View
    {
        $query = PresidentActivity::query()
            ->with(['type:id,name', 'community:id,name', 'assignee:id,name'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('folio', 'like', $term)->orWhere('evidence_text', 'like', $term)
                    ->orWhereHas('type', fn ($t) => $t->where('name', 'like', $term))
                    ->orWhereHas('community', fn ($c) => $c->where('name', 'like', $term)));
            })
            ->when(ActivityStatus::tryFrom((string) $request->input('status')), fn ($q, $s) => $q->where('status', $s->value))
            ->when($request->input('status') === 'vencidas', fn ($q) => $q->whereIn('status', [ActivityStatus::NoIniciado->value, ActivityStatus::EnProceso->value])->whereDate('end_date', '<', today()))
            ->when($request->filled('community_id'), fn ($q) => $q->where('community_id', $request->integer('community_id')))
            ->when($request->filled('activity_type_id'), fn ($q) => $q->where('activity_type_id', $request->integer('activity_type_id')))
            ->when($request->filled('mes') && preg_match('/^\d{4}-\d{2}$/', (string) $request->input('mes')), function ($q) use ($request) {
                $from = \Carbon\Carbon::parse($request->input('mes').'-01');
                $q->whereDate('start_date', '<=', $from->copy()->endOfMonth())->whereDate('end_date', '>=', $from);
            })
            ->orderBy('start_date')
            ->orderBy('id');

        $all = PresidentActivity::get(['status', 'end_date']);

        return view('president.index', [
            'activities' => $query->paginate(30)->withQueryString(),
            'counts' => [
                'total' => $all->count(),
                'abiertas' => $all->filter(fn ($a) => $a->status->isOpen())->count(),
                'vencidas' => $all->filter(fn ($a) => $a->isOverdue())->count(),
                'concluidas' => $all->where('status', ActivityStatus::Concluido)->count(),
            ],
            'types' => ActivityType::orderBy('name')->get(['id', 'name']),
            'communities' => Community::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Calendario propio de la Agenda del Presidente: cada actividad aparece en todos los días que dura. */
    public function calendar(Request $request): View
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('mes'))
            ? \Carbon\CarbonImmutable::parse($request->input('mes').'-01')
            : today()->toImmutable()->startOfMonth();
        $gridStart = $month->startOfWeek(\Carbon\CarbonInterface::MONDAY);
        $gridEnd = $month->endOfMonth()->endOfWeek(\Carbon\CarbonInterface::SUNDAY);

        $activities = PresidentActivity::with(['type:id,name', 'community:id,name', 'assignee:id,name'])
            ->whereDate('start_date', '<=', $gridEnd)
            ->whereDate('end_date', '>=', $gridStart)
            ->when(ActivityStatus::tryFrom((string) $request->input('status')), fn ($q, $s) => $q->where('status', $s->value))
            ->when($request->filled('community_id'), fn ($q) => $q->where('community_id', $request->integer('community_id')))
            ->orderBy('start_date')->orderBy('id')
            ->get();

        $days = [];
        foreach ($activities as $a) {
            $from = $a->start_date->max($gridStart);
            $to = $a->end_date->min($gridEnd);
            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                $days[$d->toDateString()][] = $a;
            }
        }

        return view('president.calendar', [
            'month' => $month,
            'gridStart' => $gridStart,
            'gridEnd' => $gridEnd,
            'days' => $days,
            'inMonth' => $activities->filter(fn ($a) => $a->start_date->lte($month->endOfMonth()) && $a->end_date->gte($month)),
            'communities' => Community::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('president.create', $this->formData(new PresidentActivity([
            'assigned_to' => auth()->id(), 'status' => ActivityStatus::NoIniciado, 'start_date' => today(), 'end_date' => today(),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        if ($request->hasFile('evidence')) {
            $data['evidence_path'] = PhotoStorage::store($request->file('evidence'), 'agenda-presidente');
        }
        $activity = PresidentActivity::create($data);

        return redirect()->route('president.show', $activity)->with('status', "Actividad {$activity->folio} agregada a la Agenda del Presidente.");
    }

    public function show(PresidentActivity $activity): View
    {
        $activity->load(['type', 'community', 'assignee', 'creator', 'marker']);

        return view('president.show', ['activity' => $activity]);
    }

    public function edit(PresidentActivity $activity): View
    {
        abort_if($activity->isLocked(), 403, 'Esta actividad ya está concluida y no se puede modificar.');

        return view('president.edit', $this->formData($activity));
    }

    public function update(Request $request, PresidentActivity $activity): RedirectResponse
    {
        abort_if($activity->isLocked(), 403, 'Esta actividad ya está concluida y no se puede modificar.');
        $data = $this->validated($request, $activity);
        if ($request->hasFile('evidence')) {
            PhotoStorage::delete($activity->evidence_path);
            $data['evidence_path'] = PhotoStorage::store($request->file('evidence'), 'agenda-presidente');
        } elseif ($request->boolean('remove_photo') && $activity->evidence_path) {
            PhotoStorage::delete($activity->evidence_path);
            $data['evidence_path'] = null;
        }
        $activity->update($data);

        return redirect()->route('president.show', $activity)->with('status', "Actividad {$activity->folio} actualizada.");
    }

    public function destroy(Request $request, PresidentActivity $activity): RedirectResponse
    {
        // Una concluida solo la puede borrar el administrador (por si se cargó por error)
        abort_if($activity->isLocked() && ! $request->user()->isAdmin(), 403, 'Una actividad concluida solo la puede borrar el administrador.');
        PhotoStorage::delete($activity->evidence_path);
        $activity->delete();

        return redirect()->route('president.index')->with('status', "Actividad {$activity->folio} eliminada.");
    }

    /** ✓ Concluido (definitivo, con confirmación) · ✗ No completada · quitar el rojo. */
    public function mark(Request $request, PresidentActivity $activity): RedirectResponse
    {
        abort_if($activity->isLocked(), 403, 'Esta actividad ya está concluida.');
        $data = $request->validate([
            'marca' => ['required', Rule::in(['concluido', 'no_completada', 'sin_marcar'])],
            'mark_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['marca'] === 'sin_marcar') {
            $activity->update(['status' => ActivityStatus::EnProceso, 'marked_by' => null, 'marked_at' => null, 'mark_notes' => null, 'completed_at' => null]);

            return back()->with('status', 'Se quitó la marca; la actividad vuelve a «En proceso».');
        }

        $status = $data['marca'] === 'concluido' ? ActivityStatus::Concluido : ActivityStatus::NoCompletada;
        $activity->update([
            'status' => $status,
            'marked_by' => $request->user()->id,
            'marked_at' => now(),
            'mark_notes' => $data['mark_notes'] ?? null,
            'completed_at' => $status === ActivityStatus::Concluido ? now() : null,
        ]);

        return back()->with('status', "Actividad {$activity->folio} marcada como {$status->label()}.");
    }

    /** Estado editable a mano: No iniciado, En proceso o Suspendida. */
    public function updateStatus(Request $request, PresidentActivity $activity): RedirectResponse
    {
        abort_if($activity->status->isMarked(), 403, 'Primero quita la marca para cambiar el estado.');
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(ActivityStatus::editableOptions()))]]);
        $activity->update(['status' => $data['status']]);

        return back()->with('status', 'Estado actualizado.');
    }

    public function evidence(PresidentActivity $activity)
    {
        abort_unless(PhotoStorage::exists($activity->evidence_path), 404);

        return PhotoStorage::response($activity->evidence_path);
    }

    // ---------- Auxiliares ----------

    /** Responsables posibles: administradores y gerentes activos (son quienes ven esta agenda). */
    private function people(?PresidentActivity $activity = null)
    {
        return User::where(fn ($q) => $q->where('active', true)->whereIn('role', [Role::Admin->value, Role::Gerente->value]))
            ->when($activity?->assigned_to, fn ($q, $id) => $q->orWhere('id', $id))
            ->orderBy('name')->get(['id', 'name', 'role']);
    }

    private function formData(PresidentActivity $activity): array
    {
        return [
            'activity' => $activity,
            'people' => $this->people($activity),
            'types' => ActivityType::active()->orWhere('id', $activity->activity_type_id)->orderBy('name')->get(['id', 'name']),
            'communities' => Community::active()->orWhere('id', $activity->community_id)->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function validated(Request $request, ?PresidentActivity $activity = null): array
    {
        $typeIds = ActivityType::active()->pluck('id')->push($activity?->activity_type_id)->filter()->all();
        $communityIds = Community::active()->pluck('id')->push($activity?->community_id)->filter()->all();

        $data = $request->validate([
            'activity_type_id' => ['required', Rule::in([...$typeIds, 'otro'])],
            'activity_type_new' => ['nullable', 'required_if:activity_type_id,otro', 'string', 'max:120'],
            'community_id' => ['required', Rule::in([...$communityIds, 'otro'])],
            'community_new' => ['nullable', 'required_if:community_id,otro', 'string', 'max:120'],
            'assigned_to' => ['required', 'integer', Rule::in($this->people($activity)->pluck('id')->all())],
            'status' => ['nullable', Rule::in(array_keys(ActivityStatus::editableOptions()))],
            'evidence' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,heic,heif'],
            'evidence_text' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ], [
            'activity_type_id.in' => 'Elige un tipo de actividad de la lista o escribe uno nuevo.',
            'community_id.in' => 'Elige una comunidad de la lista o escribe una nueva.',
            'activity_type_new.required_if' => 'Escribe el tipo de actividad.',
            'community_new.required_if' => 'Escribe el nombre de la comunidad.',
            'assigned_to.in' => 'Solo se puede asignar a un administrador o gerente.',
            'evidence.mimes' => 'La evidencia debe ser una fotografía (JPG, PNG, WEBP o HEIC).',
            'end_date.after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.',
        ], [
            'activity_type_id' => 'tipo de actividad', 'community_id' => 'comunidad', 'assigned_to' => 'persona asignada',
            'evidence' => 'fotografía', 'evidence_text' => 'evidencia en texto', 'start_date' => 'fecha de inicio', 'end_date' => 'fecha de fin',
        ]);

        $data['activity_type_id'] = $this->catalogId(ActivityType::class, $data['activity_type_id'], $data['activity_type_new'] ?? null);
        $data['community_id'] = $this->catalogId(Community::class, $data['community_id'], $data['community_new'] ?? null);
        $data['status'] = $data['status'] ?? ($activity?->status?->value ?? ActivityStatus::NoIniciado->value);
        if ($activity?->status?->isMarked()) {
            $data['status'] = $activity->status->value;   // la marca se cambia con los botones, no en el formulario
        }
        unset($data['activity_type_new'], $data['community_new'], $data['evidence']);

        return $data;
    }

    private function catalogId(string $model, int|string $selected, ?string $typed): int
    {
        if ($selected !== 'otro') {
            return (int) $selected;
        }
        $name = preg_replace('/\s+/', ' ', trim((string) $typed));
        $item = $model::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first() ?? $model::create(['name' => $name, 'active' => true]);
        if (! $item->active) {
            $item->update(['active' => true]);
        }

        return $item->id;
    }
}
