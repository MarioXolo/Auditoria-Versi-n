@extends('layouts.app')

@section('title', 'Editar equipo')

@section('header')
    <div>
        <a href="{{ route('teams.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Equipos</a>
        <h1 class="mt-1 text-2xl font-semibold">{{ $team->name }}</h1>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('teams.update', $team) }}" class="card p-6 lg:col-span-2">
            @method('PUT')
            @include('teams._form')
            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('teams.index') }}" class="btn btn-secondary">Cancelar</a>
                <button class="btn btn-primary">Guardar cambios</button>
            </div>
        </form>

        <aside class="space-y-6">
            <section class="card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold">Integrantes ({{ $team->members->count() }})</h2>
                    <p class="text-xs text-slate-500">El administrador los asigna desde la edición de cada usuario.</p>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($team->members as $member)
                        <li class="flex justify-between px-5 py-2.5 text-sm">
                            @if (auth()->user()->isAdmin())
                                <a href="{{ route('users.edit', $member) }}" class="hover:text-brand-600">{{ $member->name }}</a>
                            @else
                                <span>{{ $member->name }}</span>
                            @endif
                            <span class="text-xs text-slate-500">{{ $member->role->label() }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-4 text-sm text-slate-500">Sin integrantes.</li>
                    @endforelse
                </ul>
            </section>

            <form method="POST" action="{{ route('teams.destroy', $team) }}" onsubmit="return confirm('¿Eliminar el equipo? Sus integrantes quedarán sin equipo.')">
                @csrf
                @method('DELETE')
                <button class="btn btn-danger w-full">Eliminar equipo</button>
            </form>
        </aside>
    </div>
@endsection
