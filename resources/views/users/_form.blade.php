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
            <p class="mt-1 text-xs text-slate-500">No puedes cambiar tu propio rol.</p>
        @endif
    </div>
    <div>
        <label for="area" class="label">Área <span class="font-normal text-slate-500">(gerentes)</span></label>
        <input id="area" name="area" list="areas" value="{{ old('area', $user->area) }}" maxlength="60" class="input" placeholder="Coordinación, Territorial, Técnica, Agenda">
        <datalist id="areas">
            @foreach (['Coordinación', 'Territorial', 'Técnica', 'Agenda'] as $a)<option value="{{ $a }}">@endforeach
        </datalist>
        <p class="mt-1 text-xs text-slate-500">Es lo que ve el encargado: «Técnica te encomendó esta actividad», no el nombre.</p>
        @if ($user->folio_number)
            <p class="mt-2 text-sm">Número de encargado: <b class="font-mono">{{ $user->folioLabel() }}</b> <span class="text-xs text-slate-500">(sus actividades llevan folio {{ $user->folioLabel() }}.0001, .0002…)</span></p>
        @endif
    </div>
    <div>
        <label for="access_profile_id" class="label">Perfil de acceso <span class="font-normal text-slate-500">(Cuaderno, Estrategia, Minutas…)</span></label>
        <select id="access_profile_id" name="access_profile_id" class="input">
            <option value="">Sin perfil (gerente: acceso completo · encargado: sin acceso)</option>
            @foreach ($profiles as $profile)
                <option value="{{ $profile->id }}" @selected((int) old('access_profile_id', $user->access_profile_id) === $profile->id)>{{ $profile->name }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500">El administrador puede todo, tenga o no perfil. <a href="{{ route('profiles.index') }}" class="text-brand-700 hover:underline">Ver qué permite cada perfil</a>.</p>
    </div>
    <div class="flex items-end">
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="active" value="0">
            <input type="checkbox" name="active" value="1" @checked(old('active', $user->active ?? true)) @disabled($isSelf) class="size-4 rounded border-slate-300">
            Usuario activo
        </label>
        @if ($isSelf)
            <input type="hidden" name="active" value="1">
        @endif
    </div>

    <div class="border-t border-slate-200 pt-5 sm:col-span-2">
        <p class="text-sm font-medium">{{ $user->exists ? 'Cambiar contraseña' : 'Contraseña' }}</p>
        @if ($user->exists)
            <p class="text-xs text-slate-500">Déjalo vacío para conservar la actual.</p>
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
