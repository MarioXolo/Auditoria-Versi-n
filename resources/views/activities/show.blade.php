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
            @if ($activity->locationAlert())
                <span class="badge bg-rose-600 text-white">{{ $activity->locationLabel() }}</span>
            @elseif ($activity->locationStatus() === 'verificada')
                <span class="badge bg-emerald-100 text-emerald-800">Ubicación verificada</span>
            @elseif ($activity->locationStatus() === 'manual')
                <span class="badge bg-sky-100 text-sky-800">Ubicación validada a mano</span>
            @endif
            @if ($activity->isSelfAssigned() && $activity->assignee?->selfAssignOnly())
                <span class="badge bg-brand-50 text-brand-700">Se la cargó él mismo</span>
            @endif
        </div>
        @if (auth()->user()->selfAssignOnly() && ($by = $activity->assignedByText(auth()->user())))
            <p class="mt-2 rounded-lg bg-brand-50 px-3 py-2 text-sm font-medium text-brand-900">{{ $by }}</p>
        @endif
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

            @if ($activity->requiere_ubicacion && $activity->destino_lat)
                @php $loc = $activity->locationStatus(); @endphp
                <section @class([
                    'card p-6',
                    'border-rose-300 bg-rose-50' => $activity->locationAlert(),
                    'border-emerald-300 bg-emerald-50' => $loc === 'verificada',
                    'border-sky-300 bg-sky-50' => $loc === 'manual',
                ])>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-500 uppercase">Ubicación</h2>
                            <p class="mt-1 text-slate-800">Hay que acudir a <strong>{{ $activity->destino_nombre ?: 'el lugar marcado' }}</strong>
                                <span class="text-sm text-slate-500">(radio de {{ number_format($activity->destino_radio) }} m)</span></p>
                        </div>
                        <span @class(['badge', 'bg-rose-600 text-white' => $activity->locationAlert(), 'bg-emerald-600 text-white' => $loc === 'verificada', 'bg-sky-600 text-white' => $loc === 'manual'])>{{ $activity->locationLabel() }}</span>
                    </div>
                    @if ($activity->ubicacion_at)
                        <p class="mt-2 text-xs text-slate-600">GPS del encargado: {{ $activity->ubicacion_at->format('d/m/Y H:i') }} · precisión ±{{ $activity->ubicacion_precision }} m · a {{ number_format($activity->ubicacion_distancia) }} m del lugar</p>
                    @endif
                    @if ($loc === 'manual')
                        <div class="mt-3 rounded-lg border border-sky-200 bg-white px-3 py-2 text-sm">
                            <p class="font-medium text-sky-900">Validada a mano por {{ $activity->locationValidator?->name }} · {{ $activity->ubicacion_validada_at?->format('d/m/Y H:i') }}</p>
                            <p class="mt-1 whitespace-pre-line text-slate-700">{{ $activity->ubicacion_nota }}</p>
                            @if (auth()->user()->seesEverything())
                                <form method="POST" action="{{ route('activities.location.revoke', $activity) }}" class="mt-2" onsubmit="return confirm('¿Quitar la validación manual? La actividad volverá a quedar en alerta si no tiene GPS válido.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-xs font-medium text-rose-700 hover:underline">Quitar validación manual</button>
                                </form>
                            @endif
                        </div>
                    @endif

                    <div class="mt-4 h-64 w-full overflow-hidden rounded-lg border border-slate-200" data-map-view
                         data-dest-lat="{{ $activity->destino_lat }}" data-dest-lng="{{ $activity->destino_lng }}" data-radius="{{ $activity->destino_radio }}"
                         @if ($activity->ubicacion_at) data-me-lat="{{ $activity->ubicacion_lat }}" data-me-lng="{{ $activity->ubicacion_lng }}" data-ok="{{ $loc === 'verificada' ? 1 : 0 }}" @endif></div>

                    @if (auth()->user()->seesEverything() && $activity->locationAlert())
                        <details class="mt-4 rounded-lg border border-slate-200 bg-white p-3" @if ($errors->has('ubicacion_nota')) open @endif>
                            <summary class="cursor-pointer text-sm font-medium text-slate-800">Validar a mano (sin GPS)</summary>
                            <form method="POST" action="{{ route('activities.location.manual', $activity) }}" class="mt-3 space-y-2">
                                @csrf
                                <label for="ubicacion_nota" class="label">¿Cómo confirmaste que estuvo en el lugar? *</label>
                                <textarea id="ubicacion_nota" name="ubicacion_nota" rows="2" maxlength="500" required class="input"
                                          placeholder="Ej. Lo vi en la junta; foto del evento en el grupo; se le descompuso el GPS">{{ old('ubicacion_nota') }}</textarea>
                                <button class="btn btn-secondary">Validar ubicación</button>
                                <p class="text-xs text-slate-500">No necesitas estar cerca del lugar. Queda registrado quién la validó y el motivo.</p>
                            </form>
                        </details>
                    @endif
                    @if (auth()->id() === $activity->assigned_to && ! $activity->status->isMarked())
                        <form method="POST" action="{{ route('activities.location', $activity) }}" class="mt-4" data-geo-validate>
                            @csrf
                            <input type="hidden" name="lat">
                            <input type="hidden" name="lng">
                            <input type="hidden" name="precision">
                            <button class="btn btn-primary w-full sm:w-auto">{{ $activity->ubicacion_at ? 'Volver a validar mi ubicación' : 'Validar mi ubicación' }}</button>
                            <p class="mt-2 text-xs text-slate-600" data-geo-msg>Hazlo desde tu celular cuando estés en el lugar. Se toma tu ubicación en ese momento.</p>
                        </form>
                    @endif
                </section>
            @endif
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
                        {{ $done ? 'Concluido' : 'No completada' }} · marcada por {{ $activity->markedByText(auth()->user()) }}
                    </p>
                    <p class="text-xs text-slate-500">{{ $activity->marked_at->format('d/m/Y H:i') }}</p>
                    @if ($done)
                        <p class="mt-2 text-xs font-medium text-emerald-800">Concluida: ya no se puede modificar.</p>
                    @endif
                    @if ($activity->mark_notes)
                        <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $activity->mark_notes }}</p>
                    @endif
                @else
                    <p class="mt-2 text-sm text-slate-600">Sin marcar. La persona asignada, un gerente o el administrador la marca en verde («Concluido», definitivo); el rojo («No completada») lo pone un gerente o el administrador.</p>
                @endif

                @can('mark', $activity)
                    <form method="POST" action="{{ route('activities.mark', $activity) }}" class="mt-4 space-y-3 border-t border-slate-200 pt-4">
                        @csrf
                        <div>
                            <label for="mark_notes" class="label">Comentario (opcional)</label>
                            <textarea id="mark_notes" name="mark_notes" rows="2" class="input" placeholder="Ej. Falta la evidencia de la segunda visita">{{ old('mark_notes', $activity->mark_notes) }}</textarea>
                        </div>
                        <div class="grid gap-2">
                            @if ($activity->locationBlocksSelfConclusion(auth()->user()))
                                <p class="rounded-lg border-2 border-dashed border-rose-300 bg-rose-50 px-3 py-2.5 text-sm font-medium text-rose-800">Para marcarla como concluida, primero valida tu ubicación en el lugar.</p>
                            @else
                            <button name="marca" value="concluido" data-confirm="¿Seguro que quieres marcarla como Concluido? Después ya no podrás hacerle ningún cambio." onclick="return confirm(this.dataset.confirm)" role="checkbox" aria-checked="{{ $done ? 'true' : 'false' }}"
                                    @class(['flex items-center gap-3 rounded-lg border-2 px-3 py-2.5 text-left text-sm font-medium transition',
                                        'border-emerald-600 bg-emerald-600 text-white' => $done,
                                        'border-slate-300 bg-white text-slate-700 hover:border-emerald-500' => ! $done])>
                                <span @class(['grid size-5 place-items-center rounded border-2', 'border-white' => $done, 'border-slate-400' => ! $done])>
                                    @if ($done)<svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>@endif
                                </span>
                                Marcar como concluido
                            </button>
                            @endif
                            @can('markFailed', $activity)
                            <button name="marca" value="{{ $failed ? 'sin_marcar' : 'no_completada' }}" role="checkbox" aria-checked="{{ $failed ? 'true' : 'false' }}"
                                    @class(['flex items-center gap-3 rounded-lg border-2 px-3 py-2.5 text-left text-sm font-medium transition',
                                        'border-rose-600 bg-rose-600 text-white' => $failed,
                                        'border-slate-300 bg-white text-slate-700 hover:border-rose-500' => ! $failed])>
                                <span @class(['grid size-5 place-items-center rounded border-2', 'border-white' => $failed, 'border-slate-400' => ! $failed])>
                                    @if ($failed)<svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>@endif
                                </span>
                                {{ $failed ? 'No completada (tocar para quitar)' : 'Marcar como no completada' }}
                            </button>
                            @endcan
                        </div>
                    </form>
                @endcan
            </section>

            <dl class="card divide-y divide-slate-100 text-sm">
                @foreach ([
                    'ID' => $activity->code,
                    'Tipo' => $activity->type?->name,
                    'Comunidad' => $activity->community?->name,
                    'Asignada a' => $activity->assignee?->name.($activity->assignee?->folio_number ? ' · '.$activity->assignee->folioLabel() : ''),
                    ($activity->isSelfAssigned() ? 'Cargada por' : 'Asignada por') => $activity->isSelfAssigned() ? $activity->creator?->name
                        : (auth()->user()->selfAssignOnly() ? $activity->creator?->areaLabel() : $activity->creator?->name.($activity->creator?->area ? ' ('.$activity->creator->area.')' : '')),
                    'Fecha de inicio' => $activity->start_date->format('d/m/Y'),
                    'Fecha de fin' => $activity->end_date->format('d/m/Y'),
                    'Creada' => $activity->created_at->format('d/m/Y H:i'),
                    'Concluido el' => $activity->completed_at?->format('d/m/Y H:i') ?? '—',
                    'Plazo planeado' => $activity->plannedDays() ? $activity->plannedDays().' '.($activity->plannedDays() === 1 ? 'día' : 'días') : '—',
                ] as $label => $value)
                    <div class="flex justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">{{ $label }}</dt>
                        <dd class="text-right font-medium">{{ $value }}</dd>
                    </div>
                @endforeach
                @if ($activity->durationDays())
                    @php $r = $activity->deadlineResult(); @endphp
                    <div class="flex justify-between gap-4 px-5 py-3">
                        <dt class="text-slate-500">Duró</dt>
                        <dd class="text-right">
                            <span class="font-medium">{{ $activity->durationDays() }} {{ $activity->durationDays() === 1 ? 'día' : 'días' }}</span>
                            @if ($r)
                                <span @class(['badge mt-1 block', 'bg-emerald-100 text-emerald-800' => $r === 'antes', 'bg-sky-100 text-sky-800' => $r === 'a_tiempo', 'bg-rose-100 text-rose-800' => $r === 'despues'])>{{ $activity->deadlineLabel() }}</span>
                            @endif
                        </dd>
                    </div>
                @endif
            </dl>

        </aside>
    </div>
@endsection

@push('scripts')
@vite('resources/js/map.js')
@endpush
