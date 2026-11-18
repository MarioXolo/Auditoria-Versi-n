@extends('layouts.app')

@section('title', 'Crea tu minuta')

@section('header')
    <div>
        <a href="{{ route('minutes.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Minutas</a>
        <h1 class="mt-1 text-2xl font-semibold">Crea tu minuta</h1>
        <p class="text-sm text-slate-500">Mismo formato que la minuta en Excel. Solo la fecha es obligatoria.</p>
    </div>
@endsection

@section('content')
    <form method="POST" action="{{ route('minutes.store') }}">
        @include('minutes._form')
        <div class="flex justify-end gap-2">
            <a href="{{ route('minutes.index') }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar minuta</button>
        </div>
    </form>
@endsection
