@extends('layouts.app')

@section('title', $sheet['title'].' · '.$campaign->name)

@use('App\Http\Controllers\Campaigns\SheetController')
@use('App\Services\CampaignSheets')

@php
    $key = $sheet['key'];
    $columns = SheetController::columns($sheet);
    $totals = $sheet['totals'] ?? [];
    $sumOf = function (string $name) use ($all, $sheet) {
        return isset($sheet['computed'][$name])
            ? $all->sum(fn ($r) => ($sheet['computed'][$name]['value'])($r))
            : $all->sum($name);
    };
@endphp

@section('content')
    @include('campaigns._nav')

    <p class="mb-4 text-sm text-slate-500">{{ $sheet['intro'] }}</p>

    {{-- Resumen --}}
    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($summary as $card)
            <div class="card p-4">
                <p class="text-xs text-slate-500">{{ $card[0] }}</p>
                <p class="text-2xl font-semibold tabular-nums">{{ $card[1] }}</p>
                @if (! empty($card[2]))<p class="text-xs text-slate-500 tabular-nums">{{ $card[2] }}</p>@endif
            </div>
        @endforeach
    </div>

    {{-- Presupuesto: tope legal y alerta, igual que «¿Asignado dentro del tope?» del Excel --}}
    @if ($key === 'presupuesto')
        @php $asignado = $all->sum('asignado'); $ejercido = $all->sum('ejercido'); $tope = $campaign->tope_gastos; @endphp
        <div class="mb-4 grid gap-3 lg:grid-cols-[1fr_1.4fr]">
            <form method="POST" action="{{ route('campaigns.sheets.tope', $campaign) }}" class="card flex flex-wrap items-end gap-3 p-4">
                @csrf @method('PUT')
                <div class="min-w-48 flex-1">
                    <label for="tope_gastos" class="label">Tope de gastos de campaña (IEEQ)</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-slate-400">$</span>
                        <input id="tope_gastos" type="number" min="0" step="any" name="tope_gastos" value="{{ rtrim(rtrim(number_format($tope, 2, '.', ''), '0'), '.') }}" class="input pl-6 text-right tabular-nums" required>
                    </div>
                </div>
                <button class="btn btn-secondary">Guardar tope</button>
            </form>
            <div @class([
                'card flex items-center gap-3 p-4 text-sm',
                'border-rose-300 bg-rose-50 text-rose-800' => $tope > 0 && ($asignado > $tope || $ejercido > $tope),
                'border-emerald-200 bg-emerald-50 text-emerald-800' => $tope > 0 && $asignado <= $tope && $ejercido <= $tope,
                'text-slate-500' => $tope <= 0,
            ])>
                @if ($tope <= 0)
                    Captura el tope del IEEQ para verificar que el presupuesto no lo rebase.
                @elseif ($ejercido > $tope)
                    <b>¡El gasto ejercido rebasa el tope legal!</b> Ejercido {{ CampaignSheets::money($ejercido) }} de {{ CampaignSheets::money($tope) }}.
                @elseif ($asignado > $tope)
                    <b>¡El asignado rebasa el tope!</b> Asignado {{ CampaignSheets::money($asignado) }} de {{ CampaignSheets::money($tope) }}; recorta {{ CampaignSheets::money($asignado - $tope) }}.
                @else
                    <b>¿Asignado dentro del tope? SÍ ✓</b> Queda margen de {{ CampaignSheets::money($tope - $asignado) }} sin asignar.
                @endif
            </div>
        </div>
    @endif

    {{-- Agregar fila --}}
    <details class="card mb-4" @if ($errors->any() || $all->isEmpty()) open @endif>
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-brand-700">+ Agregar {{ $sheet['singular'] }}</summary>
        <form method="POST" action="{{ route('campaigns.sheets.store', [$campaign, $key]) }}" class="grid gap-3 border-t border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            @foreach ($sheet['fields'] as $name => $f)
                <div @class(['sm:col-span-2' => in_array($name, ['notas', 'frase', 'tarea', 'logro', 'nombre'])])>
                    <label for="new-{{ $name }}" class="label">{{ $f['label'] }}@if ($f['required'] ?? false) *@endif</label>
                    @include('campaigns.sheets._input', ['f' => $f, 'name' => $name, 'value' => old($name, $f['default'] ?? null), 'form' => null, 'compact' => false])
                </div>
            @endforeach
            <div class="flex items-end sm:col-span-2 lg:col-span-4 lg:justify-end">
                <button class="btn btn-primary">Agregar</button>
            </div>
        </form>
    </details>

    {{-- Búsqueda y filtros --}}
    @if ($sheet['search'] || $sheet['filters'] || $all->isNotEmpty())
        <form method="GET" class="card mb-4 flex flex-wrap items-center gap-3 p-3">
            @if ($sheet['search'])
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar…" class="input min-w-48 flex-1">
            @endif
            @foreach ($sheet['filters'] as $filter)
                @php $f = $sheet['fields'][$filter] ?? null; @endphp
                <select name="{{ $filter }}" class="input w-auto" onchange="this.form.submit()" aria-label="Filtro">
                    @if ($filter === 'vencidas')
                        <option value="">Vencidas y no</option>
                        <option value="1" @selected(request('vencidas'))>Solo vencidas</option>
                    @elseif ($f['type'] === 'bool')
                        <option value="">{{ $f['label'] }} (todos)</option>
                        <option value="si" @selected(request($filter) === 'si')>{{ trim($f['label'], '¿?') }}: Sí</option>
                        <option value="no" @selected(request($filter) === 'no')>{{ trim($f['label'], '¿?') }}: No</option>
                    @elseif ($f['type'] === 'community')
                        <option value="">Todas las comunidades</option>
                        @foreach ($communities as $community)
                            <option value="{{ $community->id }}" @selected((int) request($filter) === $community->id)>{{ $community->name }}</option>
                        @endforeach
                    @else
                        <option value="">{{ $f['label'] }} (todos)</option>
                        @foreach ($f['options'] as $option)
                            <option value="{{ $option }}" @selected(request($filter) === $option)>{{ $option }}</option>
                        @endforeach
                    @endif
                </select>
            @endforeach
            @if (request()->hasAny(['q', ...$sheet['filters']]))
                <a href="{{ route('campaigns.sheets.index', [$campaign, $key]) }}" class="text-sm text-slate-500 hover:underline">Limpiar</a>
            @endif
            <a href="{{ route('campaigns.sheets.export', [$campaign, $key] + request()->query()) }}" class="btn btn-secondary ml-auto">Exportar CSV</a>
        </form>
    @endif

    {{-- Tabla con las columnas del Excel; cada fila se edita en su lugar --}}
    <div class="card overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="table-head">#</th>
                    @foreach ($columns as $c)
                        <th @class(['table-head whitespace-nowrap', 'text-right' => in_array($c['type'], ['int', 'money', 'percent']), 'text-center' => $c['type'] === 'bool'])>{{ $c['label'] }}</th>
                    @endforeach
                    <th class="table-head"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    @php $formId = $key.'-'.$row->id; @endphp
                    <tr @class(['bg-rose-50/60' => $key === 'cronograma' && $row->vencida()])>
                        <td class="table-cell text-slate-400 tabular-nums">{{ $rows->firstItem() + $loop->index }}</td>
                        @foreach ($columns as $c)
                            @if ($c['computed'] ?? false)
                                @php $v = ($c['value'])($row); @endphp
                                <td @class(['table-cell text-right font-medium tabular-nums', 'text-rose-600' => $v < 0])>{{ CampaignSheets::money($v) }}</td>
                            @else
                                <td @class(['table-cell', 'text-center' => $c['type'] === 'bool'])>
                                    @include('campaigns.sheets._input', ['f' => $c, 'name' => $c['name'], 'value' => $row->{$c['name']}, 'form' => $formId, 'compact' => true])
                                    @if ($key === 'cronograma' && $c['name'] === 'fecha_limite' && $row->vencida())
                                        <span class="mt-0.5 block text-xs font-medium text-rose-600">Vencida</span>
                                    @endif
                                </td>
                            @endif
                        @endforeach
                        <td class="table-cell whitespace-nowrap text-right">
                            <form id="{{ $formId }}" method="POST" action="{{ route('campaigns.sheets.update', [$campaign, $key, $row->id]) }}" class="inline">@csrf @method('PUT')
                                <button class="text-xs font-medium text-brand-600 hover:underline">Guardar</button>
                            </form>
                            <form method="POST" action="{{ route('campaigns.sheets.destroy', [$campaign, $key, $row->id]) }}" class="ml-2 inline" onsubmit="return confirm('¿Eliminar esta fila?')">@csrf @method('DELETE')
                                <button class="text-xs font-medium text-rose-600 hover:underline">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) + 2 }}" class="table-cell py-10 text-center text-slate-500">
                            @if (request()->hasAny(['q', ...$sheet['filters']]))
                                Ningún registro coincide con el filtro.
                            @else
                                Aún no hay registros. Usa «Agregar {{ $sheet['singular'] }}».
                                @if ($key === 'cronograma')
                                    <form method="POST" action="{{ route('campaigns.sheets.template', $campaign) }}" class="mt-3">@csrf
                                        <button class="btn btn-secondary">Cargar las {{ count(CampaignSheets::TASK_TEMPLATE) }} tareas sugeridas del Excel</button>
                                        <p class="mt-1 text-xs text-slate-400">Fases y fechas del calendario IEEQ 2026-2027; luego las ajustas.</p>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if ($totals && $all->isNotEmpty())
                <tfoot class="bg-slate-50 font-semibold">
                    <tr>
                        <td class="table-cell">TOTAL</td>
                        @foreach ($columns as $c)
                            <td class="table-cell text-right tabular-nums">
                                @if (in_array($c['name'], $totals))
                                    @php $sum = $sumOf($c['name']); @endphp
                                    {{ match ($c['type']) { 'money' => CampaignSheets::money($sum), 'percent' => number_format($sum * 100, 1).'%', default => number_format($sum) } }}
                                @endif
                            </td>
                        @endforeach
                        <td class="table-cell"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="mt-4">{{ $rows->links() }}</div>
@endsection

@include('campaigns._other_script')
