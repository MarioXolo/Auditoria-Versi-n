@extends('layouts.app')

@section('title', 'Agenda IEEQ · '.$campaign->name)

@use('App\Models\StrategyRecord')
@use('App\Services\CampaignSheets')

@php
    $sem = [
        'vencido' => ['bg-rose-50', 'bg-rose-600 text-white', 'Vencido'],
        'por_vencer' => ['bg-amber-50', 'bg-amber-100 text-amber-800', 'Por vencer'],
        'en_tiempo' => ['', 'bg-slate-100 text-slate-700', 'En tiempo'],
        'reportado' => ['', 'bg-emerald-100 text-emerald-800', 'Reportado'],
        'cancelado' => ['opacity-60', 'bg-slate-200 text-slate-500', 'Cancelado'],
    ];
@endphp

@section('content')
    @include('campaigns._nav')

    <p class="mb-4 text-sm text-slate-500">Los eventos de los libros (mítines, desayunos, caravanas, cabalgatas y agenda metropolitana de campaña) aparecen aquí solos. La fecha límite es {{ StrategyRecord::diasAnticipacion() }} días antes del evento; se puede ajustar en cada uno. Si un evento ya reportado cambia de fecha, hora o lugar, pasa a «Modificado» para reportar el cambio.</p>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card p-4"><p class="text-xs text-slate-500">Eventos</p><p class="text-2xl font-semibold tabular-nums">{{ number_format($cards['total']) }}</p><p class="text-xs text-slate-500">Gasto estimado {{ CampaignSheets::money($cards['gasto']) }}</p></div>
        <a href="{{ route('campaigns.agenda.index', [$campaign, 'estatus' => 'pendientes']) }}" @class(['card p-4 hover:border-brand-500', 'border-amber-300 bg-amber-50' => $cards['pendientes'] > 0])><p class="text-xs text-slate-500">Por reportar</p><p class="text-2xl font-semibold tabular-nums">{{ number_format($cards['pendientes']) }}</p><p class="text-xs text-slate-500">{{ $cards['por_vencer'] }} por vencer en {{ config('auditoria.ieeq_dias_aviso', 3) }} días</p></a>
        <div @class(['card p-4', 'border-rose-300 bg-rose-50 text-rose-900' => $cards['vencidos'] > 0])><p class="text-xs">Vencidos sin reportar</p><p class="text-2xl font-semibold tabular-nums">{{ number_format($cards['vencidos']) }}</p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">Reportados</p><p class="text-2xl font-semibold tabular-nums">{{ number_format($cards['reportados']) }}</p></div>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <select name="estatus" class="input w-48" aria-label="Estatus" onchange="this.form.submit()">
            <option value="">Todos los estatus</option>
            <option value="pendientes" @selected(request('estatus') === 'pendientes')>Pendientes de reportar</option>
            @foreach (StrategyRecord::IEEQ_ESTATUS as $e)<option @selected(request('estatus') === $e)>{{ $e }}</option>@endforeach
        </select>
        <select name="libro" class="input w-48" aria-label="Tipo de evento" onchange="this.form.submit()">
            <option value="">Todos los tipos</option>
            @foreach ($eventBooks as $k => $name)<option value="{{ $k }}" @selected(request('libro') === $k)>{{ $name }}</option>@endforeach
        </select>
        <noscript><button class="btn btn-secondary">Filtrar</button></noscript>
    </form>

    <div class="space-y-3">
        @forelse ($events as $e)
            @php [$rowBg, $badge, $semLabel] = $sem[$e->agendaSemaforo()] ?? $sem['en_tiempo']; @endphp
            <article class="card overflow-hidden {{ $rowBg }}">
                <div class="flex flex-wrap items-start justify-between gap-3 p-4">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ $e->book()['name'] }}</p>
                        <a href="{{ route('campaigns.strategy.edit', [$campaign, $e->libro, $e]) }}" class="font-semibold text-slate-900 hover:text-brand-700 hover:underline">{{ $e->title() }}</a>
                        <p class="text-sm text-slate-600">
                            {{ $e->fecha?->translatedFormat('D j M Y') ?? 'Sin fecha' }}@if ($e->horaCorta()) · {{ $e->horaCorta() }}@endif
                            · {{ $e->community?->name }} · {{ $e->responsable?->name }}
                            @if ($e->costo_estimado !== null) · gasto est. {{ CampaignSheets::money((float) $e->costo_estimado) }}@endif
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="badge {{ $badge }}">{{ $e->ieeq_estatus }}{{ in_array($e->agendaSemaforo(), ['vencido', 'por_vencer']) ? ' · '.$semLabel : '' }}</span>
                        <p class="mt-1 text-xs text-slate-500">Límite: <b>{{ $e->ieeq_fecha_limite?->format('d/m/Y') ?? '—' }}</b></p>
                    </div>
                </div>
                <form method="POST" action="{{ route('campaigns.agenda.update', [$campaign, $e]) }}" class="grid gap-2 border-t border-slate-200 bg-white/70 p-3 sm:grid-cols-[10rem_10rem_1fr_10rem_auto] sm:items-end">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="label text-xs" for="st{{ $e->id }}">Estatus</label>
                        <select id="st{{ $e->id }}" name="ieeq_estatus" class="input py-1.5">
                            @foreach (StrategyRecord::IEEQ_ESTATUS as $s)<option @selected($e->ieeq_estatus === $s)>{{ $s }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label text-xs" for="rep{{ $e->id }}">Reportado el</label>
                        <input id="rep{{ $e->id }}" type="date" name="ieeq_reportado_el" value="{{ $e->ieeq_reportado_el?->format('Y-m-d') }}" class="input py-1.5">
                    </div>
                    <div>
                        <label class="label text-xs" for="fol{{ $e->id }}">Folio o acuse</label>
                        <input id="fol{{ $e->id }}" name="ieeq_folio" value="{{ $e->ieeq_folio }}" maxlength="80" class="input py-1.5">
                    </div>
                    <div>
                        <label class="label text-xs" for="lim{{ $e->id }}">Fecha límite</label>
                        <input id="lim{{ $e->id }}" type="date" name="ieeq_fecha_limite" value="{{ $e->ieeq_fecha_limite?->format('Y-m-d') }}" class="input py-1.5">
                    </div>
                    <button class="btn btn-secondary py-1.5">Guardar</button>
                </form>
            </article>
        @empty
            <div class="card p-10 text-center text-sm text-slate-500">
                Aún no hay eventos. Se agregan solos al capturar <a href="{{ route('campaigns.strategy.index', [$campaign, 'mitines']) }}" class="text-brand-700 hover:underline">mítines</a>, desayunos, caravanas, cabalgatas o reuniones metropolitanas de campaña.
            </div>
        @endforelse
    </div>
@endsection
