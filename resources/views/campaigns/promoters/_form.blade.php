@csrf
<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="name" class="label">Nombre del promotor *</label>
        <input id="name" name="name" value="{{ old('name', $promoter->name) }}" required maxlength="150" class="input" autofocus>
    </div>
    <div>
        <label for="phone" class="label">Tel / WhatsApp</label>
        <input id="phone" type="tel" name="phone" value="{{ old('phone', $promoter->phone) }}" maxlength="30" class="input">
    </div>
    <div>
        <label for="community_id" class="label">Comunidad</label>
        <select id="community_id" name="community_id" class="input" data-other="community_new">
            <option value="">Sin definir</option>
            @foreach ($communities as $community)
                <option value="{{ $community->id }}" @selected((string) old('community_id', $promoter->community_id) === (string) $community->id)>{{ $community->name }}</option>
            @endforeach
            <option value="otro" @selected(old('community_id') === 'otro')>Otra (escribirla)…</option>
        </select>
        <input id="community_new" name="community_new" value="{{ old('community_new') }}" maxlength="120" placeholder="Nombre de la comunidad" aria-label="Nueva comunidad"
               @class(['input mt-2', 'hidden' => old('community_id') !== 'otro'])>
    </div>
    <div>
        <label for="secciones" class="label">Sección(es)</label>
        <input id="secciones" name="secciones" value="{{ old('secciones', $promoter->secciones) }}" maxlength="120" class="input" placeholder="0512, 0513">
    </div>
    <div>
        <label for="coordinador" class="label">Coordinador seccional</label>
        <input id="coordinador" name="coordinador" value="{{ old('coordinador', $promoter->coordinador) }}" maxlength="150" class="input">
    </div>
    <div>
        <label for="meta_votos" class="label">Meta (votos) *</label>
        <input id="meta_votos" type="number" min="0" name="meta_votos" value="{{ old('meta_votos', $promoter->meta_votos) }}" required class="input tabular-nums">
        <p class="mt-1 text-xs text-slate-500">Por defecto, los votantes por promotor de la campaña ({{ $campaign->votantes_por_promotor }}).</p>
    </div>
    <div class="flex items-center">
        <label class="inline-flex items-center gap-2 text-sm font-medium">
            <input type="checkbox" name="verificado" value="1" @checked(old('verificado', $promoter->verificado)) class="size-4 rounded border-slate-300">
            Promotor verificado
        </label>
    </div>
</div>
