@extends('layouts.app')

@section('title', $activity->code.' · '.$activity->label)

@section('header')
    <div class="min-w-0">
        <a href="{{ route('activities.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Actividades</a>
        <p class="mt-1 font-mono text-sm font-semibold text-slate-500">{{ $activity->code }}</p>
        <h1 class="text-2xl font-semibold break-words">{{ $activity->type?->name }}</h1>
        <p class="text-slate-600">{{ $activity->community?->name }}</p>
        <div class="mt-2 flex flex-wrap gap-2">
            <span class="badge {{ $activity->status->badge() }}">{{ $activity->status->label() }}</span>
            @if ($activity->isOverdue())
                <span class="badge bg-rose-600 text-white">Vencida</span>
            @endif
            @if ($activity->isSelfAssigned() && $activity->assignee?->isEmpleado())
                <span class="badge bg-brand-50 text-brand-700">Cargada por el empleado</span>
            @endif
        </div>
    </div>
    <div class="flex gap-2">
        @can('update', $activity)
            <a href="{{ route('activities.edit', $activity) }}" class="btn btn-secondary">Editar</a>
        @endcan
        @can('delete', $activity)
            <form method="POST" action="{{ route('activities.destroy', $activity) }}" onsubmit="return confirm('¿Eliminar esta actividad? No se puede deshacer.')">
                @csrf
                @method('DELETE')
                <button class="btn btn-danger">Eliminar</button>
            </form>
        @endcan
    </div>
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-500 uppercase">Evidencia</h2>
                @if (! $activity->hasEvidence())
                    <p class="mt-2 text-sm text-slate-500">Aún no se ha cargado evidencia.</p>
                @endif
                @if ($activity->evidence_path)
                    @if (\Illuminate\Support\Str::endsWith(strtolower($activity->evidence_path), '.pdf'))
                        <a href="{{ route('activities.evidence', $activity) }}" target="_blank" class="btn btn-secondary mt-3">Abrir PDF</a>
                    @else
                        <a href="{{ route('activities.evidence', $activity) }}" target="_blank">
                            <img src="{{ route('activities.evidence', $activity) }}" alt="Fotografía de evidencia" class="mt-3 max-h-96 rounded-lg border border-slate-200">
                        </a>
                    @endif
                @endif
                @if (filled($activity->evidence_text))
                    <p class="mt-3 whitespace-pre-line text-slate-800">{{ $activity->evidence_text }}</p>
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            @php
                $done = $activity->status === \App\Enums\ActivityStatus::Concluido;
                $failed = $activity->status === \App\Enums\ActivityStatus::NoCompletada;
            @endphp
            {{-- Estado manual: lo cambia quien edita y también la persona asignada --}}
            @can('changeStatus', $activity)
                <section class="card p-5">
                    <h2 class="text-sm font-semibold text-slate-500 uppercase">Estado</h2>
                    <form method="POST" action="{{ route('activities.status', $activity) }}" class="mt-3 flex gap-2">
                        @csrf
                        @method('PATCH')
                        <select name="status" class="input" aria-label="Estado de la actividad">
                            @foreach (\App\Enums\ActivityStatus::editableOptions() as $value => $label)
                                <option value="{{ $value }}" @selected($activity->status->value === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-secondary shrink-0">Cambiar</button>
                    </form>
                    <p class="mt-2 text-xs text-slate-500">«Concluido» y «No completada» los pone un superior con la revisión.</p>
                </section>
            @endcan

            {{-- Revisión: un superior marca ✓ Concluido (verde) o ✗ No completada (rojo) --}}
            <section @class([
                'card p-5',
                'border-rose-300 bg-rose-50' => $failed,
                'border-emerald-300 bg-emerald-50' => $done,
            ])>
                <h2 class="text-sm font-semibold text-slate-500 uppercase">Revisión</h2>

                @if ($activity->marked_at)
                    <p @class(['mt-2 text-sm font-medium', 'text-rose-900' => $failed, 'text-emerald-900' => $done])>
                        {{ $done ? 'Concluido' : 'No completada' }} · marcada por {{ $activity->marker?->name }}
                    </p>
                    <p class="text-xs text-slate-500">{{ $activity->marked_at->format('d/m/Y H:i') }}</p>
                    @if ($activity->mark_notes)
                        <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $activity->mark_notes }}</p>
                    @endif
                @else
                    <p class="mt-2 text-sm text-slate-600">Sin revisar. Su encargado, un gerente o el administrador la marca en verde («Concluido») o en rojo («No completada»).</p>
                @endif

                @can('mark', $activity)
                    <form method="POST" action="{{ route('activities.mark', $activity) }}" class="mt-4 space-y-3 border-t border-slate-200 pt-4">
                        @csrf
                        <div>
                            <label for="mark_notes" class="label">Comentario (opcional)</label>
                            <textarea id="mark_notes" name="mark_notes" rows="2" class="input" placeholder="Ej. Falta la evidencia de la segunda visita">{{ old('mark_notes', $activity->mark_notes) }}</textarea>
                        </div>
                        <div class="grid gap-2">
                            <button name="marca" value="{{ $done ? 'sin_marcar' : 'concluido' }}" role="checkbox" aria-checked="{{ $done ? 'true' : 'false' }}"
                                    @class(['flex items-center gap-3 rounded-lg border-2 px-3 py-2.5 text-left text-sm font-medium transition',
                                        'border-emerald-600 bg-emerald-600 text-white' => $done,
                                        'border-slate-300 bg-white text-slate-700 hover:border-emerald-500' => ! $done])>
                                <span @class(['grid size-5 place-items-center rounded border-2', 'border-white' => $done, 'border-slate-400' => ! $done])>
                                    @if ($done)<svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>@endif
                                </span>
                                {{ $done ? 'Concluido (tocar para quitar)' : 'Marcar como concluido' }}
                            </button>
                            <button name="marca" value="{{ $failed ? 'sin_marcar' : 'no_completada' }}" role="checkbox" aria-checked="{{ $failed ? 'true' : 'false' }}"
                                    @class(['flex items-center gap-3 rounded-lg border-2 px-3 py-2.5 text-left text-sm font-medium transition',
                                        'border-rose-600 bg-rose-600 text-white' => $failed,
                                        'border-slate-300 bg-white text-slate-700 hover:border-rose-500' => ! $failed])>
                                <span @class(['grid size-5 place-items-center rounded border-2', 'border-white' => $failed, 'border-slate-400' => ! $failed])>
                                    @if ($failed)<svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>@endif
                                </span>
                                {{ $failed ? 'No completada (tocar para quitar)' : 'Marcar como no completada' }}
                            </button>
                        </div>
                    </form>
                @endcan
            </section>

            <dl class="card divide-y divide-slate-100 text-sm">
                @foreach ([
                    'ID' => $activity->code,
                    'Tipo' => $activity->type?->name,
                    'Comunidad' => $activity->community?->name,
                    'Asignada a' => $activity->assignee?->name,
                    ($activity->isSelfAssigned() ? 'Cargada por' : 'Asignada por') => $activity->creator?->name,
                    'Equipo' => $activity->team?->name ?? '—',
                    'Fecha de inicio' => $activity->start_date->format('d/m/Y'),
                    'Fecha de fin' => $activity->end_date->format('d/m/Y'),
                    'Creada' => $activity->created_at->format('d/m/Y H:i'),
                    'Concluido el' => $activity->completed_at?->format('d/m/Y H:i') ?? '—',
                ] as $label => $value)
                    <div class="flex justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">{{ $label }}</dt>
                        <dd class="text-right font-medium">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

        </aside>
    </div>
@endsection
