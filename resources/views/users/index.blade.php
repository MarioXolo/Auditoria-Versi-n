@extends('layouts.app')

@section('title', 'Usuarios')

@section('header')
    <div>
        <h1 class="text-2xl font-semibold">Usuarios</h1>
        <p class="text-sm text-slate-500">{{ $users->total() }} registrados</p>
    </div>
    @if (auth()->user()->isAdmin())
        <a href="{{ route('users.create') }}" class="btn btn-primary">Nuevo usuario</a>
    @else
        <p class="text-sm text-slate-500">Solo el administrador puede crear o editar usuarios.</p>
    @endif
@endsection

@section('content')
    <form method="GET" class="card mb-5 grid gap-3 p-4 sm:grid-cols-4">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nombre o correo" class="input sm:col-span-2">
        <select name="role" class="input" onchange="this.form.submit()">
            <option value="">Todos los roles</option>
            @foreach ($roles as $value => $label)
                <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="team_id" class="input" onchange="this.form.submit()">
            <option value="">Todos los equipos</option>
            @foreach ($teams as $team)
                <option value="{{ $team->id }}" @selected((int) request('team_id') === $team->id)>{{ $team->name }}</option>
            @endforeach
        </select>
    </form>

    <div class="card overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="table-head">Nombre</th>
                    <th class="table-head">Rol</th>
                    <th class="table-head">Equipo</th>
                    <th class="table-head">Estado</th>
                    <th class="table-head"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $user)
                    <tr @class(['opacity-60' => ! $user->active])>
                        <td class="table-cell">
                            <span class="font-medium text-slate-900">{{ $user->name }}</span>
                            <span class="block text-xs text-slate-500">{{ $user->email }}</span>
                        </td>
                        <td class="table-cell">{{ $user->role->label() }}</td>
                        <td class="table-cell">{{ $user->team?->name ?? '—' }}</td>
                        <td class="table-cell">
                            <span @class(['badge', 'bg-emerald-100 text-emerald-800' => $user->active, 'bg-slate-200 text-slate-600' => ! $user->active])>
                                {{ $user->active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="table-cell text-right whitespace-nowrap">
                            @if (auth()->user()->isAdmin())
                            <a href="{{ route('users.edit', $user) }}" class="text-brand-600 hover:underline">Editar</a>
                            @unless ($user->is(auth()->user()))
                                <form method="POST" action="{{ route('users.toggle', $user) }}" class="ml-3 inline">
                                    @csrf
                                    @method('PATCH')
                                    <button class="text-slate-500 hover:text-slate-900 hover:underline">{{ $user->active ? 'Desactivar' : 'Activar' }}</button>
                                </form>
                            @endunless
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="table-cell py-8 text-center text-slate-500">No se encontraron usuarios.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-5">{{ $users->links() }}</div>
@endsection
