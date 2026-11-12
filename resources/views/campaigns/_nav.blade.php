{{-- Encabezado del Cuaderno Maestro. Dentro de una hoja muestra las burbujas numeradas para saltar a otra. --}}
@php
    $bubbles = \App\Http\Controllers\Campaigns\CampaignController::bubbles($campaign);
    $onCover = request()->routeIs('campaigns.show');
    // Qué burbuja corresponde a la página actual
    $current = match (true) {
        request()->routeIs('campaigns.edit') => 1,
        request()->routeIs('campaigns.territories.*') => 2,
        request()->routeIs('campaigns.voters.*') => 3,
        request()->routeIs('campaigns.promoters.*') => 4,
        request()->routeIs('campaigns.kpis.*') => 9,
        request()->routeIs('campaigns.sheets.*') => [ 'actores' => 5, 'defensa-del-voto' => 6, 'presupuesto' => 7, 'cronograma' => 8, 'logros' => 10 ][request()->route('sheet')] ?? null,
        default => null,
    };
    $currentBubble = collect($bubbles)->firstWhere(0, $current);
@endphp
<div class="mb-5">
    @if ($onCover)
        <p class="text-xs font-semibold tracking-wide text-brand-600 uppercase">Cuaderno Maestro de Campaña</p>
        <h1 class="mt-1 text-2xl font-semibold">{{ $campaign->name }}</h1>
        <p class="text-sm text-slate-500">
            {{ $campaign->municipality }}
            @if ($campaign->election_date) · Elección {{ $campaign->election_date->translatedFormat('j \d\e F \d\e Y') }} @endif
        </p>
    @else
        <a href="{{ route('campaigns.show', $campaign) }}" class="text-sm text-slate-500 hover:text-slate-800">← Cuaderno Maestro de Campaña</a>
        <div class="mt-2 flex items-center gap-3">
            @if ($currentBubble)
                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-brand-600 text-lg font-semibold text-white" aria-hidden="true">{{ $currentBubble[0] }}</span>
            @endif
            <div class="min-w-0">
                <h1 class="text-2xl font-semibold">{{ $currentBubble[1] ?? $campaign->name }}</h1>
                <p class="truncate text-sm text-slate-500">{{ $campaign->name }} · {{ $campaign->municipality }}</p>
            </div>
        </div>
        <nav class="mt-4 flex gap-2 overflow-x-auto pb-1" aria-label="Hojas del cuaderno">
            @foreach ($bubbles as [$num, $label, $desc, $url, $done])
                <a href="{{ $url }}" title="{{ $num }}. {{ $label }}" aria-label="{{ $num }}. {{ $label }}" @if ($num === $current) aria-current="page" @endif @class([
                    'grid size-9 shrink-0 place-items-center rounded-full text-sm font-semibold transition',
                    'bg-brand-600 text-white shadow' => $num === $current,
                    'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' => $num !== $current && $done,
                    'bg-white text-slate-500 ring-1 ring-slate-200 hover:bg-slate-100 hover:text-slate-800' => $num !== $current && ! $done,
                ])>{{ $num }}</a>
            @endforeach
        </nav>
    @endif
</div>
