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

    {{-- Las hojas del cuaderno como burbujas (solo las que el perfil deja ver): se entra a cada una para llenarla --}}
    <section class="mb-8" aria-labelledby="hojas">
        <h2 id="hojas" class="font-semibold">Hojas del cuaderno</h2>
        <p class="text-sm text-slate-500">Entra a cada burbuja para llenarla. Es tu documento vivo: los cálculos se hacen solos conforme llega la información.</p>

        {{-- Semáforo automático: sale de fechas, estatus y capturas; nadie lo marca a mano --}}
        @php $semColor = ['rojo' => 'bg-rose-500', 'amarillo' => 'bg-amber-400', 'verde' => 'bg-emerald-500', 'gris' => 'bg-slate-300']; @endphp
        <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm" role="status">
            <span class="font-semibold">Semáforo</span>
            @foreach (['rojo' => 'en rojo', 'amarillo' => 'en amarillo', 'verde' => 'al día', 'gris' => 'sin información'] as $c => $label)
                <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-full {{ $semColor[$c] }}" aria-hidden="true"></span><b class="tabular-nums">{{ $semaforoResumen[$c] }}</b> {{ $label }}</span>
            @endforeach
            <details class="w-full text-xs text-slate-500">
                <summary class="cursor-pointer select-none">¿Cómo se calcula?</summary>
                <p class="mt-1">Se calcula solo. <b class="text-rose-700">Rojo</b>: algo con fecha pasada que no está en «Hecho», tareas vencidas, eventos sin reportar al IEEQ a tiempo, tope de gastos rebasado o indicadores en rojo. <b class="text-amber-700">Amarillo</b>: algo vence en los próximos {{ \App\Services\CuadernoSemaforo::diasAviso() }} días, la hoja lleva {{ \App\Services\CuadernoSemaforo::diasSinMovimiento() }} días o más sin capturas, el gasto pasa del 90 % del tope o hay indicadores en ámbar. <b class="text-emerald-700">Verde</b>: tiene información y nada de lo anterior. Para apagar un rojo, marca el registro como «Hecho» o corrige su fecha.</p>
            </details>
        </div>
        @foreach ([['Hojas 1 a 10', fn ($n) => $n <= 10], ['Puntos nuevos 11 a 20', fn ($n) => $n > 10]] as [$groupLabel, $inGroup])
        @php $group = collect($steps)->filter(fn ($st) => $inGroup($st[0])); @endphp
        @continue($group->isEmpty())
        <h3 class="mt-5 text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ $groupLabel }}</h3>
        <ul class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($group as [$num, $label, $desc, $url, $done, $stat])
                @php $sem = $semaforo[$num] ?? ['color' => 'gris', 'motivo' => null]; @endphp
                <li>
                    <a href="{{ $url }}" data-semaforo="{{ $sem['color'] }}" class="group relative flex h-full flex-col items-center rounded-3xl border border-slate-200 bg-white px-3 py-5 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-brand-500 hover:shadow-md focus-visible:ring-2 focus-visible:ring-brand-500">
                        <span class="absolute top-3 left-3 size-3.5 rounded-full ring-2 ring-white {{ $semColor[$sem['color']] }}" title="Semáforo: {{ $sem['color'] }}{{ $sem['motivo'] ? ' — '.$sem['motivo'] : '' }}"></span>
                        <span class="sr-only">Semáforo {{ $sem['color'] }}{{ $sem['motivo'] ? ': '.$sem['motivo'] : '' }}.</span>
                        <span @class([
                            'relative grid size-16 place-items-center rounded-full text-2xl font-semibold transition',
                            'bg-brand-600 text-white' => $done,
                            'bg-white text-brand-600 ring-2 ring-brand-500 group-hover:bg-brand-50' => ! $done,
                        ])>
                            {{ $num }}
                            @if ($done)
                                <span class="absolute -right-1 -bottom-1 grid size-6 place-items-center rounded-full bg-white text-xs font-bold text-brand-600 ring-2 ring-brand-600" title="Ya tiene información">✓</span>
                            @endif
                        </span>
                        <span class="mt-3 text-sm leading-tight font-semibold text-slate-900">{{ $num }}. {{ $label }}</span>
                        <span class="mt-1 text-xs leading-snug text-slate-500">{{ $desc }}</span>
                        <span @class(['mt-auto pt-3 text-xs font-medium tabular-nums', 'text-brand-700' => $done, 'text-slate-400' => ! $done])>{{ $done ? $stat : 'Por llenar' }}</span>
                        @if (in_array($sem['color'], ['rojo', 'amarillo'], true))
                            <span @class(['mt-1 rounded-full px-2 py-0.5 text-[11px] font-semibold leading-tight', 'bg-rose-100 text-rose-800' => $sem['color'] === 'rojo', 'bg-amber-100 text-amber-800' => $sem['color'] === 'amarillo'])>{{ $sem['motivo'] }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
        @endforeach
    </section>

    @if (auth()->user()->puede('cuaderno'))
    <h2 class="mb-3 font-semibold">Resumen</h2>
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

    @if ($summary->metaTerritorial > 0 && $summary->metaTerritorial < round($summary->meta))
        <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            La suma de metas por comunidad ({{ $n($summary->metaTerritorial) }}) es menor que la meta municipal con colchón ({{ $n($summary->meta) }}). Reparte la diferencia en <a class="font-medium underline" href="{{ route('campaigns.territories.index', $campaign) }}">Estructura territorial</a>.
        </div>
    @endif
    @endif
@endsection
