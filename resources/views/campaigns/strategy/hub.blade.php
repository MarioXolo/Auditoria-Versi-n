@extends('layouts.app')

@section('title', $punto['n'].'. '.$punto['name'].' · '.$campaign->name)

@use('App\Services\StrategyBooks')
@use('App\Services\CampaignSheets')

@section('content')
    @include('campaigns._nav')

    <p class="mb-4 text-sm text-slate-500">{{ $punto['desc'] }} Cada libro es una tabla con los campos comunes (comunidad y sección, responsable, fecha, estatus, costos y evidencia) más los suyos.</p>

    @if ($pendientesIeeq > 0)
        <a href="{{ route('campaigns.agenda.index', [$campaign, 'estatus' => 'pendientes']) }}" class="mb-5 flex items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 hover:border-amber-500">
            <span><b>{{ $pendientesIeeq }}</b> {{ $pendientesIeeq === 1 ? 'evento falta' : 'eventos faltan' }} de reportar al IEEQ.</span>
            <span class="font-medium">Ver agenda →</span>
        </a>
    @endif

    @if ($punto['n'] === 13)
        <div class="mb-5 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900">
            Acceso restringido: solo los perfiles que incluyan «13. Campaña de apoyo». Sus eventos pasan a la Agenda IEEQ y su gasto cuenta como gasto de la campaña: <b>{{ CampaignSheets::money($gasto) }}</b> registrados.
        </div>
    @elseif ($punto['n'] === 16)
        <div class="mb-5 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900">
            Acceso restringido: solo los perfiles que incluyan «16. Estadísticas» (responsable de análisis y coordinación general). Cada consulta, archivo abierto o exportación queda registrado abajo.
        </div>
    @elseif ($punto['n'] === 17)
        <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Fecha límite para cualquier cierre: <b>2 de junio de 2027</b>{{ today()->lte(\Carbon\Carbon::parse('2027-06-02')) ? ' · faltan '.today()->diffInDays(\Carbon\Carbon::parse('2027-06-02')).' días' : '' }}.
        </div>
    @endif

    <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($books as $key => $book)
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
        @if ($punto['n'] === 11)
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
                        <span class="mt-1 text-[11px] text-slate-500">Misma lista que Defensa del Voto y Día D</span>
                        <span @class(['mt-auto pt-3 text-xs font-medium tabular-nums', 'text-brand-700' => $n > 0, 'text-slate-400' => $n === 0])>{{ $n > 0 ? number_format($n).' '.($n === 1 ? 'casilla' : 'casillas') : 'Por llenar' }}</span>
                    </a>
                </li>
            @endforeach
        @endif
        @if ($punto['n'] === 16)
            @foreach (['Base de datos Nica', 'Base de datos Anahí'] as $base)
                <li>
                    <div class="flex h-full flex-col items-center rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-3 py-5 text-center" aria-disabled="true">
                        <span class="grid size-14 place-items-center rounded-full bg-slate-200 text-2xl" aria-hidden="true">🔒</span>
                        <span class="mt-3 text-sm leading-tight font-semibold text-slate-700">{{ $base }}</span>
                        <span class="mt-auto pt-3 text-xs font-medium text-slate-500">Bloqueada para carga hasta que el jurídico lo autorice</span>
                    </div>
                </li>
            @endforeach
        @endif
    </ul>

    @if ($punto['n'] === 12)
        <p class="mt-4 text-sm text-slate-500">Las casillas salen de <a href="{{ route('campaigns.sheets.index', [$campaign, 'defensa-del-voto']) }}" class="text-brand-700 hover:underline">Defensa del Voto</a> y los promotores del <a href="{{ route('campaigns.promoters.index', $campaign) }}" class="text-brand-700 hover:underline">Padrón de Promotores</a>.</p>
    @endif

    @if ($punto['n'] === 16)
        <section class="card mt-6 overflow-hidden">
            <h2 class="border-b border-slate-200 px-5 py-4 font-semibold">Registro de consultas y exportaciones</h2>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($logs as $log)
                    <li class="flex flex-wrap justify-between gap-2 px-5 py-2">
                        <span><b>{{ $log->user?->name ?? '—' }}</b> · {{ $log->action }} · {{ $log->detail }}</span>
                        <span class="text-xs text-slate-500 tabular-nums">{{ $log->created_at?->format('d/m/Y H:i') }}</span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-slate-500">Sin consultas todavía.</li>
                @endforelse
            </ul>
        </section>
    @endif
@endsection
