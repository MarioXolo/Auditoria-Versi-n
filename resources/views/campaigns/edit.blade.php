@extends('layouts.app')

@section('title', 'Meta de votos · '.$campaign->name)

@section('content')
    @include('campaigns._nav')
    <form method="POST" action="{{ route('campaigns.update', $campaign) }}">
        @method('PUT')
        @include('campaigns._form')
        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('campaigns.show', $campaign) }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar cambios</button>
        </div>
    </form>
@endsection
