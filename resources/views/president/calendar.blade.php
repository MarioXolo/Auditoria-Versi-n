@extends('layouts.app')

@section('title', 'Calendario · Agenda del Presidente')

@php
    $prev = $month->subMonth()->format('Y-m');
    $next = $month->addMonth()->format('Y-m');
    $keep = request()->only(['status', 'community_id']);
    $chip = [
        'no_iniciado' => 'bg-amber-100 text-amber-900',
        'en_proceso' => 'bg-sky-100 text-sky-900',
        'suspendida' => 'bg-slate-200 text-slate-700',
        'concluido' => 'bg-emerald-600 text-white',
        'no_completada' => 'bg-rose-600 text-white',
    ];
    $visible = 4;
@endphp

@section('header')
    <div>
        <h1 class="text-2xl font-semibold">Agenda del Presidente</h1>
        <p class="text-sm text-slate-500">Calendario propio: solo las actividades de esta agenda.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2 print:hidden">
        <a href="{{ route('president.calendar', ['mes' => $prev] + $keep) }}" class="btn btn-secondary px-3" aria-label="Mes anterior">←</a>
        <span class="min-w-36 text-center font-semibold capitalize">{{ $month->translatedFormat('F Y') }}</span>
        <a href="{{ route('president.calendar', ['mes' => $next] + $keep) }}" class="btn btn-secondary px-3" aria-label="Mes siguiente">→</a>
        <a href="{{ route('president.calendar', $keep) }}" class="btn btn-secondary">Hoy</a>
        <button type="button" onclick="window.print()" class="btn btn-secondary">Imprimir / PDF</button>
        <a href="{{ route('president.create') }}" class="btn btn-primary">+ Nueva actividad</a>
    </div>
@endsection

@section('content')
    @include('president._tabs')

    <form method="GET" class="card mb-4 flex flex-wrap gap-3 p-4 print:hidden">
        <input type="hidden" name="mes" value="{{ $month->format('Y-m') }}">
        <select name="status" class="input w-48" aria-label="Estado" onchange="this.form.submit()">
            <option value="">Todos los estados</option>
            @foreach (\App\Enums\ActivityStatus::options() as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach
        </select>
        <select name="community_id" class="input w-56" aria-label="Comunidad" onchange="this.form.submit()">
            <option value="">Todas las comunidades</option>
            @foreach ($communities as $c)<option value="{{ $c->id }}" @selected((int) request('community_id') === $c->id)>{{ $c->name }}</option>@endforeach
        </select>
        <p class="ml-auto self-center text-sm text-slate-500"><b class="text-slate-900">{{ $inMonth->count() }}</b> {{ $inMonth->count() === 1 ? 'actividad' : 'actividades' }} este mes</p>
    </form>

    {{-- Cuadrícula del mes (escritorio e impresión) --}}
    <div class="card hidden overflow-hidden md:block print:block">
        <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50 text-center text-xs font-semibold tracking-wide text-slate-500 uppercase">
            @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $dn)<div class="py-2">{{ $dn }}</div>@endforeach
        </div>
        <div class="grid grid-cols-7">
            @for ($day = $gridStart; $day->lte($gridEnd); $day = $day->addDay())
                @php $list = collect($days[$day->toDateString()] ?? []); $inM = $day->month === $month->month; @endphp
                <div @class(['min-h-28 border-r border-b border-slate-100 p-1.5', 'bg-slate-50/70' => ! $inM])>
                    <div class="mb-1 flex items-center justify-between">
                        <span @class(['grid size-6 place-items-center rounded-full text-xs', 'bg-brand-600 font-semibold text-white' => $day->isToday(), 'font-medium text-slate-700' => ! $day->isToday() && $inM, 'text-slate-400' => ! $inM])>{{ $day->day }}</span>
                    </div>
                    <ul class="space-y-1">
                        @foreach ($list as $i => $a)
                            <li @class(['hidden print:block' => $i >= $visible])>
                                <a href="{{ route('president.show', $a) }}" title="{{ $a->folio }} · {{ $a->type?->name }} · {{ $a->community?->name }} · {{ $a->status->label() }}"
                                   class="block truncate rounded px-1.5 py-0.5 text-[11px] font-medium hover:brightness-95 {{ $chip[$a->status->value] ?? '' }}">{{ $a->type?->name }} · {{ $a->community?->name }}</a>
                            </li>
                        @endforeach
                        @if ($list->count() > $visible)
                            <li class="text-[11px] text-slate-500 print:hidden">+{{ $list->count() - $visible }} más</li>
                        @endif
                    </ul>
                </div>
            @endfor
        </div>
    </div>

    {{-- Celular: lista por día --}}
    <div class="space-y-3 md:hidden print:hidden">
        @forelse (collect($days)->filter(fn ($l, $d) => substr($d, 0, 7) === $month->format('Y-m'))->sortKeys() as $date => $list)
            <section class="card overflow-hidden">
                <h2 class="border-b border-slate-100 bg-slate-50 px-4 py-2 text-sm font-semibold capitalize">{{ \Carbon\Carbon::parse($date)->translatedFormat('l j \d\e F') }}</h2>
                <ul class="divide-y divide-slate-100">
                    @foreach ($list as $a)
                        <li><a href="{{ route('president.show', $a) }}" class="flex items-center justify-between gap-3 px-4 py-2.5">
                            <span class="min-w-0"><span class="block truncate text-sm font-medium">{{ $a->type?->name }}</span><span class="text-xs text-slate-500">{{ $a->folio }} · {{ $a->community?->name }}</span></span>
                            <span class="badge shrink-0 {{ $a->status->badge() }}">{{ $a->status->label() }}</span>
                        </a></li>
                    @endforeach
                </ul>
            </section>
        @empty
            <div class="card p-8 text-center text-sm text-slate-500">No hay actividades este mes.</div>
        @endforelse
    </div>

    <div class="mt-3 flex flex-wrap gap-3 text-xs text-slate-600 print:hidden">
        @foreach (\App\Enums\ActivityStatus::options() as $v => $l)<span class="inline-flex items-center gap-1"><span class="inline-block size-3 rounded {{ $chip[$v] }}"></span>{{ $l }}</span>@endforeach
    </div>
@endsection
