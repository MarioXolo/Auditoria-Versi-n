@extends('layouts.app')

@section('title', 'Nueva actividad')

@section('header')
    <div>
        <a href="{{ route('activities.index') }}" class="text-sm text-stone-500 hover:text-stone-800">← Actividades</a>
        <h1 class="mt-1 text-2xl font-semibold">Nueva actividad</h1>
    </div>
@endsection

@section('content')
    <form method="POST" action="{{ route('activities.store') }}" enctype="multipart/form-data" class="card max-w-3xl p-6">
        @include('activities._form')
        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('activities.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar y asignar</button>
        </div>
    </form>
@endsection
