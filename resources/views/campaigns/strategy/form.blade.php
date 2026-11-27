@extends('layouts.app')

@section('title', ($record->exists ? 'Editar' : 'Agregar').' · '.$book['name'])

@use('App\Services\StrategyBooks')

@php
    $key = $book['key'];
    $val = fn (string $k) => old("d.$k", $record->dato($k));
    $fileSlot = function (string $k, string $label, string $accept, string $hint = '') use ($record, $campaign, $key) {
        return compact('k', 'label', 'accept', 'hint') + ['current' => $record->exists ? $record->archivo($k) : null];
    };
    $slots = [];
    foreach ($book['fields'] as $k => $f) {
        if (in_array($f['type'], ['photo', 'file'], true)) {
            $slots[] = $fileSlot($k, $f['label'], $f['type'] === 'photo' ? 'image/*' : '.pdf,image/*,.doc,.docx') + ['name' => "f[$k]"];
        }
    }
    $slots[] = $fileSlot('foto', 'Foto (evidencia)', 'image/*') + ['name' => 'foto'];
    $slots[] = $fileSlot('archivo', 'Archivo adjunto', '.pdf,image/*,.doc,.docx,.xls,.xlsx') + ['name' => 'archivo'];
@endphp

@section('content')
    @include('campaigns._nav')

    <div class="mb-4">
        <a href="{{ route('campaigns.strategy.index', [$campaign, $key]) }}" class="text-sm text-slate-500 hover:text-slate-800">← {{ $book['name'] }}</a>
        <h2 class="mt-1 text-xl font-semibold"><span aria-hidden="true">{{ $book['icon'] }}</span> {{ $record->exists ? $record->title() : 'Nuevo registro · '.$book['name'] }}</h2>
        @if ($record->es_evento)
            <p class="mt-1 text-sm text-slate-600">Agenda IEEQ: <b>{{ $record->ieeq_estatus }}</b>@if ($record->ieeq_fecha_limite) · fecha límite {{ $record->ieeq_fecha_limite->format('d/m/Y') }}@endif · <a href="{{ route('campaigns.agenda.index', $campaign) }}" class="text-brand-700 hover:underline">ver agenda</a></p>
        @elseif ($book['evento'] ?? false)
            <p class="mt-1 text-sm text-slate-600">Al guardarlo pasa solo a la Agenda IEEQ con fecha límite {{ \App\Models\StrategyRecord::diasAnticipacion() }} días antes del evento.</p>
        @endif
    </div>

    <form method="POST" action="{{ $record->exists ? route('campaigns.strategy.update', [$campaign, $key, $record]) : route('campaigns.strategy.store', [$campaign, $key]) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @if ($record->exists) @method('PUT') @endif

        {{-- Datos propios del libro --}}
        <section class="card p-5">
            <h3 class="mb-4 text-sm font-semibold tracking-wide text-slate-500 uppercase">{{ $book['name'] }}</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($book['fields'] as $k => $f)
                    @continue(in_array($f['type'], ['photo', 'file'], true))
                    <div @class(['sm:col-span-2' => in_array($f['type'], ['longtext', 'multiselect']) || $k === $book['title']])>
                        <label for="d_{{ $k }}" class="label">{{ $f['label'] }}{{ ($f['required'] ?? false) ? ' *' : '' }}</label>
                        @switch($f['type'])
                            @case('longtext')
                                <textarea id="d_{{ $k }}" name="d[{{ $k }}]" rows="3" maxlength="{{ $f['max'] ?? 2000 }}" class="input">{{ $val($k) }}</textarea>
                                @break
                            @case('select')
                                <select id="d_{{ $k }}" name="d[{{ $k }}]" class="input" @required($f['required'] ?? false)>
                                    @unless ($f['required'] ?? false)<option value="">—</option>@endunless
                                    @foreach (StrategyBooks::options($f) as $ov => $ol)
                                        <option value="{{ $ov }}" @selected((string) ($val($k) ?? ($record->exists ? '' : ($f['default'] ?? ''))) === (string) $ov)>{{ $ol }}</option>
                                    @endforeach
                                </select>
                                @break
                            @case('multiselect')
                                @php $sel = (array) ($val($k) ?? []); @endphp
                                <div class="flex flex-wrap gap-2">
                                    @foreach (StrategyBooks::options($f) as $ov => $ol)
                                        <label class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 px-3 py-1 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                            <input type="checkbox" name="d[{{ $k }}][]" value="{{ $ov }}" class="rounded border-slate-300" @checked(in_array($ov, $sel, true))> {{ $ol }}
                                        </label>
                                    @endforeach
                                </div>
                                @break
                            @case('int')
                                <input id="d_{{ $k }}" type="number" min="0" step="1" name="d[{{ $k }}]" value="{{ $val($k) }}" class="input tabular-nums" @required($f['required'] ?? false)>
                                @break
                            @case('money')
                                <input id="d_{{ $k }}" type="number" min="0" step="any" name="d[{{ $k }}]" value="{{ $val($k) }}" class="input tabular-nums" placeholder="$">
                                @break
                            @case('date')
                                <input id="d_{{ $k }}" type="date" name="d[{{ $k }}]" value="{{ $val($k) }}" class="input">
                                @break
                            @default
                                <input id="d_{{ $k }}" name="d[{{ $k }}]" value="{{ $val($k) }}" maxlength="{{ $f['max'] ?? 255 }}" class="input" placeholder="{{ $f['placeholder'] ?? '' }}" @required($f['required'] ?? false)>
                        @endswitch
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Ubicación en mapa (bardas y lonas) --}}
        @if ($book['mapa'] ?? false)
            <section class="card p-5" data-map-point>
                <h3 class="mb-1 text-sm font-semibold tracking-wide text-slate-500 uppercase">Ubicación en el mapa</h3>
                <div class="mb-3 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                    <button type="button" class="btn btn-secondary px-3 py-1 text-xs" data-map-here>Usar mi ubicación actual</button>
                    <span data-map-hint>{{ $record->lat ? 'Toca el mapa para mover el punto.' : 'Toca el mapa para marcar dónde está.' }}</span>
                    <button type="button" class="text-rose-600 hover:underline" data-map-clear>Quitar punto</button>
                </div>
                <input type="hidden" name="lat" value="{{ old('lat', $record->lat) }}">
                <input type="hidden" name="lng" value="{{ old('lng', $record->lng) }}">
                <div data-map class="h-72 w-full overflow-hidden rounded-lg border border-slate-200"></div>
            </section>
        @endif

        {{-- Campos comunes a todos los puntos --}}
        <section class="card p-5">
            <h3 class="mb-4 text-sm font-semibold tracking-wide text-slate-500 uppercase">Datos generales</h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="community_id" class="label">Comunidad *</label>
                    <select id="community_id" name="community_id" class="input" required data-community-select>
                        <option value="">— Elige —</option>
                        @foreach ($communities as $c)<option value="{{ $c->id }}" @selected(old('community_id', $record->community_id) == $c->id)>{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="section_id" class="label">Sección electoral</label>
                    <select id="section_id" name="section_id" class="input" data-section-select>
                        <option value="">— Sin sección —</option>
                        @foreach ($sections as $s)
                            <option value="{{ $s->id }}" data-community="{{ $s->community_id }}" @selected(old('section_id', $record->section_id) == $s->id)>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                    @if ($sections->isEmpty())
                        <p class="mt-1 text-xs text-slate-500">Aún no hay secciones. <a href="{{ route('catalogs.index') }}#secciones" class="text-brand-700 hover:underline">Agrégalas en Catálogos</a>.</p>
                    @endif
                </div>
                <div>
                    <label for="responsable_id" class="label">Responsable *</label>
                    <select id="responsable_id" name="responsable_id" class="input" required>
                        <option value="">— Elige —</option>
                        @foreach ($users as $u)<option value="{{ $u->id }}" @selected(old('responsable_id', $record->responsable_id ?? ($record->exists ? null : auth()->id())) == $u->id)>{{ $u->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="fecha" class="label">{{ $book['fecha_label'] ?? 'Fecha' }}{{ ($book['evento'] ?? false) ? ' *' : '' }}</label>
                    <input id="fecha" type="date" name="fecha" value="{{ old('fecha', $record->fecha?->format('Y-m-d')) }}" class="input" @required($book['evento'] ?? false)>
                </div>
                @if ($book['hora'] ?? false)
                    <div>
                        <label for="hora" class="label">Hora</label>
                        <input id="hora" type="time" name="hora" value="{{ old('hora', $record->horaCorta()) }}" class="input">
                    </div>
                @endif
                <div>
                    <label for="estatus" class="label">Estatus *</label>
                    <select id="estatus" name="estatus" class="input" required>
                        @foreach (StrategyBooks::ESTATUS as $e)<option @selected(old('estatus', $record->estatus ?? 'Pendiente') === $e)>{{ $e }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="costo_estimado" class="label">Costo estimado</label>
                    <input id="costo_estimado" type="number" min="0" step="any" name="costo_estimado" value="{{ old('costo_estimado', $record->costo_estimado) }}" class="input tabular-nums" placeholder="$">
                </div>
                <div>
                    <label for="costo_real" class="label">Costo real</label>
                    <input id="costo_real" type="number" min="0" step="any" name="costo_real" value="{{ old('costo_real', $record->costo_real) }}" class="input tabular-nums" placeholder="$">
                </div>
            </div>
        </section>

        {{-- Evidencia y archivos --}}
        <section class="card p-5">
            <h3 class="mb-4 text-sm font-semibold tracking-wide text-slate-500 uppercase">Evidencia y archivos</h3>
            @isset($book['formato'])
                <p class="mb-4 rounded-lg bg-brand-50 px-3 py-2 text-sm text-brand-900">
                    1) Descarga el <a href="{{ route('campaigns.strategy.formato', [$campaign, $key, $record->exists ? $record : null]) }}" target="_blank" class="font-semibold underline">formato de autorización</a>{{ $record->exists ? ' (ya lleva los datos de este registro)' : '' }}, 2) imprímelo y que lo firme el dueño, 3) súbelo aquí como foto o PDF.
                </p>
            @endisset
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($slots as $slot)
                    <div class="rounded-lg border border-slate-200 p-3">
                        <label for="file_{{ $slot['k'] }}" class="label">{{ $slot['label'] }}</label>
                        @if ($slot['current'])
                            <div class="mb-2 flex items-center justify-between gap-2 text-sm">
                                <a href="{{ route('campaigns.strategy.file', [$campaign, $key, $record, $slot['k']]) }}" target="_blank" class="font-medium text-brand-700 hover:underline">✓ Ver archivo actual</a>
                                <label class="inline-flex items-center gap-1 text-xs text-rose-700"><input type="checkbox" name="quitar[{{ $slot['k'] }}]" value="1" class="rounded border-slate-300"> Quitar</label>
                            </div>
                        @endif
                        <input id="file_{{ $slot['k'] }}" type="file" name="{{ $slot['name'] }}" accept="{{ $slot['accept'] }}" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium hover:file:bg-slate-200">
                        <p class="mt-1 text-[11px] text-slate-400">{{ $slot['current'] ? 'Sube otro para reemplazarlo.' : 'Máximo 10 MB.' }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-2">
                @if (auth()->user()->puede('estrategia', 'capturar'))
                <button class="btn btn-primary">{{ $record->exists ? 'Guardar cambios' : 'Guardar' }}</button>
                @else
                <p class="text-sm text-slate-500">Tu perfil solo permite consultar.</p>
                @endif
                <a href="{{ route('campaigns.strategy.index', [$campaign, $key]) }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </div>
    </form>

    @if ($record->exists && auth()->user()->puede('estrategia', 'capturar'))
        <form method="POST" action="{{ route('campaigns.strategy.destroy', [$campaign, $key, $record]) }}" class="mt-6 border-t border-slate-200 pt-4" onsubmit="return confirm('¿Eliminar este registro y sus archivos? No se puede deshacer.')">
            @csrf
            @method('DELETE')
            <button class="text-sm font-medium text-rose-600 hover:underline">Eliminar registro</button>
        </form>
    @endif
@endsection

@push('scripts')
<script>
    // La lista de secciones se acota a la comunidad elegida
    (() => {
        const com = document.querySelector('[data-community-select]');
        const sec = document.querySelector('[data-section-select]');
        if (!com || !sec) return;
        const sync = () => {
            [...sec.options].forEach((o) => {
                if (!o.value) return;
                const ok = !com.value || !o.dataset.community || o.dataset.community === com.value;
                o.hidden = !ok;
                if (!ok && o.selected) sec.value = '';
            });
        };
        com.addEventListener('change', sync);
        sync();
    })();
</script>
@if ($book['mapa'] ?? false)
    @vite('resources/js/map.js')
@endif
@endpush
