@csrf
<div class="grid gap-5">
    <div>
        <span class="label">Número de equipo</span>
        <p class="flex items-center gap-3">
            <span class="badge bg-brand-50 font-mono text-base text-brand-700">ID{{ $team->number ?? '—' }}</span>
            <span class="text-xs text-slate-500">Se asigna solo. Las actividades del equipo llevan el folio ID{{ $team->number }}.0001, ID{{ $team->number }}.0002…</span>
        </p>
    </div>
    <div>
        <label for="name" class="label">Nombre *</label>
        <input id="name" name="name" value="{{ old('name', $team->name) }}" required class="input">
    </div>
    <div>
        <label for="description" class="label">Descripción</label>
        <textarea id="description" name="description" rows="3" class="input">{{ old('description', $team->description) }}</textarea>
    </div>
    <div>
        <label for="leader_id" class="label">Encargado</label>
        <select id="leader_id" name="leader_id" class="input">
            <option value="">Sin encargado</option>
            @foreach ($leaders as $leader)
                <option value="{{ $leader->id }}" @selected((int) old('leader_id', $team->leader_id) === $leader->id)>{{ $leader->name }}</option>
            @endforeach
        </select>
        @if ($leaders->isEmpty())
            <p class="mt-1 text-xs text-amber-700">Primero crea un usuario con rol de encargado.</p>
        @endif
    </div>
</div>
