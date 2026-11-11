@extends('layouts.app')

@section('title', 'Equipos')

@section('header')
    <div>
        <h1 class="text-2xl font-semibold">Equipos</h1>
        <p class="text-sm text-slate-500">Cada equipo tiene un encargado y sus integrantes.</p>
    </div>
    <a href="{{ route('teams.create') }}" class="btn btn-primary">Nuevo equipo</a>
@endsection

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($teams as $team)
            <a href="{{ route('teams.edit', $team) }}" class="card block p-5 transition hover:border-brand-500">
                <h2 class="font-semibold">{{ $team->name }}</h2>
                @if ($team->description)
                    <p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ $team->description }}</p>
                @endif
                <div class="mt-4 flex justify-between text-sm">
                    <span class="text-slate-500">Encargado</span>
                    <span class="font-medium">{{ $team->leader?->name ?? 'Sin asignar' }}</span>
                </div>
                <div class="mt-1 flex justify-between text-sm">
                    <span class="text-slate-500">Integrantes</span>
                    <span class="font-medium tabular-nums">{{ $team->members_count }}</span>
                </div>
            </a>
        @empty
            <div class="card p-8 text-center text-sm text-slate-500 sm:col-span-2 lg:col-span-3">
                Aún no hay equipos. Crea uno y asígnale un encargado.
            </div>
        @endforelse
    </div>
@endsection
