@extends('layouts.app')

@section('title', 'Estrategia de campaña · '.$campaign->name)

@use('App\Services\StrategyBooks')

@section('content')
    @include('campaigns._nav')

    <p class="mb-4 text-sm text-slate-500">Cada libro es una tabla con los campos comunes (comunidad y sección, responsable, fecha, estatus, costos y evidencia) más los suyos. Los eventos pasan solos a la Agenda IEEQ.</p>

    @if ($pendientesIeeq > 0)
        <a href="{{ route('campaigns.agenda.index', [$campaign, 'estatus' => 'pendientes']) }}" class="mb-5 flex items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 hover:border-amber-500">
            <span><b>{{ $pendientesIeeq }}</b> {{ $pendientesIeeq === 1 ? 'evento falta' : 'eventos faltan' }} de reportar al IEEQ.</span>
            <span class="font-medium">Ver agenda →</span>
        </a>
    @endif

    <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @foreach (StrategyBooks::all() as $key => $book)
            @php $n = $counts[$key] ?? 0; @endphp
            <li>
                <a href="{{ route('campaigns.strategy.index', [$campaign, $key]) }}" class="group flex h-full flex-col items-center rounded-3xl border border-slate-200 bg-white px-3 py-5 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-brand-500 hover:shadow-md focus-visible:ring-2 focus-visible:ring-brand-500">
                    <span @class([
                        'grid size-14 place-items-center rounded-full text-2xl transition',
                        'bg-brand-600 text-white' => $n > 0,
                        'bg-white ring-2 ring-brand-500 group-hover:bg-brand-50' => $n === 0,
                    ]) aria-hidden="true">{{ $book['icon'] }}</span>
                    <span class="mt-3 text-sm leading-tight font-semibold text-slate-900">{{ $book['name'] }}</span>
                    @if ($book['evento'] ?? false)
                        <span class="mt-1 text-[11px] font-medium text-brand-700">Pasa a la Agenda IEEQ</span>
                    @endif
                    <span @class(['mt-auto pt-3 text-xs font-medium tabular-nums', 'text-brand-700' => $n > 0, 'text-slate-400' => $n === 0])>{{ $n > 0 ? number_format($n).' '.($n === 1 ? 'registro' : 'registros') : 'Por llenar' }}</span>
                </a>
            </li>
        @endforeach
        @foreach (StrategyBooks::CASILLAS as $tipo => [$label, $desc])
            @php $n = $casillas[$tipo] ?? 0; @endphp
            <li>
                <a href="{{ route('campaigns.sheets.index', [$campaign, 'defensa-del-voto', 'tipo' => $tipo]) }}" class="group flex h-full flex-col items-center rounded-3xl border border-slate-200 bg-white px-3 py-5 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-brand-500 hover:shadow-md focus-visible:ring-2 focus-visible:ring-brand-500">
                    <span @class([
                        'grid size-14 place-items-center rounded-full text-2xl transition',
                        'bg-brand-600 text-white' => $n > 0,
                        'bg-white ring-2 ring-brand-500 group-hover:bg-brand-50' => $n === 0,
                    ]) aria-hidden="true">🗳️</span>
                    <span class="mt-3 text-sm leading-tight font-semibold text-slate-900">{{ $label }}</span>
                    <span class="mt-1 text-[11px] text-slate-500">Misma lista que Defensa del Voto</span>
                    <span @class(['mt-auto pt-3 text-xs font-medium tabular-nums', 'text-brand-700' => $n > 0, 'text-slate-400' => $n === 0])>{{ $n > 0 ? number_format($n).' '.($n === 1 ? 'casilla' : 'casillas') : 'Por llenar' }}</span>
                </a>
            </li>
        @endforeach
    </ul>
@endsection
