@csrf
@php
    $pct = fn ($field) => old($field, isset($campaign->{$field}) ? rtrim(rtrim(number_format($campaign->{$field} * 100, 2, '.', ''), '0'), '.') : '');
@endphp
<div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
    <div class="space-y-6">
        <section class="card p-6">
            <h2 class="font-semibold">Datos de la campaña</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="label">Nombre de la campaña *</label>
                    <input id="name" name="name" value="{{ old('name', $campaign->name) }}" required maxlength="150" class="input" placeholder="Ej. Reelección Huimilpan 2027">
                </div>
                <div>
                    <label for="municipality" class="label">Municipio *</label>
                    <input id="municipality" name="municipality" value="{{ old('municipality', $campaign->municipality) }}" required maxlength="120" class="input">
                </div>
                <div>
                    <label for="election_date" class="label">Fecha de la elección</label>
                    <input id="election_date" type="date" name="election_date" value="{{ old('election_date', $campaign->election_date?->format('Y-m-d')) }}" class="input">
                </div>
            </div>
        </section>

        <section class="card p-6">
            <h2 class="font-semibold">Calculadora de meta de votos</h2>
            <p class="text-sm text-slate-500">Llena estos datos; la meta y los promotores necesarios se calculan solos.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2" data-calc>
                <div>
                    <label for="listado_nominal" class="label">Listado nominal del municipio *</label>
                    <input id="listado_nominal" type="number" min="0" name="listado_nominal" value="{{ old('listado_nominal', $campaign->listado_nominal) }}" required class="input tabular-nums">
                    <p class="mt-1 text-xs text-slate-500">Dato oficial del instituto electoral local.</p>
                </div>
                <div>
                    <label for="participacion" class="label">Participación estimada (%) *</label>
                    <input id="participacion" type="number" step="0.1" min="0" max="100" name="participacion" value="{{ $pct('participacion') }}" required class="input tabular-nums">
                    <p class="mt-1 text-xs text-slate-500">Histórico típico: 55 a 65 %.</p>
                </div>
                <div>
                    <label for="objetivo" class="label">% de la votación que buscas ganar *</label>
                    <input id="objetivo" type="number" step="0.1" min="0" max="100" name="objetivo" value="{{ $pct('objetivo') }}" required class="input tabular-nums">
                </div>
                <div>
                    <label for="colchon" class="label">Colchón de seguridad (%) *</label>
                    <input id="colchon" type="number" step="0.1" min="0" max="100" name="colchon" value="{{ $pct('colchon') }}" required class="input tabular-nums">
                    <p class="mt-1 text-xs text-slate-500">Margen extra recomendado: 10 %.</p>
                </div>
                <div>
                    <label for="votantes_por_promotor" class="label">Votantes por promotor *</label>
                    <input id="votantes_por_promotor" type="number" min="1" name="votantes_por_promotor" value="{{ old('votantes_por_promotor', $campaign->votantes_por_promotor) }}" required class="input tabular-nums">
                    <p class="mt-1 text-xs text-slate-500">Sistema 1×N: 10 = cada promotor moviliza 10 votos.</p>
                </div>
            </div>
        </section>

        <section class="card p-6">
            <label for="notes" class="label">Notas</label>
            <textarea id="notes" name="notes" rows="3" class="input" placeholder="Ej. Meta provisional; actualizar cuando llegue el listado nominal real por sección.">{{ old('notes', $campaign->notes) }}</textarea>
        </section>
    </div>

    {{-- Resultado en vivo --}}
    <aside class="lg:sticky lg:top-6 lg:self-start">
        <div class="card overflow-hidden">
            <div class="bg-ink px-5 py-4 text-white">
                <p class="text-xs font-semibold tracking-wide text-white/60 uppercase">Meta de votos con colchón</p>
                <p class="mt-1 font-serif text-4xl font-semibold tabular-nums" data-out="metaColchon">—</p>
                <p class="text-xs text-white/60">El número que persigue toda la campaña</p>
            </div>
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex justify-between px-5 py-2.5"><dt class="text-slate-500">Votos totales esperados</dt><dd class="font-medium tabular-nums" data-out="esperados">—</dd></div>
                <div class="flex justify-between px-5 py-2.5"><dt class="text-slate-500">Meta de votos (base)</dt><dd class="font-medium tabular-nums" data-out="base">—</dd></div>
                <div class="flex justify-between bg-brand-50 px-5 py-2.5"><dt class="font-medium text-brand-700">Promotores necesarios</dt><dd class="font-semibold text-brand-700 tabular-nums" data-out="promotores">—</dd></div>
            </dl>
        </div>
    </aside>
</div>

@push('scripts')
<script>
    (() => {
        const $ = (id) => parseFloat(document.getElementById(id)?.value) || 0;
        const fmt = (n) => Math.round(n).toLocaleString('es-MX');
        const out = (k, v) => { const el = document.querySelector(`[data-out="${k}"]`); if (el) el.textContent = v; };
        const calc = () => {
            const esperados = $('listado_nominal') * $('participacion') / 100;
            const base = esperados * $('objetivo') / 100;
            const colchon = base * (1 + $('colchon') / 100);
            const vpp = $('votantes_por_promotor');
            out('esperados', fmt(esperados));
            out('base', fmt(base));
            out('metaColchon', fmt(colchon));
            out('promotores', vpp > 0 ? Math.ceil(Math.round(colchon / vpp * 1e6) / 1e6).toLocaleString('es-MX') : '0');
        };
        document.querySelector('[data-calc]')?.addEventListener('input', calc);
        calc();
    })();
</script>
@endpush
