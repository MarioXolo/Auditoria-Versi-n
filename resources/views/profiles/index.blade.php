@extends('layouts.app')

@section('title', 'Perfiles de acceso')

@use('App\Support\Access')

@section('header')
    <div>
        <h1 class="text-2xl font-semibold">Perfiles de acceso</h1>
        <p class="text-sm text-slate-500">Qué puede ver, capturar, revisar y exportar cada perfil. Se asignan en cada usuario.</p>
    </div>
    <a href="{{ route('users.index') }}" class="btn btn-secondary">← Usuarios</a>
@endsection


@section('content')
    <div class="mb-5 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
        <p><b>Ver</b>: entrar y consultar · <b>Capturar</b>: agregar, editar y borrar · <b>Revisar</b>: aprobar o cambiar estatus de control (p. ej. reportar un evento al IEEQ) · <b>Exportar</b>: descargar CSV. Marcar cualquier otra acción incluye «Ver».</p>
        <p class="mt-1">El <b>administrador</b> puede todo. Un <b>gerente sin perfil</b> conserva el acceso completo de antes; un <b>encargado sin perfil</b> no entra a estos módulos. Sus actividades no cambian.</p>
    </div>

    <div class="space-y-6">
        @foreach ([...$profiles, null] as $profile)
            <form method="POST" action="{{ $profile ? route('profiles.update', $profile) : route('profiles.store') }}" class="card overflow-hidden" @if (! $profile) id="nuevo" @endif>
                @csrf
                @if ($profile) @method('PUT') @endif
                <div class="flex flex-wrap items-end gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4">
                    <div class="min-w-48 flex-1">
                        <label class="label text-xs" for="n{{ $profile?->id ?? 'new' }}">{{ $profile ? 'Perfil' : 'Nuevo perfil' }}</label>
                        <input id="n{{ $profile?->id ?? 'new' }}" name="name" value="{{ $profile?->name }}" required maxlength="80" class="input font-semibold" placeholder="Nombre del perfil">
                    </div>
                    <div class="min-w-64 flex-[2]">
                        <label class="label text-xs" for="d{{ $profile?->id ?? 'new' }}">Descripción</label>
                        <input id="d{{ $profile?->id ?? 'new' }}" name="description" value="{{ $profile?->description }}" maxlength="255" class="input">
                    </div>
                    @if ($profile)
                        <span class="pb-2 text-xs text-slate-500">{{ $profile->users_count }} {{ $profile->users_count === 1 ? 'usuario' : 'usuarios' }}</span>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="table-head">Módulo</th>
                                @foreach (Access::ACTIONS as $a => $label)<th class="table-head text-center">{{ $label }}</th>@endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach (Access::MODULES as $m => [$mLabel, $mDesc])
                                <tr>
                                    <td class="table-cell"><span class="font-medium">{{ $mLabel }}</span><span class="block text-xs text-slate-500">{{ $mDesc }}</span></td>
                                    @foreach (Access::ACTIONS as $a => $label)
                                        <td class="table-cell text-center">
                                            <input type="checkbox" name="permisos[{{ $m }}][]" value="{{ $a }}" class="size-4 rounded border-slate-300" aria-label="{{ $label }} {{ $mLabel }}" @checked($profile?->allows($m, $a))>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-3">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="solo_sus_votantes" value="1" class="size-4 rounded border-slate-300" @checked($profile?->solo_sus_votantes)>
                        En la base de votantes, <b>solo ve y captura a sus propios votantes</b> (promotor)
                    </label>
                    <div class="flex items-center gap-3">
                        <button class="btn btn-primary">{{ $profile ? 'Guardar' : 'Crear perfil' }}</button>
                    </div>
                </div>
            </form>
            @if ($profile)
                <form method="POST" action="{{ route('profiles.destroy', $profile) }}" class="-mt-4 text-right" onsubmit="return confirm('¿Eliminar el perfil «{{ $profile->name }}»? Quienes lo tengan quedarán sin perfil.')">
                    @csrf
                    @method('DELETE')
                    <button class="text-xs font-medium text-rose-600 hover:underline">Eliminar perfil</button>
                </form>
            @endif
        @endforeach
    </div>
@endsection
