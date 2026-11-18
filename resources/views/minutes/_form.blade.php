{{-- Formulario de minuta: las mismas 5 secciones y columnas del formato de Excel del equipo. --}}
@csrf
@php
    $old = fn (string $key, $fallback) => old($key, $fallback);
    $participantes = old('participantes', $minute->exists
        ? $minute->participants->map(fn ($p) => ['nombre' => $p->nombre, 'puesto' => $p->puesto, 'asistio' => $p->asistio, 'modalidad' => $p->modalidad])->all()
        : []);
    $participantes = $participantes ?: [['nombre' => '', 'puesto' => '', 'asistio' => true, 'modalidad' => 'Presencial']];
    $puntos = old('puntos', $minute->exists ? $minute->puntos->pluck('texto')->all() : []) ?: [''];
    $acuerdos = old('acuerdos', $minute->exists ? $minute->acuerdos->pluck('texto')->all() : []) ?: [''];
    $compromisos = old('compromisos', $minute->exists
        ? $minute->commitments->map(fn ($c) => ['actividad' => $c->actividad, 'responsable' => $c->responsable, 'fecha_limite' => $c->fechaLimiteDate()?->format('d/m/Y') ?? $c->fecha_limite, 'estatus' => $c->estatus])->all()
        : []);
    $compromisos = $compromisos ?: [['actividad' => '', 'responsable' => '', 'fecha_limite' => '', 'estatus' => '']];
    $estatus = collect(\App\Models\Minute::ESTATUS)->merge(collect($compromisos)->pluck('estatus')->filter())->unique()->values();
    $section = 'flex items-center gap-2 rounded-t-xl bg-ink px-5 py-2.5 text-sm font-semibold tracking-wide text-white uppercase';
    $hora = fn ($v) => $v ? substr($v, 0, 5) : '';
@endphp

{{-- Encabezado, como la primera fila del Excel --}}
<div class="card mb-5 p-5">
    <label for="titulo" class="label">Título de la minuta</label>
    <input id="titulo" name="titulo" maxlength="200" value="{{ $old('titulo', $minute->titulo) }}" class="input font-semibold uppercase"
           placeholder="Se arma solo con la fecha: MINUTA REUNIÓN MARTES 10/11/2026" data-titulo>
    <p class="mt-1 text-xs text-slate-500">Déjalo vacío para que se forme con el día y la fecha, como en el Excel.</p>
</div>

{{-- 1. Información general --}}
<section class="card mb-5">
    <h2 class="{{ $section }}">1. Información general</h2>
    <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="sm:col-span-2 lg:col-span-4">
            <label for="tema" class="label">Tema de la reunión</label>
            <input id="tema" name="tema" maxlength="255" value="{{ $old('tema', $minute->tema) }}" class="input">
        </div>
        <div class="sm:col-span-2 lg:col-span-4">
            <label for="objetivo" class="label">Objetivo principal</label>
            <textarea id="objetivo" name="objetivo" rows="2" class="input">{{ $old('objetivo', $minute->objetivo) }}</textarea>
        </div>
        <div>
            <label for="fecha" class="label">Fecha (DD/MM/AAAA) *</label>
            <input id="fecha" type="date" name="fecha" required value="{{ $old('fecha', $minute->fecha?->format('Y-m-d')) }}" class="input" data-fecha>
        </div>
        <div>
            <label for="hora_inicio" class="label">Hora de inicio</label>
            <input id="hora_inicio" type="time" name="hora_inicio" value="{{ $old('hora_inicio', $hora($minute->hora_inicio)) }}" class="input">
        </div>
        <div>
            <label for="hora_fin" class="label">Hora de fin</label>
            <input id="hora_fin" type="time" name="hora_fin" value="{{ $old('hora_fin', $hora($minute->hora_fin)) }}" class="input">
        </div>
        <div>
            <label for="lugar" class="label">Lugar / Enlace</label>
            <input id="lugar" name="lugar" maxlength="200" value="{{ $old('lugar', $minute->lugar) }}" class="input" placeholder="Sala de juntas, Huimilpan…">
        </div>
    </div>
</section>

{{-- 2. Control de participantes --}}
<section class="card mb-5" data-rows="participantes">
    <h2 class="{{ $section }}">2. Control de participantes</h2>
    <div class="overflow-x-auto p-3 sm:p-5">
        <table class="min-w-full text-sm">
            <thead><tr class="text-left text-xs text-slate-500">
                <th class="px-1 pb-2 font-medium">Nombre y apellido</th><th class="px-1 pb-2 font-medium">Puesto / Área</th>
                <th class="px-1 pb-2 text-center font-medium">Asistencia</th><th class="px-1 pb-2 font-medium">Modalidad</th><th></th>
            </tr></thead>
            <tbody data-body>
                @foreach ($participantes as $i => $p)
                    @include('minutes._row_participant', ['i' => $i, 'p' => $p])
                @endforeach
            </tbody>
        </table>
        <template>@include('minutes._row_participant', ['i' => '__i__', 'p' => ['nombre' => '', 'puesto' => '', 'asistio' => true, 'modalidad' => 'Presencial']])</template>
        <button type="button" class="mt-2 text-sm font-medium text-brand-600 hover:underline" data-add>+ Agregar participante</button>
    </div>
</section>

{{-- 3. Puntos tratados y 4. Acuerdos: listas numeradas --}}
@foreach (['puntos' => ['3. Puntos tratados / Orden del día', $puntos, 'punto'], 'acuerdos' => ['4. Acuerdos y decisiones principales', $acuerdos, 'acuerdo']] as $key => [$title, $rows, $singular])
    <section class="card mb-5" data-rows="{{ $key }}">
        <h2 class="{{ $section }}">{{ $title }}</h2>
        <div class="p-3 sm:p-5">
            <ol class="space-y-2" data-body>
                @foreach ($rows as $i => $texto)
                    @include('minutes._row_text', ['key' => $key, 'texto' => $texto])
                @endforeach
            </ol>
            <template>@include('minutes._row_text', ['key' => $key, 'texto' => ''])</template>
            <button type="button" class="mt-2 text-sm font-medium text-brand-600 hover:underline" data-add>+ Agregar {{ $singular }}</button>
        </div>
    </section>
@endforeach

{{-- 5. Compromisos y/o asuntos pendientes --}}
<section class="card mb-5" data-rows="compromisos">
    <h2 class="{{ $section }}">5. Compromisos y/o asuntos pendientes · seguimiento</h2>
    <div class="overflow-x-auto p-3 sm:p-5">
        <table class="min-w-full text-sm">
            <thead><tr class="text-left text-xs text-slate-500">
                <th class="px-1 pb-2 font-medium">ID</th><th class="px-1 pb-2 font-medium">Actividad / Tarea específica</th><th class="px-1 pb-2 font-medium">Responsable</th>
                <th class="px-1 pb-2 font-medium">Fecha límite</th><th class="px-1 pb-2 font-medium">Estatus</th><th></th>
            </tr></thead>
            <tbody data-body>
                @foreach ($compromisos as $i => $c)
                    @include('minutes._row_commitment', ['i' => $i, 'c' => $c, 'estatus' => $estatus])
                @endforeach
            </tbody>
        </table>
        <template>@include('minutes._row_commitment', ['i' => '__i__', 'c' => ['actividad' => '', 'responsable' => '', 'fecha_limite' => '', 'estatus' => ''], 'estatus' => $estatus])</template>
        <button type="button" class="mt-2 text-sm font-medium text-brand-600 hover:underline" data-add>+ Agregar compromiso</button>
        <p class="mt-2 text-xs text-slate-500">Fecha límite: escribe la fecha como dd/mm/aaaa, o un texto si aún no es exacta («Primera quincena de diciembre»).</p>
    </div>
</section>

@push('scripts')
<script>
    // Renglones dinámicos: agregar, quitar y renumerar
    document.querySelectorAll('[data-rows]').forEach((box) => {
        const body = box.querySelector('[data-body]');
        const tpl = box.querySelector('template');
        let next = body.children.length + 1000;
        const renumber = () => body.querySelectorAll('[data-num]').forEach((el, i) => el.textContent = (i + 1) + '.');
        box.querySelector('[data-add]').addEventListener('click', () => {
            body.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__i__', next++));
            renumber();
            body.lastElementChild.querySelector('input, textarea')?.focus();
        });
        body.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-remove]');
            if (!btn) return;
            const row = btn.closest('[data-row]');
            if (body.children.length > 1) row.remove(); else row.querySelectorAll('input:not([type=checkbox]), textarea').forEach((el) => el.value = '');
            renumber();
        });
        renumber();
    });

    // Vista previa del título automático
    const titulo = document.querySelector('[data-titulo]');
    const fecha = document.querySelector('[data-fecha]');
    const dias = ['DOMINGO', 'LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES', 'SÁBADO'];
    const preview = () => {
        if (!fecha.value) return;
        const [y, m, d] = fecha.value.split('-').map(Number);
        titulo.placeholder = `MINUTA REUNIÓN ${dias[new Date(y, m - 1, d).getDay()]} ${String(d).padStart(2, '0')}/${String(m).padStart(2, '0')}/${y}`;
    };
    fecha.addEventListener('change', preview);
    preview();
</script>
@endpush
