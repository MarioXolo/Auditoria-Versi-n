@extends('layouts.app')

@section('title', 'Editar votante · '.$campaign->name)

@section('content')
    @include('campaigns._nav')
    <form method="POST" action="{{ route('campaigns.voters.update', [$campaign, $voter]) }}" class="card max-w-3xl p-6">
        @method('PUT')
        <h2 class="mb-4 font-semibold">Editar votante</h2>
        @include('campaigns.voters._form')
        <div class="mt-6 flex flex-wrap items-center justify-between gap-2">
            <button form="delete-voter" class="text-sm font-medium text-rose-600 hover:underline">Eliminar registro</button>
            <div class="flex gap-2">
                <a href="{{ route('campaigns.voters.index', $campaign) }}" class="btn btn-secondary">Cancelar</a>
                <button class="btn btn-primary">Guardar cambios</button>
            </div>
        </div>
    </form>
    <form id="delete-voter" method="POST" action="{{ route('campaigns.voters.destroy', [$campaign, $voter]) }}" onsubmit="return confirm('¿Eliminar a {{ addslashes($voter->nombre) }} de la base de votantes?')">@csrf @method('DELETE')</form>
@endsection

@include('campaigns._other_script')
