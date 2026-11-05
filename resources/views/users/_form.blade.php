@csrf
@php $isSelf = $user->exists && $user->is(auth()->user()); @endphp

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="name" class="label">Nombre completo *</label>
        <input id="name" name="name" value="{{ old('name', $user->name) }}" required class="input">
    </div>
    <div>
        <label for="email" class="label">Correo *</label>
        <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required class="input">
    </div>
    <div>
        <label for="phone" class="label">Teléfono</label>
        <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="input">
    </div>
    <div>
        <label for="role" class="label">Rol *</label>
        <select id="role" name="role" class="input" @disabled($isSelf)>
            @foreach ($roles as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $user->role?->value) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @if ($isSelf)
            <input type="hidden" name="role" value="{{ $user->role->value }}">
            <p class="mt-1 text-xs text-stone-500">No puedes cambiar tu propio rol.</p>
        @endif
    </div>
    <div>
        <label for="team_id" class="label">Equipo</label>
        <select id="team_id" name="team_id" class="input">
            <option value="">Sin equipo</option>
            @foreach ($teams as $team)
                <option value="{{ $team->id }}" @selected((int) old('team_id', $user->team_id) === $team->id)>{{ $team->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex items-end">
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1" @checked(old('active', $user->active ?? true)) @disabled($isSelf) class="size-4 rounded border-stone-300">
            Usuario activo
        </label>
        @if ($isSelf)
            <input type="hidden" name="active" value="1">
        @endif
    </div>

    <div class="border-t border-stone-200 pt-5 sm:col-span-2">
        <p class="text-sm font-medium">{{ $user->exists ? 'Cambiar contraseña' : 'Contraseña' }}</p>
        @if ($user->exists)
            <p class="text-xs text-stone-500">Déjalo vacío para conservar la actual.</p>
        @endif
    </div>
    <div>
        <label for="password" class="label">Contraseña {{ $user->exists ? '' : '*' }}</label>
        <input id="password" type="password" name="password" autocomplete="new-password" @required(! $user->exists) class="input">
    </div>
    <div>
        <label for="password_confirmation" class="label">Confirmar contraseña</label>
        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" class="input">
    </div>
</div>
