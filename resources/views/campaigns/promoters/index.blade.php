@extends('layouts.app')

@section('title', 'Promotores · '.$campaign->name)

@section('content')
    @include('campaigns._nav')

    <div class="mb-4 grid gap-3 sm:grid-cols-4">
        <div class="card p-4"><p class="text-xs text-slate-500">Promotores</p><p class="text-2xl font-semibold tabular-nums">{{ number_format($totals->total) }} <span class="text-sm font-normal text-slate-400">/ {{ number_format($campaign->promotoresNecesarios()) }} necesarios</span></p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">Verificados</p><p class="text-2xl font-semibold tabular-nums">{{ number_format($totals->verificados) }}</p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">Meta sumada</p><p class="text-2xl font-semibold tabular-nums">{{ number_format($totals->meta) }}</p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">Votantes identificados por promotores</p><p class="text-2xl font-semibold tabular-nums">{{ number_format($totals->identificados) }} <span class="text-sm font-normal text-slate-400">{{ $totals->meta > 0 ? number_format($totals->identificados / $totals->meta * 100, 1).'%' : '' }}</span></p></div>
    </div>

    <form method="GET" class="card mb-5 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_auto_auto]">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nombre, teléfono o sección" class="input">
        <select name="community_id" class="input" onchange="this.form.submit()">
            <option value="">Todas las comunidades</option>
            @foreach ($communities as $community)
                <option value="{{ $community->id }}" @selected((int) request('community_id') === $community->id)>{{ $community->name }}</option>
            @endforeach
        </select>
        <select name="verificado" class="input" onchange="this.form.submit()">
            <option value="">Verificados y no</option>
            <option value="si" @selected(request('verificado') === 'si')>Solo verificados</option>
            <option value="no" @selected(request('verificado') === 'no')>Sin verificar</option>
        </select>
        <a href="{{ route('campaigns.promoters.export', [$campaign] + request()->query()) }}" class="btn btn-secondary">Exportar CSV</a>
        <a href="{{ route('campaigns.promoters.create', $campaign) }}" class="btn btn-primary">Registrar promotor</a>
    </form>

    <div class="card overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="table-head">Promotor</th>
                    <th class="table-head">Comunidad</th>
                    <th class="table-head">Sección(es)</th>
                    <th class="table-head">Coordinador</th>
                    <th class="table-head text-right">Meta</th>
                    <th class="table-head text-right">Identificados</th>
                    <th class="table-head">% avance</th>
                    <th class="table-head">¿Verificado?</th>
                    <th class="table-head"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($promoters as $promoter)
                    @php $avance = $promoter->avance(); @endphp
                    <tr>
                        <td class="table-cell">
                            <span class="font-medium text-slate-900">{{ $promoter->name }}</span>
                            @if ($promoter->phone)<span class="block text-xs text-slate-500">{{ $promoter->phone }}</span>@endif
                        </td>
                        <td class="table-cell">{{ $promoter->community?->name ?? '—' }}</td>
                        <td class="table-cell tabular-nums">{{ $promoter->secciones ?? '—' }}</td>
                        <td class="table-cell">{{ $promoter->coordinador ?? '—' }}</td>
                        <td class="table-cell text-right tabular-nums">{{ number_format($promoter->meta_votos) }}</td>
                        <td class="table-cell text-right tabular-nums">
                            <a href="{{ route('campaigns.voters.index', [$campaign, 'promoter_id' => $promoter->id]) }}" class="hover:text-brand-600 hover:underline">{{ number_format($promoter->voters_count) }}</a>
                        </td>
                        <td class="table-cell">
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100"><div @class(['h-full rounded-full', 'bg-emerald-600' => $avance >= 1, 'bg-brand-600' => $avance < 1]) style="width: {{ min(100, round($avance * 100)) }}%"></div></div>
                                <span class="tabular-nums">{{ number_format($avance * 100, 1) }}%</span>
                            </div>
                        </td>
                        <td class="table-cell"><span @class(['badge', 'bg-emerald-100 text-emerald-800' => $promoter->verificado, 'bg-slate-100 text-slate-600' => ! $promoter->verificado])>{{ $promoter->verificado ? 'Sí' : 'No' }}</span></td>
                        <td class="table-cell text-right"><a href="{{ route('campaigns.promoters.edit', [$campaign, $promoter]) }}" class="text-sm font-medium text-brand-600 hover:underline">Editar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="table-cell py-10 text-center text-slate-500">Aún no hay promotores registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $promoters->links() }}</div>
@endsection
