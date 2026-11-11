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
    <form method="GET" class="card mb-5 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por título" class="input lg:col-span-2">
        <select name="status" class="input">
            <option value="">Todos los estados</option>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
            <option value="vencida" @selected(request('status') === 'vencida')>Vencidas</option>
            <option value="por_validar" @selected(request('status') === 'por_validar')>Por validar</option>
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
        <div class="flex gap-2 sm:col-span-2 lg:col-span-5 lg:justify-end">
            <a href="{{ route('activities.index') }}" class="btn btn-secondary">Limpiar</a>
            <button class="btn btn-primary">Filtrar</button>
        </div>
    </form>

    {{-- Tarjetas en móvil --}}
    <div class="space-y-3 md:hidden">
        @forelse ($activities as $activity)
            <a href="{{ route('activities.show', $activity) }}" class="card block p-4">
                <div class="flex items-start justify-between gap-3">
                    <p class="font-medium">{{ $activity->title }}</p>
                    <span class="badge {{ $activity->status->badge() }}">{{ $activity->status->label() }}</span>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                    <span>{{ $activity->assignee?->name }}</span>
                    @if ($activity->due_date)
                        <span @class(['font-semibold text-rose-600' => $activity->isOverdue()])>Vence {{ $activity->due_date->format('d/m/Y') }}</span>
                    @endif
                    <span class="badge {{ $activity->priority->badge() }}">{{ $activity->priority->label() }}</span>
                    @if ($activity->validation_status)
                        <span class="badge {{ $activity->validation_status->badge() }}">{{ $activity->validation_status->label() }}</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="card p-8 text-center text-sm text-slate-500">No hay actividades con estos filtros.</div>
        @endforelse
    </div>

    {{-- Tabla en escritorio --}}
    <div class="card hidden overflow-hidden md:block">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="table-head">Actividad</th>
                    <th class="table-head">Asignada a</th>
                    <th class="table-head">Prioridad</th>
                    <th class="table-head">Fecha límite</th>
                    <th class="table-head">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($activities as $activity)
                    <tr class="hover:bg-slate-50">
                        <td class="table-cell">
                            <a href="{{ route('activities.show', $activity) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $activity->title }}</a>
                            @if ($activity->team)
                                <span class="block text-xs text-slate-500">{{ $activity->team->name }}</span>
                            @endif
                        </td>
                        <td class="table-cell">{{ $activity->assignee?->name }}</td>
                        <td class="table-cell"><span class="badge {{ $activity->priority->badge() }}">{{ $activity->priority->label() }}</span></td>
                        <td @class(['table-cell whitespace-nowrap', 'font-semibold text-rose-600' => $activity->isOverdue()])>
                            {{ $activity->due_date?->format('d/m/Y') ?? '—' }}
                            @if ($activity->isOverdue())
                                <span class="block text-xs font-normal">Vencida</span>
                            @endif
                        </td>
                        <td class="table-cell">
                            @can('update', $activity)
                                <form method="POST" action="{{ route('activities.status', $activity) }}">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" onchange="this.form.submit()" class="rounded-full border-0 py-0.5 pr-7 pl-2 text-xs font-medium {{ $activity->status->badge() }}">
                                        @foreach ($statuses as $value => $label)
                                            <option value="{{ $value }}" @selected($activity->status->value === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            @else
                                <span class="badge {{ $activity->status->badge() }}">{{ $activity->status->label() }}</span>
                            @endcan
                            @if ($activity->validation_status)
                                <span class="badge mt-1 {{ $activity->validation_status->badge() }}">{{ $activity->validation_status->label() }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="table-cell py-10 text-center text-slate-500">No hay actividades con estos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-5">{{ $activities->links() }}</div>
@endsection
