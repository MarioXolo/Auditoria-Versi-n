@extends('layouts.app')

@section('title', 'Nuevo promotor · '.$campaign->name)

@section('content')
    @include('campaigns._nav')
    <form method="POST" action="{{ route('campaigns.promoters.store', $campaign) }}" class="card max-w-3xl p-6">
        <h2 class="mb-4 font-semibold">Registrar promotor</h2>
        @include('campaigns.promoters._form')
        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('campaigns.promoters.index', $campaign) }}" class="btn btn-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
@endsection

@include('campaigns._other_script')
