@extends('layouts.app')

@section('title', 'Nueva actividad · Agenda del Presidente')

@section('header')
    <div>
        <a href="{{ route('president.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Agenda del Presidente</a>
        <h1 class="text-2xl font-semibold">Nueva actividad</h1>
    </div>
@endsection

@section('content')
    <form method="POST" action="{{ route('president.store') }}" enctype="multipart/form-data" class="card max-w-3xl p-6">
        @include('president._form')
        <div class="mt-6 flex gap-2">
            <button class="btn btn-primary">Guardar</button>
            <a href="{{ route('president.index') }}" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
@endsection
