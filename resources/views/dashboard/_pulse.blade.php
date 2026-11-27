{{--
    Paneles de "pulso": Cuaderno Maestro, próximos días, cumplimiento por área, ubicación,
    encargados sin movimiento, comunidades olvidadas y compromisos de minutas.
    Todo se calcula solo con datos que ya existen.
--}}
@php
    $semDot = ['rojo' => 'bg-rose-500', 'amarillo' => 'bg-amber-400', 'verde' => 'bg-emerald-500', 'gris' => 'bg-slate-300'];
    $pctTone = fn (?float $v, int $goal) => $v === null ? 'text-slate-400' : ($v >= $goal ? 'text-emerald-700' : ($v >= $goal - config('auditoria.margen_ambar') ? 'text-amber-700' : 'text-rose-700'));
    $goalCump = (int) config('auditoria.metas.cumplimiento');
    $goalTiempo = (int) config('auditoria.metas.a_tiempo');
    $num = fn ($v) => number_format((float) $v);
@endphp

{{-- 1. Cuaderno Maestro + próximos días --}}
<div class="mt-6 grid gap-6 lg:grid-cols-3">
    @if ($cuaderno)
        <section class="card overflow-hidden" aria-labelledby="pulso-cuaderno">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h2 id="pulso-cuaderno" class="font-semibold">Cuaderno Maestro</h2>
                <a href="{{ route('campaigns.show', $cuaderno->campaign) }}" class="text-xs font-medium text-brand-600 hover:underline">Abrir</a>
            </div>
            <div class="px-5 py-4">
                <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Semáforo de las hojas</p>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    @foreach (['rojo' => 'en rojo', 'amarillo' => 'en amarillo', 'verde' => 'al día', 'gris' => 'sin información'] as $c => $label)
                        <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full {{ $semDot[$c] }}" aria-hidden="true"></span><b class="tabular-nums">{{ $cuaderno->resumen[$c] }}</b> {{ $label }}</span>
                    @endforeach
                </div>
                @if ($cuaderno->alertas->isNotEmpty())
                    <ul class="mt-3 space-y-1.5">
                        @foreach ($cuaderno->alertas->take(6) as $b)
                            <li>
                                <a href="{{ $b->url }}" class="flex items-start gap-2 rounded-lg px-2 py-1 text-sm hover:bg-slate-50">
                                    <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $semDot[$b->color] }}" aria-hidden="true"></span>
                                    <span class="min-w-0"><span class="font-medium">{{ $b->num }}. {{ $b->name }}</span><span class="block text-xs text-slate-500">{{ $b->motivo }}</span></span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-3 text-sm text-emerald-700">Ninguna hoja en rojo ni en amarillo.</p>
                @endif
            </div>
            @if ($cuaderno->summary || $cuaderno->dias_cierre !== null)
                <dl class="grid grid-cols-2 border-t border-slate-200 text-sm">
                    @if ($cuaderno->summary)
                        @php $s = $cuaderno->summary; @endphp
                        <div class="px-5 py-3">
                            <dt class="text-xs text-slate-500">Votos identificados</dt>
                            <dd class="font-semibold tabular-nums">{{ $num($s->votantes) }} <span class="font-normal text-slate-400">/ {{ $num(round($s->meta)) }}</span></dd>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-600" style="width: {{ min(100, round($s->avance * 100, 1)) }}%"></div></div>
                        </div>
                        <div class="px-5 py-3">
                            <dt class="text-xs text-slate-500">Promotores</dt>
                            <dd class="font-semibold tabular-nums">{{ $num($s->promotores) }} <span class="font-normal text-slate-400">/ {{ $num(round($s->necesarios)) }}</span></dd>
                        </div>
                    @endif
                    @if ($cuaderno->dias_cierre !== null)
                        <div class="col-span-2 border-t border-slate-100 px-5 py-3">
                            <dt class="text-xs text-slate-500">Fecha límite de cierre (2 jun 2027)</dt>
                            <dd class="font-semibold tabular-nums">Faltan {{ $num($cuaderno->dias_cierre) }} días</dd>
                        </div>
                    @endif
                </dl>
            @endif
        </section>
    @endif

    <section @class(['card overflow-hidden', 'lg:col-span-2' => (bool) $cuaderno, 'lg:col-span-3' => ! $cuaderno]) aria-labelledby="pulso-proximos">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 id="pulso-proximos" class="font-semibold">Próximos {{ $upcoming->days }} días</h2>
            <a href="{{ route('calendar.index') }}" class="text-xs font-medium text-brand-600 hover:underline">Calendario</a>
        </div>
        <div class="grid divide-y divide-slate-100 md:grid-cols-2 md:divide-x md:divide-y-0">
            <div class="px-5 py-4">
                <h3 class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Actividades que vencen <span class="badge ml-1 bg-slate-100 text-slate-600">{{ $upcoming->actividades->count() }}</span></h3>
                <ul class="mt-2 space-y-2 text-sm">
                    @forelse ($upcoming->actividades->take(8) as $a)
                        <li class="flex items-start justify-between gap-3">
                            <a href="{{ route('activities.show', $a) }}" class="min-w-0 hover:underline">
                                <span class="font-medium">{{ $a->code }}</span> {{ $a->type?->name }}
                                <span class="block truncate text-xs text-slate-500">{{ $a->assignee?->folioLabel() ? $a->assignee->folioLabel().' · ' : '' }}{{ $a->assignee?->name }}</span>
                            </a>
                            <span @class(['shrink-0 text-xs font-medium tabular-nums', 'text-rose-700' => $a->end_date->isToday(), 'text-amber-700' => ! $a->end_date->isToday()])>{{ $a->end_date->isToday() ? 'Hoy' : $a->end_date->translatedFormat('D j') }}</span>
                        </li>
                    @empty
                        <li class="text-slate-500">Nada vence esta semana.</li>
                    @endforelse
                </ul>
                @if ($upcoming->actividades->count() > 8)
                    <p class="mt-2 text-xs text-slate-500">y {{ $upcoming->actividades->count() - 8 }} más</p>
                @endif
            </div>
            <div class="space-y-4 px-5 py-4">
                @if ($cuaderno)
                    <div>
                        <h3 class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Eventos de campaña <span class="badge ml-1 bg-slate-100 text-slate-600">{{ $upcoming->eventos->count() }}</span></h3>
                        <ul class="mt-2 space-y-2 text-sm">
                            @forelse ($upcoming->eventos->take(5) as $r)
                                <li class="flex items-start justify-between gap-3">
                                    <a href="{{ route('campaigns.strategy.edit', [$cuaderno->campaign, $r->libro, $r]) }}" class="min-w-0 hover:underline">
                                        <span class="font-medium">{{ $r->title() }}</span>
                                        <span class="block truncate text-xs text-slate-500">{{ \App\Services\StrategyBooks::names()[$r->libro] ?? '' }}{{ $r->community ? ' · '.$r->community->name : '' }}</span>
                                    </a>
                                    <span class="shrink-0 text-xs font-medium tabular-nums text-slate-600">{{ $r->fecha->isToday() ? 'Hoy' : $r->fecha->translatedFormat('D j') }}</span>
                                </li>
                            @empty
                                <li class="text-slate-500">Sin eventos programados.</li>
                            @endforelse
                        </ul>
                        @if ($upcoming->ieeq > 0)
                            <a href="{{ route('campaigns.agenda.index', $cuaderno->campaign) }}" class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800 hover:underline">{{ $upcoming->ieeq }} {{ $upcoming->ieeq === 1 ? 'evento por reportar' : 'eventos por reportar' }} al IEEQ</a>
                        @endif
                    </div>
                @endif
                @if (auth()->user()->seesEverything())
                    <div>
                        <h3 class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Agenda del Presidente <span class="badge ml-1 bg-slate-100 text-slate-600">{{ $upcoming->presidente->count() }}</span></h3>
                        <ul class="mt-2 space-y-2 text-sm">
                            @forelse ($upcoming->presidente->take(5) as $p)
                                <li class="flex items-start justify-between gap-3">
                                    <a href="{{ route('president.show', $p) }}" class="min-w-0 hover:underline">
                                        <span class="font-medium">{{ $p->folio }}</span> {{ $p->type?->name }}
                                        @if ($p->community)<span class="block truncate text-xs text-slate-500">{{ $p->community->name }}</span>@endif
                                    </a>
                                    <span class="shrink-0 text-xs font-medium tabular-nums text-slate-600">{{ $p->start_date?->lte(today()) ? 'En curso' : $p->start_date?->translatedFormat('D j') }}</span>
                                </li>
                            @empty
                                <li class="text-slate-500">Sin compromisos esta semana.</li>
                            @endforelse
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </section>
</div>

{{-- 2. Cumplimiento por área + control de ubicación --}}
<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <section class="card overflow-hidden lg:col-span-2" aria-labelledby="pulso-areas">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 id="pulso-areas" class="font-semibold">Cumplimiento por área</h2>
            <p class="text-xs text-slate-500">Lo que encomendó cada área a los encargados en el periodo, y lo que ellos mismos se cargaron.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm [&_td]:px-3 [&_th]:px-3">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="table-head whitespace-nowrap">Encomendó</th>
                        <th class="table-head text-right">Total</th>
                        <th class="table-head text-right" title="Concluidas">Concl.</th>
                        <th class="table-head text-right" title="Cumplimiento">Cumpl.</th>
                        <th class="table-head whitespace-nowrap text-right">A tiempo</th>
                        <th class="table-head text-right">Vencidas</th>
                        <th class="table-head text-right" title="No completadas">✗</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($byArea as $r)
                        <tr>
                            <td class="table-cell min-w-40 font-medium">
                                @if (in_array($r->label, $areas, true))
                                    <a href="{{ route('dashboard', ['period' => $period, 'area' => $r->label]) }}" class="hover:underline">{{ $r->label }}</a>
                                @else
                                    {{ $r->label }}
                                @endif
                            </td>
                            <td class="table-cell text-right tabular-nums">{{ $r->total }}</td>
                            <td class="table-cell text-right tabular-nums">{{ $r->completed }}</td>
                            <td class="table-cell text-right font-semibold tabular-nums {{ $pctTone($r->cumplimiento, $goalCump) }}">{{ $fmt($r->cumplimiento) }}</td>
                            <td class="table-cell text-right tabular-nums {{ $pctTone($r->a_tiempo, $goalTiempo) }}">{{ $fmt($r->a_tiempo) }}</td>
                            <td @class(['table-cell text-right tabular-nums', 'font-semibold text-rose-700' => $r->overdue > 0])>{{ $r->overdue }}</td>
                            <td @class(['table-cell text-right tabular-nums', 'text-rose-700' => $r->incompletas > 0])>{{ $r->incompletas }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-6 text-center text-slate-500">Sin actividades en el periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card p-5" aria-labelledby="pulso-ubicacion">
        <div class="flex items-center justify-between">
            <h2 id="pulso-ubicacion" class="font-semibold">Control de ubicación</h2>
            @if ($location['fuera'] + $location['pendiente'] > 0)
                <a href="{{ route('activities.index', ['ubicacion' => 'alerta']) }}" class="text-xs font-medium text-brand-600 hover:underline">Ver alertas</a>
            @endif
        </div>
        @if ($location['total'] === 0)
            <p class="mt-3 text-sm text-slate-500">Ninguna actividad del periodo exigía acudir a un lugar.</p>
        @else
            <p class="mt-3 font-serif text-4xl font-semibold tabular-nums {{ $pctTone($location['pct_comprobadas'], 90) }}">{{ $fmt($location['pct_comprobadas']) }}</p>
            <p class="text-xs text-slate-500">de {{ $location['total'] }} {{ $location['total'] === 1 ? 'actividad con ubicación está comprobada' : 'actividades con ubicación están comprobadas' }}</p>
            @php $w = fn ($n) => round($n / $location['total'] * 100, 2); @endphp
            <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                <div class="bg-emerald-500" style="width: {{ $w($location['verificada']) }}%"></div>
                <div class="bg-sky-500" style="width: {{ $w($location['manual']) }}%"></div>
                <div class="bg-rose-500" style="width: {{ $w($location['fuera']) }}%"></div>
                <div class="bg-slate-300" style="width: {{ $w($location['pendiente']) }}%"></div>
            </div>
            <ul class="mt-3 space-y-1 text-sm">
                <li class="flex justify-between"><span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full bg-emerald-500"></span>Verificadas con GPS</span><b class="tabular-nums">{{ $location['verificada'] }}</b></li>
                <li class="flex justify-between"><span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full bg-sky-500"></span>Validadas a mano</span><b class="tabular-nums">{{ $location['manual'] }}</b></li>
                <li class="flex justify-between"><span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full bg-rose-500"></span>Fuera del radio</span><b @class(['tabular-nums', 'text-rose-700' => $location['fuera'] > 0])>{{ $location['fuera'] }}</b></li>
                <li class="flex justify-between"><span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full bg-slate-300"></span>Sin validar</span><b @class(['tabular-nums', 'text-rose-700' => $location['pendiente'] > 0])>{{ $location['pendiente'] }}</b></li>
            </ul>
        @endif
    </section>
</div>

{{-- 3. Quién no se mueve, qué comunidades nadie atiende y compromisos vencidos --}}
<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <section class="card overflow-hidden" aria-labelledby="pulso-fantasmas">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 id="pulso-fantasmas" class="font-semibold">Encargados sin movimiento</h2>
            <span @class(['badge', 'bg-rose-100 text-rose-800' => $ghosts->isNotEmpty(), 'bg-emerald-100 text-emerald-800' => $ghosts->isEmpty()])>{{ $ghosts->count() }}</span>
        </div>
        <p class="px-5 pt-3 text-xs text-slate-500">{{ config('auditoria.alertas.dias_sin_movimiento', 5) }} días o más sin cargar, concluir ni checar.</p>
        <ul class="divide-y divide-slate-100 px-5 pb-3 text-sm">
            @forelse ($ghosts->take(8) as $g)
                <li class="flex items-center justify-between gap-3 py-2">
                    <a href="{{ route('dashboard', ['period' => $period, 'encargado' => $g->user->id]) }}" class="min-w-0 truncate hover:underline">
                        @if ($g->user->folioLabel())<span class="text-slate-500">{{ $g->user->folioLabel() }} ·</span>@endif {{ $g->user->name }}
                    </a>
                    <span @class(['shrink-0 text-xs font-semibold tabular-nums', 'text-rose-700' => $g->dias >= 2 * config('auditoria.alertas.dias_sin_movimiento', 5), 'text-amber-700' => $g->dias < 2 * config('auditoria.alertas.dias_sin_movimiento', 5)])>
                        {{ $g->nunca ? 'Nunca ('.$g->dias.' d)' : 'Hace '.$g->dias.' días' }}
                    </span>
                </li>
            @empty
                <li class="py-3 text-emerald-700">Todos tuvieron movimiento reciente.</li>
            @endforelse
        </ul>
    </section>

    <section class="card overflow-hidden" aria-labelledby="pulso-comunidades">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 id="pulso-comunidades" class="font-semibold">Comunidades olvidadas</h2>
            <span @class(['badge', 'bg-rose-100 text-rose-800' => $forgotten->olvidadas->isNotEmpty(), 'bg-emerald-100 text-emerald-800' => $forgotten->olvidadas->isEmpty()])>{{ $forgotten->olvidadas->count() }} de {{ $forgotten->total }}</span>
        </div>
        <p class="px-5 pt-3 text-xs text-slate-500">Sin actividades ni registros del Cuaderno en {{ $forgotten->dias }} días o más.</p>
        <ul class="divide-y divide-slate-100 px-5 pb-3 text-sm">
            @forelse ($forgotten->olvidadas->take(8) as $o)
                <li class="flex items-center justify-between gap-3 py-2">
                    <span class="min-w-0 truncate">{{ $o->community->name }}</span>
                    <span class="shrink-0 text-xs font-semibold tabular-nums {{ $o->dias === null ? 'text-rose-700' : 'text-amber-700' }}">{{ $o->dias === null ? 'Nunca' : 'Hace '.$o->dias.' días' }}</span>
                </li>
            @empty
                <li class="py-3 text-emerald-700">Todas las comunidades tienen trabajo reciente.</li>
            @endforelse
        </ul>
        @if ($forgotten->olvidadas->count() > 8)
            <p class="px-5 pb-3 text-xs text-slate-500">y {{ $forgotten->olvidadas->count() - 8 }} más: {{ $forgotten->olvidadas->slice(8)->take(10)->map(fn ($o) => $o->community->name)->join(', ') }}{{ $forgotten->olvidadas->count() > 18 ? '…' : '' }}</p>
        @endif
    </section>

    @if ($commitments !== null)
        <section class="card overflow-hidden" aria-labelledby="pulso-compromisos">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h2 id="pulso-compromisos" class="font-semibold">Compromisos de minutas vencidos</h2>
                <span @class(['badge', 'bg-rose-100 text-rose-800' => $commitments->isNotEmpty(), 'bg-emerald-100 text-emerald-800' => $commitments->isEmpty()])>{{ $commitments->count() }}</span>
            </div>
            <ul class="divide-y divide-slate-100 px-5 py-1 text-sm">
                @forelse ($commitments->take(6) as $c)
                    <li class="py-2">
                        <a href="{{ route('minutes.show', $c->minute_id) }}" class="block hover:underline">
                            <span class="line-clamp-2 font-medium">{{ $c->actividad }}</span>
                        </a>
                        <span class="block truncate text-xs text-slate-500">
                            {{ $c->responsable ?: 'Sin responsable' }} · venció {{ $c->fechaLimiteLabel() }}@if ($c->minute) · {{ $c->minute->titulo }}@endif
                        </span>
                    </li>
                @empty
                    <li class="py-3 text-emerald-700">No hay compromisos vencidos.</li>
                @endforelse
            </ul>
            @if ($commitments->count() > 6)
                <p class="px-5 pb-3 text-xs text-slate-500">y {{ $commitments->count() - 6 }} más</p>
            @endif
        </section>
    @endif
</div>
