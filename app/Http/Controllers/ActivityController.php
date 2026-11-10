<?php

namespace App\Http\Controllers;

use App\Enums\ActivityStatus;
use App\Enums\Priority;
use App\Enums\ValidationStatus;
use App\Models\Activity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = Activity::query()
            ->visibleTo($user)
            ->with(['assignee:id,name', 'team:id,name'])
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('status'), function ($q) use ($request) {
                if ($request->input('status') === 'vencida') {
                    return $q->overdue();
                }
                if ($request->input('status') === 'por_validar') {
                    return $q->pendingValidation();
                }

                return $q->where('status', $request->input('status'));
            })
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->integer('assigned_to')))
            ->when($request->filled('team_id'), fn ($q) => $q->where('team_id', $request->integer('team_id')));

        $activities = $query
            ->orderByRaw("CASE WHEN status IN ('pendiente','en_progreso') THEN 0 ELSE 1 END")
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('activities.index', [
            'activities' => $activities,
            'people' => $user->canManageActivities() ? $this->visiblePeople($user) : collect(),
            'teams' => $this->teamsFor($user),
            'statuses' => ActivityStatus::options(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Activity::class);

        return view('activities.create', $this->formData($request->user(), new Activity([
            'priority' => Priority::Media->value,
            'status' => ActivityStatus::Pendiente->value,
            'assigned_to' => $request->integer('assigned_to') ?: null,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Activity::class);
        $user = $request->user();

        $data = $this->validated($request, $user);
        $data['created_by'] = $user->id;
        $data['team_id'] ??= User::find($data['assigned_to'])?->team_id;

        if ($request->hasFile('evidence')) {
            $data['evidence_path'] = $request->file('evidence')->store('evidencias', 'local');
        }

        $completed = $data['status'] === ActivityStatus::Completada->value;
        $data['completed_at'] = $completed ? now() : null;
        $data['validation_status'] = $completed ? ValidationStatus::Pendiente->value : null;

        $activity = Activity::create($data);

        return redirect()->route('activities.show', $activity)->with('status', 'Actividad creada y asignada.');
    }

    public function show(Activity $activity): View
    {
        $this->authorize('view', $activity);
        $activity->load(['assignee', 'creator', 'team', 'validator']);

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

        if ($request->hasFile('evidence')) {
            if ($activity->evidence_path) {
                Storage::disk('local')->delete($activity->evidence_path);
            }
            $data['evidence_path'] = $request->file('evidence')->store('evidencias', 'local');
        }

        $this->applyCompletion($activity, $data['status']);
        $activity->fill($data)->save();

        return redirect()->route('activities.show', $activity)->with('status', 'Actividad actualizada.');
    }

    /** Cambio rápido de estado desde el listado o el detalle. */
    public function updateStatus(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorize('update', $activity);

        $data = $request->validate([
            'status' => ['required', new Enum(ActivityStatus::class)],
        ]);

        $this->applyCompletion($activity, $data['status']);
        $activity->status = $data['status'];
        $activity->save();

        return back()->with('status', 'Estado actualizado a "'.$activity->status->label().'".');
    }

    /** Validar o rechazar una actividad completada. */
    public function review(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorize('review', $activity);

        $data = $request->validate([
            'decision' => ['required', 'in:validar,rechazar'],
            'validation_notes' => ['nullable', 'required_if:decision,rechazar', 'string', 'max:2000'],
        ], [
            'validation_notes.required_if' => 'Escribe el motivo del rechazo para que la persona sepa qué corregir.',
        ], [
            'validation_notes' => 'comentario',
        ]);

        $activity->validated_by = $request->user()->id;
        $activity->validated_at = now();
        $activity->validation_notes = $data['validation_notes'] ?? null;

        if ($data['decision'] === 'validar') {
            $activity->validation_status = ValidationStatus::Validada;
            $message = 'Actividad validada.';
        } else {
            // Se regresa para corregir: vuelve a "en progreso"
            $activity->validation_status = ValidationStatus::Rechazada;
            $activity->status = ActivityStatus::EnProgreso;
            $activity->completed_at = null;
            $message = 'Actividad rechazada. Regresó a "En progreso" con tu comentario.';
        }

        $activity->save();

        return back()->with('status', $message);
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

        // Al editar, conservar la persona asignada aunque ya no esté en la lista
        if ($activity) {
            $assignableIds[] = $activity->assigned_to;
        }

        $teamIds = $this->teamsFor($user)->pluck('id')->all();

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => ['required', 'integer', Rule::in($assignableIds)],
            'team_id' => ['nullable', 'integer', Rule::in($teamIds)],
            'priority' => ['required', new Enum(Priority::class)],
            'status' => ['required', new Enum(ActivityStatus::class)],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'evidence' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,heic,pdf'],
        ], [
            'assigned_to.in' => 'No puedes asignar actividades a esa persona.',
            'team_id.in' => 'No puedes usar ese equipo.',
        ], [
            'title' => 'título',
            'description' => 'descripción',
            'assigned_to' => 'persona asignada',
            'team_id' => 'equipo',
            'priority' => 'prioridad',
            'status' => 'estado',
            'due_date' => 'fecha límite',
            'notes' => 'notas',
            'evidence' => 'evidencia',
        ]);
    }

    /** Al completar, la actividad queda por validar; si deja de estar completada, se limpia la validación. */
    private function applyCompletion(Activity $activity, string $newStatus): void
    {
        if ($newStatus === ActivityStatus::Completada->value && $activity->status !== ActivityStatus::Completada) {
            $activity->completed_at = now();
            $activity->validation_status = ValidationStatus::Pendiente;
            $activity->validated_by = null;
            $activity->validated_at = null;
        } elseif ($newStatus !== ActivityStatus::Completada->value) {
            $activity->completed_at = null;
            if ($activity->validation_status !== ValidationStatus::Rechazada) {
                $activity->validation_status = null;
            }
        }
    }

    private function formData(User $user, Activity $activity): array
    {
        $people = $user->assignableUsers()->get(['id', 'name', 'role']);

        if ($activity->assigned_to && ! $people->contains('id', $activity->assigned_to)) {
            $people->push(User::find($activity->assigned_to, ['id', 'name', 'role']));
        }

        return [
            'activity' => $activity,
            'people' => $people->filter(),
            'teams' => $this->teamsFor($user),
            'statuses' => ActivityStatus::options(),
            'priorities' => Priority::options(),
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
