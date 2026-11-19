@props(['activity', 'size' => 'size-7'])
@php
    $done = $activity->status === \App\Enums\ActivityStatus::Concluido;
    $failed = $activity->status === \App\Enums\ActivityStatus::NoCompletada;
@endphp
<div class="flex items-center gap-1.5">
    {{-- ✓ Concluido: es definitivo, por eso pide confirmación y luego queda bloqueado --}}
    @if ($done)
        <span class="grid {{ $size }} place-items-center rounded-md border-2 border-emerald-600 bg-emerald-600 text-white" title="Concluido · ya no se puede cambiar" aria-label="Concluido">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </span>
    @else
        <form method="POST" action="{{ route('activities.mark', $activity) }}" data-confirm="¿Seguro que quieres marcarla como Concluido? Después ya no podrás hacerle ningún cambio." onsubmit="return confirm(this.dataset.confirm)">
            @csrf
            <input type="hidden" name="marca" value="concluido">
            <button type="submit" role="checkbox" aria-checked="false" aria-label="Marcar como concluido" title="Marcar como concluido (verde, definitivo)"
                    class="grid {{ $size }} place-items-center rounded-md border-2 border-slate-300 bg-white text-transparent transition hover:border-emerald-500 hover:text-emerald-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-1">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </button>
        </form>
    @endif

    {{-- ✗ No completada: pinta la actividad de rojo --}}
    @unless ($done)
    <form method="POST" action="{{ route('activities.mark', $activity) }}">
        @csrf
        <input type="hidden" name="marca" value="{{ $failed ? 'sin_marcar' : 'no_completada' }}">
        <button type="submit" role="checkbox" aria-checked="{{ $failed ? 'true' : 'false' }}"
                aria-label="{{ $failed ? 'Quitar No completada' : 'Marcar como no completada' }}"
                title="{{ $failed ? 'No completada · tocar para quitar la marca' : 'Marcar como no completada (rojo)' }}"
                @class([
                    "grid $size place-items-center rounded-md border-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-1",
                    'border-rose-600 bg-rose-600 text-white' => $failed,
                    'border-slate-300 bg-white text-slate-300 hover:border-rose-500 hover:text-rose-500' => ! $failed,
                ])>
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </form>
    @endunless
</div>
