@csrf
<div class="grid gap-5">
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
