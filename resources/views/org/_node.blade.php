{{-- Un nodo del organigrama y, debajo, quienes le reportan (recursivo) --}}
<li>
    <a href="#p{{ $m->id }}" @class([
        'org-card inline-block w-48 rounded-xl border bg-white px-3 py-2.5 text-left shadow-sm transition hover:border-brand-500',
        'border-rose-400 ring-2 ring-rose-200' => $m->servidor_publico,
        'border-slate-200' => ! $m->servidor_publico,
    ])>
        <span class="block text-[11px] font-semibold tracking-wide text-brand-700 uppercase">{{ $m->puesto }}</span>
        <span class="block text-sm leading-tight font-semibold text-slate-900">{{ $m->nombre }}</span>
        @if ($m->team || $m->delegacion || $m->section)
            <span class="mt-0.5 block truncate text-[11px] text-slate-500">{{ collect([$m->team?->name, $m->delegacion, $m->section ? 'Secc. '.$m->section->clave : null])->filter()->join(' · ') }}</span>
        @endif
        @if ($m->servidor_publico)
            <span class="mt-1 inline-block rounded bg-rose-600 px-1.5 text-[10px] font-semibold text-white">⚠ Servidor público</span>
        @endif
    </a>
    @if (($children[$m->id] ?? collect())->isNotEmpty())
        <ul>
            @foreach ($children[$m->id] as $child)
                @include('org._node', ['m' => $child])
            @endforeach
        </ul>
    @endif
</li>
