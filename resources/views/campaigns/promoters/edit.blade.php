@extends('layouts.app')

@section('title', 'Editar promotor · '.$campaign->name)

@section('content')
    @include('campaigns._nav')
    <form method="POST" action="{{ route('campaigns.promoters.update', [$campaign, $promoter]) }}" class="card max-w-3xl p-6">
        @method('PUT')
        <h2 class="mb-4 font-semibold">Editar promotor</h2>
        @include('campaigns.promoters._form')
        <div class="mt-6 flex flex-wrap items-center justify-between gap-2">
            <button form="delete-promoter" class="text-sm font-medium text-rose-600 hover:underline">Eliminar promotor</button>
            <div class="flex gap-2">
                <a href="{{ route('campaigns.promoters.index', $campaign) }}" class="btn btn-secondary">Cancelar</a>
                <button class="btn btn-primary">Guardar cambios</button>
            </div>
        </div>
    </form>
    <form id="delete-promoter" method="POST" action="{{ route('campaigns.promoters.destroy', [$campaign, $promoter]) }}" onsubmit="return confirm('¿Eliminar a este promotor? Sus votantes quedarán sin promotor asignado.')">@csrf @method('DELETE')</form>
@endsection

@include('campaigns._other_script')
