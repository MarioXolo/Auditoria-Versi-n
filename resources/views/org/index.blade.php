@extends('layouts.app')

@section('title', 'Organigrama')

@php $canEdit = auth()->user()->puede('organigrama', 'capturar'); @endphp

@section('header')
    <div>
        <h1 class="text-2xl font-semibold">Organigrama</h1>
        <p class="text-sm text-slate-500">Se dibuja solo con el campo «a quién reporta». {{ $members->count() }} {{ $members->count() === 1 ? 'persona' : 'personas' }}.</p>
    </div>
    @if ($canEdit)
        <a href="#nueva" class="btn btn-primary">+ Agregar persona</a>
    @endif
@endsection

@section('content')
    @if ($servidores->isNotEmpty())
        <div class="mb-5 rounded-xl border-2 border-rose-400 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
            <p class="font-semibold">⚠ {{ $servidores->count() === 1 ? 'Hay 1 persona marcada' : 'Hay '.$servidores->count().' personas marcadas' }} como servidor público</p>
            <p class="mt-1">{{ $servidores->map(fn ($s) => $s->nombre.' ('.$s->puesto.')')->join(' · ') }}. Revisen con el jurídico qué actividades de campaña puede hacer y en qué horario.</p>
        </div>
    @endif

    <style>
        .org-tree, .org-tree ul { display: flex; justify-content: center; padding-top: 1.25rem; position: relative; }
        .org-tree { padding-top: 0; }
        .org-tree li { list-style: none; text-align: center; position: relative; padding: 1.25rem .4rem 0; }
        .org-tree > li { padding-top: 0; }
        .org-tree li::before, .org-tree li::after { content: ''; position: absolute; top: 0; right: 50%; width: 50%; height: 1.25rem; border-top: 2px solid #cbd5e1; }
        .org-tree li::after { right: auto; left: 50%; border-left: 2px solid #cbd5e1; }
        .org-tree li:only-child::before, .org-tree li:only-child::after { display: none; }
        .org-tree li:only-child { padding-top: 0; }
        .org-tree > li::before, .org-tree > li::after { display: none; }
        .org-tree li:first-child::before, .org-tree li:last-child::after { border: 0 none; }
        .org-tree li:last-child::before { border-right: 2px solid #cbd5e1; border-radius: 0 6px 0 0; }
        .org-tree li:first-child::after { border-radius: 6px 0 0 0; }
        .org-tree ul ul::before, .org-tree li > ul::before { content: ''; position: absolute; top: 0; left: 50%; height: 1.25rem; border-left: 2px solid #cbd5e1; }
        .org-tree ul > li:only-child { padding-top: 1.25rem; }
        .org-tree ul > li:only-child::after { display: block; left: 50%; width: 0; border-top: 0; }
    </style>

    <section class="card mb-6 overflow-x-auto p-5" data-org-scroll>
        @if ($members->isEmpty())
            <p class="py-8 text-center text-sm text-slate-500">Aún no hay nadie. Agrega primero a quien está hasta arriba (el candidato) y luego a quienes le reportan.</p>
        @else
            <ul class="org-tree mx-auto w-max min-w-full">
                @foreach ($children[0] ?? [] as $root)
                    @include('org._node', ['m' => $root])
                @endforeach
            </ul>
        @endif
    </section>

    <section class="card overflow-hidden">
        <h2 class="border-b border-slate-200 px-5 py-4 font-semibold">Personas</h2>
        <ul class="divide-y divide-slate-100">
            @foreach ($members as $m)
                <li id="p{{ $m->id }}" @class(['scroll-mt-24', 'bg-rose-50/60' => $m->servidor_publico])>
                    <details @if ($errors->any() && old('_member') == $m->id) open @endif>
                        <summary class="flex cursor-pointer flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3">
                            <span class="min-w-48 flex-1">
                                <span class="font-semibold">{{ $m->nombre }}</span>
                                <span class="text-sm text-slate-500"> · {{ $m->puesto }}</span>
                                @if ($m->servidor_publico)<span class="ml-1 badge bg-rose-600 text-white">⚠ Servidor público</span>@endif
                            </span>
                            <span class="text-sm text-slate-600">Reporta a: {{ $m->jefe?->nombre ?? '—' }}</span>
                            <span class="text-sm text-slate-600">{{ $m->telefono }}</span>
                            <span class="text-xs text-slate-500">{{ $m->rolEnSistema() ?? 'Sin cuenta' }}</span>
                        </summary>
                        @if ($canEdit)
                            <form method="POST" action="{{ route('org.update', $m) }}" class="border-t border-slate-100 bg-slate-50/60 px-5 py-4">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="_member" value="{{ $m->id }}">
                                @include('org._form', ['m' => $m])
                                <div class="mt-3 flex items-center justify-between">
                                    <button class="btn btn-primary">Guardar</button>
                                </div>
                            </form>
                            <form method="POST" action="{{ route('org.destroy', $m) }}" class="px-5 pb-4 text-right" onsubmit="return confirm('¿Quitar a {{ $m->nombre }} del organigrama? Quienes le reportan pasarán a reportar a su jefe.')">
                                @csrf
                                @method('DELETE')
                                <button class="text-xs font-medium text-rose-600 hover:underline">Quitar del organigrama</button>
                            </form>
                        @else
                            <dl class="grid gap-2 border-t border-slate-100 px-5 py-4 text-sm sm:grid-cols-3">
                                <div><dt class="text-xs text-slate-500">Equipo</dt><dd>{{ $m->team?->name ?? '—' }}</dd></div>
                                <div><dt class="text-xs text-slate-500">Delegación / sección</dt><dd>{{ collect([$m->delegacion, $m->section?->clave])->filter()->join(' · ') ?: '—' }}</dd></div>
                                <div><dt class="text-xs text-slate-500">Notas</dt><dd>{{ $m->notas ?? '—' }}</dd></div>
                            </dl>
                        @endif
                    </details>
                </li>
            @endforeach
        </ul>
    </section>

    @if ($canEdit)
        <section class="card mt-6 p-5" id="nueva">
            <h2 class="mb-4 font-semibold">Agregar persona</h2>
            <form method="POST" action="{{ route('org.store') }}">
                @csrf
                @include('org._form', ['m' => new \App\Models\OrgMember(old() ?: [])])
                <button class="btn btn-primary mt-4">Agregar</button>
            </form>
        </section>
    @endif
@endsection

@push('scripts')
<script>
    // En el celular el organigrama se desplaza de lado: que abra centrado en quien está arriba
    document.querySelectorAll('[data-org-scroll]').forEach((el) => { el.scrollLeft = (el.scrollWidth - el.clientWidth) / 2; });
</script>
@endpush
