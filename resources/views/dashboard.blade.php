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
            · {{ $kpi['workdays'] }} días laborales · actualizado {{ now()->format('H:i') }}
        </p>
    </div>

    <form method="GET" class="flex flex-wrap items-center gap-2">
        <select name="period" class="input w-auto" onchange="this.form.submit()" aria-label="Periodo">
            @foreach ($periods as $value => $label)
                <option value="{{ $value }}" @selected($period === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="team_id" class="input w-auto" onchange="this.form.submit()" aria-label="Equipo">
            <option value="">Todos los equipos</option>
            @foreach ($teams as $team)
                <option value="{{ $team->id }}" @selected($teamId === $team->id)>{{ $team->name }}</option>
            @endforeach
        </select>
        <noscript><button class="btn btn-secondary">Aplicar</button></noscript>
        <a href="{{ route('dashboard.export', request()->only('period', 'team_id')) }}" class="btn btn-secondary">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
            Exportar CSV
        </a>
    </form>
@endsection

@section('content')
    {{-- KPI principales con meta y semáforo --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['key' => 'cumplimiento', 'label' => 'Cumplimiento', 'hint' => $kpi['completed'].' de '.($kpi['total'] - $kpi['cancelled']).' marcadas como completadas'],
            ['key' => 'a_tiempo', 'label' => 'Entrega a tiempo', 'hint' => 'de las completadas, antes de su fecha de fin'],
            ['key' => 'asistencia', 'label' => 'Asistencia', 'hint' => $kpi['present'].' de '.$kpi['expected'].' jornadas esperadas'],
            ['key' => 'puntualidad', 'label' => 'Puntualidad', 'hint' => $kpi['late'].' retardos (entrada después de '.config('auditoria.hora_entrada').' + '.config('auditoria.tolerancia_minutos').' min)'],
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
            ['label' => 'Actividades', 'value' => $kpi['total'], 'sub' => $kpi['sin_marcar'].' sin marcar · '.$kpi['incompletas'].' incompletas', 'delta' => $deltaHtml($delta['total'], ''), 'link' => route('activities.index')],
            ['label' => 'Vencidas', 'value' => $kpi['overdue'], 'sub' => 'pendientes con fecha de fin pasada', 'delta' => $deltaHtml($delta['overdue'], '', true), 'link' => route('activities.index', ['status' => 'vencida']), 'alert' => $kpi['overdue'] > 0],
            ['label' => 'Tiempo de cierre', 'value' => $fmt($kpi['dias_cierre'], ' días'), 'sub' => 'promedio de creación a completado', 'delta' => $deltaHtml($delta['dias_cierre'], 'días', true)],
            ['label' => 'Horas por jornada', 'value' => $fmt($kpi['horas'], ' h'), 'sub' => 'promedio entre entrada y salida', 'delta' => $deltaHtml($delta['horas'], 'h')],
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

    {{-- Gráficas --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="card p-5 lg:col-span-2">
            <h2 class="font-semibold">Actividades creadas vs completadas</h2>
            <p class="text-xs text-slate-500">{{ $metrics->from->diffInDays($metrics->to) > 45 ? 'Por semana' : 'Por día' }} en el periodo</p>
            <div class="mt-4 h-64"><canvas id="chart-trend" role="img" aria-label="Gráfica de actividades creadas y completadas"></canvas></div>
        </section>
        <section class="card p-5">
            <h2 class="font-semibold">Distribución por estado</h2>
            <p class="text-xs text-slate-500">{{ $kpi['total'] }} actividades del periodo</p>
            <div class="mt-4 h-64"><canvas id="chart-status" role="img" aria-label="Gráfica de distribución por estado"></canvas></div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="card p-5">
            <h2 class="font-semibold">Asistencia y puntualidad diaria</h2>
            <p class="text-xs text-slate-500">Días laborales, últimos 30 días como máximo</p>
            <div class="mt-4 h-60"><canvas id="chart-attendance" role="img" aria-label="Gráfica de asistencia y puntualidad"></canvas></div>
        </section>
        <section class="card p-5">
            <h2 class="font-semibold">Comparativo por equipo</h2>
            <p class="text-xs text-slate-500">Cumplimiento, entrega a tiempo y asistencia</p>
            <div class="mt-4 h-60"><canvas id="chart-teams" role="img" aria-label="Gráfica comparativa por equipo"></canvas></div>
        </section>
        <section class="card p-5">
            <h2 class="font-semibold">Por tipo de actividad</h2>
            <p class="text-xs text-slate-500">Completadas, incompletas y sin marcar de cada tipo</p>
            <div class="mt-4 h-60"><canvas id="chart-types" role="img" aria-label="Gráfica de actividades por tipo"></canvas></div>
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
            <a href="{{ route('dashboard.export', request()->only('period', 'team_id')) }}" class="text-xs font-medium text-brand-600 hover:underline">Descargar esta tabla (CSV)</a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="table-head">Persona</th>
                        <th class="table-head text-right">Asignadas</th>
                        <th class="table-head text-right">Completadas</th>
                        <th class="table-head text-right">Incompletas</th>
                        <th class="table-head text-right">Vencidas</th>
                        <th class="table-head text-right">Cumplimiento</th>
                        <th class="table-head text-right">A tiempo</th>
                        <th class="table-head text-right">Días cierre</th>
                        <th class="table-head text-right">Asistencia</th>
                        <th class="table-head text-right">Faltas</th>
                        <th class="table-head text-right">Retardos</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($people as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="table-cell">
                                <a href="{{ route('activities.index', ['assigned_to' => $row->user->id]) }}" class="font-medium whitespace-nowrap text-slate-900 hover:text-brand-600">{{ $row->user->name }}</a>
                                <span class="block text-xs whitespace-nowrap text-slate-500">{{ $row->user->role->label() }}{{ $row->user->team ? ' · '.$row->user->team->name : '' }}</span>
                            </td>
                            <td class="table-cell text-right font-medium tabular-nums">{{ $row->assigned }}</td>
                            <td class="table-cell text-right tabular-nums">{{ $row->completed }}</td>
                            <td @class(['table-cell text-right tabular-nums', 'font-semibold text-rose-600' => $row->incompletas > 0])>{{ $row->incompletas }}</td>
                            <td @class(['table-cell text-right tabular-nums', 'font-semibold text-rose-600' => $row->overdue > 0])>{{ $row->overdue }}</td>
                            @foreach (['cumplimiento', 'a_tiempo'] as $k)
                                <td class="table-cell text-right"><span class="badge tabular-nums {{ $tone[M::status($k, $row->$k)]['chip'] }}">{{ $fmt($row->$k) }}</span></td>
                            @endforeach
                            <td class="table-cell text-right tabular-nums">{{ $fmt($row->dias_cierre, '') }}</td>
                            <td class="table-cell text-right"><span class="badge tabular-nums {{ $tone[M::status('asistencia', $row->asistencia)]['chip'] }}">{{ $fmt($row->asistencia) }}</span></td>
                            <td @class(['table-cell text-right tabular-nums', 'font-semibold text-rose-600' => $row->absences >= 2])>{{ $row->absences }}</td>
                            <td @class(['table-cell text-right tabular-nums', 'font-semibold text-amber-700' => $row->late >= 3])>{{ $row->late }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="table-cell py-8 text-center text-slate-500">No hay personal activo con estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Auditoría de encargados --}}
    <section class="card mt-6 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="font-semibold">Auditoría de encargados</h2>
            <p class="text-xs text-slate-500">Lo que cada encargado asignó en el periodo, cómo se cumplió y la asistencia de su equipo</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="table-head">Encargado</th>
                        <th class="table-head text-right">Integrantes</th>
                        <th class="table-head text-right">Asignó</th>
                        <th class="table-head text-right">Completadas</th>
                        <th class="table-head text-right">Vencidas</th>
                        <th class="table-head text-right">Cumplimiento</th>
                        <th class="table-head text-right">A tiempo</th>
                        <th class="table-head text-right">Asistencia del equipo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($leaders as $leader)
                        <tr>
                            <td class="table-cell">
                                <span class="font-medium whitespace-nowrap text-slate-900">{{ $leader->user->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $leader->teams ?: 'Sin equipo a cargo' }}</span>
                            </td>
                            <td class="table-cell text-right tabular-nums">{{ $leader->members }}</td>
                            <td class="table-cell text-right font-medium tabular-nums">{{ $leader->assigned }}</td>
                            <td class="table-cell text-right tabular-nums">{{ $leader->completed }}</td>
                            <td @class(['table-cell text-right tabular-nums', 'font-semibold text-rose-600' => $leader->overdue > 0])>{{ $leader->overdue }}</td>
                            <td class="table-cell text-right"><span class="badge tabular-nums {{ $tone[M::status('cumplimiento', $leader->cumplimiento)]['chip'] }}">{{ $fmt($leader->cumplimiento) }}</span></td>
                            <td class="table-cell text-right"><span class="badge tabular-nums {{ $tone[M::status('a_tiempo', $leader->a_tiempo)]['chip'] }}">{{ $fmt($leader->a_tiempo) }}</span></td>
                            <td class="table-cell text-right"><span class="badge tabular-nums {{ $tone[M::status('asistencia', $leader->asistencia_equipo)]['chip'] }}">{{ $fmt($leader->asistencia_equipo) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="table-cell py-6 text-center text-slate-500">No hay encargados registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
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
                            <span class="block truncate text-xs text-slate-500">{{ $activity->assignee?->name }} · asignada por {{ $activity->creator?->name }} · {{ $activity->updated_at->diffForHumans() }}</span>
                        </div>
                        <span class="badge {{ $activity->status->badge() }}">{{ $activity->status->label() }}</span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-slate-500">Aún no hay actividades.</li>
                @endforelse
            </ul>
        </section>

        {{-- Sin entrada hoy --}}
        <section class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <div>
                    <h2 class="font-semibold">Sin entrada registrada hoy</h2>
                    <p class="text-xs text-slate-500">{{ now()->translatedFormat('l j \d\e F') }}</p>
                </div>
                <span class="badge {{ $absent->isEmpty() ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">{{ $absent->count() }}</span>
            </div>
            <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                @forelse ($absent as $person)
                    <li class="flex items-center justify-between px-5 py-3 text-sm">
                        <span>{{ $person->name }}</span>
                        <span class="text-xs text-slate-500">{{ $person->role->label() }}{{ $person->team ? ' · '.$person->team->name : '' }}</span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-slate-500">Todo el personal registró su entrada, o hoy no es día laboral.</li>
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
            Días laborales: {{ collect(config('auditoria.dias_laborales'))->map(fn ($d) => ['', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'][$d])->join(', ') }}.
        </p>
    </details>

    <script type="application/json" id="dashboard-data">@json($charts)</script>
@endsection

@push('scripts')
    @vite('resources/js/dashboard.js')
@endpush
