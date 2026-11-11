@extends('layouts.app')

@section('title', 'Calendario · '.ucfirst($month->translatedFormat('F Y')))

@php
    use App\Enums\ActivityStatus;

    $chip = [
        ActivityStatus::NoIniciado->value => 'border-amber-500 bg-amber-50 text-amber-950',
        ActivityStatus::EnProceso->value => 'border-sky-500 bg-sky-50 text-sky-950',
        ActivityStatus::Suspendida->value => 'border-slate-400 bg-slate-100 text-slate-600',
        ActivityStatus::Concluido->value => 'border-emerald-600 bg-emerald-50 text-emerald-950',
        ActivityStatus::NoCompletada->value => 'border-rose-600 bg-rose-100 text-rose-950',
    ];
    $dot = [
        ActivityStatus::NoIniciado->value => 'bg-amber-500',
        ActivityStatus::EnProceso->value => 'bg-sky-500',
        ActivityStatus::Suspendida->value => 'bg-slate-400',
        ActivityStatus::Concluido->value => 'bg-emerald-600',
        ActivityStatus::NoCompletada->value => 'bg-rose-600',
    ];
    $query = request()->except('mes');
    $prev = route('calendar.index', ['mes' => $month->subMonth()->format('Y-m')] + $query);
    $next = route('calendar.index', ['mes' => $month->addMonth()->format('Y-m')] + $query);
    $title = ucfirst($month->translatedFormat('F Y'));
    $visible = 4; // chips por día en pantalla; al imprimir se muestran todos
    $user = auth()->user();
@endphp

@section('header')
    <div>
        <h1 class="text-2xl font-semibold">Calendario</h1>
        <p class="text-sm text-slate-500">Actividades por fecha {{ $mode === 'inicio' ? 'de inicio' : '(todos los días que duran)' }}.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ $prev }}" class="btn btn-secondary px-3" aria-label="Mes anterior">←</a>
        <span class="min-w-36 text-center font-semibold">{{ $title }}</span>
        <a href="{{ $next }}" class="btn btn-secondary px-3" aria-label="Mes siguiente">→</a>
        <a href="{{ route('calendar.index', $query) }}" class="btn btn-secondary">Hoy</a>
        <button type="button" onclick="window.print()" class="btn btn-primary">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a1 1 0 01-1-1v-6a2 2 0 012-2h14a2 2 0 012 2v6a1 1 0 01-1 1h-2M7 14h10v7H7z"/></svg>
            Imprimir / PDF
        </button>
    </div>
@endsection

@section('content')
    {{-- Filtros (no se imprimen) --}}
    <form method="GET" class="card mb-5 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4 print:hidden">
        <input type="hidden" name="mes" value="{{ $month->format('Y-m') }}">
        <div class="inline-flex rounded-lg border border-slate-300 bg-slate-100 p-1 text-sm font-medium sm:col-span-2" role="radiogroup" aria-label="Mostrar actividades">
            @foreach (['inicio' => 'Por fecha de inicio', 'duracion' => 'Todos los días que dura'] as $value => $label)
                <label class="flex-1 cursor-pointer text-center">
                    <input type="radio" name="modo" value="{{ $value }}" class="peer sr-only" @checked($mode === $value) onchange="this.form.submit()">
                    <span class="block rounded-md px-3 py-1.5 text-slate-600 transition peer-checked:bg-white peer-checked:text-slate-900 peer-checked:shadow-sm">{{ $label }}</span>
                </label>
            @endforeach
        </div>
        @if ($filters['teams']->count() > 1)
            <select name="team_id" class="input">
                <option value="">Todos los equipos</option>
                @foreach ($filters['teams'] as $team)
                    <option value="{{ $team->id }}" @selected((int) request('team_id') === $team->id)>ID{{ $team->number }} · {{ $team->name }}</option>
                @endforeach
            </select>
        @endif
        @if ($filters['people']->isNotEmpty())
            <select name="assigned_to" class="input">
                <option value="">Todas las personas</option>
                @foreach ($filters['people'] as $person)
                    <option value="{{ $person->id }}" @selected((int) request('assigned_to') === $person->id)>{{ $person->name }}</option>
                @endforeach
            </select>
        @endif
        <select name="activity_type_id" class="input">
            <option value="">Todos los tipos</option>
            @foreach ($filters['types'] as $type)
                <option value="{{ $type->id }}" @selected((int) request('activity_type_id') === $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
        <select name="community_id" class="input">
            <option value="">Todas las comunidades</option>
            @foreach ($filters['communities'] as $community)
                <option value="{{ $community->id }}" @selected((int) request('community_id') === $community->id)>{{ $community->name }}</option>
            @endforeach
        </select>
        <select name="status" class="input">
            <option value="">Todos los estados</option>
            @foreach ($filters['statuses'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="flex gap-2 sm:col-span-2 lg:col-span-1 lg:justify-end">
            <a href="{{ route('calendar.index', ['mes' => $month->format('Y-m')]) }}" class="btn btn-secondary">Limpiar</a>
            <button class="btn btn-primary">Filtrar</button>
        </div>
    </form>

    {{-- Encabezado del reporte impreso --}}
    <div class="mb-3 hidden items-end justify-between border-b-2 border-slate-900 pb-2 print:flex">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/logo-oscuro.svg') }}" alt="WeAdvise" class="h-8 w-auto">
            <div>
                <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ config('app.name') }}</p>
                <p class="font-serif text-xl font-semibold leading-tight">Calendario de actividades · {{ $title }}</p>
            </div>
        </div>
        <div class="text-right text-[10px] leading-snug text-slate-600">
            <p>{{ $mode === 'inicio' ? 'Por fecha de inicio' : 'Todos los días que dura cada actividad' }}@foreach ($appliedFilters as $k => $v) · {{ $k }}: {{ $v }}@endforeach</p>
            <p>Generado el {{ now()->format('d/m/Y H:i') }} por {{ $user->name }} ({{ $user->role->label() }})</p>
        </div>
    </div>

    {{-- Resumen del mes --}}
    <div class="mb-4 flex flex-wrap items-center gap-x-5 gap-y-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm print:mb-2 print:px-3 print:py-1.5 print:text-[10px]">
        <span><strong class="tabular-nums">{{ $total }}</strong> actividades</span>
        @foreach ($summary as $row)
            <span class="inline-flex items-center gap-1.5">
                <span class="size-2.5 rounded-full {{ $dot[$row['status']->value] }}"></span>
                {{ $row['status']->label() }} <strong class="tabular-nums">{{ $row['count'] }}</strong>
            </span>
        @endforeach
        <span class="ml-auto inline-flex gap-4">
            <span>Cumplimiento <strong class="tabular-nums">{{ $cumplimiento !== null ? $cumplimiento.'%' : '—' }}</strong></span>
            <span @class(['font-semibold text-rose-600' => $overdue > 0])>Vencidas <strong class="tabular-nums">{{ $overdue }}</strong></span>
        </span>
    </div>

    {{-- Cuadrícula mensual (escritorio e impresión) --}}
    <div class="hidden overflow-hidden rounded-xl border border-slate-300 bg-white md:block print:block print:rounded-none">
        <div class="grid grid-cols-7 border-b border-slate-300 bg-slate-50 text-center text-xs font-semibold tracking-wide text-slate-500 uppercase print:text-[9px]">
            @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $name)
                <div class="py-2 print:py-1">{{ $name }}</div>
            @endforeach
        </div>
        @foreach ($weeks as $week)
            <div class="grid grid-cols-7 border-b border-slate-200 last:border-b-0 print:break-inside-avoid">
                @foreach ($week as $day)
                    @php
                        $list = $days[$day->toDateString()] ?? collect();
                        $inMonth = $day->month === $month->month;
                    @endphp
                    <div @class([
                        'min-h-32 border-r border-slate-200 p-1.5 last:border-r-0 print:min-h-20 print:p-1',
                        'bg-slate-50/80' => ! $inMonth,
                    ])>
                        <div class="mb-1 flex items-center justify-between">
                            <span @class([
                                'grid size-6 place-items-center rounded-full text-xs font-semibold print:size-auto print:text-[10px]',
                                'bg-brand-600 text-white print:bg-transparent print:text-slate-900' => $day->isToday(),
                                'text-slate-800' => $inMonth && ! $day->isToday(),
                                'text-slate-400' => ! $inMonth,
                            ])>{{ $day->day }}</span>
                            @if ($list->isNotEmpty())
                                <span class="text-[10px] text-slate-400 tabular-nums">{{ $list->count() }}</span>
                            @endif
                        </div>
                        <ul class="space-y-1 print:space-y-0.5">
                            @foreach ($list as $i => $activity)
                                <li @class(['hidden print:block' => $i >= $visible])>
                                    <a href="{{ route('activities.show', $activity) }}"
                                       title="{{ $activity->code }} · {{ $activity->type?->name }} · {{ $activity->community?->name }} · {{ $activity->assignee?->name }} · {{ $activity->status->label() }}"
                                       class="block rounded border-l-[3px] px-1.5 py-0.5 text-[11px] leading-tight hover:brightness-95 print:px-1 print:text-[8px] {{ $chip[$activity->status->value] }}">
                                        <span class="font-mono font-semibold">{{ $activity->code }}</span>
                                        <span class="block truncate print:whitespace-normal">{{ $activity->type?->name }}</span>
                                        <span class="block truncate opacity-75 print:whitespace-normal">{{ $activity->community?->name }}</span>
                                    </a>
                                </li>
                            @endforeach
                            @if ($list->count() > $visible)
                                <li class="print:hidden">
                                    <button type="button" class="w-full rounded px-1 text-left text-[11px] font-medium text-brand-600 hover:underline"
                                            onclick="this.closest('ul').querySelectorAll('li.hidden').forEach(li => li.classList.remove('hidden')); this.parentElement.remove()">
                                        + {{ $list->count() - $visible }} más
                                    </button>
                                </li>
                            @endif
                        </ul>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    {{-- Agenda en celular --}}
    <div class="space-y-3 md:hidden print:hidden">
        @forelse (collect($days)->sortKeys() as $date => $list)
            @php $d = \Carbon\Carbon::parse($date); @endphp
            @continue($d->month !== $month->month)
            <section class="card overflow-hidden">
                <h2 @class(['px-4 py-2 text-sm font-semibold', 'bg-brand-600 text-white' => $d->isToday(), 'bg-slate-50' => ! $d->isToday()])>
                    {{ ucfirst($d->translatedFormat('l j \d\e F')) }}
                </h2>
                <ul class="divide-y divide-slate-100">
                    @foreach ($list as $activity)
                        <li>
                            <a href="{{ route('activities.show', $activity) }}" class="flex items-center justify-between gap-3 px-4 py-2.5">
                                <span class="min-w-0">
                                    <span class="font-mono text-xs font-semibold text-slate-500">{{ $activity->code }}</span>
                                    <span class="block truncate text-sm font-medium">{{ $activity->type?->name }} · {{ $activity->community?->name }}</span>
                                </span>
                                <span class="badge shrink-0 {{ $activity->status->badge() }}">{{ $activity->status->label() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <div class="card p-8 text-center text-sm text-slate-500">No hay actividades en {{ $title }}.</div>
        @endforelse
    </div>

    {{-- Detalle del mes (en el PDF va en hoja aparte) --}}
    <section class="card mt-6 overflow-hidden print:mt-0 print:break-before-page print:rounded-none print:border-0">
        <div class="flex items-baseline justify-between border-b border-slate-200 px-5 py-3 print:px-0 print:py-1.5">
            <h2 class="font-semibold print:text-sm">Detalle de actividades · {{ $title }}</h2>
            <span class="text-xs text-slate-500">{{ $total }} {{ $total === 1 ? 'actividad' : 'actividades' }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm print:text-[9px]">
                <thead class="bg-slate-50">
                    <tr>
                        @foreach (['ID', 'Tipo de actividad', 'Comunidad', 'Asignada a', 'Equipo', 'Inicio', 'Fin', 'Estado'] as $h)
                            <th class="table-head print:px-1.5 print:py-1">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($activities as $activity)
                        <tr class="print:break-inside-avoid">
                            <td class="table-cell font-mono font-semibold whitespace-nowrap print:px-1.5 print:py-0.5">{{ $activity->code }}</td>
                            <td class="table-cell print:px-1.5 print:py-0.5">{{ $activity->type?->name }}</td>
                            <td class="table-cell print:px-1.5 print:py-0.5">{{ $activity->community?->name }}</td>
                            <td class="table-cell print:px-1.5 print:py-0.5">{{ $activity->assignee?->name }}</td>
                            <td class="table-cell print:px-1.5 print:py-0.5">{{ $activity->team?->name ?? '—' }}</td>
                            <td class="table-cell whitespace-nowrap print:px-1.5 print:py-0.5">{{ $activity->start_date->format('d/m/Y') }}</td>
                            <td @class(['table-cell whitespace-nowrap print:px-1.5 print:py-0.5', 'font-semibold text-rose-600' => $activity->isOverdue()])>{{ $activity->end_date->format('d/m/Y') }}</td>
                            <td class="table-cell print:px-1.5 print:py-0.5">
                                <span class="inline-flex items-center gap-1.5 whitespace-nowrap"><span class="size-2 rounded-full {{ $dot[$activity->status->value] }}"></span>{{ $activity->status->label() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="table-cell py-8 text-center text-slate-500">No hay actividades con estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
