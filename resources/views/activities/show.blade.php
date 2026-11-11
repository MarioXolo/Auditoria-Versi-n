@extends('layouts.app')

@section('title', $activity->title)

@section('header')
    <div class="min-w-0">
        <a href="{{ route('activities.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Actividades</a>
        <h1 class="mt-1 text-2xl font-semibold break-words">{{ $activity->title }}</h1>
        <div class="mt-2 flex flex-wrap gap-2">
            <span class="badge {{ $activity->status->badge() }}">{{ $activity->status->label() }}</span>
            <span class="badge {{ $activity->priority->badge() }}">Prioridad {{ strtolower($activity->priority->label()) }}</span>
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
                <h2 class="text-sm font-semibold text-slate-500 uppercase">Descripción</h2>
                <p class="mt-2 whitespace-pre-line text-slate-800">{{ $activity->description ?: 'Sin descripción.' }}</p>
            </section>

            @if ($activity->notes)
                <section class="card p-6">
                    <h2 class="text-sm font-semibold text-slate-500 uppercase">Notas de seguimiento</h2>
                    <p class="mt-2 whitespace-pre-line text-slate-800">{{ $activity->notes }}</p>
                </section>
            @endif

            <section class="card p-6">
                <h2 class="text-sm font-semibold text-slate-500 uppercase">Evidencia</h2>
                @if ($activity->evidence_path)
                    @if (\Illuminate\Support\Str::endsWith(strtolower($activity->evidence_path), '.pdf'))
                        <a href="{{ route('activities.evidence', $activity) }}" target="_blank" class="btn btn-secondary mt-3">Abrir PDF</a>
                    @else
                        <a href="{{ route('activities.evidence', $activity) }}" target="_blank">
                            <img src="{{ route('activities.evidence', $activity) }}" alt="Evidencia" class="mt-3 max-h-96 rounded-lg border border-slate-200">
                        </a>
                    @endif
                @else
                    <p class="mt-2 text-sm text-slate-500">Aún no se ha cargado evidencia.</p>
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            @php
                $done = $activity->status === \App\Enums\ActivityStatus::Completada;
                $failed = $activity->status === \App\Enums\ActivityStatus::Incompleta;
            @endphp
            {{-- Revisión: un superior marca ✓ completada o ✗ incompleta --}}
            <section @class([
                'card p-5',
                'border-rose-300 bg-rose-50' => $failed,
                'border-emerald-300 bg-emerald-50' => $done,
            ])>
                <h2 class="text-sm font-semibold text-slate-500 uppercase">Revisión</h2>

                @if ($activity->marked_at)
                    <p @class(['mt-2 text-sm font-medium', 'text-rose-900' => $failed, 'text-emerald-900' => $done])>
                        {{ $done ? 'Completada' : 'Incompleta' }} · marcada por {{ $activity->marker?->name }}
                    </p>
                    <p class="text-xs text-slate-500">{{ $activity->marked_at->format('d/m/Y H:i') }}</p>
                    @if ($activity->mark_notes)
                        <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $activity->mark_notes }}</p>
                    @endif
                @else
                    <p class="mt-2 text-sm text-slate-600">Sin marcar. La marca como completada o incompleta su encargado, un gerente o el administrador.</p>
                @endif

                @can('mark', $activity)
                    <form method="POST" action="{{ route('activities.mark', $activity) }}" class="mt-4 space-y-3 border-t border-slate-200 pt-4">
                        @csrf
                        <div>
                            <label for="mark_notes" class="label">Comentario (opcional)</label>
                            <textarea id="mark_notes" name="mark_notes" rows="2" class="input" placeholder="Ej. Falta la evidencia de la segunda visita">{{ old('mark_notes', $activity->mark_notes) }}</textarea>
                        </div>
                        <div class="grid gap-2">
                            <button name="marca" value="{{ $done ? 'sin_marcar' : 'completada' }}" role="checkbox" aria-checked="{{ $done ? 'true' : 'false' }}"
                                    @class(['flex items-center gap-3 rounded-lg border-2 px-3 py-2.5 text-left text-sm font-medium transition',
                                        'border-emerald-600 bg-emerald-600 text-white' => $done,
                                        'border-slate-300 bg-white text-slate-700 hover:border-emerald-500' => ! $done])>
                                <span @class(['grid size-5 place-items-center rounded border-2', 'border-white' => $done, 'border-slate-400' => ! $done])>
                                    @if ($done)<svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>@endif
                                </span>
                                {{ $done ? 'Completada (tocar para quitar)' : 'Marcar como completada' }}
                            </button>
                            <button name="marca" value="{{ $failed ? 'sin_marcar' : 'incompleta' }}" role="checkbox" aria-checked="{{ $failed ? 'true' : 'false' }}"
                                    @class(['flex items-center gap-3 rounded-lg border-2 px-3 py-2.5 text-left text-sm font-medium transition',
                                        'border-rose-600 bg-rose-600 text-white' => $failed,
                                        'border-slate-300 bg-white text-slate-700 hover:border-rose-500' => ! $failed])>
                                <span @class(['grid size-5 place-items-center rounded border-2', 'border-white' => $failed, 'border-slate-400' => ! $failed])>
                                    @if ($failed)<svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>@endif
                                </span>
                                {{ $failed ? 'Incompleta (tocar para quitar)' : 'Marcar como incompleta' }}
                            </button>
                        </div>
                    </form>
                @endcan
            </section>

            <dl class="card divide-y divide-slate-100 text-sm">
                @foreach ([
                    'Asignada a' => $activity->assignee?->name,
                    ($activity->isSelfAssigned() ? 'Cargada por' : 'Asignada por') => $activity->creator?->name,
                    'Equipo' => $activity->team?->name ?? '—',
                    'Fecha límite' => $activity->due_date?->format('d/m/Y') ?? 'Sin fecha',
                    'Creada' => $activity->created_at->format('d/m/Y H:i'),
                    'Completada' => $activity->completed_at?->format('d/m/Y H:i') ?? '—',
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
