@extends('layouts.app')

@section('title', 'Editar minuta')

@section('header')
    <div>
        <a href="{{ route('minutes.show', $minute) }}" class="text-sm text-slate-500 hover:text-slate-800">← {{ $minute->titulo }}</a>
        <h1 class="mt-1 text-2xl font-semibold">Editar minuta</h1>
    </div>
@endsection

@section('content')
    <form method="POST" action="{{ route('minutes.update', $minute) }}">
        @method('PUT')
        @include('minutes._form')
        <div class="flex justify-end gap-2">
            <a href="{{ route('minutes.show', $minute) }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar cambios</button>
        </div>
    </form>
@endsection
