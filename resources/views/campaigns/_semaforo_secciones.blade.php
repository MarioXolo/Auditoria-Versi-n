{{--
    Semáforo de votos por sección (dentro de «Meta de votos»). La meta de cada sección sale sola:
    la meta total con colchón repartida según la lista nominal oficial de la sección.
--}}
@php
    $t = $porSeccion['totals'];
    $dot = ['verde' => 'bg-emerald-500', 'amarillo' => 'bg-amber-400', 'rojo' => 'bg-rose-500', 'gris' => 'bg-slate-300'];
    $txt = ['verde' => 'text-emerald-700', 'amarillo' => 'text-amber-700', 'rojo' => 'text-rose-700', 'gris' => 'text-slate-400'];
    $n = fn ($v) => number_format((float) $v);
    $pct = fn (?float $v) => $v === null ? '—' : number_format($v * 100, 1).' %';
@endphp
<section class="card mt-8 overflow-hidden" id="semaforo-secciones" aria-labelledby="semaforo-secciones-titulo">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
        <div>
            <h2 id="semaforo-secciones-titulo" class="font-semibold">Semáforo del voto por sección</h2>
            <p class="text-xs text-slate-500">La meta de cada sección sale sola: la meta total con colchón repartida según su lista nominal. Abre una sección para ver sus comunidades.</p>
        </div>
        <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs">
            @foreach (['verde' => '80 % o más', 'amarillo' => '50 – 79 %', 'rojo' => 'menos de 50 %', 'gris' => 'sin lista nominal'] as $c => $label)
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full {{ $dot[$c] }}"></span><b class="tabular-nums">{{ $t->colores[$c] ?? 0 }}</b> {{ $label }}</span>
            @endforeach
        </div>
    </div>

    @if ($porSeccion['rows']->isEmpty())
        <p class="px-5 py-8 text-center text-sm text-slate-500">Aún no hay secciones. Se dan de alta en Catálogos → Secciones, con su lista nominal y sus comunidades.</p>
    @else
        <div class="hidden grid-cols-[1fr_7rem_6rem_7rem_9rem] gap-3 bg-slate-50 px-5 py-2 text-xs font-semibold tracking-wide text-slate-500 uppercase md:grid">
            <span>Sección</span><span class="text-right">Lista nominal</span><span class="text-right">Meta</span><span class="text-right">Identificados</span><span>Avance</span>
        </div>
        <ul class="divide-y divide-slate-100">
            @foreach ($porSeccion['rows'] as $r)
                <li data-semaforo="{{ $r->color }}">
                    <details class="group">
                        <summary class="grid cursor-pointer list-none grid-cols-2 items-center gap-x-3 gap-y-1 px-5 py-3 hover:bg-slate-50 md:grid-cols-[1fr_7rem_6rem_7rem_9rem] [&::-webkit-details-marker]:hidden">
                            <span class="col-span-2 flex min-w-0 items-start gap-2 md:col-span-1">
                                <span class="mt-1.5 size-3 shrink-0 rounded-full {{ $dot[$r->color] }}" aria-hidden="true"></span>
                                <span class="min-w-0">
                                    <span class="font-semibold">Sección {{ $r->section->clave }}</span>
                                    @if ($r->section->representante)<span class="text-sm text-slate-500"> · {{ $r->section->representante }}</span>@endif
                                    <span class="block truncate text-xs text-slate-500">{{ $r->comunidades->pluck('name')->join(', ') ?: 'Sin comunidades asignadas' }}</span>
                                </span>
                            </span>
                            <span class="text-sm text-slate-500 tabular-nums md:text-right"><span class="md:hidden">LN </span>{{ $r->section->listado_nominal ? $n($r->section->listado_nominal) : 'Sin dato' }}</span>
                            <span class="text-right text-sm tabular-nums md:text-right"><span class="text-slate-500 md:hidden">Meta </span>{{ $r->meta !== null ? $n($r->meta) : '—' }}</span>
                            <span class="text-sm font-semibold tabular-nums md:text-right"><span class="font-normal text-slate-500 md:hidden">Identificados </span>{{ $n($r->identificados) }}</span>
                            <span class="flex items-center gap-2">
                                <span class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full {{ $dot[$r->color] }}" style="width: {{ $r->avance !== null ? min(100, round($r->avance * 100, 1)) : 0 }}%"></span></span>
                                <span class="w-14 text-right text-sm font-semibold tabular-nums {{ $txt[$r->color] }}">{{ $pct($r->avance) }}</span>
                            </span>
                        </summary>
                        <div class="bg-slate-50/70 px-5 pt-1 pb-4 md:pl-10">
                            @if ($r->comunidades->isEmpty())
                                <p class="text-sm text-slate-500">Asígnale sus comunidades en Catálogos → Secciones.</p>
                            @else
                                <ul class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($r->comunidades as $c)
                                        <li class="flex justify-between gap-3 border-b border-slate-200/70 py-1">
                                            <span class="min-w-0 truncate">{{ $c->name }}</span>
                                            <span @class(['tabular-nums', 'font-semibold' => $c->identificados > 0, 'text-slate-400' => $c->identificados === 0])>{{ $n($c->identificados) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </details>
                </li>
            @endforeach
        </ul>
        <div class="grid grid-cols-2 gap-x-3 gap-y-1 border-t border-slate-200 bg-slate-50 px-5 py-3 text-sm font-semibold md:grid-cols-[1fr_7rem_6rem_7rem_9rem]">
            <span class="col-span-2 md:col-span-1">Total municipio</span>
            <span class="tabular-nums md:text-right">{{ $n($t->listado) }}</span>
            <span class="text-right tabular-nums">{{ $n($t->meta) }}</span>
            <span class="tabular-nums md:text-right">{{ $n($t->identificados) }}</span>
            <span class="text-right tabular-nums md:text-left">{{ $pct($t->avance) }}</span>
        </div>
        @if ($t->sin_lista || $t->sin_seccion)
            <p class="border-t border-slate-200 px-5 py-2 text-xs text-slate-500">
                @if ($t->sin_lista){{ $t->sin_lista }} {{ $t->sin_lista === 1 ? 'sección no tiene' : 'secciones no tienen' }} lista nominal: captúrala en Catálogos para que reciba su meta.@endif
                @if ($t->sin_seccion) {{ $n($t->sin_seccion) }} {{ $t->sin_seccion === 1 ? 'votante identificado no tiene' : 'votantes identificados no tienen' }} sección y su comunidad cae en varias: no se cuentan en ninguna.@endif
            </p>
        @endif
    @endif
</section>
