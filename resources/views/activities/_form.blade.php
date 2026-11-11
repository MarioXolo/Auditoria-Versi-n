@csrf

@php
    $mode = old('evidence_mode', (! $activity->evidence_path && filled($activity->evidence_text)) ? 'texto' : 'foto');
@endphp

<div class="grid gap-5 sm:grid-cols-2">
    {{-- 1. ID automático --}}
    <div class="sm:col-span-2">
        <label for="code" class="label">ID</label>
        <div class="flex items-center gap-3">
            <input id="code" value="{{ $code }}" readonly tabindex="-1"
                   class="input w-40 cursor-default bg-slate-100 font-mono font-semibold tracking-wide text-slate-700">
            <span class="text-xs text-slate-500">{{ $activity->exists ? 'Folio de la actividad.' : 'Se asigna automáticamente al guardar.' }}</span>
        </div>
    </div>

    {{-- 2. Tipo de actividad --}}
    <div>
        <label for="activity_type_id" class="label">Tipo de actividad *</label>
        <select id="activity_type_id" name="activity_type_id" required class="input">
            <option value="">Selecciona un tipo</option>
            @foreach ($types as $type)
                <option value="{{ $type->id }}" @selected((int) old('activity_type_id', $activity->activity_type_id) === $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
        @if ($types->isEmpty())
            <p class="mt-1 text-xs text-amber-700">No hay tipos de actividad. Un administrador o gerente debe darlos de alta en Catálogos.</p>
        @endif
    </div>

    {{-- 3. Comunidad --}}
    <div>
        <label for="community_id" class="label">Comunidad *</label>
        <select id="community_id" name="community_id" required class="input">
            <option value="">Selecciona una comunidad</option>
            @foreach ($communities as $community)
                <option value="{{ $community->id }}" @selected((int) old('community_id', $activity->community_id) === $community->id)>{{ $community->name }}</option>
            @endforeach
        </select>
        @if ($communities->isEmpty())
            <p class="mt-1 text-xs text-amber-700">No hay comunidades. Un administrador o gerente debe darlas de alta en Catálogos.</p>
        @endif
    </div>

    {{-- Asignar a --}}
    <div class="sm:col-span-2">
        @if (auth()->user()->isEmpleado())
            <div class="rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-700">
                La actividad quedará a tu nombre. Tu encargado la revisará: verde la pone en «Concluido» y rojo en «No completada».
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

    @if ($activity->exists)
        <div>
            <label for="status" class="label">Estado</label>
            @if ($activity->status->isMarked())
                <p class="input bg-slate-50 text-slate-600">{{ $activity->status->label() }} (lo pone la revisión con el checkbox)</p>
            @else
                <select id="status" name="status" class="input">
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $activity->status->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            @endif
        </div>
    @endif
</div>

@push('scripts')
<script>
    document.querySelectorAll('[data-evidence]').forEach((box) => {
        box.addEventListener('change', (e) => {
            if (e.target.name !== 'evidence_mode') return;
            box.querySelectorAll('[data-evidence-panel]').forEach((panel) => {
                panel.classList.toggle('hidden', panel.dataset.evidencePanel !== e.target.value);
            });
        });
    });
    const start = document.getElementById('start_date');
    const end = document.getElementById('end_date');
    start?.addEventListener('change', () => {
        end.min = start.value;
        if (end.value && end.value < start.value) end.value = start.value;
    });
</script>
@endpush
