@extends('layouts.app')

@section('title', 'Mi perfil')

@section('header')
    <div>
        <h1 class="text-2xl font-semibold">Mi perfil</h1>
        <p class="text-sm text-slate-500">{{ $user->email }} · {{ $user->role->label() }}{{ $user->team ? ' · '.$user->team->name : '' }}</p>
    </div>
@endsection

@section('content')
    <form method="POST" action="{{ route('profile.password') }}" class="card max-w-xl space-y-5 p-6">
        @csrf
        @method('PUT')
        <h2 class="font-semibold">Cambiar contraseña</h2>
        <div>
            <label for="current_password" class="label">Contraseña actual</label>
            <input id="current_password" type="password" name="current_password" required autocomplete="current-password" class="input">
        </div>
        <div>
            <label for="password" class="label">Nueva contraseña</label>
            <input id="password" type="password" name="password" required autocomplete="new-password" class="input">
            <p class="mt-1 text-xs text-slate-500">Mínimo 8 caracteres.</p>
        </div>
        <div>
            <label for="password_confirmation" class="label">Confirmar nueva contraseña</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="input">
        </div>
        <div class="flex justify-end">
            <button class="btn btn-primary">Actualizar contraseña</button>
        </div>
    </form>
@endsection
