@extends('layouts.app')

@section('title', 'Editar usuario')

@section('header')
    <div>
        <a href="{{ route('users.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Usuarios</a>
        <h1 class="mt-1 text-2xl font-semibold">Editar a {{ $user->name }}</h1>
    </div>
@endsection

@section('content')
    <form method="POST" action="{{ route('users.update', $user) }}" class="card max-w-3xl p-6">
        @method('PUT')
        @include('users._form')
        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar cambios</button>
        </div>
    </form>
@endsection
