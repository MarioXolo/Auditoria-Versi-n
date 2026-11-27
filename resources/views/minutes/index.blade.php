@extends('layouts.app')

@section('title', 'Minutas')

@section('header')
    <div>
        <h1 class="text-2xl font-semibold">Minutas</h1>
        <p class="text-sm text-slate-500">Acuerdos y compromisos de cada reunión, ordenados por fecha.</p>
    </div>
    @if (auth()->user()->puede('minutas', 'capturar'))
        <a href="{{ route('minutes.create') }}" class="btn btn-primary">+ Crea tu minuta</a>
    @endif
@endsection

@section('content')
    @if ($errors->has('archivos') || $errors->has('archivos.*'))
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            @foreach ($errors->get('archivos*') as $msgs)
                @foreach ((array) $msgs as $msg)<p>{{ is_array($msg) ? implode(' ', $msg) : $msg }}</p>@endforeach
            @endforeach
        </div>
    @endif

    <div class="mb-4 grid gap-3 lg:grid-cols-[1fr_auto]">
        <form method="GET" class="card flex flex-wrap items-center gap-3 p-3">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar en temas, acuerdos, participantes, responsables…" class="input min-w-56 flex-1">
            <select name="mes" class="input w-auto" onchange="this.form.submit()" aria-label="Mes">
                <option value="">Todos los meses</option>
                @foreach ($months as $m)
                    <option value="{{ $m }}" @selected(request('mes') === $m)>{{ ucfirst(\Illuminate\Support\Carbon::parse($m.'-01')->locale('es')->translatedFormat('F Y')) }}</option>
                @endforeach
            </select>
            <button class="btn btn-secondary">Buscar</button>
            @if (request()->hasAny(['q', 'mes']))<a href="{{ route('minutes.index') }}" class="text-sm text-slate-500 hover:underline">Limpiar</a>@endif
        </form>

        <form method="POST" action="{{ route('minutes.import') }}" enctype="multipart/form-data" class="card flex flex-wrap items-center gap-3 p-3">
            @csrf
            <label class="text-sm">
                <span class="mb-1 block text-xs font-medium text-slate-500">Importar minutas en Excel (.xlsx)</span>
                <input type="file" name="archivos[]" accept=".xlsx" multiple required class="text-sm file:mr-2 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium">
            </label>
            <button class="btn btn-secondary">Importar</button>
        </form>
    </div>

    <div class="card overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="table-head">Fecha</th>
                    <th class="table-head">Horario</th>
                    <th class="table-head">Tema</th>
                    <th class="table-head">Lugar</th>
                    <th class="table-head text-right">Participantes</th>
                    <th class="table-head text-right">Acuerdos</th>
                    <th class="table-head text-right">Compromisos</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($minutes as $m)
                    <tr class="hover:bg-slate-50">
                        <td class="table-cell whitespace-nowrap">
                            <a href="{{ route('minutes.show', $m) }}" class="font-semibold text-brand-700 hover:underline">{{ $m->fecha->format('d/m/Y') }}</a>
                            <span class="block text-xs text-slate-500">{{ ucfirst($m->fecha->locale('es')->dayName) }}</span>
                        </td>
                        <td class="table-cell whitespace-nowrap tabular-nums">{{ \App\Models\Minute::hhmm($m->hora_inicio) ?? '—' }} @if ($m->hora_fin) – {{ \App\Models\Minute::hhmm($m->hora_fin) }}@endif</td>
                        <td class="table-cell"><a href="{{ route('minutes.show', $m) }}" class="hover:underline">{{ $m->tema ?: $m->titulo }}</a></td>
                        <td class="table-cell text-slate-600">{{ $m->lugar }}</td>
                        <td class="table-cell text-right tabular-nums">{{ $m->participants_count }}</td>
                        <td class="table-cell text-right tabular-nums">{{ $m->acuerdos_count }}</td>
                        <td class="table-cell text-right tabular-nums">{{ $m->commitments_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="table-cell py-10 text-center text-slate-500">
                        @if (request()->hasAny(['q', 'mes'])) Ninguna minuta coincide con la búsqueda. @else Aún no hay minutas. Crea la primera o importa tus Excel. @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $minutes->links() }}</div>
@endsection
