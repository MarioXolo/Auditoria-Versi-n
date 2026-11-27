@extends('layouts.app')

@section('title', $activity->folio.' · Agenda del Presidente')

@php
    $done = $activity->status === \App\Enums\ActivityStatus::Concluido;
    $failed = $activity->status === \App\Enums\ActivityStatus::NoCompletada;
    $confirm = '¿Seguro que quieres marcarla como Concluido? Después ya no podrás hacerle ningún cambio.';
@endphp

@section('header')
    <div class="min-w-0">
        <a href="{{ route('president.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Agenda del Presidente</a>
        <p class="mt-1 font-mono text-sm text-slate-500">{{ $activity->folio }}</p>
        <h1 class="text-2xl font-semibold break-words">{{ $activity->type?->name }}</h1>
        <p class="text-slate-500">{{ $activity->community?->name }}</p>
        <div class="mt-2 flex flex-wrap gap-2">
            <span class="badge {{ $activity->status->badge() }}">{{ $activity->status->label() }}</span>
            @if ($activity->isOverdue())<span class="badge bg-rose-600 text-white">Vencida</span>@endif
        </div>
    </div>
    <div class="flex gap-2">
        @unless ($done)
            <a href="{{ route('president.edit', $activity) }}" class="btn btn-secondary">Editar</a>
        @endunless
        @if (! $done || auth()->user()->isAdmin())
            <form method="POST" action="{{ route('president.destroy', $activity) }}" onsubmit="return confirm('¿Eliminar la actividad {{ $activity->folio }}?')">
                @csrf
                @method('DELETE')
                <button class="btn btn-secondary text-rose-700">Eliminar</button>
            </form>
        @endif
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
                    <a href="{{ route('president.evidence', $activity) }}" target="_blank" class="mt-3 block">
                        <img src="{{ route('president.evidence', $activity) }}" alt="Evidencia de {{ $activity->folio }}" class="max-h-96 rounded-lg border border-slate-200">
                    </a>
                @endif
                @if (filled($activity->evidence_text))
                    <p class="mt-3 whitespace-pre-line text-slate-800">{{ $activity->evidence_text }}</p>
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            @unless ($activity->status->isMarked())
                <section class="card p-5">
                    <h2 class="text-sm font-semibold text-slate-500 uppercase">Estado</h2>
                    <form method="POST" action="{{ route('president.status', $activity) }}" class="mt-3 flex gap-2">
                        @csrf
                        @method('PATCH')
                        <select name="status" class="input" aria-label="Estado">
                            @foreach (\App\Enums\ActivityStatus::editableOptions() as $v => $l)<option value="{{ $v }}" @selected($activity->status->value === $v)>{{ $l }}</option>@endforeach
                        </select>
                        <button class="btn btn-secondary">Guardar</button>
                    </form>
                </section>
            @endunless

            <section class="card p-5">
                <h2 class="text-sm font-semibold text-slate-500 uppercase">Revisión</h2>
                @if ($activity->marked_at)
                    <p @class(['mt-2 font-medium', 'text-emerald-800' => $done, 'text-rose-700' => $failed])>{{ $activity->status->label() }} · marcada por {{ $activity->marker?->name }}</p>
                    <p class="text-xs text-slate-500">{{ $activity->marked_at->format('d/m/Y H:i') }}</p>
                    @if ($activity->mark_notes)<p class="mt-2 text-sm text-slate-700">{{ $activity->mark_notes }}</p>@endif
                    @if ($done)<p class="mt-2 text-xs font-medium text-emerald-800">Concluida: ya no se puede modificar.</p>@endif
                @else
                    <p class="mt-2 text-sm text-slate-600">Sin marcar. Verde («Concluido», definitivo) o rojo («No completada»).</p>
                @endif

                @unless ($done)
                    <form method="POST" action="{{ route('president.mark', $activity) }}" class="mt-4 space-y-3">
                        @csrf
                        <textarea name="mark_notes" rows="2" maxlength="1000" class="input" placeholder="Comentario (opcional)">{{ $activity->mark_notes }}</textarea>
                        <div class="flex flex-wrap gap-2">
                            <button name="marca" value="concluido" data-confirm="{{ $confirm }}" onclick="return confirm(this.dataset.confirm)" class="btn bg-emerald-600 text-white hover:bg-emerald-700">✓ Concluido</button>
                            <button name="marca" value="{{ $failed ? 'sin_marcar' : 'no_completada' }}" class="btn {{ $failed ? 'btn-secondary' : 'bg-rose-600 text-white hover:bg-rose-700' }}">{{ $failed ? 'Quitar el rojo' : '✗ No completada' }}</button>
                        </div>
                    </form>
                @endunless
            </section>

            <section class="card p-5 text-sm">
                <dl class="space-y-3">
                    <div><dt class="text-xs text-slate-500">Asignada a</dt><dd class="font-medium">{{ $activity->assignee?->name }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Inicio – Fin</dt><dd>{{ $activity->start_date->format('d/m/Y') }} al {{ $activity->end_date->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Cargada por</dt><dd>{{ $activity->creator?->name }} · {{ $activity->created_at->format('d/m/Y H:i') }}</dd></div>
                </dl>
            </section>
        </aside>
    </div>
@endsection
