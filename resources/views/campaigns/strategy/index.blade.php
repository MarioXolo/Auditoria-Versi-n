@extends('layouts.app')

@section('title', $book['name'].' · '.$campaign->name)

@use('App\Http\Controllers\Campaigns\StrategyController')
@use('App\Services\CampaignSheets')

@php
    $key = $book['key'];
    $listFields = collect($book['list'])->mapWithKeys(fn ($k) => [$k => $book['fields'][$k]]);
    $estatusColor = ['Pendiente' => 'bg-slate-100 text-slate-700', 'En curso' => 'bg-amber-100 text-amber-800', 'Hecho' => 'bg-emerald-100 text-emerald-800'];
    $ieeqColor = ['vencido' => 'bg-rose-600 text-white', 'por_vencer' => 'bg-amber-100 text-amber-800', 'en_tiempo' => 'bg-slate-100 text-slate-700', 'reportado' => 'bg-emerald-100 text-emerald-800', 'cancelado' => 'bg-slate-200 text-slate-500 line-through'];
@endphp

@section('content')
    @include('campaigns._nav')

    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <a href="{{ $punto['n'] === 11 ? route('campaigns.strategy.hub', $campaign) : route('campaigns.strategy.punto', [$campaign, $punto['n']]) }}" class="text-sm text-slate-500 hover:text-slate-800">← {{ $punto['n'] }}. {{ $punto['name'] }}</a>
            <h2 class="mt-1 text-xl font-semibold"><span aria-hidden="true">{{ $book['icon'] }}</span> {{ $book['title_text'] ?? $book['name'] }}</h2>
            <p class="text-sm text-slate-500">{{ $book['intro'] }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @isset($book['formato'])
                <a href="{{ route('campaigns.strategy.formato', [$campaign, $key]) }}" target="_blank" class="btn btn-secondary">Formato de autorización</a>
            @endisset
            @if (auth()->user()->puede(\App\Services\StrategyBooks::module($key), 'exportar'))
                <a href="{{ route('campaigns.strategy.export', [$campaign, $key] + request()->query()) }}" class="btn btn-secondary">Exportar CSV</a>
            @endif
            @if (auth()->user()->puede(\App\Services\StrategyBooks::module($key), 'capturar'))
                <a href="{{ route('campaigns.strategy.create', [$campaign, $key]) }}" class="btn btn-primary">+ Agregar</a>
            @endif
        </div>
    </div>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($summary as $card)
            <div class="card p-4">
                <p class="text-xs text-slate-500">{{ $card[0] }}</p>
                <p class="text-2xl font-semibold tabular-nums">{{ $card[1] }}</p>
                @if (! empty($card[2]))<p class="text-xs text-slate-500 tabular-nums">{{ $card[2] }}</p>@endif
            </div>
        @endforeach
    </div>

    <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
        <input name="q" value="{{ request('q') }}" placeholder="Buscar…" class="input lg:col-span-2" aria-label="Buscar">
        <select name="community_id" class="input" aria-label="Comunidad">
            <option value="">Todas las comunidades</option>
            @foreach ($communities as $c)<option value="{{ $c->id }}" @selected(request('community_id') == $c->id)>{{ $c->name }}</option>@endforeach
        </select>
        <select name="section_id" class="input" aria-label="Sección">
            <option value="">Todas las secciones</option>
            @foreach ($sections as $s)<option value="{{ $s->id }}" @selected(request('section_id') == $s->id)>{{ $s->label() }}</option>@endforeach
        </select>
        <select name="estatus" class="input" aria-label="Estatus">
            <option value="">Todos los estatus</option>
            @foreach (\App\Services\StrategyBooks::ESTATUS as $e)<option @selected(request('estatus') === $e)>{{ $e }}</option>@endforeach
        </select>
        <div class="flex gap-2">
            <button class="btn btn-secondary flex-1">Filtrar</button>
            @if (request()->hasAny(['q', 'community_id', 'section_id', 'estatus', 'responsable_id']))
                <a href="{{ route('campaigns.strategy.index', [$campaign, $key]) }}" class="btn btn-secondary px-3" title="Quitar filtros">✕</a>
            @endif
        </div>
    </form>

    <div class="card overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="table-head whitespace-nowrap">{{ $book['fecha_label'] ?? 'Fecha' }}</th>
                    @foreach ($listFields as $k => $f)
                        <th @class(['table-head whitespace-nowrap', 'text-right' => in_array($f['type'], ['int', 'money'])])>{{ $f['label'] }}</th>
                    @endforeach
                    <th class="table-head whitespace-nowrap">Comunidad · sección</th>
                    <th class="table-head">Responsable</th>
                    <th class="table-head">Estatus</th>
                    <th class="table-head text-right">Costo</th>
                    @if ($book['evento'] ?? false)<th class="table-head whitespace-nowrap">IEEQ</th>@endif
                    <th class="table-head"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($records as $r)
                    <tr class="hover:bg-slate-50">
                        <td class="table-cell whitespace-nowrap tabular-nums">
                            {{ $r->fecha?->format('d/m/Y') ?? '—' }}
                            @if ($r->horaCorta())<span class="block text-xs text-slate-500">{{ $r->horaCorta() }}</span>@endif
                        </td>
                        @foreach ($listFields as $k => $f)
                            @if ($f['type'] === 'minuta')
                                <td class="table-cell whitespace-nowrap">
                                    @if ($r->dato($k))<a href="{{ route('minutes.show', $r->dato($k)) }}" class="text-brand-700 hover:underline">Ver minuta</a>@endif
                                </td>
                            @elseif ($f['type'] === 'bool' && ($book['escalate'] ?? false) && $k === 'escalado')
                                <td class="table-cell whitespace-nowrap">
                                    @if ($r->dato('escalado'))
                                        <span class="badge bg-brand-100 text-brand-800" title="{{ $r->dato('escalado_por') }} · {{ $r->dato('escalado_el') }}">⬆ En el central{{ $r->dato('cuarto_origen') ? ' (de '.$r->dato('cuarto_origen').')' : '' }}</span>
                                    @elseif (auth()->user()->puede(\App\Services\StrategyBooks::module($key), 'capturar'))
                                        <form method="POST" action="{{ route('campaigns.strategy.escalate', [$campaign, $key, $r]) }}" onsubmit="return confirm('¿Subir este tema al cuarto central?')">
                                            @csrf
                                            <button class="btn btn-secondary px-2.5 py-1 text-xs">⬆ Subir al central</button>
                                        </form>
                                    @endif
                                </td>
                            @elseif ($f['type'] === 'file' || $f['type'] === 'photo')
                                <td class="table-cell whitespace-nowrap">
                                    @if ($r->archivo($k))
                                        <a href="{{ route('campaigns.strategy.file', [$campaign, $key, $r, $k]) }}" target="_blank" class="text-brand-700 hover:underline">✓ Ver</a>
                                    @else
                                        <span class="text-rose-600">Falta</span>
                                    @endif
                                </td>
                            @elseif ($f['type'] === 'select' && isset($f['colors']) && $r->dato($k))
                                <td class="table-cell"><span class="badge {{ $f['colors'][$r->dato($k)] ?? '' }}">{{ $r->dato($k) }}</span></td>
                            @else
                                <td @class(['table-cell', 'text-right tabular-nums' => in_array($f['type'], ['int', 'money']), 'min-w-48 font-medium text-slate-900' => $k === $book['title']])>
                                    @if ($k === $book['title'])
                                        <a href="{{ route('campaigns.strategy.edit', [$campaign, $key, $r]) }}" class="hover:text-brand-700 hover:underline">{{ StrategyController::display($f, $r->dato($k), $lookups) ?: '—' }}</a>
                                    @else
                                        {{ StrategyController::display($f, $r->dato($k), $lookups) }}
                                    @endif
                                </td>
                            @endif
                        @endforeach
                        <td class="table-cell">{{ $r->community?->name }}@if ($r->section)<span class="text-slate-500"> · {{ $r->section->clave }}</span>@endif</td>
                        <td class="table-cell">{{ $r->responsable?->name }}</td>
                        <td class="table-cell"><span class="badge {{ $estatusColor[$r->estatus] ?? '' }}">{{ $r->estatus }}</span></td>
                        <td class="table-cell text-right tabular-nums">
                            {{ $r->costo_real !== null ? CampaignSheets::money((float) $r->costo_real) : ($r->costo_estimado !== null ? CampaignSheets::money((float) $r->costo_estimado) : '') }}
                            @if ($r->costo_real === null && $r->costo_estimado !== null)<span class="block text-[11px] text-slate-400">estimado</span>@endif
                        </td>
                        @if ($book['evento'] ?? false)
                            <td class="table-cell whitespace-nowrap">
                                @if ($r->es_evento)
                                    <span class="badge {{ $ieeqColor[$r->agendaSemaforo()] ?? '' }}" title="Fecha límite: {{ $r->ieeq_fecha_limite?->format('d/m/Y') }}">{{ $r->ieeq_estatus }}</span>
                                @else
                                    <span class="text-xs text-slate-400">No aplica</span>
                                @endif
                            </td>
                        @endif
                        <td class="table-cell text-right"><a href="{{ route('campaigns.strategy.edit', [$campaign, $key, $r]) }}" class="text-sm font-medium text-brand-700 hover:underline">{{ auth()->user()->puede(\App\Services\StrategyBooks::module($key), 'capturar') ? 'Editar' : 'Ver' }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="20" class="px-4 py-10 text-center text-slate-500">Aún no hay registros. <a href="{{ route('campaigns.strategy.create', [$campaign, $key]) }}" class="font-medium text-brand-700 hover:underline">Agrega el primero</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $records->links() }}</div>
@endsection
