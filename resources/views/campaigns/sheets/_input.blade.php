{{-- Un campo de una hoja: $f (definición), $name, $value, $form (id del formulario de la fila, o null en «agregar»), $compact --}}
@php
    $id = ($form ?? 'new').'-'.$name;
    $size = $compact ? 'py-1.5 text-sm' : '';
    $w = $compact ? ($f['w'] ?? 'w-32') : 'w-full';
    $formAttr = $form ? 'form='.$form : '';
    $aria = $compact ? 'aria-label="'.e($f['label']).'"' : '';
@endphp
@switch($f['type'])
    @case('bool')
        @if ($compact)
            <input {!! $formAttr !!} type="checkbox" name="{{ $name }}" value="1" @checked($value) class="size-4 rounded border-slate-300" aria-label="{{ $f['label'] }}">
        @else
            <label class="inline-flex h-10 items-center gap-2 text-sm font-medium"><input type="checkbox" name="{{ $name }}" value="1" @checked($value) class="size-4 rounded border-slate-300"> Sí</label>
        @endif
        @break
    @case('select')
        <select {!! $formAttr !!} id="{{ $id }}" name="{{ $name }}" @required($f['required'] ?? false) {!! $aria !!}
                class="input {{ $size }} {{ $w }} {{ $compact ? ($f['colors'][$value] ?? '') : '' }}">
            @unless ($f['required'] ?? false)<option value="">—</option>@endunless
            @foreach ($f['options'] as $option)
                <option value="{{ $option }}" @selected((string) $value === $option)>{{ $option }}</option>
            @endforeach
        </select>
        @break
    @case('community')
        <select {!! $formAttr !!} id="{{ $id }}" name="{{ $name }}" {!! $aria !!} class="input {{ $size }} {{ $w }}" @unless ($compact) data-other="new-community_new" @endunless>
            <option value="">—</option>
            @foreach ($communities as $community)
                <option value="{{ $community->id }}" @selected((string) $value === (string) $community->id)>{{ $community->name }}</option>
            @endforeach
            @unless ($compact)<option value="otro" @selected(old($name) === 'otro')>Otra (escribirla)…</option>@endunless
        </select>
        @unless ($compact)
            <input id="new-community_new" name="community_new" value="{{ old('community_new') }}" maxlength="120" placeholder="Nombre de la comunidad" aria-label="Nueva comunidad"
                   @class(['input mt-2', 'hidden' => old($name) !== 'otro'])>
        @endunless
        @break
    @case('int')
    @case('money')
    @case('percent')
        @php
            $shown = match (true) {
                $value === null || $value === '' => '',
                $f['type'] === 'percent' && ! $compact && old($name) === null => rtrim(rtrim(number_format((float) $value * 100, 2, '.', ''), '0'), '.'),
                $f['type'] === 'percent' && $compact => rtrim(rtrim(number_format((float) $value * 100, 2, '.', ''), '0'), '.'),
                default => is_numeric($value) ? rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') : $value,
            };
        @endphp
        <div class="relative">
            @if ($f['type'] === 'money')<span class="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-slate-400 {{ $compact ? 'text-sm' : '' }}">$</span>@endif
            <input {!! $formAttr !!} id="{{ $id }}" type="number" name="{{ $name }}" value="{{ $shown }}" min="0" step="{{ $f['type'] === 'int' ? 1 : 'any' }}" {!! $aria !!}
                   @if ($f['type'] === 'percent') max="100" @endif
                   class="input {{ $size }} {{ $w }} text-right tabular-nums {{ $f['type'] === 'money' ? 'pl-6' : '' }} {{ $f['type'] === 'percent' ? 'pr-6' : '' }}">
            @if ($f['type'] === 'percent')<span class="pointer-events-none absolute inset-y-0 right-2.5 flex items-center text-slate-400 {{ $compact ? 'text-sm' : '' }}">%</span>@endif
        </div>
        @break
    @case('date')
        <input {!! $formAttr !!} id="{{ $id }}" type="date" name="{{ $name }}" value="{{ $value instanceof \Carbon\CarbonInterface ? $value->format('Y-m-d') : $value }}" {!! $aria !!} class="input {{ $size }} {{ $w }}">
        @break
    @default
        <div class="flex items-center gap-1">
            <input {!! $formAttr !!} id="{{ $id }}" type="{{ $f['type'] === 'phone' ? 'tel' : 'text' }}" name="{{ $name }}" value="{{ $value }}" maxlength="{{ $f['max'] ?? 30 }}" {!! $aria !!}
                   @required($f['required'] ?? false) placeholder="{{ $compact ? '' : ($f['placeholder'] ?? '') }}" class="input {{ $size }} {{ $w }}">
            @if ($compact && $f['type'] === 'phone' && filled($value))<x-whatsapp :phone="$value" icon-only />@endif
        </div>
@endswitch
