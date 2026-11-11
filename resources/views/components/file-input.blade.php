@props([
    'name',
    'id' => null,
    'accept' => null,
    'capture' => null,
    'required' => false,
    'button' => 'Seleccionar archivo',
    'icon' => 'file',
])
@php
    $id ??= $name;
    $empty = 'Ningún archivo seleccionado';
    $paths = [
        'camera' => 'M3 8a2 2 0 012-2h1.5l1.4-2h8.2l1.4 2H19a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm9 9a4 4 0 100-8 4 4 0 000 8z',
        'file' => 'M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zm0 0v5h5M12 18v-6m-3 3l3-3 3 3',
    ];
@endphp
<div>
    <label for="{{ $id }}" class="flex cursor-pointer items-center gap-3 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-2 transition hover:border-brand-500 hover:bg-brand-50">
        <span class="btn btn-secondary pointer-events-none shrink-0">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $paths[$icon] ?? $paths['file'] }}"/></svg>
            {{ $button }}
        </span>
        <span data-file-name class="min-w-0 truncate text-sm text-slate-500">{{ $empty }}</span>
    </label>
    <input
        type="file"
        id="{{ $id }}"
        name="{{ $name }}"
        class="sr-only"
        @if ($accept) accept="{{ $accept }}" @endif
        @if ($capture) capture="{{ $capture }}" @endif
        @required($required)
        onchange="this.previousElementSibling.querySelector('[data-file-name]').textContent = this.files.length ? this.files[0].name : @js($empty)"
    >
</div>
