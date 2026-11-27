@extends('layouts.app')

@section('title', 'Agenda del Presidente')

@section('header')
    <div>
        <h1 class="text-2xl font-semibold">Agenda del Presidente</h1>
        <p class="text-sm text-slate-500">Actividades aparte: no se mezclan con las de los demás usuarios ni con sus dashboards o calendario.</p>
    </div>
    <a href="{{ route('president.create') }}" class="btn btn-primary">+ Nueva actividad</a>
@endsection

@section('content')
    @include('president._tabs')

    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([['Actividades', $counts['total'], ''], ['Abiertas', $counts['abiertas'], ''], ['Vencidas', $counts['vencidas'], 'vencidas'], ['Concluidas', $counts['concluidas'], 'concluido']] as [$label, $n, $filter])
            <a href="{{ route('president.index', $filter ? ['status' => $filter] : []) }}" @class(['card p-4 hover:border-brand-500', 'border-rose-300 bg-rose-50' => $filter === 'vencidas' && $n > 0])>
                <p class="text-xs text-slate-500">{{ $label }}</p>
                <p class="text-2xl font-semibold tabular-nums">{{ number_format($n) }}</p>
            </a>
        @endforeach
    </div>

    <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Folio, tipo, comunidad o notas" class="input lg:col-span-2" aria-label="Buscar">
        <select name="status" class="input" aria-label="Estado">
            <option value="">Todos los estados</option>
            @foreach (\App\Enums\ActivityStatus::options() as $v => $l)<option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach
            <option value="vencidas" @selected(request('status') === 'vencidas')>Vencidas</option>
        </select>
        <select name="community_id" class="input" aria-label="Comunidad">
            <option value="">Todas las comunidades</option>
            @foreach ($communities as $c)<option value="{{ $c->id }}" @selected((int) request('community_id') === $c->id)>{{ $c->name }}</option>@endforeach
        </select>
        <input type="month" name="mes" value="{{ request('mes') }}" class="input" aria-label="Mes">
        <div class="flex gap-2">
            <button class="btn btn-secondary flex-1">Filtrar</button>
            @if (request()->hasAny(['q', 'status', 'community_id', 'mes', 'activity_type_id']))
                <a href="{{ route('president.index') }}" class="btn btn-secondary px-3" title="Quitar filtros">✕</a>
            @endif
        </div>
    </form>

    <div class="card overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="table-head">ID</th>
                    <th class="table-head">Tipo de actividad</th>
                    <th class="table-head">Comunidad</th>
                    <th class="table-head">Asignada a</th>
                    <th class="table-head">Inicio – Fin</th>
                    <th class="table-head">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($activities as $a)
                    <tr @class(['hover:bg-slate-50', 'bg-rose-50' => $a->status === \App\Enums\ActivityStatus::NoCompletada])>
                        <td class="table-cell font-mono text-xs"><a href="{{ route('president.show', $a) }}" class="hover:text-brand-700 hover:underline">{{ $a->folio }}</a></td>
                        <td class="table-cell font-medium"><a href="{{ route('president.show', $a) }}" class="hover:text-brand-700 hover:underline">{{ $a->type?->name }}</a></td>
                        <td class="table-cell">{{ $a->community?->name }}</td>
                        <td class="table-cell">{{ $a->assignee?->name }}</td>
                        <td class="table-cell whitespace-nowrap tabular-nums">{{ $a->start_date->format('d/m/Y') }}<span class="block text-xs text-slate-500">al {{ $a->end_date->format('d/m/Y') }}</span></td>
                        <td class="table-cell whitespace-nowrap">
                            <span class="badge {{ $a->status->badge() }}">{{ $a->status->label() }}</span>
                            @if ($a->isOverdue())<span class="badge mt-1 block bg-rose-600 text-white">Vencida</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Aún no hay actividades. <a href="{{ route('president.create') }}" class="font-medium text-brand-700 hover:underline">Agrega la primera</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $activities->links() }}</div>
@endsection
