@extends('layouts.app')

@section('title', 'Nuevo equipo')

@section('header')
    <div>
        <a href="{{ route('teams.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Equipos</a>
        <h1 class="mt-1 text-2xl font-semibold">Nuevo equipo</h1>
    </div>
@endsection

@section('content')
    <form method="POST" action="{{ route('teams.store') }}" class="card max-w-2xl p-6">
        @include('teams._form')
        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('teams.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Crear equipo</button>
        </div>
    </form>
@endsection
