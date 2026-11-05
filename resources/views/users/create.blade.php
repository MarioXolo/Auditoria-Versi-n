@extends('layouts.app')

@section('title', 'Nuevo usuario')

@section('header')
    <div>
        <a href="{{ route('users.index') }}" class="text-sm text-stone-500 hover:text-stone-800">← Usuarios</a>
        <h1 class="mt-1 text-2xl font-semibold">Nuevo usuario</h1>
    </div>
@endsection

@section('content')
    <form method="POST" action="{{ route('users.store') }}" class="card max-w-3xl p-6">
        @include('users._form')
        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Crear usuario</button>
        </div>
    </form>
@endsection
