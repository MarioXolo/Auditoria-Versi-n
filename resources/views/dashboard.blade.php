@extends('layouts.app')

@section('title', 'Dashboard')

@use('App\Services\DashboardMetrics', 'M')

@php
    $tone = [
        'ok' => ['chip' => 'bg-emerald-100 text-emerald-800', 'bar' => 'bg-emerald-500', 'text' => 'text-emerald-700'],
        'warn' => ['chip' => 'bg-amber-100 text-amber-800', 'bar' => 'bg-amber-500', 'text' => 'text-amber-700'],
        'bad' => ['chip' => 'bg-rose-100 text-rose-800', 'bar' => 'bg-rose-500', 'text' => 'text-rose-700'],
        'none' => ['chip' => 'bg-slate-100 text-slate-600', 'bar' => 'bg-slate-300', 'text' => 'text-slate-500'],
    ];
    $toneLabel = ['ok' => 'En meta', 'warn' => 'Cerca', 'bad' => 'Bajo meta', 'none' => 'Sin datos'];
    $goals = config('auditoria.metas');

    // Cambio contra el periodo anterior. $lowerIsBetter invierte el color.
    $deltaHtml = function (?float $d, string $unit = 'pts', bool $lowerIsBetter = false) use ($hasPrev) {
        if (! $hasPrev || $d === null) {
            return '<span class="text-slate-400">Sin comparativo</span>';
        }
        if ($d == 0) {
            return '<span class="text-slate-500">Sin cambio vs periodo anterior</span>';
        }
        $good = $lowerIsBetter ? $d < 0 : $d > 0;
        $arrow = $d > 0 ? '▲' : '▼';
        $cls = $good ? 'text-emerald-700' : 'text-rose-700';
        $val = rtrim(rtrim(number_format(abs($d), 1), '0'), '.');

        return "<span class=\"{$cls} font-medium\">{$arrow} {$val} {$unit}</span> <span class=\"text-slate-400\">vs periodo anterior</span>";
    };
    $fmt = fn ($v, $suffix = '%') => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.').$suffix;
@endphp

@section('header')
    <div>
        <h1 class="text-2xl font-semibold">Dashboard de auditoría</h1>
        <p class="text-sm text-slate-500">
            {{ $metrics->from->translatedFormat('j M Y') }} – {{ $metrics->to->translatedFormat('j M Y') }}
            · actualizado {{ now()->format('H:i') }}
        </p>
    </div>

    <form method="GET" class="flex flex-wrap items-center gap-2">
        <select name="period" class="input w-auto" onchange="this.form.submit()" aria-label="Periodo">
            @foreach ($periods as $value => $label)
                <option value="{{ $value }}" @selected($period === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="area" class="input w-auto" onchange="this.form.submit()" aria-label="Área que encomendó">
            <option value="">Todas las áreas</option>
            @foreach ($areas as $a)
                <option value="{{ $a }}" @selected($area === $a)>{{ $a }}</option>
            @endforeach
        </select>
        <select name="encargado" class="input w-auto" onchange="this.form.submit()" aria-label="Encargado">
            <option value="">Todos los encargados</option>
            @foreach ($encargados as $e)
                <option value="{{ $e->id }}" @selected($encargadoId === $e->id)>{{ $e->folioLabel() ? $e->folioLabel().' · ' : '' }}{{ $e->name }}</option>
            @endforeach
        </select>
        <noscript><button class="btn btn-secondary">Aplicar</button></noscript>
        <a href="{{ route('dashboard.export', request()->only('period', 'area', 'encargado')) }}" class="btn btn-secondary">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
            Exportar CSV
        </a>
    </form>
@endsection

@section('content')
    {{-- KPI principales con meta y semáforo --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach ([
            ['key' => 'cumplimiento', 'label' => 'Cumplimiento', 'hint' => $kpi['completed'].' de '.($kpi['total'] - $kpi['cancelled']).' marcadas «Concluido»'],
            ['key' => 'a_tiempo', 'label' => 'Entrega a tiempo', 'hint' => 'de las concluidas, antes de su fecha de fin'],
        ] as $card)
            @php
                $value = $kpi[$card['key']];
                $st = M::status($card['key'], $value);
            @endphp
            <div class="card p-5">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ $card['label'] }}</p>
                    <span class="badge {{ $tone[$st]['chip'] }}">{{ $toneLabel[$st] }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <p class="text-4xl font-semibold tabular-nums {{ $tone[$st]['text'] }}">{{ $fmt($value) }}</p>
                    <p class="text-xs text-slate-500">meta {{ $goals[$card['key']] }}%</p>
                </div>
                <div class="relative mt-3 h-2 rounded-full bg-slate-100" aria-hidden="true">
                    <div class="h-2 rounded-full {{ $tone[$st]['bar'] }}" style="width: {{ min(100, $value ?? 0) }}%"></div>
                    <div class="absolute -top-1 h-4 w-0.5 bg-slate-700" style="left: {{ $goals[$card['key']] }}%" title="Meta"></div>
                </div>
                <p class="mt-3 text-xs text-slate-500">{{ $card['hint'] }}</p>
                <p class="mt-1 text-xs">{!! $deltaHtml($delta[$card['key']]) !!}</p>
            </div>
        @endforeach
    </div>

    {{-- KPI de volumen --}}
    <div class="mt-4 grid grid-cols-2 gap-4 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Actividades', 'value' => $kpi['total'], 'sub' => $kpi['sin_marcar'].' sin revisar · '.$kpi['incompletas'].' no completadas', 'delta' => $deltaHtml($delta['total'], ''), 'link' => route('activities.index')],
            ['label' => 'Vencidas', 'value' => $kpi['overdue'], 'sub' => 'abiertas con fecha de fin pasada', 'delta' => $deltaHtml($delta['overdue'], '', true), 'link' => route('activities.index', ['status' => 'vencida']), 'alert' => $kpi['overdue'] > 0],
            ['label' => 'Duración promedio', 'value' => $fmt($kpi['duracion'], ' días'), 'sub' => 'de la fecha de inicio a concluida · planeado '.$fmt($durations['planeado'], ' días'), 'delta' => $deltaHtml($delta['duracion'], 'días', true)],
            ['label' => 'Contra su fecha de fin', 'value' => $fmt($durations['a_tiempo_pct']), 'sub' => $durations['antes'].' antes · '.$durations['en_fecha'].' en la fecha · '.$durations['despues'].' después', 'delta' => '', 'alert' => $durations['despues'] > 0 && $durations['despues'] >= $durations['antes'] + $durations['en_fecha']],
        ] as $card)
            @php $tag = isset($card['link']) ? 'a' : 'div'; @endphp
            <{{ $tag }} @if (isset($card['link'])) href="{{ $card['link'] }}" @endif class="card block p-4 {{ isset($card['link']) ? 'transition hover:border-brand-500' : '' }}">
                <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ $card['label'] }}</p>
                <p @class(['mt-1 text-2xl font-semibold tabular-nums', 'text-rose-600' => $card['alert'] ?? false])>{{ $card['value'] }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $card['sub'] }}</p>
                <p class="mt-1 text-xs">{!! $card['delta'] !!}</p>
            </{{ $tag }}>
        @endforeach
    </div>

    {{-- Actividades por estado (los 5 estados; respeta periodo y equipo del filtro) --}}
    @php
        $statusRows = $charts['status'];
        $statusTotal = array_sum(array_column($statusRows, 'value'));
        $statusDot = ['no_iniciado' => 'bg-amber-400', 'en_proceso' => 'bg-sky-500', 'suspendida' => 'bg-slate-400', 'concluido' => 'bg-emerald-600', 'no_completada' => 'bg-rose-600'];
        $statusHint = ['no_iniciado' => 'aún sin arrancar', 'en_proceso' => 'trabajándose', 'suspendida' => 'detenidas por ahora', 'concluido' => 'revisadas en verde', 'no_completada' => 'revisadas en rojo'];
    @endphp
    <section class="mt-6" aria-labelledby="por-estado">
        <div class="mb-2 flex items-baseline justify-between">
            <h2 id="por-estado" class="font-semibold">Actividades por estado</h2>
            <p class="text-xs text-slate-500">{{ $statusTotal }} en el periodo</p>
        </div>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5">
            @foreach ($statusRows as $row)
                <a href="{{ route('activities.index', ['status' => $row['key']]) }}" class="card block p-4 transition hover:border-brand-500">
                    <p class="flex items-center gap-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        <span class="size-2.5 rounded-full {{ $statusDot[$row['key']] ?? 'bg-slate-300' }}" aria-hidden="true"></span>{{ $row['label'] }}
                    </p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $row['value'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $statusTotal > 0 ? number_format($row['value'] / $statusTotal * 100, 1).' %' : '—' }} · {{ $statusHint[$row['key']] ?? '' }}
                    </p>
                    <div class="mt-2 h-1.5 rounded-full bg-slate-100" aria-hidden="true">
                        <div class="h-1.5 rounded-full {{ $statusDot[$row['key']] ?? 'bg-slate-300' }}" style="width: {{ $statusTotal > 0 ? round($row['value'] / $statusTotal * 100, 1) : 0 }}%"></div>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Alertas --}}
    @if (count($alerts))
        <section class="card mt-6 overflow-hidden" aria-labelledby="alertas">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h2 id="alertas" class="font-semibold">Requiere atención</h2>
                <span class="badge bg-slate-100 text-slate-600">{{ count($alerts) }}</span>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($alerts as $alert)
                    <li class="flex gap-3 px-5 py-3">
                        <span @class([
                            'mt-1.5 size-2.5 shrink-0 rounded-full',
                            'bg-rose-500' => $alert['level'] === 'bad',
                            'bg-amber-500' => $alert['level'] === 'warn',
                            'bg-sky-500' => $alert['level'] === 'info',
                        ])></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">{{ $alert['title'] }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $alert['detail'] }}</p>
                        </div>
                        @if ($alert['link'])
                            <a href="{{ $alert['link'] }}" class="shrink-0 self-center text-xs font-medium text-brand-600 hover:underline">Ver</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @include('dashboard._pulse')

    {{-- Gráficas --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="card p-5 lg:col-span-2">
            <h2 class="font-semibold">Actividades creadas vs concluidas</h2>
            <p class="text-xs text-slate-500">{{ $metrics->from->diffInDays($metrics->to) > 45 ? 'Por semana' : 'Por día' }} en el periodo</p>
            <div class="mt-4 h-64"><canvas id="chart-trend" role="img" aria-label="Gráfica de actividades creadas y concluidas"></canvas></div>
        </section>
        <section class="card p-5">
            <h2 class="font-semibold">Distribución por estado</h2>
            <p class="text-xs text-slate-500">{{ $kpi['total'] }} actividades del periodo</p>
            <div class="mt-4 h-64"><canvas id="chart-status" role="img" aria-label="Gráfica de distribución por estado"></canvas></div>
        </section>
    </div>

    <div class="mt-6">
        <section class="card p-5">
            <h2 class="font-semibold">Por tipo de actividad</h2>
            <p class="text-xs text-slate-500">Concluidas, no completadas y sin revisar de cada tipo</p>
            <div class="mt-4 h-60"><canvas id="chart-types" role="img" aria-label="Gráfica de actividades por tipo"></canvas></div>
        </section>
    </div>

    {{-- Calidad de la evidencia y velocidad de revisión --}}
    @php $bar = fn ($n) => $quality['concluded'] > 0 ? round($n / $quality['concluded'] * 100, 1) : 0; @endphp
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="card p-5">
            <h2 class="font-semibold">Calidad de la evidencia</h2>
            <p class="text-xs text-slate-500">De las {{ $quality['concluded'] }} actividades concluidas del periodo</p>
            <div class="mt-4 flex items-baseline gap-2">
                <p @class(['text-4xl font-semibold tabular-nums', 'text-rose-600' => $quality['none'] > 0 && $quality['with_pct'] < 80])>{{ $fmt($quality['with_pct']) }}</p>
                <p class="text-xs text-slate-500">se cerraron con evidencia</p>
            </div>
            @if ($quality['concluded'] > 0)
                <div class="mt-3 flex h-2.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                    <div class="bg-emerald-600" style="width: {{ $bar($quality['photo']) }}%"></div>
                    <div class="bg-sky-500" style="width: {{ $bar($quality['text']) }}%"></div>
                    <div class="bg-rose-500" style="width: {{ $bar($quality['none']) }}%"></div>
                </div>
            @endif
            <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
                <div><dt class="flex items-center gap-1.5 text-xs text-slate-500"><span class="size-2 rounded-full bg-emerald-600"></span>Con fotografía</dt><dd class="text-xl font-semibold tabular-nums">{{ $quality['photo'] }}</dd></div>
                <div><dt class="flex items-center gap-1.5 text-xs text-slate-500"><span class="size-2 rounded-full bg-sky-500"></span>Solo texto</dt><dd class="text-xl font-semibold tabular-nums">{{ $quality['text'] }}</dd></div>
                <div><dt class="flex items-center gap-1.5 text-xs text-slate-500"><span class="size-2 rounded-full bg-rose-500"></span>Sin evidencia</dt><dd @class(['text-xl font-semibold tabular-nums', 'text-rose-600' => $quality['none'] > 0])>{{ $quality['none'] }}</dd></div>
            </dl>
            @if ($quality['none'] > 0)
                <a href="{{ route('activities.index', ['status' => 'concluido', 'evidencia' => 'sin']) }}" class="mt-4 inline-block text-xs font-medium text-brand-600 hover:underline">Ver las concluidas sin evidencia →</a>
            @endif
        </section>

        <section class="card p-5">
            <h2 class="font-semibold">Velocidad de revisión</h2>
            <p class="text-xs text-slate-500">Días entre la fecha de fin y la marca verde o roja</p>
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <p class="text-4xl font-semibold tabular-nums">{{ $fmt($quality['review_days'], ' días') }}</p>
                    <p class="text-xs text-slate-500">promedio en {{ $quality['reviewed'] }} revisadas</p>
                </div>
                <a href="{{ route('activities.index', ['status' => 'vencida']) }}" class="block rounded-lg transition hover:bg-slate-50">
                    <p @class(['text-4xl font-semibold tabular-nums', 'text-rose-600' => $quality['pending'] > 0])>{{ $quality['pending'] }}</p>
                    <p class="text-xs text-slate-500">esperando revisión con fecha de fin pasada
                        @if ($quality['pending_max_days'])
                            · la más antigua lleva {{ $quality['pending_max_days'] }} {{ $quality['pending_max_days'] === 1 ? 'día' : 'días' }}
                        @endif
                    </p>
                </a>
            </div>
            @if ($quality['reviewers']->isNotEmpty())
                <table class="mt-4 w-full text-sm">
                    <thead><tr class="text-left text-xs text-slate-500"><th class="py-1 font-medium">Quién revisa</th><th class="py-1 text-right font-medium">Revisadas</th><th class="py-1 text-right font-medium">Días promedio</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($quality['reviewers'] as $r)
                            <tr><td class="py-1.5">{{ $r->name }}</td><td class="py-1.5 text-right tabular-nums">{{ $r->count }}</td><td @class(['py-1.5 text-right font-medium tabular-nums', 'text-rose-600' => $r->days > 3])>{{ number_format($r->days, 1) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="mt-2 text-xs text-slate-400">Los más lentos primero; 0 significa que se revisó a más tardar en la fecha de fin.</p>
            @else
                <p class="mt-4 text-sm text-slate-500">Aún no hay actividades revisadas en el periodo.</p>
            @endif
        </section>
    </div>

    <section class="card mt-6 p-5">
        <h2 class="font-semibold">Por comunidad</h2>
        <p class="text-xs text-slate-500">Las {{ count($charts['communities']) }} comunidades con más actividades en el periodo</p>
        <div class="mt-4 h-64"><canvas id="chart-communities" role="img" aria-label="Gráfica de actividades por comunidad"></canvas></div>
    </section>

    {{-- Indicadores por persona --}}
    <section class="card mt-6 overflow-hidden">
        <div class="flex flex-col gap-1 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold">Indicadores por persona</h2>
                <p class="text-xs text-slate-500">Ordenado por carga de trabajo. Los colores comparan contra la meta de cada indicador.</p>
            </div>
            <a href="{{ route('dashboard.export', request()->only('period', 'area', 'encargado')) }}" class="text-xs font-medium text-brand-600 hover:underline">Descargar esta tabla (CSV)</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="table-head">Persona</th>
                        <th class="table-head text-right">Asignadas</th>
                        <th class="table-head text-right">Concluidas</th>
                        <th class="table-head text-right">No completadas</th>
                        <th class="table-head text-right">Vencidas</th>
                        <th class="table-head text-right">Cumplimiento</th>
                        <th class="table-head text-right">A tiempo</th>
                        <th class="table-head text-right">Duración (días)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($people as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="table-cell">
                                <a href="{{ route('activities.index', ['assigned_to' => $row->user->id]) }}" class="font-medium whitespace-nowrap text-slate-900 hover:text-brand-600">{{ $row->user->name }}</a>
                                <span class="block text-xs whitespace-nowrap text-slate-500">{{ $row->user->role->label() }}{{ $row->user->folioLabel() ? ' · '.$row->user->folioLabel() : '' }}</span>
                            </td>
                            <td class="table-cell text-right font-medium tabular-nums">{{ $row->assigned }}<span class="block text-xs font-normal text-slate-500">{{ $row->propias }} propias</span></td>
                            <td class="table-cell text-right tabular-nums">{{ $row->completed }}</td>
                            <td @class(['table-cell text-right tabular-nums', 'font-semibold text-rose-600' => $row->incompletas > 0])>{{ $row->incompletas }}</td>
                            <td @class(['table-cell text-right tabular-nums', 'font-semibold text-rose-600' => $row->overdue > 0])>{{ $row->overdue }}</td>
                            @foreach (['cumplimiento', 'a_tiempo'] as $k)
                                <td class="table-cell text-right"><span class="badge tabular-nums {{ $tone[M::status($k, $row->$k)]['chip'] }}">{{ $fmt($row->$k) }}</span></td>
                            @endforeach
                            <td class="table-cell text-right tabular-nums">{{ $fmt($row->duracion, '') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="table-cell py-8 text-center text-slate-500">No hay encargados activos con estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @include('dashboard._durations', ['d' => $durations, 'showPerson' => true])

    <div class="mt-6">
        {{-- Movimientos recientes --}}
        <section class="card overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold">Movimientos recientes</h2>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($recent as $activity)
                    <li class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <a href="{{ route('activities.show', $activity) }}" class="block truncate text-sm font-medium hover:text-brand-600"><span class="font-mono text-xs text-slate-500">{{ $activity->code }}</span> {{ $activity->type?->name }} · {{ $activity->community?->name }}</a>
                            <span class="block truncate text-xs text-slate-500">{{ $activity->assignee?->name }} · {{ $activity->isSelfAssigned() ? 'se la cargó él' : 'asignada por '.$activity->creator?->name }} · {{ $activity->updated_at->diffForHumans() }}</span>
                        </div>
                        <span class="badge {{ $activity->status->badge() }}">{{ $activity->status->label() }}</span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-slate-500">Aún no hay actividades.</li>
                @endforelse
            </ul>
        </section>
    </div>

    {{-- Definiciones de indicadores --}}
    <details class="card mt-6 overflow-hidden" open>
        <summary class="cursor-pointer px-5 py-4 font-semibold">Cómo se calcula cada indicador</summary>
        <div class="overflow-x-auto border-t border-slate-200">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="table-head">Indicador</th>
                        <th class="table-head">Fórmula</th>
                        <th class="table-head">Meta</th>
                        <th class="table-head">Fuente</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($definitions as [$name, $formula, $goal, $source])
                        <tr>
                            <td class="table-cell font-medium whitespace-nowrap">{{ $name }}</td>
                            <td class="table-cell">{{ $formula }}</td>
                            <td class="table-cell whitespace-nowrap">{{ $goal }}</td>
                            <td class="table-cell text-slate-500">{{ $source }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500">
            Semáforo: verde en meta o arriba, ámbar hasta {{ config('auditoria.margen_ambar') }} puntos debajo de la meta, rojo por debajo de eso.
            El checador de asistencia no interviene en ningún indicador.
        </p>
    </details>

    <script type="application/json" id="dashboard-data">@json($charts)</script>
@endsection

@push('scripts')
    @vite('resources/js/dashboard.js')
@endpush
