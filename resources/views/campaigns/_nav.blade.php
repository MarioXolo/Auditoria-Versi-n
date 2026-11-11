{{-- Encabezado y pestañas de una campaña --}}
@php
    // Mismas pestañas y numeración que el Cuaderno Maestro de Campaña (Excel). Las de la etapa 2 aún no están activas.
    $tabs = [
        ['route' => 'campaigns.show', 'match' => 'campaigns.show', 'label' => 'Inicio'],
        ['route' => 'campaigns.edit', 'match' => 'campaigns.edit', 'label' => '1. Meta de Votos'],
        ['route' => 'campaigns.territories.index', 'match' => 'campaigns.territories.*', 'label' => '2. Estructura Territorial'],
        ['route' => 'campaigns.voters.index', 'match' => 'campaigns.voters.*', 'label' => '3. Base de Votantes'],
        ['route' => 'campaigns.promoters.index', 'match' => 'campaigns.promoters.*', 'label' => '4. Padrón de Promotores'],
        ['route' => null, 'label' => '5. Mapeo de Actores'],
        ['route' => null, 'label' => '6. Defensa del Voto'],
        ['route' => null, 'label' => '7. Presupuesto'],
        ['route' => null, 'label' => '8. Cronograma'],
        ['route' => 'campaigns.kpis.index', 'match' => 'campaigns.kpis.*', 'label' => '9. Tablero KPI'],
        ['route' => null, 'label' => '10. Logros Comunicables'],
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
            @if (! $tab['route'])
                <span class="shrink-0 cursor-not-allowed border-b-2 border-transparent px-3 py-2 whitespace-nowrap text-slate-300" title="Llega en la etapa 2">{{ $tab['label'] }}</span>
                @continue
            @endif
            @php $active = request()->routeIs($tab['match']); @endphp
            <a href="{{ route($tab['route'], $campaign) }}" @class([
                'shrink-0 border-b-2 px-3 py-2 font-medium whitespace-nowrap transition',
                'border-brand-600 text-brand-700' => $active,
                'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800' => ! $active,
            ]) @if ($active) aria-current="page" @endif>{{ $tab['label'] }}</a>
        @endforeach
    </nav>
</div>
