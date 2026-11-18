@extends('layouts.app')

@section('title', $minute->titulo)

@php
    $band = 'bg-ink px-4 py-2 text-sm font-semibold tracking-wide text-white uppercase print:px-2 print:py-1';
    $hhmm = fn ($v) => \App\Models\Minute::hhmm($v) ?? '—';
@endphp

@section('header')
    <div>
        <a href="{{ route('minutes.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Minutas</a>
        <h1 class="mt-1 text-2xl font-semibold">{{ $minute->tema ?: 'Minuta' }}</h1>
        <p class="text-sm text-slate-500">{{ ucfirst($minute->fecha->locale('es')->translatedFormat('l j \d\e F \d\e Y')) }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <button type="button" onclick="window.print()" class="btn btn-secondary">Imprimir / PDF</button>
        <a href="{{ route('minutes.edit', $minute) }}" class="btn btn-primary">Editar</a>
    </div>
@endsection

@section('content')
    {{-- Mismo orden y secciones que la minuta en Excel --}}
    <article class="card overflow-hidden text-sm print:rounded-none print:border-slate-400">
        <h2 class="border-b border-slate-200 px-4 py-3 text-center text-base font-bold tracking-wide uppercase sm:text-lg">{{ $minute->titulo }}</h2>

        <h3 class="{{ $band }}">1. Información general</h3>
        <dl class="grid divide-y divide-slate-100 sm:grid-cols-[12rem_1fr]">
            <dt class="bg-slate-50 px-4 py-2 font-semibold text-slate-600">Tema de la reunión</dt><dd class="px-4 py-2">{{ $minute->tema ?: '—' }}</dd>
            <dt class="bg-slate-50 px-4 py-2 font-semibold text-slate-600">Objetivo principal</dt><dd class="px-4 py-2 whitespace-pre-line">{{ $minute->objetivo ?: '—' }}</dd>
            <dt class="bg-slate-50 px-4 py-2 font-semibold text-slate-600">Fecha</dt>
            <dd class="flex flex-wrap gap-x-8 gap-y-1 px-4 py-2">
                <span class="font-semibold">{{ $minute->fecha->format('d/m/Y') }}</span>
                <span><span class="text-slate-500">Hora de inicio:</span> {{ $hhmm($minute->hora_inicio) }}</span>
                <span><span class="text-slate-500">Hora de fin:</span> {{ $hhmm($minute->hora_fin) }}</span>
            </dd>
            <dt class="bg-slate-50 px-4 py-2 font-semibold text-slate-600">Lugar / Enlace</dt><dd class="px-4 py-2">{{ $minute->lugar ?: '—' }}</dd>
        </dl>

        <h3 class="{{ $band }}">2. Control de participantes</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-slate-50 text-left text-xs text-slate-600"><tr>
                    <th class="px-4 py-2 font-semibold">Nombre y apellido</th><th class="px-4 py-2 font-semibold">Puesto / Área</th>
                    <th class="px-4 py-2 text-center font-semibold">Asistencia</th><th class="px-4 py-2 font-semibold">Modalidad</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($minute->participants as $p)
                        <tr><td class="px-4 py-2">{{ $p->nombre }}</td><td class="px-4 py-2 text-slate-600">{{ $p->puesto }}</td>
                            <td class="px-4 py-2 text-center font-semibold">{{ $p->asistio ? 'X' : '' }}</td><td class="px-4 py-2">{{ $p->modalidad }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-3 text-slate-400">Sin participantes registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @foreach (['3. Puntos tratados / Orden del día' => $minute->puntos, '4. Acuerdos y decisiones principales' => $minute->acuerdos] as $title => $rows)
            <h3 class="{{ $band }}">{{ $title }}</h3>
            @if ($rows->isEmpty())
                <p class="px-4 py-3 text-slate-400">Sin registros.</p>
            @else
                <ol class="divide-y divide-slate-100">
                    @foreach ($rows as $row)
                        @php $num = $loop->iteration; $texto = preg_replace('/^\s*\d+\s*[.)]\s*/u', '', $row->texto); @endphp
                        <li class="flex gap-3 px-4 py-2 break-inside-avoid"><span class="w-6 shrink-0 text-right font-semibold text-slate-400 tabular-nums">{{ $num }}.</span><span class="whitespace-pre-line">{{ $texto }}</span></li>
                    @endforeach
                </ol>
            @endif
        @endforeach

        <h3 class="{{ $band }}">5. Compromisos y/o asuntos pendientes · seguimiento</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-slate-50 text-left text-xs text-slate-600"><tr>
                    <th class="px-4 py-2 font-semibold">ID</th><th class="px-4 py-2 font-semibold">Actividad / Tarea específica</th><th class="px-4 py-2 font-semibold">Responsable</th>
                    <th class="px-4 py-2 font-semibold">Fecha límite</th><th class="px-4 py-2 font-semibold">Estatus</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($minute->commitments as $c)
                        <tr @class(['break-inside-avoid', 'bg-rose-50/60' => $c->vencido()])>
                            <td class="px-4 py-2 font-semibold text-slate-400 tabular-nums">{{ $loop->iteration }}</td>
                            <td class="px-4 py-2">{{ $c->actividad }}</td>
                            <td class="px-4 py-2">{{ $c->responsable }}</td>
                            <td class="px-4 py-2 whitespace-nowrap">{{ $c->fechaLimiteLabel() }} @if ($c->vencido())<span class="block text-xs font-medium text-rose-600 print:hidden">Vencido</span>@endif</td>
                            <td class="px-4 py-2">
                                @if ($c->estatus)
                                    <span @class(['badge', 'bg-emerald-100 text-emerald-800' => ! $c->abierto(), 'bg-amber-100 text-amber-800' => $c->abierto() && in_array($c->estatus, ['En proceso', 'Iniciado']), 'bg-slate-100 text-slate-700' => $c->estatus === 'Suspendida'])>{{ $c->estatus }}</span>
                                @else <span class="text-slate-400">Pendiente</span> @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-3 text-slate-400">Sin compromisos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500 print:hidden">
        <p>
            @if ($minute->archivo_origen) Importada de «{{ $minute->archivo_origen }}». @endif
            @if ($minute->creator) Registrada por {{ $minute->creator->name }} el {{ $minute->created_at->format('d/m/Y H:i') }}. @endif
        </p>
        <form method="POST" action="{{ route('minutes.destroy', $minute) }}" onsubmit="return confirm('¿Eliminar esta minuta? No se puede deshacer.')">
            @csrf @method('DELETE')
            <button class="font-medium text-rose-600 hover:underline">Eliminar minuta</button>
        </form>
    </div>
@endsection

@push('scripts')
<style>@media print { @page { size: letter portrait; margin: 12mm; } }</style>
@endpush
