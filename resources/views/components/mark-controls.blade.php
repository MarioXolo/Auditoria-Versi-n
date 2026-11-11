@props(['activity', 'size' => 'size-7'])
@php
    $done = $activity->status === \App\Enums\ActivityStatus::Completada;
    $failed = $activity->status === \App\Enums\ActivityStatus::Incompleta;
@endphp
<div class="flex items-center gap-1.5">
    {{-- ✓ Completada: funciona como checkbox, al volver a tocarlo se quita la marca --}}
    <form method="POST" action="{{ route('activities.mark', $activity) }}">
        @csrf
        <input type="hidden" name="marca" value="{{ $done ? 'sin_marcar' : 'completada' }}">
        <button type="submit" role="checkbox" aria-checked="{{ $done ? 'true' : 'false' }}"
                aria-label="{{ $done ? 'Quitar marca de completada' : 'Marcar como completada' }}"
                title="{{ $done ? 'Completada · tocar para quitar la marca' : 'Marcar como completada' }}"
                @class([
                    "grid $size place-items-center rounded-md border-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-1",
                    'border-emerald-600 bg-emerald-600 text-white' => $done,
                    'border-slate-300 bg-white text-transparent hover:border-emerald-500 hover:text-emerald-500' => ! $done,
                ])>
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </button>
    </form>

    {{-- ✗ Incompleta: pinta la actividad de rojo --}}
    <form method="POST" action="{{ route('activities.mark', $activity) }}">
        @csrf
        <input type="hidden" name="marca" value="{{ $failed ? 'sin_marcar' : 'incompleta' }}">
        <button type="submit" role="checkbox" aria-checked="{{ $failed ? 'true' : 'false' }}"
                aria-label="{{ $failed ? 'Quitar marca de incompleta' : 'Marcar como incompleta' }}"
                title="{{ $failed ? 'Incompleta · tocar para quitar la marca' : 'Marcar como incompleta' }}"
                @class([
                    "grid $size place-items-center rounded-md border-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-1",
                    'border-rose-600 bg-rose-600 text-white' => $failed,
                    'border-slate-300 bg-white text-slate-300 hover:border-rose-500 hover:text-rose-500' => ! $failed,
                ])>
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </form>
</div>
