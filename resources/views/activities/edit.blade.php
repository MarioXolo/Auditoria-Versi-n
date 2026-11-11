@extends('layouts.app')

@section('title', 'Editar actividad')

@section('header')
    <div>
        <a href="{{ route('activities.show', $activity) }}" class="text-sm text-slate-500 hover:text-slate-800">← Volver a la actividad</a>
        <h1 class="mt-1 text-2xl font-semibold">Editar actividad</h1>
    </div>
@endsection

@section('content')
    <form method="POST" action="{{ route('activities.update', $activity) }}" enctype="multipart/form-data" class="card max-w-3xl p-6">
        @method('PUT')
        @include('activities._form')
        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('activities.show', $activity) }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar cambios</button>
        </div>
    </form>
@endsection
