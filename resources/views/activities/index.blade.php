@extends('layouts.app')

@section('title', 'Actividades')

@section('header')
    <div>
        <h1 class="text-2xl font-semibold">{{ auth()->user()->isEmpleado() ? 'Mis actividades' : 'Actividades' }}</h1>
        <p class="text-sm text-slate-500">{{ $activities->total() }} {{ $activities->total() === 1 ? 'actividad' : 'actividades' }}</p>
    </div>
    @can('create', \App\Models\Activity::class)
        <a href="{{ route('activities.create') }}" class="btn btn-primary">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
            Nueva actividad
        </a>
    @endcan
@endsection

@section('content')
    <form method="GET" class="card mb-5 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por ID, tipo o comunidad" class="input lg:col-span-2">
        <select name="status" class="input">
            <option value="">Todos los estados</option>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
            <option value="vencida" @selected(request('status') === 'vencida')>Vencidas</option>
            <option value="sin_marcar" @selected(request('status') === 'sin_marcar')>Sin marcar</option>
        </select>
        <select name="activity_type_id" class="input">
            <option value="">Todos los tipos</option>
            @foreach ($types as $type)
                <option value="{{ $type->id }}" @selected((int) request('activity_type_id') === $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
        <select name="community_id" class="input">
            <option value="">Todas las comunidades</option>
            @foreach ($communities as $community)
                <option value="{{ $community->id }}" @selected((int) request('community_id') === $community->id)>{{ $community->name }}</option>
            @endforeach
        </select>
        @if ($people->isNotEmpty())
            <select name="assigned_to" class="input">
                <option value="">Todas las personas</option>
                @foreach ($people as $person)
                    <option value="{{ $person->id }}" @selected((int) request('assigned_to') === $person->id)>{{ $person->name }}</option>
                @endforeach
            </select>
        @endif
        @if ($teams->count() > 1)
            <select name="team_id" class="input">
                <option value="">Todos los equipos</option>
                @foreach ($teams as $team)
                    <option value="{{ $team->id }}" @selected((int) request('team_id') === $team->id)>{{ $team->name }}</option>
                @endforeach
            </select>
        @endif
        <div class="flex gap-2 sm:col-span-2 lg:col-span-6 lg:justify-end">
            <a href="{{ route('activities.index') }}" class="btn btn-secondary">Limpiar</a>
            <button class="btn btn-primary">Filtrar</button>
        </div>
    </form>

    {{-- Tarjetas en móvil --}}
    <div class="space-y-3 md:hidden">
        @forelse ($activities as $activity)
            @php $failed = $activity->status === \App\Enums\ActivityStatus::Incompleta; @endphp
            <div @class(['card flex items-start gap-3 p-4', 'border-rose-300 bg-rose-50' => $failed])>
                @can('mark', $activity)
                    <x-mark-controls :activity="$activity" />
                @endcan
                <a href="{{ route('activities.show', $activity) }}" class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-mono text-xs font-semibold text-slate-500">{{ $activity->code }}</p>
                            <p @class(['font-medium', 'text-rose-900' => $failed])>{{ $activity->type?->name }}</p>
                            <p class="text-sm text-slate-600">{{ $activity->community?->name }}</p>
                        </div>
                        <span class="badge {{ $activity->status->badge() }}">{{ $activity->status->label() }}</span>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                        <span>{{ $activity->assignee?->name }}</span>
                        <span @class(['font-semibold text-rose-600' => $activity->isOverdue()])>
                            {{ $activity->start_date->format('d/m') }} – {{ $activity->end_date->format('d/m/Y') }}{{ $activity->isOverdue() ? ' · Vencida' : '' }}
                        </span>
                    </div>
                </a>
            </div>
        @empty
            <div class="card p-8 text-center text-sm text-slate-500">No hay actividades con estos filtros.</div>
        @endforelse
    </div>

    {{-- Tabla en escritorio --}}
    <div class="card hidden overflow-hidden md:block">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    @if ($canMarkAny)
                        <th class="table-head w-24">Revisión</th>
                    @endif
                    <th class="table-head">ID</th>
                    <th class="table-head">Tipo de actividad</th>
                    <th class="table-head">Comunidad</th>
                    <th class="table-head">Asignada a</th>
                    <th class="table-head">Inicio – Fin</th>
                    <th class="table-head">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($activities as $activity)
                    @php $failed = $activity->status === \App\Enums\ActivityStatus::Incompleta; @endphp
                    <tr @class([
                        'bg-rose-100 shadow-[inset_4px_0_0_var(--color-rose-600)] hover:bg-rose-100' => $failed,
                        'hover:bg-slate-50' => ! $failed,
                    ])>
                        @if ($canMarkAny)
                            <td class="table-cell">
                                @can('mark', $activity)
                                    <x-mark-controls :activity="$activity" />
                                @else
                                    <span class="text-xs text-slate-400" title="La marca un superior">—</span>
                                @endcan
                            </td>
                        @endif
                        <td class="table-cell whitespace-nowrap">
                            <a href="{{ route('activities.show', $activity) }}" @class(['font-mono text-sm font-semibold hover:text-brand-600', 'text-rose-900' => $failed, 'text-slate-700' => ! $failed])>{{ $activity->code }}</a>
                        </td>
                        <td class="table-cell">
                            <a href="{{ route('activities.show', $activity) }}" @class(['font-medium hover:text-brand-600', 'text-rose-900' => $failed, 'text-slate-900' => ! $failed])>{{ $activity->type?->name }}</a>
                            @if ($activity->team)
                                <span class="block text-xs text-slate-500">{{ $activity->team->name }}</span>
                            @endif
                        </td>
                        <td class="table-cell">{{ $activity->community?->name }}</td>
                        <td class="table-cell">{{ $activity->assignee?->name }}</td>
                        <td @class(['table-cell whitespace-nowrap', 'font-semibold text-rose-600' => $activity->isOverdue()])>
                            {{ $activity->start_date->format('d/m/Y') }}
                            <span class="block text-xs {{ $activity->isOverdue() ? '' : 'text-slate-500' }}">al {{ $activity->end_date->format('d/m/Y') }}{{ $activity->isOverdue() ? ' · Vencida' : '' }}</span>
                        </td>
                        <td class="table-cell">
                            <span class="badge {{ $activity->status->badge() }}">{{ $activity->status->label() }}</span>
                            @if ($activity->isSelfAssigned() && $activity->assignee?->isEmpleado())
                                <span class="mt-1 block text-xs text-slate-500">Cargada por el empleado</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $canMarkAny ? 7 : 6 }}" class="table-cell py-10 text-center text-slate-500">No hay actividades con estos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-5">{{ $activities->links() }}</div>
@endsection
