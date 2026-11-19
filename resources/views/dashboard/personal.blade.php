@extends('layouts.app')

@section('title', 'Mi dashboard')

@php
    $statusDot = ['no_iniciado' => 'bg-amber-400', 'en_proceso' => 'bg-sky-500', 'suspendida' => 'bg-slate-400', 'concluido' => 'bg-emerald-600', 'no_completada' => 'bg-rose-600'];
    $pct = fn ($n) => $total > 0 ? round($n / $total * 100, 1) : 0;
@endphp

@section('header')
    <div>
        <h1 class="text-2xl font-semibold">Mi dashboard</h1>
        <p class="text-sm text-slate-500">{{ auth()->user()->name }} · tus actividades, las que te asignaron y las que te cargaste</p>
    </div>
    <form method="GET" class="flex items-center gap-2">
        <select name="period" class="input w-auto" onchange="this.form.submit()" aria-label="Periodo">
            @foreach ($periods as $value => $label)
                <option value="{{ $value }}" @selected($period === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
        <a href="{{ route('activities.create') }}" class="btn btn-primary">+ Cargar actividad</a>
    </form>
@endsection

@section('content')
    {{-- Cuántas tiene y de dónde vienen --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <a href="{{ route('activities.index') }}" class="card block p-5 transition hover:border-brand-500">
            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Mis actividades</p>
            <p class="mt-1 text-4xl font-semibold tabular-nums">{{ $total }}</p>
            <p class="mt-1 text-xs text-slate-500">en total en el periodo</p>
        </a>
        <div class="card p-5">
            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Me las asignaron</p>
            <p class="mt-1 text-4xl font-semibold text-brand-700 tabular-nums">{{ $asignadas }}</p>
            <p class="mt-1 truncate text-xs text-slate-500" title="{{ $asignadasPor->map(fn ($n, $who) => "$who ($n)")->join(', ') }}">
                {{ $asignadasPor->isEmpty() ? 'nadie te ha asignado' : $asignadasPor->map(fn ($n, $who) => "$who ($n)")->take(2)->join(', ') }}
            </p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Me las cargué yo</p>
            <p class="mt-1 text-4xl font-semibold tabular-nums">{{ $propias }}</p>
            <p class="mt-1 text-xs text-slate-500">actividades que registraste</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Cumplimiento</p>
            <p class="mt-1 text-4xl font-semibold tabular-nums">{{ $cumplimiento === null ? '—' : $cumplimiento.'%' }}</p>
            <p class="mt-1 text-xs text-slate-500">concluidas ÷ (todas − suspendidas)</p>
        </div>
    </div>

    {{-- Estado de sus actividades --}}
    <section class="mt-6" aria-labelledby="estado">
        <h2 id="estado" class="mb-2 font-semibold">Estado de mis actividades</h2>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5">
            @foreach ($byStatus as $row)
                <a href="{{ route('activities.index', ['status' => $row['key']]) }}" class="card block p-4 transition hover:border-brand-500">
                    <p class="flex items-center gap-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        <span class="size-2.5 rounded-full {{ $statusDot[$row['key']] }}" aria-hidden="true"></span>{{ $row['label'] }}
                    </p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $row['value'] }}</p>
                    <div class="mt-2 h-1.5 rounded-full bg-slate-100" aria-hidden="true">
                        <div class="h-1.5 rounded-full {{ $statusDot[$row['key']] }}" style="width: {{ $pct($row['value']) }}%"></div>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ $total > 0 ? $pct($row['value']).' %' : '—' }}</p>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Vencidas y próximas a vencer --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @foreach ([['Vencidas', 'Abiertas con la fecha de fin ya pasada', $overdue, true], ['Vencen en los próximos 7 días', 'Abiertas que se acercan a su fecha de fin', $upcoming, false]] as [$title, $sub, $list, $danger])
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div><h2 class="font-semibold">{{ $title }}</h2><p class="text-xs text-slate-500">{{ $sub }}</p></div>
                    <span @class(['badge', 'bg-rose-100 text-rose-800' => $danger && $list->isNotEmpty(), 'bg-slate-100 text-slate-600' => ! ($danger && $list->isNotEmpty())])>{{ $list->count() }}</span>
                </div>
                <ul class="max-h-80 divide-y divide-slate-100 overflow-y-auto">
                    @forelse ($list as $a)
                        <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                            <a href="{{ route('activities.show', $a) }}" class="min-w-0 truncate hover:text-brand-600"><span class="font-mono text-xs text-slate-500">{{ $a->code }}</span> {{ $a->type?->name }} · {{ $a->community?->name }}</a>
                            <span @class(['shrink-0 text-xs tabular-nums', 'font-medium text-rose-600' => $danger, 'text-slate-500' => ! $danger])>{{ $a->end_date->format('d/m/Y') }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-sm text-slate-500">{{ $danger ? 'No tienes actividades vencidas.' : 'Nada vence en los próximos 7 días.' }}</li>
                    @endforelse
                </ul>
            </section>
        @endforeach
    </div>

    @include('dashboard._durations', ['d' => $durations, 'showPerson' => false])
@endsection
