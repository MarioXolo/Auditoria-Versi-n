@extends('layouts.app')

@section('title', $activity->title)

@section('header')
    <div class="min-w-0">
        <a href="{{ route('activities.index') }}" class="text-sm text-stone-500 hover:text-stone-800">← Actividades</a>
        <h1 class="mt-1 text-2xl font-semibold break-words">{{ $activity->title }}</h1>
        <div class="mt-2 flex flex-wrap gap-2">
            <span class="badge {{ $activity->status->badge() }}">{{ $activity->status->label() }}</span>
            <span class="badge {{ $activity->priority->badge() }}">Prioridad {{ strtolower($activity->priority->label()) }}</span>
            @if ($activity->isOverdue())
                <span class="badge bg-rose-600 text-white">Vencida</span>
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
                <h2 class="text-sm font-semibold text-stone-500 uppercase">Descripción</h2>
                <p class="mt-2 whitespace-pre-line text-stone-800">{{ $activity->description ?: 'Sin descripción.' }}</p>
            </section>

            @if ($activity->notes)
                <section class="card p-6">
                    <h2 class="text-sm font-semibold text-stone-500 uppercase">Notas de seguimiento</h2>
                    <p class="mt-2 whitespace-pre-line text-stone-800">{{ $activity->notes }}</p>
                </section>
            @endif

            <section class="card p-6">
                <h2 class="text-sm font-semibold text-stone-500 uppercase">Evidencia</h2>
                @if ($activity->evidence_path)
                    @if (\Illuminate\Support\Str::endsWith(strtolower($activity->evidence_path), '.pdf'))
                        <a href="{{ route('activities.evidence', $activity) }}" target="_blank" class="btn btn-secondary mt-3">Abrir PDF</a>
                    @else
                        <a href="{{ route('activities.evidence', $activity) }}" target="_blank">
                            <img src="{{ route('activities.evidence', $activity) }}" alt="Evidencia" class="mt-3 max-h-96 rounded-lg border border-stone-200">
                        </a>
                    @endif
                @else
                    <p class="mt-2 text-sm text-stone-500">Aún no se ha cargado evidencia.</p>
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            <dl class="card divide-y divide-stone-100 text-sm">
                @foreach ([
                    'Asignada a' => $activity->assignee?->name,
                    'Asignada por' => $activity->creator?->name,
                    'Equipo' => $activity->team?->name ?? '—',
                    'Fecha límite' => $activity->due_date?->format('d/m/Y') ?? 'Sin fecha',
                    'Creada' => $activity->created_at->format('d/m/Y H:i'),
                    'Completada' => $activity->completed_at?->format('d/m/Y H:i') ?? '—',
                ] as $label => $value)
                    <div class="flex justify-between gap-4 px-5 py-3">
                        <dt class="text-stone-500">{{ $label }}</dt>
                        <dd class="text-right font-medium">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @can('update', $activity)
                <form method="POST" action="{{ route('activities.status', $activity) }}" class="card p-5">
                    @csrf
                    @method('PATCH')
                    <label for="quick-status" class="label">Cambiar estado</label>
                    <div class="flex gap-2">
                        <select id="quick-status" name="status" class="input">
                            @foreach (\App\Enums\ActivityStatus::options() as $value => $label)
                                <option value="{{ $value }}" @selected($activity->status->value === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            @endcan
        </aside>
    </div>
@endsection
