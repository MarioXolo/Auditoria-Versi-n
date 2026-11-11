@extends('layouts.app')

@section('title', 'Base de votantes · '.$campaign->name)

@php
    $afin = ['Duro' => 'bg-emerald-100 text-emerald-800', 'Blando' => 'bg-sky-100 text-sky-800', 'Indeciso' => 'bg-amber-100 text-amber-800'];
@endphp

@section('content')
    @include('campaigns._nav')

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500"><b class="text-slate-900">{{ number_format($total) }}</b> votantes identificados · meta {{ number_format(round($campaign->metaConColchon())) }}. Información personal: solo la ven administradores y gerentes.</p>
        <div class="flex gap-2">
            <a href="{{ route('campaigns.voters.export', [$campaign] + request()->query()) }}" class="btn btn-secondary">Exportar CSV</a>
            <a href="{{ route('campaigns.voters.create', $campaign) }}" class="btn btn-primary">Registrar votante</a>
        </div>
    </div>

    <form method="GET" class="card mb-5 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nombre, teléfono o domicilio" class="input lg:col-span-2">
        <select name="community_id" class="input" onchange="this.form.submit()">
            <option value="">Todas las comunidades</option>
            @foreach ($communities as $community)
                <option value="{{ $community->id }}" @selected((int) request('community_id') === $community->id)>{{ $community->name }}</option>
            @endforeach
        </select>
        <select name="promoter_id" class="input" onchange="this.form.submit()">
            <option value="">Todos los promotores</option>
            <option value="sin" @selected(request('promoter_id') === 'sin')>Sin promotor</option>
            @foreach ($promoters as $promoter)
                <option value="{{ $promoter->id }}" @selected((string) request('promoter_id') === (string) $promoter->id)>{{ $promoter->name }}</option>
            @endforeach
        </select>
        <select name="afinidad" class="input" onchange="this.form.submit()">
            <option value="">Toda afinidad</option>
            @foreach (\App\Models\CampaignVoter::AFINIDADES as $a)
                <option value="{{ $a }}" @selected(request('afinidad') === $a)>{{ $a }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <input name="seccion" value="{{ request('seccion') }}" placeholder="Sección" class="input" aria-label="Sección">
            <button class="btn btn-primary">Filtrar</button>
        </div>
    </form>

    {{-- Celular --}}
    <div class="space-y-3 md:hidden">
        @forelse ($voters as $voter)
            <a href="{{ route('campaigns.voters.edit', [$campaign, $voter]) }}" class="card block p-4">
                <div class="flex items-start justify-between gap-3">
                    <p class="font-medium">{{ $voter->nombre }}</p>
                    @if ($voter->afinidad)<span class="badge {{ $afin[$voter->afinidad] ?? '' }}">{{ $voter->afinidad }}</span>@endif
                </div>
                <p class="mt-1 text-xs text-slate-500">{{ $voter->community?->name ?? 'Sin comunidad' }}@if ($voter->seccion) · Secc. {{ $voter->seccion }}@endif · {{ $voter->promoter?->name ?? 'Sin promotor' }}</p>
            </a>
        @empty
            <div class="card p-8 text-center text-sm text-slate-500">No hay votantes con estos filtros.</div>
        @endforelse
    </div>

    {{-- Escritorio: mismas columnas que la pestaña «Base de Votantes» del Excel --}}
    <div class="card hidden overflow-x-auto md:block">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="table-head">#</th>
                    <th class="table-head">Nombre completo</th>
                    <th class="table-head">Sección</th>
                    <th class="table-head">Casilla</th>
                    <th class="table-head">Domicilio</th>
                    <th class="table-head">Tel / WhatsApp</th>
                    <th class="table-head">Comunidad</th>
                    <th class="table-head">Promotor responsable</th>
                    <th class="table-head">Afinidad</th>
                    <th class="table-head">¿Recibe apoyo gob?</th>
                    <th class="table-head">Notas</th>
                    <th class="table-head"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($voters as $voter)
                    <tr>
                        <td class="table-cell text-slate-400 tabular-nums">{{ $voters->firstItem() + $loop->index }}</td>
                        <td class="table-cell font-medium text-slate-900">{{ $voter->nombre }}</td>
                        <td class="table-cell tabular-nums">{{ $voter->seccion ?? '—' }}</td>
                        <td class="table-cell">{{ $voter->casilla ?? '—' }}</td>
                        <td class="table-cell"><span class="block max-w-52 truncate" title="{{ $voter->domicilio }}">{{ $voter->domicilio ?? '—' }}</span></td>
                        <td class="table-cell"><x-whatsapp :phone="$voter->telefono" /></td>
                        <td class="table-cell">{{ $voter->community?->name ?? '—' }}</td>
                        <td class="table-cell">{{ $voter->promoter?->name ?? '—' }}</td>
                        <td class="table-cell">@if ($voter->afinidad)<span class="badge {{ $afin[$voter->afinidad] ?? '' }}">{{ $voter->afinidad }}</span>@else — @endif</td>
                        <td class="table-cell">{{ $voter->recibe_apoyo === null ? '—' : ($voter->recibe_apoyo ? 'Sí' : 'No') }}</td>
                        <td class="table-cell"><span class="block max-w-44 truncate text-slate-500" title="{{ $voter->notas }}">{{ $voter->notas ?? '—' }}</span></td>
                        <td class="table-cell text-right"><a href="{{ route('campaigns.voters.edit', [$campaign, $voter]) }}" class="text-sm font-medium text-brand-600 hover:underline">Editar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="table-cell py-10 text-center text-slate-500">No hay votantes con estos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $voters->links() }}</div>
@endsection
