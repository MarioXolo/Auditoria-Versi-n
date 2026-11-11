@extends('layouts.app')

@section('title', $campaign->name)

@php
    $n = fn ($v) => number_format(round($v));
    $p = fn ($v) => number_format($v * 100, 1).'%';
    $afin = ['Duro' => 'bg-emerald-600', 'Blando' => 'bg-sky-500', 'Indeciso' => 'bg-amber-500'];
    $totalAfin = array_sum($summary->afinidad) ?: 1;
@endphp

@section('content')
    @include('campaigns._nav')

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Meta y avance --}}
        <section class="card overflow-hidden lg:col-span-2">
            <div class="grid sm:grid-cols-[1fr_auto]">
                <div class="p-6">
                    <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Votos identificados vs. meta con colchón</p>
                    <p class="mt-2 font-serif text-4xl font-semibold tabular-nums">{{ $n($summary->votantes) }} <span class="text-xl text-slate-400">/ {{ $n($summary->meta) }}</span></p>
                    <div class="mt-4 h-3 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-brand-600" style="width: {{ min(100, round($summary->avance * 100, 1)) }}%"></div>
                    </div>
                    <p class="mt-2 text-sm text-slate-500">{{ $p($summary->avance) }} de la meta · faltan {{ $n(max($summary->meta - $summary->votantes, 0)) }} votos por identificar</p>
                </div>
                <dl class="grid grid-cols-2 border-t border-slate-200 text-sm sm:grid-cols-1 sm:border-t-0 sm:border-l">
                    <div class="px-6 py-3"><dt class="text-slate-500">Promotores</dt><dd class="text-lg font-semibold tabular-nums">{{ $summary->promotores }} <span class="text-sm font-normal text-slate-400">/ {{ $n($summary->necesarios) }}</span></dd></div>
                    <div class="px-6 py-3"><dt class="text-slate-500">Verificados</dt><dd class="text-lg font-semibold tabular-nums">{{ $summary->verificados }}</dd></div>
                    <div class="px-6 py-3"><dt class="text-slate-500">Comunidades</dt><dd class="text-lg font-semibold tabular-nums">{{ $summary->comunidades }}</dd></div>
                    <div class="px-6 py-3"><dt class="text-slate-500">Votantes sin promotor</dt><dd @class(['text-lg font-semibold tabular-nums', 'text-amber-600' => $summary->sinPromotor > 0])>{{ $n($summary->sinPromotor) }}</dd></div>
                </dl>
            </div>
        </section>

        {{-- Calculadora --}}
        <section class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                <h2 class="font-semibold">Meta de votos</h2>
                <a href="{{ route('campaigns.edit', $campaign) }}" class="text-sm font-medium text-brand-600 hover:underline">Editar</a>
            </div>
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between px-5 py-2"><dt class="text-slate-500">Listado nominal</dt><dd class="tabular-nums">{{ $n($campaign->listado_nominal) }}</dd></div>
                <div class="flex justify-between px-5 py-2"><dt class="text-slate-500">Participación estimada</dt><dd class="tabular-nums">{{ $p($campaign->participacion) }}</dd></div>
                <div class="flex justify-between px-5 py-2"><dt class="text-slate-500">Votos esperados</dt><dd class="tabular-nums">{{ $n($campaign->votosEsperados()) }}</dd></div>
                <div class="flex justify-between px-5 py-2"><dt class="text-slate-500">% objetivo</dt><dd class="tabular-nums">{{ $p($campaign->objetivo) }}</dd></div>
                <div class="flex justify-between px-5 py-2"><dt class="text-slate-500">Meta base</dt><dd class="tabular-nums">{{ $n($campaign->metaBase()) }}</dd></div>
                <div class="flex justify-between px-5 py-2"><dt class="text-slate-500">Colchón</dt><dd class="tabular-nums">{{ $p($campaign->colchon) }}</dd></div>
                <div class="flex justify-between bg-slate-50 px-5 py-2 font-semibold"><dt>Meta con colchón</dt><dd class="tabular-nums">{{ $n($campaign->metaConColchon()) }}</dd></div>
                <div class="flex justify-between px-5 py-2"><dt class="text-slate-500">Votantes por promotor</dt><dd class="tabular-nums">{{ $campaign->votantes_por_promotor }}</dd></div>
                <div class="flex justify-between bg-brand-50 px-5 py-2 font-semibold text-brand-700"><dt>Promotores necesarios</dt><dd class="tabular-nums">{{ $n($campaign->promotoresNecesarios()) }}</dd></div>
            </dl>
        </section>

        {{-- Afinidad --}}
        <section class="card p-5">
            <h2 class="font-semibold">Afinidad de los votantes</h2>
            @if (array_sum($summary->afinidad) === 0)
                <p class="mt-3 text-sm text-slate-500">Aún no hay votantes registrados.</p>
            @else
                <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-slate-100">
                    @foreach ($afin as $label => $color)
                        <div class="{{ $color }}" style="width: {{ round(($summary->afinidad[$label] ?? 0) / $totalAfin * 100, 2) }}%"></div>
                    @endforeach
                </div>
                <ul class="mt-3 space-y-1 text-sm">
                    @foreach ($afin + ['' => 'bg-slate-300'] as $label => $color)
                        @php $count = $summary->afinidad[$label] ?? 0; @endphp
                        @if ($count || $label !== '')
                            <li class="flex items-center justify-between"><span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full {{ $color }}"></span>{{ $label ?: 'Sin definir' }}</span><span class="tabular-nums text-slate-600">{{ $n($count) }}</span></li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- KPI principales --}}
        <section class="card overflow-hidden lg:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                <h2 class="font-semibold">Indicadores clave</h2>
                <a href="{{ route('campaigns.kpis.index', $campaign) }}" class="text-sm font-medium text-brand-600 hover:underline">Ver tablero completo</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($kpis as $kpi)
                    <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <span>{{ $kpi->nombre }}</span>
                        <span class="flex items-center gap-3">
                            <span class="tabular-nums text-slate-600">{{ $n($kpi->valor) }} / {{ $n($kpi->meta) }}</span>
                            <span class="badge {{ $kpi->status['class'] }}">{{ $kpi->status['label'] }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    {{-- Índice del cuaderno --}}
    <section class="card mt-6 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-3">
            <h2 class="font-semibold">Pestañas del cuaderno</h2>
            <p class="text-xs text-slate-500">Es tu documento vivo: ve llenando cada pestaña conforme llega la información; los cálculos se hacen solos.</p>
        </div>
        <ul class="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-y-0">
            @foreach ($steps as [$label, $desc, $route, $done])
                <li class="flex items-start gap-3 px-5 py-3 sm:border-b sm:border-slate-100">
                    <span @class([
                        'mt-0.5 grid size-5 shrink-0 place-items-center rounded-full text-[11px] font-bold',
                        'bg-emerald-600 text-white' => $done,
                        'border-2 border-slate-300' => ! $done && $route,
                        'bg-slate-100 text-slate-300' => ! $route,
                    ])>@if ($done)✓@endif</span>
                    <div class="min-w-0 text-sm">
                        @if ($route)
                            <a href="{{ route($route, $campaign) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $label }}</a>
                        @else
                            <span class="font-medium text-slate-400">{{ $label }}</span> <span class="badge bg-slate-100 text-slate-500">Etapa 2</span>
                        @endif
                        <p @class(['text-xs', 'text-slate-500' => $route, 'text-slate-400' => ! $route])>{{ $desc }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    @if ($summary->metaTerritorial > 0 && $summary->metaTerritorial < round($summary->meta))
        <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            La suma de metas por comunidad ({{ $n($summary->metaTerritorial) }}) es menor que la meta municipal con colchón ({{ $n($summary->meta) }}). Reparte la diferencia en <a class="font-medium underline" href="{{ route('campaigns.territories.index', $campaign) }}">Estructura territorial</a>.
        </div>
    @endif
@endsection
