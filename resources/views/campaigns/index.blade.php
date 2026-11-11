@extends('layouts.app')

@section('title', 'Campañas')

@section('header')
    <div>
        <h1 class="text-2xl font-semibold">Campañas</h1>
        <p class="text-sm text-slate-500">Cuaderno Maestro de Campaña: meta de votos, estructura, votantes, promotores e indicadores.</p>
    </div>
    <a href="{{ route('campaigns.create') }}" class="btn btn-primary">Nueva campaña</a>
@endsection

@section('content')
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($campaigns as $campaign)
            @php
                $meta = $campaign->metaConColchon();
                $avance = $meta > 0 ? $campaign->voters_count / $meta : 0;
            @endphp
            <a href="{{ route('campaigns.show', $campaign) }}" @class(['card block p-5 transition hover:border-brand-300 hover:shadow-md', 'opacity-60' => ! $campaign->active])>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="truncate font-semibold">{{ $campaign->name }}</h2>
                        <p class="text-sm text-slate-500">{{ $campaign->municipality }}@if ($campaign->election_date) · {{ $campaign->election_date->format('d/m/Y') }}@endif</p>
                    </div>
                    @unless ($campaign->active)
                        <span class="badge bg-slate-200 text-slate-600">Archivada</span>
                    @endunless
                </div>
                <div class="mt-4">
                    <div class="flex items-baseline justify-between text-sm">
                        <span class="text-slate-500">Votos identificados</span>
                        <span class="font-semibold tabular-nums">{{ number_format($campaign->voters_count) }} / {{ number_format(round($meta)) }}</span>
                    </div>
                    <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-brand-600" style="width: {{ min(100, round($avance * 100, 1)) }}%"></div>
                    </div>
                </div>
                <dl class="mt-4 grid grid-cols-3 gap-2 text-center text-xs text-slate-500">
                    <div><dd class="text-lg font-semibold text-slate-900 tabular-nums">{{ $campaign->territories_count }}</dd><dt>comunidades</dt></div>
                    <div><dd class="text-lg font-semibold text-slate-900 tabular-nums">{{ $campaign->promoters_count }}</dd><dt>promotores</dt></div>
                    <div><dd class="text-lg font-semibold text-slate-900 tabular-nums">{{ round($avance * 100, 1) }}%</dd><dt>de la meta</dt></div>
                </dl>
            </a>
        @empty
            <div class="card p-10 text-center md:col-span-2 xl:col-span-3">
                <p class="font-medium">Aún no hay campañas.</p>
                <p class="mt-1 text-sm text-slate-500">Crea la primera para empezar a llenar su cuaderno.</p>
                <a href="{{ route('campaigns.create') }}" class="btn btn-primary mt-4">Nueva campaña</a>
            </div>
        @endforelse
    </div>
@endsection
