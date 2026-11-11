@extends('layouts.app')

@section('title', 'Nuevo votante · '.$campaign->name)

@section('content')
    @include('campaigns._nav')
    <form method="POST" action="{{ route('campaigns.voters.store', $campaign) }}" class="card max-w-3xl p-6">
        <h2 class="mb-4 font-semibold">Registrar votante identificado</h2>
        @include('campaigns.voters._form')
        <div class="mt-6 flex flex-wrap justify-end gap-2">
            <a href="{{ route('campaigns.voters.index', $campaign) }}" class="btn btn-secondary">Cancelar</a>
            <button name="otro" value="1" class="btn btn-secondary">Guardar y capturar otro</button>
            <button class="btn btn-primary">Guardar</button>
        </div>
        <p class="mt-3 text-right text-xs text-slate-500">«Guardar y capturar otro» conserva la comunidad, el promotor y la sección para registrar al siguiente más rápido.</p>
    </form>
@endsection

@include('campaigns._other_script')
