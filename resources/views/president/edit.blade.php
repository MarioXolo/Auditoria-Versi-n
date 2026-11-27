@extends('layouts.app')

@section('title', 'Editar '.$activity->folio.' · Agenda del Presidente')

@section('header')
    <div>
        <a href="{{ route('president.show', $activity) }}" class="text-sm text-slate-500 hover:text-slate-800">← {{ $activity->folio }}</a>
        <h1 class="text-2xl font-semibold">Editar actividad</h1>
    </div>
@endsection

@section('content')
    <form method="POST" action="{{ route('president.update', $activity) }}" enctype="multipart/form-data" class="card max-w-3xl p-6">
        @method('PUT')
        @include('president._form')
        <div class="mt-6 flex gap-2">
            <button class="btn btn-primary">Guardar cambios</button>
            <a href="{{ route('president.show', $activity) }}" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
@endsection
