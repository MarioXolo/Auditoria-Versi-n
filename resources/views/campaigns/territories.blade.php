@extends('layouts.app')

@section('title', 'Estructura territorial · '.$campaign->name)

@php
    $n = fn ($v) => number_format($v);
    $p = fn ($v) => number_format($v * 100, 1).'%';
@endphp

@section('content')
    @include('campaigns._nav')

    <p class="mb-4 text-sm text-slate-500">Una fila por delegación o comunidad. Los <b>votos identificados</b> y los <b>promotores reclutados</b> se cuentan solos desde la base de votantes y el padrón de promotores. La suma de metas debe igualar o superar la meta municipal ({{ number_format(round($campaign->metaConColchon())) }}).</p>

    {{-- Agregar comunidad --}}
    <form method="POST" action="{{ route('campaigns.territories.store', $campaign) }}" class="card mb-5 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[1.4fr_1.2fr_.8fr_.8fr_auto] lg:items-end">
        @csrf
        <div>
            <label for="community_id" class="label">Delegación / Comunidad *</label>
            <select id="community_id" name="community_id" required class="input" data-other="community_new">
                <option value="">Selecciona</option>
                @foreach ($available as $community)
                    <option value="{{ $community->id }}" @selected((string) old('community_id') === (string) $community->id)>{{ $community->name }}</option>
                @endforeach
                <option value="otro" @selected(old('community_id') === 'otro')>Otra (escribirla)…</option>
            </select>
            <input id="community_new" name="community_new" value="{{ old('community_new') }}" maxlength="120" placeholder="Nombre de la comunidad" aria-label="Nueva comunidad"
                   @class(['input mt-2', 'hidden' => old('community_id') !== 'otro'])>
        </div>
        <div>
            <label for="responsable" class="label">Responsable territorial</label>
            <input id="responsable" name="responsable" value="{{ old('responsable') }}" maxlength="150" class="input">
        </div>
        <div>
            <label for="listado_nominal" class="label">Listado nominal</label>
            <input id="listado_nominal" type="number" min="0" name="listado_nominal" value="{{ old('listado_nominal', 0) }}" required class="input tabular-nums">
        </div>
        <div>
            <label for="meta_votos" class="label">Meta de votos</label>
            <input id="meta_votos" type="number" min="0" name="meta_votos" value="{{ old('meta_votos', 0) }}" required class="input tabular-nums">
        </div>
        <button class="btn btn-primary sm:col-span-2 lg:col-span-1">Agregar</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="table-head">Delegación / Comunidad</th>
                    <th class="table-head">Responsable territorial</th>
                    <th class="table-head text-right">Listado nominal</th>
                    <th class="table-head text-right">Meta de votos</th>
                    <th class="table-head text-right">Votos identificados</th>
                    <th class="table-head">% avance</th>
                    <th class="table-head text-right">Promotores necesarios</th>
                    <th class="table-head text-right">Promotores reclutados</th>
                    <th class="table-head text-right">Faltan</th>
                    <th class="table-head"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    @php $t = $row->model; $formId = 'territory-'.$t->id; @endphp
                    <tr>
                        <td class="table-cell font-medium text-slate-900">{{ $t->community?->name }}</td>
                        <td class="table-cell"><input form="{{ $formId }}" name="responsable" value="{{ $t->responsable }}" maxlength="150" class="input w-36 py-1.5 text-sm" aria-label="Responsable de {{ $t->community?->name }}"></td>
                        <td class="table-cell text-right"><input form="{{ $formId }}" type="number" min="0" name="listado_nominal" value="{{ $t->listado_nominal }}" class="input w-24 py-1.5 text-right text-sm tabular-nums" aria-label="Listado nominal"></td>
                        <td class="table-cell text-right"><input form="{{ $formId }}" type="number" min="0" name="meta_votos" value="{{ $t->meta_votos }}" class="input w-20 py-1.5 text-right text-sm tabular-nums" aria-label="Meta de votos"></td>
                        <td class="table-cell text-right tabular-nums">
                            <a href="{{ route('campaigns.voters.index', [$campaign, 'community_id' => $t->community_id]) }}" class="hover:text-brand-600 hover:underline">{{ $n($row->identificados) }}</a>
                        </td>
                        <td class="table-cell">
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-600" style="width: {{ min(100, round($row->avance * 100)) }}%"></div></div>
                                <span class="tabular-nums">{{ $p($row->avance) }}</span>
                            </div>
                        </td>
                        <td class="table-cell text-right tabular-nums">{{ $n($row->necesarios) }}</td>
                        <td class="table-cell text-right tabular-nums">{{ $n($row->reclutados) }}</td>
                        <td @class(['table-cell text-right font-semibold tabular-nums', 'text-rose-600' => $row->faltan > 0, 'text-emerald-600' => $row->faltan === 0 && $row->necesarios > 0])>{{ $n($row->faltan) }}</td>
                        <td class="table-cell whitespace-nowrap text-right">
                            <form id="{{ $formId }}" method="POST" action="{{ route('campaigns.territories.update', [$campaign, $t]) }}" class="inline">@csrf @method('PUT')
                                <button class="text-xs font-medium text-brand-600 hover:underline">Guardar</button>
                            </form>
                            <form method="POST" action="{{ route('campaigns.territories.destroy', [$campaign, $t]) }}" class="ml-2 inline" onsubmit="return confirm('¿Quitar esta comunidad de la estructura?')">@csrf @method('DELETE')
                                <button class="text-xs font-medium text-rose-600 hover:underline">Quitar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="table-cell py-10 text-center text-slate-500">Agrega la primera comunidad con el formulario de arriba.</td></tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot class="bg-slate-50 font-semibold">
                    <tr>
                        <td class="table-cell" colspan="2">TOTAL</td>
                        <td class="table-cell text-right tabular-nums">{{ $n($totals->listado) }}</td>
                        <td class="table-cell text-right tabular-nums">{{ $n($totals->meta) }}</td>
                        <td class="table-cell text-right tabular-nums">{{ $n($totals->identificados) }}</td>
                        <td class="table-cell tabular-nums">{{ $p($totals->avance) }}</td>
                        <td class="table-cell text-right tabular-nums">{{ $n($totals->necesarios) }}</td>
                        <td class="table-cell text-right tabular-nums">{{ $n($totals->reclutados) }}</td>
                        <td class="table-cell text-right tabular-nums">{{ $n($totals->faltan) }}</td>
                        <td class="table-cell"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endsection

@include('campaigns._other_script')
