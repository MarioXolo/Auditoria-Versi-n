<?php

namespace App\Http\Controllers;

use App\Models\Minute;
use App\Services\MinuteExcelImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Minutas de reunión. Solo administradores y gerentes. */
class MinuteController extends Controller
{
    public function index(Request $request): View
    {
        $minutes = Minute::query()
            ->withCount(['participants', 'acuerdos', 'commitments'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('titulo', 'like', $term)->orWhere('tema', 'like', $term)
                    ->orWhere('objetivo', 'like', $term)->orWhere('lugar', 'like', $term)
                    ->orWhereHas('items', fn ($i) => $i->where('texto', 'like', $term))
                    ->orWhereHas('participants', fn ($p) => $p->where('nombre', 'like', $term))
                    ->orWhereHas('commitments', fn ($c) => $c->where('actividad', 'like', $term)->orWhere('responsable', 'like', $term)));
            })
            ->when($request->filled('mes'), fn ($q) => $q->where('fecha', 'like', $request->string('mes')->limit(7, '').'%'))
            ->orderByDesc('fecha')->orderByDesc('hora_inicio')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $months = Minute::query()->orderByDesc('fecha')->pluck('fecha')
            ->map(fn ($f) => $f->format('Y-m'))->unique()->values();

        return view('minutes.index', compact('minutes', 'months'));
    }

    public function create(): View
    {
        return view('minutes.create', ['minute' => new Minute(['fecha' => today()])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $minute = DB::transaction(function () use ($data, $request) {
            $minute = Minute::create($data['minute'] + ['created_by' => $request->user()->id]);
            $this->syncChildren($minute, $data);

            return $minute;
        });

        return redirect()->route('minutes.show', $minute)->with('status', 'Minuta guardada.');
    }

    public function show(Minute $minute): View
    {
        $minute->load(['participants', 'puntos', 'acuerdos', 'commitments', 'creator:id,name']);

        return view('minutes.show', compact('minute'));
    }

    public function edit(Minute $minute): View
    {
        $minute->load(['participants', 'puntos', 'acuerdos', 'commitments']);

        return view('minutes.edit', compact('minute'));
    }

    public function update(Request $request, Minute $minute): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($minute, $data, $request) {
            $minute->update($data['minute'] + ['updated_by' => $request->user()->id]);
            $this->syncChildren($minute, $data);
        });

        return redirect()->route('minutes.show', $minute)->with('status', 'Minuta actualizada.');
    }

    public function destroy(Minute $minute): RedirectResponse
    {
        $minute->delete();

        return redirect()->route('minutes.index')->with('status', 'Minuta eliminada.');
    }

    /** Carga una o varias minutas en el formato de Excel del equipo. */
    public function import(Request $request, MinuteExcelImporter $importer): RedirectResponse
    {
        $request->validate([
            'archivos' => ['required', 'array', 'max:30'],
            'archivos.*' => ['file', 'max:5120', 'extensions:xlsx'],
        ], [], ['archivos' => 'archivos de Excel', 'archivos.*' => 'archivo']);

        $nuevas = 0;
        $repetidas = [];
        $errores = [];
        foreach ($request->file('archivos') as $file) {
            $name = $file->getClientOriginalName();
            try {
                $result = $importer->import($file->getRealPath(), $name, $request->user()->id);
                $result['duplicate'] ? $repetidas[] = $name : $nuevas++;
            } catch (Throwable $e) {
                report($e);
                $errores[] = $name.': '.($e instanceof \RuntimeException ? $e->getMessage() : 'no se pudo leer.');
            }
        }

        $msg = $nuevas === 1 ? 'Se cargó 1 minuta.' : "Se cargaron {$nuevas} minutas.";
        if ($repetidas) {
            $msg .= ' Ya existían y no se duplicaron: '.implode(', ', $repetidas).'.';
        }

        return redirect()->route('minutes.index')->with('status', $msg)->withErrors($errores ? ['archivos' => $errores] : []);
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'titulo' => ['nullable', 'string', 'max:200'],
            'tema' => ['nullable', 'string', 'max:255'],
            'objetivo' => ['nullable', 'string', 'max:5000'],
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fin' => ['nullable', 'date_format:H:i'],
            'lugar' => ['nullable', 'string', 'max:200'],
            'participantes' => ['nullable', 'array', 'max:200'],
            'participantes.*.nombre' => ['nullable', 'string', 'max:150'],
            'participantes.*.puesto' => ['nullable', 'string', 'max:150'],
            'participantes.*.asistio' => ['nullable', 'boolean'],
            'participantes.*.modalidad' => ['nullable', Rule::in(Minute::MODALIDADES)],
            'puntos' => ['nullable', 'array', 'max:200'],
            'puntos.*' => ['nullable', 'string', 'max:5000'],
            'acuerdos' => ['nullable', 'array', 'max:200'],
            'acuerdos.*' => ['nullable', 'string', 'max:5000'],
            'compromisos' => ['nullable', 'array', 'max:200'],
            'compromisos.*.actividad' => ['nullable', 'string', 'max:5000'],
            'compromisos.*.responsable' => ['nullable', 'string', 'max:200'],
            'compromisos.*.fecha_limite' => ['nullable', 'string', 'max:100'],
            'compromisos.*.estatus' => ['nullable', 'string', 'max:30'],
        ], [
            'fecha.required' => 'La fecha de la reunión es obligatoria.',
        ], [
            'hora_inicio' => 'hora de inicio', 'hora_fin' => 'hora de fin',
        ]);

        $fecha = Carbon::parse($v['fecha'])->toDateString();

        return [
            'minute' => [
                'titulo' => filled($v['titulo'] ?? null) ? trim($v['titulo']) : Minute::defaultTitle($fecha),
                'tema' => $v['tema'] ?? null,
                'objetivo' => $v['objetivo'] ?? null,
                'fecha' => $fecha,
                'hora_inicio' => $v['hora_inicio'] ?? null,
                'hora_fin' => $v['hora_fin'] ?? null,
                'lugar' => $v['lugar'] ?? null,
            ],
            'participantes' => collect($v['participantes'] ?? [])->filter(fn ($p) => filled($p['nombre'] ?? null))
                ->map(fn ($p) => [
                    'nombre' => trim($p['nombre']),
                    'puesto' => $p['puesto'] ?? null,
                    'asistio' => (bool) ($p['asistio'] ?? false),
                    'modalidad' => $p['modalidad'] ?? null,
                ])->values()->all(),
            'puntos' => collect($v['puntos'] ?? [])->map(fn ($t) => trim((string) $t))->filter()->values()->all(),
            'acuerdos' => collect($v['acuerdos'] ?? [])->map(fn ($t) => trim((string) $t))->filter()->values()->all(),
            'compromisos' => collect($v['compromisos'] ?? [])->filter(fn ($c) => filled($c['actividad'] ?? null))
                ->map(fn ($c) => [
                    'actividad' => trim($c['actividad']),
                    'responsable' => $c['responsable'] ?? null,
                    'fecha_limite' => $this->normalizeDeadline($c['fecha_limite'] ?? null),
                    'estatus' => $c['estatus'] ?? null,
                ])->values()->all(),
        ];
    }

    /** dd/mm/aaaa → AAAA-MM-DD; cualquier otro texto («Primera quincena de diciembre») se guarda tal cual. */
    private function normalizeDeadline(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        return $v;
    }

    private function syncChildren(Minute $minute, array $data): void
    {
        $minute->participants()->delete();
        $minute->items()->delete();
        $minute->commitments()->delete();

        foreach ($data['participantes'] as $i => $p) {
            $minute->participants()->create($p + ['orden' => $i + 1]);
        }
        foreach (['puntos' => 'punto', 'acuerdos' => 'acuerdo'] as $key => $tipo) {
            foreach ($data[$key] as $i => $texto) {
                $minute->items()->create(['tipo' => $tipo, 'orden' => $i + 1, 'texto' => $texto]);
            }
        }
        foreach ($data['compromisos'] as $i => $c) {
            $minute->commitments()->create($c + ['orden' => $i + 1]);
        }
    }
}
