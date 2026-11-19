@csrf

@php
    $mode = old('evidence_mode', (! $activity->evidence_path && filled($activity->evidence_text)) ? 'texto' : 'foto');
@endphp

<div class="grid gap-5 sm:grid-cols-2">
    {{-- 1. ID automático --}}
    <div class="sm:col-span-2">
        <label for="code" class="label">ID</label>
        <div class="flex items-center gap-3">
            <input id="code" value="{{ $code ?? '—' }}" readonly tabindex="-1" data-folio
                   class="input w-40 cursor-default bg-slate-100 font-mono font-semibold tracking-wide text-slate-700">
            <span class="text-xs text-slate-500" data-folio-hint>
                @if ($activity->exists)
                    Folio de la actividad.
                @elseif ($code)
                    Folio del equipo; se confirma al guardar.
                @else
                    Elige a quién se asigna para ver el folio de su equipo.
                @endif
            </span>
        </div>
    </div>

    {{-- 2. Tipo de actividad --}}
    <div>
        <label for="activity_type_id" class="label">Tipo de actividad *</label>
        <select id="activity_type_id" name="activity_type_id" required class="input" data-other="activity_type_new">
            <option value="">Selecciona un tipo</option>
            @foreach ($types as $type)
                <option value="{{ $type->id }}" @selected((string) old('activity_type_id', $activity->activity_type_id) === (string) $type->id)>{{ $type->name }}</option>
            @endforeach
            <option value="otro" @selected(old('activity_type_id') === 'otro')>Otro (escribirlo)…</option>
        </select>
        <input id="activity_type_new" name="activity_type_new" value="{{ old('activity_type_new') }}" maxlength="120"
               placeholder="Escribe el tipo de actividad" aria-label="Nuevo tipo de actividad"
               @class(['input mt-2', 'hidden' => old('activity_type_id') !== 'otro'])>
    </div>

    {{-- 3. Comunidad --}}
    <div>
        <label for="community_id" class="label">Comunidad *</label>
        <select id="community_id" name="community_id" required class="input" data-other="community_new">
            <option value="">Selecciona una comunidad</option>
            @foreach ($communities as $community)
                <option value="{{ $community->id }}" @selected((string) old('community_id', $activity->community_id) === (string) $community->id)>{{ $community->name }}</option>
            @endforeach
            <option value="otro" @selected(old('community_id') === 'otro')>Otra (escribirla)…</option>
        </select>
        <input id="community_new" name="community_new" value="{{ old('community_new') }}" maxlength="120"
               placeholder="Escribe el nombre de la comunidad" aria-label="Nueva comunidad"
               @class(['input mt-2', 'hidden' => old('community_id') !== 'otro'])>
    </div>

    {{-- Asignar a --}}
    <div class="sm:col-span-2">
        @if (auth()->user()->selfAssignOnly())
            <div class="rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-700">
                La actividad quedará a tu nombre. Un gerente la revisará: verde la pone en «Concluido» y rojo en «No completada».
            </div>
        @else
            <label for="assigned_to" class="label">Asignar a *</label>
            <select id="assigned_to" name="assigned_to" required class="input sm:max-w-md">
                <option value="">Selecciona una persona</option>
                @foreach ($people as $person)
                    <option value="{{ $person->id }}" @selected((int) old('assigned_to', $activity->assigned_to) === $person->id)>
                        {{ $person->name }} ({{ $person->role->label() }})
                    </option>
                @endforeach
            </select>
            @if ($people->isEmpty())
                <p class="mt-1 text-xs text-amber-700">No tienes personas disponibles para asignar. Pide a un administrador que te asigne un equipo.</p>
            @endif
        @endif
    </div>

    {{-- 4. Evidencia: fotografía o texto --}}
    <fieldset class="sm:col-span-2" data-evidence>
        <legend class="label">Evidencia</legend>
        <div class="mb-3 inline-flex rounded-lg border border-slate-300 bg-slate-100 p-1 text-sm font-medium" role="radiogroup">
            @foreach (['foto' => 'Fotografía', 'texto' => 'Texto'] as $value => $label)
                <label class="cursor-pointer">
                    <input type="radio" name="evidence_mode" value="{{ $value }}" class="peer sr-only" @checked($mode === $value)>
                    <span class="block rounded-md px-4 py-1.5 text-slate-600 transition peer-checked:bg-white peer-checked:text-slate-900 peer-checked:shadow-sm peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500">{{ $label }}</span>
                </label>
            @endforeach
        </div>

        <div data-evidence-panel="foto" @class(['hidden' => $mode !== 'foto'])>
            <x-file-input name="evidence" accept="image/*" icon="camera" button="Tomar o subir foto" />
            @if ($activity->evidence_path)
                <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                    <span>Ya tiene una fotografía. Si subes otra, la reemplaza.</span>
                    <label class="inline-flex items-center gap-1.5">
                        <input type="checkbox" name="remove_photo" value="1" class="rounded border-slate-300"> Quitar fotografía
                    </label>
                </div>
            @endif
        </div>

        <div data-evidence-panel="texto" @class(['hidden' => $mode !== 'texto'])>
            <textarea id="evidence_text" name="evidence_text" rows="4" class="input"
                      placeholder="Describe lo que se hizo: con quién, qué resultados, observaciones…">{{ old('evidence_text', $activity->evidence_text) }}</textarea>
        </div>
        <p class="mt-1 text-xs text-slate-500">Puedes cargarla ahora o después, editando la actividad.</p>
    </fieldset>

    {{-- 5 y 6. Fechas --}}
    <div>
        <label for="start_date" class="label">Fecha de inicio *</label>
        <input id="start_date" type="date" name="start_date" required value="{{ old('start_date', $activity->start_date?->format('Y-m-d')) }}" class="input">
    </div>

    <div>
        <label for="end_date" class="label">Fecha de fin *</label>
        <input id="end_date" type="date" name="end_date" required value="{{ old('end_date', $activity->end_date?->format('Y-m-d')) }}"
               min="{{ old('start_date', $activity->start_date?->format('Y-m-d')) }}" class="input">
    </div>

    {{-- 7. Estado: las tres opciones manuales; Concluido y No completada los pone la revisión --}}
    <div>
        <label for="status" class="label">Estado *</label>
        @if ($activity->exists && $activity->status->isMarked())
            <p class="input bg-slate-50 text-slate-600">{{ $activity->status->label() }} (lo pone la revisión con el checkbox)</p>
        @else
            <select id="status" name="status" required class="input">
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $activity->status?->value ?? 'no_iniciado') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        @endif
    </div>

    {{-- 8. Lugar al que hay que acudir (opcional) --}}
    @php
        $pinLocked = $activity->exists && auth()->user()->selfAssignOnly();
        $needsPlace = (bool) old('requiere_ubicacion', $activity->requiere_ubicacion);
    @endphp
    <fieldset class="sm:col-span-2 rounded-xl border border-slate-200 p-4" data-map-picker data-toggle="requiere_ubicacion" data-locked="{{ $pinLocked ? 1 : 0 }}" data-manual="{{ auth()->user()->selfAssignOnly() ? 0 : 1 }}">
        <legend class="px-1 text-sm font-semibold text-slate-700">Ubicación</legend>
        @if ($pinLocked)
            <input type="checkbox" id="requiere_ubicacion" class="hidden" @checked($activity->requiere_ubicacion) disabled>
            @if ($activity->requiere_ubicacion)
                <p class="text-sm text-slate-600">Hay que acudir a <strong>{{ $activity->destino_nombre ?: 'el lugar marcado' }}</strong> (radio de {{ number_format($activity->destino_radio) }} m). Solo un gerente puede cambiar el lugar.</p>
            @else
                <p class="text-sm text-slate-500">Esta actividad no requiere acudir a un lugar.</p>
            @endif
        @else
            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                <input type="hidden" name="requiere_ubicacion" value="0">
                <input type="checkbox" id="requiere_ubicacion" name="requiere_ubicacion" value="1" class="rounded border-slate-300" @checked($needsPlace)>
                Requiere acudir al lugar
            </label>
            <p class="mt-1 text-xs text-slate-500">Si la marcas, el encargado tendrá que validar desde su celular que estuvo ahí. Si no la valida o está fuera del radio, la actividad se marca en rojo.</p>
        @endif

        <div data-map-panel @class(['mt-3 space-y-3', 'hidden' => ! $needsPlace])>
            @unless ($pinLocked)
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label for="destino_nombre" class="label">Nombre del lugar</label>
                        <input id="destino_nombre" name="destino_nombre" maxlength="150" class="input" placeholder="Ej. Plaza principal de San Pedro"
                               value="{{ old('destino_nombre', $activity->destino_nombre) }}">
                    </div>
                    <div>
                        <label for="destino_radio" class="label">Radio permitido (m)</label>
                        <input id="destino_radio" type="number" name="destino_radio" min="50" max="20000" step="50" class="input"
                               value="{{ old('destino_radio', $activity->destino_radio ?? 300) }}">
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                    <button type="button" class="btn btn-secondary px-3 py-1 text-xs" data-map-here>Usar mi ubicación actual</button>
                    @if (auth()->user()->selfAssignOnly())
                        <span data-map-hint>Estando en el lugar, toca «Usar mi ubicación actual». El punto se toma del GPS de tu celular.</span>
                    @else
                        <span data-map-hint>{{ $activity->destino_lat ? 'Toca el mapa para mover el punto.' : 'Toca el mapa para marcar el lugar, o usa tu ubicación actual.' }}</span>
                    @endif
                </div>
                <input type="hidden" name="destino_precision" value="{{ old('destino_precision') }}">
            @else
                <span data-map-hint class="hidden"></span>
                <input type="hidden" name="destino_radio" value="{{ $activity->destino_radio }}" disabled>
            @endunless
            <input type="hidden" name="destino_lat" value="{{ old('destino_lat', $activity->destino_lat) }}" @disabled($pinLocked)>
            <input type="hidden" name="destino_lng" value="{{ old('destino_lng', $activity->destino_lng) }}" @disabled($pinLocked)>
            <div data-map class="h-72 w-full overflow-hidden rounded-lg border border-slate-200"></div>
            @error('destino_lat')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
        </div>
    </fieldset>
</div>

@push('scripts')
@vite('resources/js/map.js')
<script>
    document.querySelectorAll('[data-evidence]').forEach((box) => {
        box.addEventListener('change', (e) => {
            if (e.target.name !== 'evidence_mode') return;
            box.querySelectorAll('[data-evidence-panel]').forEach((panel) => {
                panel.classList.toggle('hidden', panel.dataset.evidencePanel !== e.target.value);
            });
        });
    });
    // «Otro…»: muestra el campo para escribirlo a mano
    document.querySelectorAll('select[data-other]').forEach((select) => {
        const input = document.getElementById(select.dataset.other);
        const sync = () => {
            const other = select.value === 'otro';
            input.classList.toggle('hidden', !other);
            input.required = other;
            if (other) input.focus();
        };
        select.addEventListener('change', sync);
        input.required = select.value === 'otro';
    });

    // Folio: cambia según el equipo de la persona asignada
    const previews = @json($folioPreviews ?? []);
    const assignee = document.getElementById('assigned_to');
    const folio = document.querySelector('[data-folio]');
    const hint = document.querySelector('[data-folio-hint]');
    if (assignee && folio && Object.keys(previews).length) {
        assignee.addEventListener('change', () => {
            const code = previews[assignee.value];
            folio.value = code ?? '—';
            hint.textContent = code ? 'Folio del equipo; se confirma al guardar.' : 'Elige a quién se asigna para ver el folio de su equipo.';
        });
    }

    const start = document.getElementById('start_date');
    const end = document.getElementById('end_date');
    start?.addEventListener('change', () => {
        end.min = start.value;
        if (end.value && end.value < start.value) end.value = start.value;
    });
</script>
@endpush
