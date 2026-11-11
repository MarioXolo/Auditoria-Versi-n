@csrf

<div class="grid gap-5 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="title" class="label">Título *</label>
        <input id="title" name="title" value="{{ old('title', $activity->title) }}" required maxlength="255" class="input">
    </div>

    <div class="sm:col-span-2">
        <label for="description" class="label">Descripción</label>
        <textarea id="description" name="description" rows="4" class="input">{{ old('description', $activity->description) }}</textarea>
    </div>

    @if (auth()->user()->isEmpleado())
        <div class="rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-700 sm:col-span-2">
            La actividad quedará a tu nombre. Tu encargado la revisará y la marcará como completada o incompleta.
        </div>
    @else
    <div>
        <label for="assigned_to" class="label">Asignar a *</label>
        <select id="assigned_to" name="assigned_to" required class="input">
            <option value="">Selecciona una persona</option>
            @foreach ($people as $person)
                <option value="{{ $person->id }}" @selected((int) old('assigned_to', $activity->assigned_to) === $person->id)>
                    {{ $person->name }} ({{ $person->role->label() }})
                </option>
            @endforeach
        </select>
        @if ($people->isEmpty())
            <p class="mt-1 text-xs text-amber-700">No tienes personas disponibles para asignar. Pide a un administrador que te asigne un equipo.</p>
        @endif
    </div>

    <div>
        <label for="team_id" class="label">Equipo</label>
        <select id="team_id" name="team_id" class="input">
            <option value="">El de la persona asignada</option>
            @foreach ($teams as $team)
                <option value="{{ $team->id }}" @selected((int) old('team_id', $activity->team_id) === $team->id)>{{ $team->name }}</option>
            @endforeach
        </select>
    </div>
    @endif

    <div>
        <label for="priority" class="label">Prioridad</label>
        <select id="priority" name="priority" class="input">
            @foreach ($priorities as $value => $label)
                <option value="{{ $value }}" @selected(old('priority', $activity->priority?->value ?? $activity->priority) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="due_date" class="label">Fecha límite</label>
        <input id="due_date" type="date" name="due_date" value="{{ old('due_date', $activity->due_date?->format('Y-m-d')) }}" class="input">
    </div>

    @if ($activity->exists)
        <div>
            <label for="status" class="label">Estado</label>
            @if ($activity->status->isMarked())
                <p class="input bg-slate-50 text-slate-600">{{ $activity->status->label() }} (se cambia desde la revisión)</p>
            @else
                <select id="status" name="status" class="input">
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $activity->status->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            @endif
        </div>
    @endif

    <div>
        <label for="evidence" class="label">Evidencia (foto o PDF)</label>
        <x-file-input name="evidence" accept="image/*,application/pdf" button="Subir evidencia" />
        @if ($activity->evidence_path)
            <p class="mt-1 text-xs text-slate-500">Ya tiene evidencia. Si subes otra, la reemplaza.</p>
        @endif
    </div>

    <div class="sm:col-span-2">
        <label for="notes" class="label">Notas de seguimiento</label>
        <textarea id="notes" name="notes" rows="3" class="input">{{ old('notes', $activity->notes) }}</textarea>
    </div>
</div>
