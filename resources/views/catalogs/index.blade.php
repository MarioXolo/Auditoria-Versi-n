@extends('layouts.app')

@section('title', 'Catálogos')

@section('header')
    <div>
        <h1 class="text-2xl font-semibold">Catálogos</h1>
        <p class="text-sm text-slate-500">Las opciones que aparecen al cargar una actividad.</p>
    </div>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-2">
        @foreach ([
            ['key' => 'tipos', 'title' => 'Tipos de actividad', 'items' => $types, 'placeholder' => 'Ej. Visita domiciliaria'],
            ['key' => 'comunidades', 'title' => 'Comunidades', 'items' => $communities, 'placeholder' => 'Ej. San Isidro'],
        ] as $catalog)
            @php $bag = $errors->getBag($catalog['key']); @endphp
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold">{{ $catalog['title'] }}</h2>
                    <span class="text-sm text-slate-500">{{ $catalog['items']->where('active', true)->count() }} activos</span>
                </div>

                <form method="POST" action="{{ route('catalogs.store', $catalog['key']) }}" class="flex gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4">
                    @csrf
                    <input name="name" required maxlength="120" placeholder="{{ $catalog['placeholder'] }}" class="input"
                           value="{{ $bag->any() ? old('name') : '' }}" aria-label="Nuevo elemento de {{ strtolower($catalog['title']) }}">
                    <button class="btn btn-primary shrink-0">Agregar</button>
                </form>
                @if ($bag->any())
                    <p class="border-b border-rose-200 bg-rose-50 px-5 py-2 text-sm text-rose-700">{{ $bag->first() }}</p>
                @endif

                <ul class="divide-y divide-slate-100">
                    @forelse ($catalog['items'] as $item)
                        <li @class(['flex flex-wrap items-center gap-3 px-5 py-3', 'bg-slate-50/70' => ! $item->active])>
                            <form method="POST" action="{{ route('catalogs.update', [$catalog['key'], $item->id]) }}" class="flex min-w-0 flex-1 items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <input name="name" value="{{ $item->name }}" required maxlength="120" aria-label="Nombre"
                                       @class(['input py-1.5', 'text-slate-400 line-through' => ! $item->active])>
                                <button class="btn btn-secondary shrink-0 px-3 py-1.5 text-xs">Guardar</button>
                            </form>
                            <span class="w-24 text-right text-xs text-slate-500 tabular-nums">{{ $item->activities_count }} {{ $item->activities_count === 1 ? 'actividad' : 'actividades' }}</span>
                            <form method="POST" action="{{ route('catalogs.update', [$catalog['key'], $item->id]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="active" value="{{ $item->active ? 0 : 1 }}">
                                <button @class(['badge cursor-pointer', 'bg-emerald-100 text-emerald-800' => $item->active, 'bg-slate-200 text-slate-600' => ! $item->active])
                                        title="{{ $item->active ? 'Desactivar' : 'Activar' }}">{{ $item->active ? 'Activo' : 'Inactivo' }}</button>
                            </form>
                            @if ($item->activities_count === 0)
                                <form method="POST" action="{{ route('catalogs.destroy', [$catalog['key'], $item->id]) }}" onsubmit="return confirm('¿Eliminar «{{ $item->name }}»?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-xs font-medium text-rose-600 hover:underline">Eliminar</button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-slate-500">Aún no hay elementos.</li>
                    @endforelse
                </ul>
            </section>
        @endforeach
    </div>
    <p class="mt-4 text-sm text-slate-500">Un elemento que ya tiene actividades no se puede borrar; desactívalo para que deje de aparecer al cargar actividades sin perder el historial.</p>
@endsection
