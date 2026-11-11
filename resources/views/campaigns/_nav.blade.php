{{-- Encabezado y pestañas de una campaña --}}
@php
    // Mismas pestañas y numeración que el Cuaderno Maestro de Campaña (Excel)
    $sheetTab = fn (string $key) => ['url' => route('campaigns.sheets.index', [$campaign, $key]), 'active' => request()->routeIs('campaigns.sheets.*') && request()->route('sheet') === $key, 'label' => \App\Services\CampaignSheets::get($key)['tab']];
    $tab = fn (string $route, string $match, string $label) => ['url' => route($route, $campaign), 'active' => request()->routeIs($match), 'label' => $label];
    $tabs = [
        $tab('campaigns.show', 'campaigns.show', 'Inicio'),
        $tab('campaigns.edit', 'campaigns.edit', '1. Meta de Votos'),
        $tab('campaigns.territories.index', 'campaigns.territories.*', '2. Estructura Territorial'),
        $tab('campaigns.voters.index', 'campaigns.voters.*', '3. Base de Votantes'),
        $tab('campaigns.promoters.index', 'campaigns.promoters.*', '4. Padrón de Promotores'),
        $sheetTab('actores'),
        $sheetTab('defensa-del-voto'),
        $sheetTab('presupuesto'),
        $sheetTab('cronograma'),
        $tab('campaigns.kpis.index', 'campaigns.kpis.*', '9. Tablero KPI'),
        $sheetTab('logros'),
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
            @php $active = $tab['active']; @endphp
            <a href="{{ $tab['url'] }}" @class([
                'shrink-0 border-b-2 px-3 py-2 font-medium whitespace-nowrap transition',
                'border-brand-600 text-brand-700' => $active,
                'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800' => ! $active,
            ]) @if ($active) aria-current="page" @endif>{{ $tab['label'] }}</a>
        @endforeach
    </nav>
</div>
