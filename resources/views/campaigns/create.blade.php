@extends('layouts.app')

@section('title', 'Configurar el Cuaderno Maestro')

@section('header')
    <div>
        <p class="text-xs font-semibold tracking-wide text-brand-600 uppercase">Cuaderno Maestro de Campaña</p>
        <h1 class="mt-1 text-2xl font-semibold">Configura tu cuaderno</h1>
        <p class="text-sm text-slate-500">Se hace una sola vez. Con estos datos se calcula la meta de votos; después podrás cambiarlos en la hoja «1. Meta de Votos».</p>
    </div>
@endsection

@section('content')
    <form method="POST" action="{{ route('campaigns.store') }}">
        @include('campaigns._form')
        <div class="mt-6 flex justify-end gap-2">
            <button class="btn btn-primary">Crear el Cuaderno Maestro</button>
        </div>
    </form>
@endsection
