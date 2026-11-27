@csrf
<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="activity_type_id" class="label">Tipo de actividad *</label>
        <select id="activity_type_id" name="activity_type_id" class="input" required data-otro="activity_type_new">
            <option value="">— Elige —</option>
            @foreach ($types as $t)<option value="{{ $t->id }}" @selected((string) old('activity_type_id', $activity->president_type_id) === (string) $t->id)>{{ $t->name }}</option>@endforeach
            <option value="otro" @selected(old('activity_type_id') === 'otro')>Otro… (escribirlo)</option>
        </select>
        <input id="activity_type_new" name="activity_type_new" value="{{ old('activity_type_new') }}" maxlength="120" placeholder="Nuevo tipo de actividad" @class(['input mt-2', 'hidden' => old('activity_type_id') !== 'otro'])>
        <p class="mt-1 text-xs text-slate-500">Lista privada de esta agenda: lo que escribas aquí no aparece en las actividades de los demás.</p>
    </div>
    <div>
        <label for="community_id" class="label">Comunidad *</label>
        <select id="community_id" name="community_id" class="input" required data-otro="community_new">
            <option value="">— Elige —</option>
            @foreach ($communities as $c)<option value="{{ $c->id }}" @selected((string) old('community_id', $activity->community_id) === (string) $c->id)>{{ $c->name }}</option>@endforeach
            <option value="otro" @selected(old('community_id') === 'otro')>Otra… (escribirla)</option>
        </select>
        <input id="community_new" name="community_new" value="{{ old('community_new') }}" maxlength="120" placeholder="Nueva comunidad" @class(['input mt-2', 'hidden' => old('community_id') !== 'otro'])>
    </div>
    <div>
        <label for="assigned_to" class="label">Asignada a *</label>
        <select id="assigned_to" name="assigned_to" class="input" required>
            @foreach ($people as $p)<option value="{{ $p->id }}" @selected((int) old('assigned_to', $activity->assigned_to) === $p->id)>{{ $p->name }} · {{ $p->role->label() }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="status" class="label">Estado</label>
        @if ($activity->status?->isMarked())
            <p class="py-2 text-sm"><span class="badge {{ $activity->status->badge() }}">{{ $activity->status->label() }}</span> <span class="text-slate-500">(se cambia con la marca)</span></p>
        @else
            <select id="status" name="status" class="input">
                @foreach (\App\Enums\ActivityStatus::editableOptions() as $v => $l)<option value="{{ $v }}" @selected(old('status', $activity->status?->value) === $v)>{{ $l }}</option>@endforeach
            </select>
        @endif
    </div>
    <div>
        <label for="start_date" class="label">Fecha de inicio *</label>
        <input id="start_date" type="date" name="start_date" value="{{ old('start_date', $activity->start_date?->format('Y-m-d')) }}" required class="input">
    </div>
    <div>
        <label for="end_date" class="label">Fecha de fin *</label>
        <input id="end_date" type="date" name="end_date" value="{{ old('end_date', $activity->end_date?->format('Y-m-d')) }}" required class="input">
    </div>
    <div class="sm:col-span-2">
        <label for="evidence" class="label">Evidencia: fotografía</label>
        @if ($activity->evidence_path)
            <div class="mb-2 flex items-center gap-3 text-sm">
                <a href="{{ route('president.evidence', $activity) }}" target="_blank" class="font-medium text-brand-700 hover:underline">Ver foto actual</a>
                <label class="inline-flex items-center gap-1 text-rose-700"><input type="checkbox" name="remove_photo" value="1" class="rounded border-slate-300"> Quitar</label>
            </div>
        @endif
        <input id="evidence" type="file" name="evidence" accept="image/*" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium hover:file:bg-slate-200">
    </div>
    <div class="sm:col-span-2">
        <label for="evidence_text" class="label">Evidencia o notas en texto</label>
        <textarea id="evidence_text" name="evidence_text" rows="4" maxlength="5000" class="input">{{ old('evidence_text', $activity->evidence_text) }}</textarea>
    </div>
</div>

@push('scripts')
<script>
    document.querySelectorAll('[data-otro]').forEach((sel) => {
        const input = document.getElementById(sel.dataset.otro);
        sel.addEventListener('change', () => { input.classList.toggle('hidden', sel.value !== 'otro'); if (sel.value === 'otro') input.focus(); });
    });
</script>
@endpush
