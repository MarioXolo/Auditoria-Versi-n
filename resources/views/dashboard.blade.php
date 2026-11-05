@extends('layouts.app')

@section('title', 'Dashboard')

@section('header')
    <div>
        <h1 class="text-2xl font-semibold">Dashboard de auditoría</h1>
        <p class="text-sm text-stone-500">Cumplimiento de actividades y asistencia del personal.</p>
    </div>

    <form method="GET" class="flex flex-wrap gap-2">
        <select name="period" class="input w-auto" onchange="this.form.submit()">
            @foreach ($periods as $value => $label)
                <option value="{{ $value }}" @selected($period === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="team_id" class="input w-auto" onchange="this.form.submit()">
            <option value="">Todos los equipos</option>
            @foreach ($teams as $team)
                <option value="{{ $team->id }}" @selected($teamId === $team->id)>{{ $team->name }}</option>
            @endforeach
        </select>
        <noscript><button class="btn btn-secondary">Aplicar</button></noscript>
    </form>
@endsection

@section('content')
    {{-- Indicadores --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        @php
            $cards = [
                ['label' => 'Actividades', 'value' => $stats['total'], 'hint' => 'en el periodo', 'link' => route('activities.index')],
                ['label' => 'Completadas', 'value' => $stats['completed'], 'hint' => $stats['completionRate'].'% de cumplimiento', 'link' => route('activities.index', ['status' => 'completada'])],
                ['label' => 'Abiertas', 'value' => $stats['open'], 'hint' => 'pendientes o en progreso', 'link' => route('activities.index', ['status' => 'pendiente'])],
                ['label' => 'Vencidas', 'value' => $stats['overdue'], 'hint' => 'pasaron su fecha límite', 'link' => route('activities.index', ['status' => 'vencida']), 'alert' => $stats['overdue'] > 0],
                ['label' => 'Asistencia hoy', 'value' => $presentCount.' / '.$staffCount, 'hint' => 'con entrada registrada', 'link' => route('attendance.index')],
            ];
        @endphp
        @foreach ($cards as $card)
            <a href="{{ $card['link'] }}" class="card p-4 transition hover:border-brand-500 {{ $loop->last ? 'col-span-2 lg:col-span-1' : '' }}">
                <p class="text-xs font-medium tracking-wide text-stone-500 uppercase">{{ $card['label'] }}</p>
                <p @class(['mt-2 text-3xl font-semibold tabular-nums', 'text-rose-600' => $card['alert'] ?? false])>{{ $card['value'] }}</p>
                <p class="mt-1 text-xs text-stone-500">{{ $card['hint'] }}</p>
            </a>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Tendencia --}}
        <section class="card p-5 lg:col-span-2">
            <h2 class="font-semibold">Actividades completadas por día</h2>
            <p class="text-xs text-stone-500">Últimos 14 días</p>
            @php $max = max(1, collect($trend)->max('count')); @endphp
            <div class="mt-5 flex h-44 items-end gap-1.5 sm:gap-2">
                @foreach ($trend as $day)
                    <div class="flex h-full flex-1 flex-col items-center justify-end gap-1" title="{{ $day['label'] }}: {{ $day['count'] }}">
                        <span class="text-[10px] text-stone-500 tabular-nums">{{ $day['count'] ?: '' }}</span>
                        <div class="w-full rounded-t bg-brand-500" style="height: {{ max(2, round($day['count'] / $max * 100)) }}%"></div>
                        <span class="text-[10px] text-stone-400">{{ $day['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Distribución por estado --}}
        <section class="card p-5">
            <h2 class="font-semibold">Distribución por estado</h2>
            <div class="mt-4 space-y-3">
                @foreach (\App\Enums\ActivityStatus::cases() as $status)
                    @php
                        $count = (int) ($byStatus[$status->value] ?? 0);
                        $pct = $stats['total'] > 0 ? round($count / $stats['total'] * 100) : 0;
                    @endphp
                    <div>
                        <div class="mb-1 flex justify-between text-sm">
                            <span>{{ $status->label() }}</span>
                            <span class="tabular-nums text-stone-500">{{ $count }} · {{ $pct }}%</span>
                        </div>
                        <div class="h-2 rounded-full bg-stone-100">
                            <div class="h-2 rounded-full {{ explode(' ', $status->badge())[0] }} ring-1 ring-inset ring-black/5" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    {{-- Ranking por persona --}}
    <section class="card mt-6 overflow-hidden">
        <div class="flex items-center justify-between border-b border-stone-200 px-5 py-4">
            <div>
                <h2 class="font-semibold">Carga y cumplimiento por persona</h2>
                <p class="text-xs text-stone-500">Ordenado por quién tiene más actividades asignadas</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="table-head">Persona</th>
                        <th class="table-head">Equipo</th>
                        <th class="table-head text-right">Asignadas</th>
                        <th class="table-head text-right">Completadas</th>
                        <th class="table-head text-right">Abiertas</th>
                        <th class="table-head text-right">Vencidas</th>
                        <th class="table-head w-40">Cumplimiento</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($ranking as $row)
                        <tr class="hover:bg-stone-50">
                            <td class="table-cell">
                                <a href="{{ route('activities.index', ['assigned_to' => $row->user->id]) }}" class="font-medium text-stone-900 hover:text-brand-600">{{ $row->user->name }}</a>
                                <span class="block text-xs text-stone-500">{{ $row->user->role->label() }}</span>
                            </td>
                            <td class="table-cell">{{ $row->user->team?->name ?? '—' }}</td>
                            <td class="table-cell text-right tabular-nums font-medium">{{ $row->assigned }}</td>
                            <td class="table-cell text-right tabular-nums">{{ $row->completed }}</td>
                            <td class="table-cell text-right tabular-nums">{{ $row->open }}</td>
                            <td @class(['table-cell text-right tabular-nums', 'font-semibold text-rose-600' => $row->overdue > 0])>{{ $row->overdue }}</td>
                            <td class="table-cell">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 flex-1 rounded-full bg-stone-100">
                                        <div @class(['h-2 rounded-full', 'bg-emerald-500' => $row->rate >= 80, 'bg-amber-500' => $row->rate >= 50 && $row->rate < 80, 'bg-rose-500' => $row->rate < 50]) style="width: {{ $row->rate }}%"></div>
                                    </div>
                                    <span class="w-10 text-right text-xs tabular-nums">{{ $row->rate }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="table-cell py-8 text-center text-stone-500">No hay actividades en este periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        {{-- Encargados --}}
        <section class="card overflow-hidden">
            <div class="border-b border-stone-200 px-5 py-4">
                <h2 class="font-semibold">Encargados</h2>
                <p class="text-xs text-stone-500">Actividades que asignaron y cuántas se completaron</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200">
                    <thead class="bg-stone-50">
                        <tr>
                            <th class="table-head">Encargado</th>
                            <th class="table-head text-right">Asignó</th>
                            <th class="table-head text-right">Completadas</th>
                            <th class="table-head text-right">Propias</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($leaders as $leader)
                            <tr>
                                <td class="table-cell">
                                    <span class="font-medium text-stone-900">{{ $leader->name }}</span>
                                    <span class="block text-xs text-stone-500">{{ $leader->ledTeams->pluck('name')->join(', ') ?: 'Sin equipo a cargo' }}</span>
                                </td>
                                <td class="table-cell text-right tabular-nums">{{ $leader->created_total }}</td>
                                <td class="table-cell text-right tabular-nums">
                                    {{ $leader->created_done }}
                                    @if ($leader->created_total > 0)
                                        <span class="text-xs text-stone-400">({{ round($leader->created_done / $leader->created_total * 100) }}%)</span>
                                    @endif
                                </td>
                                <td class="table-cell text-right tabular-nums">{{ $leader->own_done }} / {{ $leader->own_total }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="table-cell py-6 text-center text-stone-500">No hay encargados registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Sin asistencia hoy --}}
        <section class="card overflow-hidden">
            <div class="border-b border-stone-200 px-5 py-4">
                <h2 class="font-semibold">Sin entrada registrada hoy</h2>
                <p class="text-xs text-stone-500">{{ $absent->count() }} de {{ $staffCount }} personas</p>
            </div>
            <ul class="max-h-80 divide-y divide-stone-100 overflow-y-auto">
                @forelse ($absent as $person)
                    <li class="flex items-center justify-between px-5 py-3 text-sm">
                        <span>{{ $person->name }}</span>
                        <span class="text-xs text-stone-500">{{ $person->role->label() }}</span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-stone-500">Todo el personal registró su entrada.</li>
                @endforelse
            </ul>
        </section>
    </div>

    {{-- Movimientos recientes --}}
    <section class="card mt-6 overflow-hidden">
        <div class="border-b border-stone-200 px-5 py-4">
            <h2 class="font-semibold">Movimientos recientes</h2>
        </div>
        <ul class="divide-y divide-stone-100">
            @forelse ($recent as $activity)
                <li class="flex flex-col gap-1 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <a href="{{ route('activities.show', $activity) }}" class="block truncate text-sm font-medium hover:text-brand-600">{{ $activity->title }}</a>
                        <span class="text-xs text-stone-500">
                            {{ $activity->assignee?->name }} · asignada por {{ $activity->creator?->name }} · {{ $activity->updated_at->diffForHumans() }}
                        </span>
                    </div>
                    <span class="badge {{ $activity->status->badge() }}">{{ $activity->status->label() }}</span>
                </li>
            @empty
                <li class="px-5 py-6 text-center text-sm text-stone-500">Aún no hay actividades.</li>
            @endforelse
        </ul>
    </section>
@endsection
