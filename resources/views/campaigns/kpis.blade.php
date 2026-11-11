@extends('layouts.app')

@section('title', 'Tablero KPI · '.$campaign->name)

@php
    $fmt = fn ($v, $pct) => $v === null ? '—' : ($pct ? number_format($v * 100, 1).'%' : number_format($v, $v == floor($v) ? 0 : 2));
    $num = fn ($v, $pct) => $pct ? rtrim(rtrim(number_format($v * 100, 2, '.', ''), '0'), '.') : rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');
@endphp

@section('content')
    @include('campaigns._nav')

    <p class="mb-4 text-sm text-slate-500">Revisar en la junta semanal. Los primeros indicadores se calculan solos con los datos del cuaderno; los de captura manual se actualizan con su <b>valor actual</b> y <b>fecha de corte</b>. Semáforo: verde ≥ 90 %, ámbar ≥ 60 %, rojo debajo.</p>

    <div class="card overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="table-head">Indicador</th>
                    <th class="table-head text-right">Meta</th>
                    <th class="table-head text-right">Valor actual</th>
                    <th class="table-head text-right">% cumplimiento</th>
                    <th class="table-head">Estatus</th>
                    <th class="table-head">Fecha de corte</th>
                    <th class="table-head"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($kpis as $kpi)
                    @if ($kpi->auto)
                        <tr @class(['text-slate-400' => $kpi->meta === null])>
                            <td class="table-cell">
                                <span class="font-medium {{ $kpi->meta === null ? '' : 'text-slate-900' }}">{{ $kpi->nombre }}</span>
                                <span class="block text-xs {{ $kpi->meta === null ? '' : 'text-slate-500' }}">{{ $kpi->fuente }}</span>
                            </td>
                            <td class="table-cell text-right tabular-nums">{{ $fmt($kpi->meta, false) }}</td>
                            <td class="table-cell text-right tabular-nums">{{ $fmt($kpi->valor, false) }}</td>
                            <td class="table-cell text-right tabular-nums">{{ $kpi->status && $kpi->status['ratio'] !== null ? number_format($kpi->status['ratio'] * 100, 1).'%' : '—' }}</td>
                            <td class="table-cell">@if ($kpi->status)<span class="badge {{ $kpi->status['class'] }}">{{ $kpi->status['label'] }}</span>@else — @endif</td>
                            <td class="table-cell text-xs">@if ($kpi->meta !== null)<span class="badge bg-brand-50 text-brand-700">Automático · hoy</span>@else Etapa 2 @endif</td>
                            <td class="table-cell"></td>
                        </tr>
                    @else
                        @php $k = $kpi->model; $formId = 'kpi-'.$k->id; @endphp
                        <tr>
                            <td class="table-cell">
                                <input form="{{ $formId }}" name="nombre" value="{{ $k->nombre }}" required maxlength="150" class="input min-w-56 py-1.5 text-sm font-medium" aria-label="Indicador">
                                <label class="mt-1 inline-flex items-center gap-1.5 text-xs text-slate-500"><input form="{{ $formId }}" type="checkbox" name="es_porcentaje" value="1" @checked($k->es_porcentaje) class="rounded border-slate-300"> Es porcentaje</label>
                            </td>
                            <td class="table-cell text-right"><input form="{{ $formId }}" type="number" step="any" min="0" name="meta" value="{{ $num($k->meta, $k->es_porcentaje) }}" class="input w-28 py-1.5 text-right text-sm tabular-nums" aria-label="Meta"></td>
                            <td class="table-cell text-right"><input form="{{ $formId }}" type="number" step="any" min="0" name="valor" value="{{ $num($k->valor, $k->es_porcentaje) }}" class="input w-28 py-1.5 text-right text-sm tabular-nums" aria-label="Valor actual"></td>
                            <td class="table-cell text-right tabular-nums">{{ $kpi->status['ratio'] !== null ? number_format($kpi->status['ratio'] * 100, 1).'%' : '—' }}</td>
                            <td class="table-cell"><span class="badge {{ $kpi->status['class'] }}">{{ $kpi->status['label'] }}</span></td>
                            <td class="table-cell"><input form="{{ $formId }}" name="fecha_corte" value="{{ $k->fecha_corte }}" maxlength="60" placeholder="Ej. jun-2026 (encuestadora)" class="input min-w-40 py-1.5 text-sm" aria-label="Fecha de corte"></td>
                            <td class="table-cell whitespace-nowrap text-right">
                                <form id="{{ $formId }}" method="POST" action="{{ route('campaigns.kpis.update', [$campaign, $k]) }}" class="inline">@csrf @method('PUT')
                                    <button class="text-xs font-medium text-brand-600 hover:underline">Guardar</button>
                                </form>
                                <form method="POST" action="{{ route('campaigns.kpis.destroy', [$campaign, $k]) }}" class="ml-2 inline" onsubmit="return confirm('¿Eliminar este indicador?')">@csrf @method('DELETE')
                                    <button class="text-xs font-medium text-rose-600 hover:underline">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('campaigns.kpis.store', $campaign) }}" class="card mt-5 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1.4fr_auto] lg:items-end">
        @csrf
        <div>
            <label for="nombre" class="label">Nuevo indicador manual</label>
            <input id="nombre" name="nombre" required maxlength="150" class="input" placeholder="Ej. Eventos realizados en la semana">
            <label class="mt-1 inline-flex items-center gap-1.5 text-xs text-slate-500"><input type="checkbox" name="es_porcentaje" value="1" class="rounded border-slate-300"> Es porcentaje</label>
        </div>
        <div><label for="meta" class="label">Meta</label><input id="meta" type="number" step="any" min="0" name="meta" value="0" required class="input tabular-nums"></div>
        <div><label for="valor" class="label">Valor actual</label><input id="valor" type="number" step="any" min="0" name="valor" value="0" required class="input tabular-nums"></div>
        <div><label for="fecha_corte" class="label">Fecha de corte</label><input id="fecha_corte" name="fecha_corte" maxlength="60" class="input"></div>
        <button class="btn btn-primary">Agregar</button>
    </form>
@endsection
