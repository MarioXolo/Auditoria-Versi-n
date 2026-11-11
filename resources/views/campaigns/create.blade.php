@extends('layouts.app')

@section('title', 'Nueva campaña')

@section('header')
    <div>
        <a href="{{ route('campaigns.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Campañas</a>
        <h1 class="mt-1 text-2xl font-semibold">Nueva campaña</h1>
    </div>
@endsection

@section('content')
    <form method="POST" action="{{ route('campaigns.store') }}">
        @include('campaigns._form')
        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('campaigns.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Crear campaña</button>
        </div>
    </form>
@endsection
