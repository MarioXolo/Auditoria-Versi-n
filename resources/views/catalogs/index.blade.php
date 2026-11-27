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
    @if ($privateTypes->isNotEmpty())
        <section class="card mt-6 overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold">Tipos de actividad privados</h2>
                <p class="text-xs text-slate-500">Los escribió cada persona con «Otro…». Solo le aparecen a quien lo escribió; los demás no los ven. Solo el administrador ve esta lista.</p>
            </div>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($privateTypes as $pt)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-2.5">
                        <span><b>{{ $pt->name }}</b> <span class="text-slate-500">· de {{ $pt->owner?->name }} · {{ $pt->activities_count }} {{ $pt->activities_count === 1 ? 'actividad' : 'actividades' }}</span></span>
                        <form method="POST" action="{{ route('catalogs.share-type', $pt->id) }}" onsubmit="return confirm('¿Pasar «{{ $pt->name }}» a la lista común? Todos lo podrán elegir.')">
                            @csrf
                            @method('PATCH')
                            <button class="text-xs font-medium text-brand-700 hover:underline">Pasar a la lista común</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @php $bag = $errors->getBag('secciones'); @endphp
    <section class="card mt-6 overflow-hidden" id="secciones">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="font-semibold">Secciones electorales</h2>
                <p class="text-xs text-slate-500">Se eligen en los libros de Estrategia. Su lista nominal reparte la meta de votos en el semáforo por sección (Meta de votos).</p>
            </div>
            <span class="text-sm text-slate-500">{{ $sections->where('active', true)->count() }} activas</span>
        </div>
        <form method="POST" action="{{ route('sections.store') }}" class="flex flex-wrap gap-2 border-b border-slate-200 bg-slate-50 px-5 py-4">
            @csrf
            <input name="clave" required maxlength="10" placeholder="Sección, ej. 0512" class="input w-40" value="{{ $bag->any() ? old('clave') : '' }}" aria-label="Nueva sección">
            <input name="listado_nominal" type="number" min="0" placeholder="Lista nominal" class="input w-36" value="{{ $bag->any() ? old('listado_nominal') : '' }}" aria-label="Lista nominal">
            <select name="community_id" class="input w-56" aria-label="Comunidad">
                <option value="">— Comunidad principal —</option>
                @foreach ($activeCommunities as $c)
                    <option value="{{ $c->id }}" @selected($bag->any() && old('community_id') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary shrink-0">Agregar</button>
        </form>
        @if ($bag->any())
            <p class="border-b border-rose-200 bg-rose-50 px-5 py-2 text-sm text-rose-700">{{ $bag->first() }}</p>
        @endif
        <ul class="divide-y divide-slate-100">
            @forelse ($sections as $section)
                <li @class(['flex flex-wrap items-center gap-2 px-5 py-2.5', 'bg-slate-50/70' => ! $section->active])>
                    <form method="POST" action="{{ route('sections.update', $section) }}" class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                        @csrf
                        @method('PATCH')
                        <input name="clave" value="{{ $section->clave }}" required maxlength="10" aria-label="Sección" @class(['input w-20 py-1.5 font-mono', 'text-slate-400 line-through' => ! $section->active])>
                        <input name="listado_nominal" type="number" min="0" value="{{ $section->listado_nominal }}" placeholder="Lista nominal" class="input w-32 py-1.5" aria-label="Lista nominal de la sección {{ $section->clave }}">
                        <input name="representante" value="{{ $section->representante }}" maxlength="120" placeholder="Representante general" class="input min-w-0 flex-1 py-1.5" aria-label="Representante de la sección {{ $section->clave }}">
                        <input type="hidden" name="community_id" value="{{ $section->community_id }}">
                        @php $mias = $section->communities->pluck('id')->all(); @endphp
                        <details class="w-full">
                            <summary class="cursor-pointer text-xs text-brand-700">Comunidades ({{ count($mias) }})</summary>
                            <div class="mt-2 grid max-h-64 grid-cols-1 gap-x-4 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($activeCommunities as $c)
                                    <label class="flex items-center gap-2 py-0.5"><input type="checkbox" name="communities[]" value="{{ $c->id }}" @checked(in_array($c->id, $mias, true) || $section->community_id === $c->id)> {{ $c->name }}</label>
                                @endforeach
                            </div>
                        </details>
                        <button class="btn btn-secondary shrink-0 px-3 py-1.5 text-xs">Guardar</button>
                    </form>
                    <form method="POST" action="{{ route('sections.update', $section) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="active" value="{{ $section->active ? 0 : 1 }}">
                        <button @class(['badge cursor-pointer', 'bg-emerald-100 text-emerald-800' => $section->active, 'bg-slate-200 text-slate-600' => ! $section->active])>{{ $section->active ? 'Activa' : 'Inactiva' }}</button>
                    </form>
                    @if ($section->strategy_records_count === 0)
                        <form method="POST" action="{{ route('sections.destroy', $section) }}" onsubmit="return confirm('¿Eliminar la sección {{ $section->clave }}?')">
                            @csrf
                            @method('DELETE')
                            <button class="text-xs font-medium text-rose-600 hover:underline">Eliminar</button>
                        </form>
                    @endif
                </li>
            @empty
                <li class="px-5 py-8 text-center text-sm text-slate-500">Aún no hay secciones. Agrega las del municipio con su lista nominal y sus comunidades.</li>
            @endforelse
        </ul>
    </section>

    <p class="mt-4 text-sm text-slate-500">Un elemento que ya tiene actividades no se puede borrar; desactívalo para que deje de aparecer al cargar actividades sin perder el historial.</p>
@endsection
