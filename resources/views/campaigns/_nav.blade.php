{{-- Encabezado y pestañas de una campaña --}}
@php
    $tabs = [
        ['route' => 'campaigns.show', 'match' => 'campaigns.show', 'label' => 'Resumen'],
        ['route' => 'campaigns.territories.index', 'match' => 'campaigns.territories.*', 'label' => 'Estructura territorial'],
        ['route' => 'campaigns.voters.index', 'match' => 'campaigns.voters.*', 'label' => 'Base de votantes'],
        ['route' => 'campaigns.promoters.index', 'match' => 'campaigns.promoters.*', 'label' => 'Promotores'],
        ['route' => 'campaigns.kpis.index', 'match' => 'campaigns.kpis.*', 'label' => 'Tablero KPI'],
        ['route' => 'campaigns.edit', 'match' => 'campaigns.edit', 'label' => 'Meta de votos'],
    ];
@endphp
<div class="mb-5">
    <a href="{{ route('campaigns.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Campañas</a>
    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
        <h1 class="text-2xl font-semibold">{{ $campaign->name }}</h1>
        @unless ($campaign->active)
            <span class="badge bg-slate-200 text-slate-600">Archivada</span>
        @endunless
    </div>
    <p class="text-sm text-slate-500">
        {{ $campaign->municipality }}@if ($campaign->election_date) · Elección {{ $campaign->election_date->translatedFormat('j \d\e F \d\e Y') }}@endif
    </p>
    <nav class="mt-4 -mb-px flex gap-1 overflow-x-auto border-b border-slate-200 text-sm" aria-label="Secciones de la campaña">
        @foreach ($tabs as $tab)
            @php $active = request()->routeIs($tab['match']); @endphp
            <a href="{{ route($tab['route'], $campaign) }}" @class([
                'shrink-0 border-b-2 px-3 py-2 font-medium whitespace-nowrap transition',
                'border-brand-600 text-brand-700' => $active,
                'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800' => ! $active,
            ]) @if ($active) aria-current="page" @endif>{{ $tab['label'] }}</a>
        @endforeach
    </nav>
</div>
