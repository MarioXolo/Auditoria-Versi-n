@extends('layouts.app')

@section('title', 'Meta de votos · '.$campaign->name)

@section('content')
    @include('campaigns._nav')
    <form method="POST" action="{{ route('campaigns.update', $campaign) }}">
        @method('PUT')
        @include('campaigns._form')
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-2">
                <button form="toggle-campaign" class="btn btn-secondary">{{ $campaign->active ? 'Archivar campaña' : 'Reactivar campaña' }}</button>
                <button form="delete-campaign" class="btn btn-secondary text-rose-600">Eliminar</button>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('campaigns.show', $campaign) }}" class="btn btn-secondary">Cancelar</a>
                <button class="btn btn-primary">Guardar cambios</button>
            </div>
        </div>
    </form>
    <form id="toggle-campaign" method="POST" action="{{ route('campaigns.toggle', $campaign) }}">@csrf @method('PATCH')</form>
    <form id="delete-campaign" method="POST" action="{{ route('campaigns.destroy', $campaign) }}" onsubmit="return confirm('¿Eliminar la campaña? Solo se puede si aún no tiene votantes ni promotores.')">@csrf @method('DELETE')</form>
@endsection
