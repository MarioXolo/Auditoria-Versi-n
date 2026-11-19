{{-- Duración de las actividades concluidas: $d (de App\Services\ActivityDurations::summarize) y $showPerson --}}
@php
    $fmtDias = fn ($v) => $v === null ? '—' : number_format($v, 1).($v == 1 ? ' día' : ' días');
    $bar = fn ($n) => $d['total'] > 0 ? round($n / max(1, $d['antes'] + $d['en_fecha'] + $d['despues']) * 100, 1) : 0;
@endphp
<section class="card mt-6 overflow-hidden" aria-labelledby="duracion">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 id="duracion" class="font-semibold">Duración de las actividades</h2>
        <p class="text-xs text-slate-500">De la fecha de inicio al día en que se marcó «Concluido», y si terminó antes o después de su fecha de fin. {{ $d['total'] }} concluidas en el periodo.</p>
    </div>

    <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Duración promedio</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums">{{ $fmtDias($d['duracion']) }}</p>
            <p class="text-xs text-slate-500">planeado: {{ $fmtDias($d['planeado']) }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold tracking-wide text-emerald-700 uppercase">Antes de la fecha</p>
            <p class="mt-1 text-3xl font-semibold text-emerald-700 tabular-nums">{{ $d['antes'] }}</p>
            <p class="text-xs text-slate-500">{{ $d['antes_pct'] === null ? '—' : $d['antes_pct'].' %' }} · {{ $d['dias_adelanto'] === null ? 'sin adelanto' : $fmtDias($d['dias_adelanto']).' de adelanto' }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold tracking-wide text-sky-700 uppercase">En la fecha</p>
            <p class="mt-1 text-3xl font-semibold text-sky-700 tabular-nums">{{ $d['en_fecha'] }}</p>
            <p class="text-xs text-slate-500">{{ $d['en_fecha_pct'] === null ? '—' : $d['en_fecha_pct'].' %' }} · terminaron justo el día límite</p>
        </div>
        <div>
            <p class="text-xs font-semibold tracking-wide text-rose-700 uppercase">Después de la fecha</p>
            <p class="mt-1 text-3xl font-semibold text-rose-700 tabular-nums">{{ $d['despues'] }}</p>
            <p class="text-xs text-slate-500">{{ $d['despues_pct'] === null ? '—' : $d['despues_pct'].' %' }} · {{ $d['dias_retraso'] === null ? 'sin retraso' : $fmtDias($d['dias_retraso']).' de retraso' }}</p>
        </div>
    </div>

    @if ($d['antes'] + $d['en_fecha'] + $d['despues'] > 0)
        <div class="px-5 pb-5">
            <div class="flex h-3 overflow-hidden rounded-full bg-slate-100" role="img" aria-label="Antes {{ $d['antes'] }}, en la fecha {{ $d['en_fecha'] }}, después {{ $d['despues'] }}">
                <div class="bg-emerald-600" style="width: {{ $bar($d['antes']) }}%"></div>
                <div class="bg-sky-500" style="width: {{ $bar($d['en_fecha']) }}%"></div>
                <div class="bg-rose-500" style="width: {{ $bar($d['despues']) }}%"></div>
            </div>
            <p class="mt-1 text-xs text-slate-500">{{ $d['a_tiempo_pct'] }} % terminaron a tiempo (antes o en la fecha).</p>
        </div>
    @endif

    <div class="overflow-x-auto border-t border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="table-head">Actividad</th>
                    @if ($showPerson)<th class="table-head">Encargado</th>@endif
                    <th class="table-head">Inicio</th>
                    <th class="table-head">Fecha de fin</th>
                    <th class="table-head">Terminó</th>
                    <th class="table-head text-right">Duró</th>
                    <th class="table-head">Resultado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($d['rows'] as $a)
                    @php $r = $a->deadlineResult(); @endphp
                    <tr>
                        <td class="table-cell"><a href="{{ route('activities.show', $a) }}" class="hover:text-brand-600"><span class="font-mono text-xs text-slate-500">{{ $a->code }}</span> {{ $a->type?->name }}</a></td>
                        @if ($showPerson)<td class="table-cell whitespace-nowrap">{{ $a->assignee?->name }}</td>@endif
                        <td class="table-cell whitespace-nowrap tabular-nums">{{ $a->start_date?->format('d/m/Y') }}</td>
                        <td class="table-cell whitespace-nowrap tabular-nums">{{ $a->end_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="table-cell whitespace-nowrap tabular-nums">{{ $a->finishedOn()?->format('d/m/Y') }}</td>
                        <td class="table-cell text-right whitespace-nowrap tabular-nums">{{ $a->durationDays() }} {{ $a->durationDays() === 1 ? 'día' : 'días' }}</td>
                        <td class="table-cell whitespace-nowrap">
                            @if ($r)
                                <span @class(['badge', 'bg-emerald-100 text-emerald-800' => $r === 'antes', 'bg-sky-100 text-sky-800' => $r === 'a_tiempo', 'bg-rose-100 text-rose-800' => $r === 'despues'])>{{ $a->deadlineLabel() }}</span>
                            @else <span class="text-slate-400">Sin fecha de fin</span> @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $showPerson ? 7 : 6 }}" class="table-cell py-6 text-center text-slate-500">Aún no hay actividades concluidas en el periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
