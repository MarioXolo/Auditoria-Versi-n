@extends('layouts.app')

@section('title', 'Asistencia')

@section('header')
    <div>
        <h1 class="text-2xl font-semibold">Asistencia</h1>
        <p class="text-sm text-stone-500">{{ now()->translatedFormat('l j \d\e F \d\e Y') }}</p>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Registro propio --}}
        <section class="card p-6">
            <h2 class="font-semibold">Mi registro de hoy</h2>

            @if (! $today)
                <p class="mt-1 text-sm text-stone-500">Toma una foto para registrar tu entrada.</p>
                <form method="POST" action="{{ route('attendance.check-in') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label for="photo-in" class="label">Foto de entrada *</label>
                        <input id="photo-in" type="file" name="photo" accept="image/*" capture="user" required class="input file:mr-3 file:rounded-md file:border-0 file:bg-stone-100 file:px-3 file:py-1 file:text-sm">
                    </div>
                    <div>
                        <label for="notes" class="label">Comentario (opcional)</label>
                        <input id="notes" name="notes" maxlength="500" class="input" placeholder="Ej. Inicio en zona centro">
                    </div>
                    <button class="btn btn-primary w-full">Registrar entrada</button>
                </form>
            @else
                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between rounded-lg bg-emerald-50 px-4 py-3">
                        <span class="text-emerald-800">Entrada</span>
                        <span class="font-semibold text-emerald-900 tabular-nums">{{ $today->check_in_at->format('H:i') }}</span>
                    </div>
                    @if ($today->check_out_at)
                        <div class="flex justify-between rounded-lg bg-stone-100 px-4 py-3">
                            <span>Salida</span>
                            <span class="font-semibold tabular-nums">{{ $today->check_out_at->format('H:i') }}</span>
                        </div>
                        <p class="text-center text-stone-500">Jornada de {{ $today->workedTime() }}</p>
                    @else
                        <form method="POST" action="{{ route('attendance.check-out') }}" enctype="multipart/form-data" class="space-y-4 pt-2">
                            @csrf
                            <div>
                                <label for="photo-out" class="label">Foto de salida (opcional)</label>
                                <input id="photo-out" type="file" name="photo" accept="image/*" capture="user" class="input file:mr-3 file:rounded-md file:border-0 file:bg-stone-100 file:px-3 file:py-1 file:text-sm">
                            </div>
                            <button class="btn btn-primary w-full">Registrar salida</button>
                        </form>
                    @endif
                </div>
            @endif
        </section>

        {{-- Historial propio --}}
        <section class="card overflow-hidden lg:col-span-2">
            <div class="border-b border-stone-200 px-5 py-4">
                <h2 class="font-semibold">Mis últimos registros</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200">
                    <thead class="bg-stone-50">
                        <tr>
                            <th class="table-head">Fecha</th>
                            <th class="table-head">Entrada</th>
                            <th class="table-head">Salida</th>
                            <th class="table-head">Tiempo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($history as $row)
                            <tr>
                                <td class="table-cell">{{ $row->work_date->translatedFormat('D j M') }}</td>
                                <td class="table-cell tabular-nums">{{ $row->check_in_at?->format('H:i') ?? '—' }}</td>
                                <td class="table-cell tabular-nums">{{ $row->check_out_at?->format('H:i') ?? '—' }}</td>
                                <td class="table-cell">{{ $row->workedTime() ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="table-cell py-6 text-center text-stone-500">Aún no tienes registros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {{-- Asistencia del personal (encargados, gerentes y administradores) --}}
    @if ($roster !== null)
        @php $present = $roster->filter(fn ($r) => $r['record'])->count(); @endphp
        <section class="card mt-6 overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-stone-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-semibold">Asistencia del personal</h2>
                    <p class="text-xs text-stone-500">{{ $present }} de {{ $roster->count() }} con entrada registrada</p>
                </div>
                <form method="GET" class="flex gap-2">
                    <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}" class="input w-auto" onchange="this.form.submit()">
                    <noscript><button class="btn btn-secondary">Ver</button></noscript>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200">
                    <thead class="bg-stone-50">
                        <tr>
                            <th class="table-head">Persona</th>
                            <th class="table-head">Entrada</th>
                            <th class="table-head">Salida</th>
                            <th class="table-head">Tiempo</th>
                            <th class="table-head">Fotos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($roster as $row)
                            @php $record = $row['record']; @endphp
                            <tr @class(['bg-rose-50/50' => ! $record])>
                                <td class="table-cell">
                                    <span class="font-medium text-stone-900">{{ $row['user']->name }}</span>
                                    <span class="block text-xs text-stone-500">{{ $row['user']->role->label() }}</span>
                                </td>
                                @if ($record)
                                    <td class="table-cell tabular-nums">{{ $record->check_in_at?->format('H:i') ?? '—' }}</td>
                                    <td class="table-cell tabular-nums">{{ $record->check_out_at?->format('H:i') ?? '—' }}</td>
                                    <td class="table-cell">{{ $record->workedTime() ?? '—' }}</td>
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
                                @else
                                    <td colspan="4" class="table-cell font-medium text-rose-700">Sin registro</td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="5" class="table-cell py-6 text-center text-stone-500">No hay personal a tu cargo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
