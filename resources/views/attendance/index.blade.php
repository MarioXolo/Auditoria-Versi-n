@extends('layouts.app')

@section('title', 'Asistencia')

@section('header')
    <div>
        <h1 class="text-2xl font-semibold">Asistencia</h1>
        <p class="text-sm text-slate-500">{{ now()->translatedFormat('l j \d\e F \d\e Y') }}</p>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Checador: un solo botón que registra la entrada y después la salida --}}
        @php
            $workday = \App\Models\Attendance::isWorkday();
            $limite = today()->setTimeFromTimeString(config('auditoria.hora_entrada'))->addMinutes(config('auditoria.tolerancia_minutos'));
            $retardo = $today?->check_in_at && $today->check_in_at->gt($limite);
            $estado = match (true) {
                ! $workday && ! $today => 'descanso',
                ! $today => 'entrada',
                ! $today->check_out_at => $workday ? 'salida' : 'descanso',
                default => 'listo',
            };
        @endphp
        <section class="card p-6" data-checador>
            <div class="flex items-start justify-between gap-3">
                <h2 class="font-semibold">Checador</h2>
                <span class="text-xs text-slate-500">{{ ucfirst(\App\Models\Attendance::workdaysLabel()) }}</span>
            </div>
            <p class="mt-3 text-center text-5xl font-semibold tracking-tight tabular-nums" data-reloj>{{ now()->format('H:i') }}</p>
            <p class="text-center text-sm text-slate-500">{{ ucfirst(now()->translatedFormat('l j \d\e F')) }}</p>

            {{-- Los dos pasos del día --}}
            <ol class="mt-5 grid grid-cols-2 gap-3 text-sm">
                <li @class(['rounded-xl border px-3 py-2.5', 'border-emerald-200 bg-emerald-50' => $today?->check_in_at, 'border-slate-200' => ! $today?->check_in_at])>
                    <span class="block text-xs text-slate-500">Entrada</span>
                    <span class="text-lg font-semibold tabular-nums">{{ $today?->check_in_at?->format('H:i') ?? '—' }}</span>
                    @if ($retardo)<span class="badge ml-1 bg-amber-100 text-amber-800">Retardo</span>@endif
                </li>
                <li @class(['rounded-xl border px-3 py-2.5', 'border-emerald-200 bg-emerald-50' => $today?->check_out_at, 'border-slate-200' => ! $today?->check_out_at])>
                    <span class="block text-xs text-slate-500">Salida</span>
                    <span class="text-lg font-semibold tabular-nums">{{ $today?->check_out_at?->format('H:i') ?? '—' }}</span>
                </li>
            </ol>

            @error('asistencia')<p class="mt-4 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $message }}</p>@enderror

            @if ($estado === 'entrada' || $estado === 'salida')
                <form method="POST" action="{{ route('attendance.register') }}" class="mt-5" onsubmit="this.querySelector('button').disabled = true">
                    @csrf
                    <button @class([
                        'w-full rounded-2xl px-4 py-4 text-lg font-semibold text-white shadow-sm transition active:scale-[0.98] disabled:opacity-60',
                        'bg-emerald-600 hover:bg-emerald-700' => $estado === 'entrada',
                        'bg-brand-600 hover:bg-brand-700' => $estado === 'salida',
                    ])>
                        {{ $estado === 'entrada' ? 'Registrar entrada' : 'Registrar salida' }}
                    </button>
                    <details class="mt-3 text-sm">
                        <summary class="cursor-pointer text-slate-500">Agregar comentario</summary>
                        <input name="notes" maxlength="500" class="input mt-2" placeholder="{{ $estado === 'entrada' ? 'Ej. Inicio en zona centro' : 'Ej. Termino en campo' }}">
                    </details>
                    <p class="mt-3 text-center text-xs text-slate-500">
                        @if ($estado === 'entrada')
                            Entrada a las {{ config('auditoria.hora_entrada') }} con {{ config('auditoria.tolerancia_minutos') }} min de tolerancia.
                        @else
                            Llevas {{ intdiv((int) $today->check_in_at->diffInMinutes(now()), 60) }}h {{ (int) $today->check_in_at->diffInMinutes(now()) % 60 }}m de jornada.
                        @endif
                    </p>
                </form>
            @elseif ($estado === 'listo')
                <p class="mt-5 rounded-2xl bg-emerald-50 px-4 py-4 text-center font-medium text-emerald-800">Jornada completa · {{ $today->workedTime() }}</p>
            @else
                <p class="mt-5 rounded-2xl bg-slate-100 px-4 py-4 text-center text-sm text-slate-600">Hoy no es día laboral. El checador registra de {{ \App\Models\Attendance::workdaysLabel() }}.</p>
            @endif
        </section>

        {{-- Historial propio --}}
        <section class="card overflow-hidden lg:col-span-2">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold">Mis últimos registros</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="table-head">Fecha</th>
                            <th class="table-head">Entrada</th>
                            <th class="table-head">Salida</th>
                            <th class="table-head">Tiempo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($history as $row)
                            <tr>
                                <td class="table-cell">{{ $row->work_date->translatedFormat('D j M') }}</td>
                                <td class="table-cell tabular-nums">{{ $row->check_in_at?->format('H:i') ?? '—' }}</td>
                                <td class="table-cell tabular-nums">{{ $row->check_out_at?->format('H:i') ?? '—' }}</td>
                                <td class="table-cell">{{ $row->workedTime() ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="table-cell py-6 text-center text-slate-500">Aún no tienes registros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {{-- Asistencia del personal (encargados, gerentes y administradores) --}}
    @if ($roster !== null)
        @php
            $present = $roster->filter(fn ($r) => $r['record'])->count();
            $hasPhotos = $roster->contains(fn ($r) => $r['record']?->check_in_photo || $r['record']?->check_out_photo);
        @endphp
        <section class="card mt-6 overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-semibold">Asistencia del personal</h2>
                    <p class="text-xs text-slate-500">{{ $present }} de {{ $roster->count() }} con entrada registrada</p>
                </div>
                <form method="GET" class="flex gap-2">
                    <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}" class="input w-auto" onchange="this.form.submit()">
                    <noscript><button class="btn btn-secondary">Ver</button></noscript>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="table-head">Persona</th>
                            <th class="table-head">Entrada</th>
                            <th class="table-head">Salida</th>
                            <th class="table-head">Tiempo</th>
                            @if ($hasPhotos)<th class="table-head">Fotos</th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($roster as $row)
                            @php $record = $row['record']; @endphp
                            <tr @class(['bg-rose-50/50' => ! $record])>
                                <td class="table-cell">
                                    <span class="font-medium text-slate-900">{{ $row['user']->name }}</span>
                                    <span class="block text-xs text-slate-500">{{ $row['user']->role->label() }}</span>
                                </td>
                                @if ($record)
                                    <td class="table-cell tabular-nums">{{ $record->check_in_at?->format('H:i') ?? '—' }}</td>
                                    <td class="table-cell tabular-nums">{{ $record->check_out_at?->format('H:i') ?? '—' }}</td>
                                    <td class="table-cell">{{ $record->workedTime() ?? '—' }}</td>
                                    @if ($hasPhotos)
                                    <td class="table-cell">
                                        <div class="flex gap-2">
                                            @if ($record->check_in_photo)
                                                <a href="{{ route('attendance.photo', [$record, 'entrada']) }}" target="_blank" class="text-brand-600 hover:underline">Entrada</a>
                                            @endif
                                            @if ($record->check_out_photo)
                                                <a href="{{ route('attendance.photo', [$record, 'salida']) }}" target="_blank" class="text-brand-600 hover:underline">Salida</a>
                                            @endif
                                        </div>
                                    </td>
                                    @endif
                                @else
                                    <td colspan="{{ $hasPhotos ? 4 : 3 }}" class="table-cell font-medium text-rose-700">Sin registro</td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $hasPhotos ? 5 : 4 }}" class="table-cell py-6 text-center text-slate-500">No hay personal a tu cargo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection

@push('scripts')
<script>
    // Reloj en vivo del checador
    const reloj = document.querySelector('[data-reloj]');
    if (reloj) {
        const tick = () => reloj.textContent = new Date().toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: 'America/Mexico_City' });
        tick();
        setInterval(tick, 15000);
    }
</script>
@endpush
