@csrf
@error('duplicado')
    <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <p>{{ $message }}</p>
        <label class="mt-2 inline-flex items-center gap-2 font-medium">
            <input type="checkbox" name="confirmar_duplicado" value="1" class="size-4 rounded border-amber-400"> Es otra persona, registrarla de todos modos
        </label>
    </div>
@enderror
<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="nombre" class="label">Nombre completo *</label>
        <input id="nombre" name="nombre" value="{{ old('nombre', $voter->nombre) }}" required maxlength="150" class="input" autofocus>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label for="seccion" class="label">Sección</label>
            <input id="seccion" name="seccion" value="{{ old('seccion', $voter->seccion) }}" maxlength="10" inputmode="numeric" class="input" placeholder="0512">
        </div>
        <div>
            <label for="casilla" class="label">Casilla</label>
            <input id="casilla" name="casilla" value="{{ old('casilla', $voter->casilla) }}" maxlength="10" class="input" placeholder="B1">
        </div>
    </div>
    <div>
        <label for="telefono" class="label">Tel / WhatsApp</label>
        <input id="telefono" type="tel" name="telefono" value="{{ old('telefono', $voter->telefono) }}" maxlength="30" class="input">
    </div>
    <div class="sm:col-span-2">
        <label for="domicilio" class="label">Domicilio</label>
        <input id="domicilio" name="domicilio" value="{{ old('domicilio', $voter->domicilio) }}" maxlength="255" class="input">
    </div>
    <div>
        <label for="community_id" class="label">Comunidad</label>
        <select id="community_id" name="community_id" class="input" data-other="community_new">
            <option value="">Sin definir</option>
            @foreach ($communities as $community)
                <option value="{{ $community->id }}" @selected((string) old('community_id', $voter->community_id) === (string) $community->id)>{{ $community->name }}</option>
            @endforeach
            <option value="otro" @selected(old('community_id') === 'otro')>Otra (escribirla)…</option>
        </select>
        <input id="community_new" name="community_new" value="{{ old('community_new') }}" maxlength="120" placeholder="Nombre de la comunidad" aria-label="Nueva comunidad"
               @class(['input mt-2', 'hidden' => old('community_id') !== 'otro'])>
    </div>
    <div>
        <label for="promoter_id" class="label">Promotor responsable</label>
        <select id="promoter_id" name="promoter_id" class="input">
            <option value="">Sin promotor</option>
            @foreach ($promoters as $promoter)
                <option value="{{ $promoter->id }}" @selected((string) old('promoter_id', $voter->promoter_id) === (string) $promoter->id)>{{ $promoter->name }}</option>
            @endforeach
        </select>
        @if ($promoters->isEmpty())
            <p class="mt-1 text-xs text-slate-500">Registra promotores en la pestaña «Promotores» para asignarlos.</p>
        @endif
    </div>
    <fieldset>
        <legend class="label">Afinidad</legend>
        <div class="flex flex-wrap gap-2">
            @foreach (['' => 'Sin definir'] + array_combine(\App\Models\CampaignVoter::AFINIDADES, \App\Models\CampaignVoter::AFINIDADES) as $value => $label)
                <label class="cursor-pointer">
                    <input type="radio" name="afinidad" value="{{ $value }}" class="peer sr-only" @checked((string) old('afinidad', $voter->afinidad) === (string) $value)>
                    <span class="block rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-600 peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:font-medium peer-checked:text-brand-700">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>
    <fieldset>
        <legend class="label">¿Recibe apoyo gob?</legend>
        @php $apoyo = old('recibe_apoyo', $voter->recibe_apoyo === null ? '' : (int) $voter->recibe_apoyo); @endphp
        <div class="flex flex-wrap gap-2">
            @foreach (['' => 'Sin dato', '1' => 'Sí', '0' => 'No'] as $value => $label)
                <label class="cursor-pointer">
                    <input type="radio" name="recibe_apoyo" value="{{ $value }}" class="peer sr-only" @checked((string) $apoyo === (string) $value)>
                    <span class="block rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-600 peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:font-medium peer-checked:text-brand-700">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>
    <div class="sm:col-span-2">
        <label for="notas" class="label">Notas</label>
        <textarea id="notas" name="notas" rows="2" class="input">{{ old('notas', $voter->notas) }}</textarea>
    </div>
</div>
