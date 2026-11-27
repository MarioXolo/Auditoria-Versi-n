<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Campaign;
use App\Models\Community;
use App\Models\Minute;
use App\Models\Section;
use App\Models\StrategyCatalogItem;
use App\Models\StrategyRecord;
use App\Models\User;
use App\Services\CampaignSheets;
use App\Services\PhotoStorage;
use App\Services\StrategyBooks;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Puntos 11 a 18: un solo controlador para todos los libros. Cada libro se define en StrategyBooks;
 * aquí se listan, capturan, guardan sus archivos y se exportan. El permiso es el del punto del libro.
 */
class StrategyController extends Controller
{
    /** Portada de un punto: sus libros como burbujas. */
    public function hub(Request $request, Campaign $campaign, int $punto = 11): View
    {
        $p = StrategyBooks::punto($punto);
        $this->allow($request, $p['module']);
        if ($p['module'] === 'estadisticas') {
            AccessLog::record('estadisticas', 'consulta', 'Portada de estadísticas');
        }

        $counts = $campaign->strategyRecords()->selectRaw('libro, count(*) as n')->groupBy('libro')->pluck('n', 'libro');

        return view('campaigns.strategy.hub', [
            'campaign' => $campaign,
            'punto' => $p,
            'books' => StrategyBooks::ofPunto($punto),
            'counts' => $counts,
            'libros' => (new \App\Services\CuadernoSemaforo($campaign))->porLibro(),
            'casillas' => $punto === 11 ? $campaign->pollingStations()->selectRaw('tipo, count(*) as n')->groupBy('tipo')->pluck('n', 'tipo') : collect(),
            'pendientesIeeq' => $campaign->strategyRecords()->pendientesIeeq()
                ->whereIn('libro', array_keys(StrategyBooks::ofPunto($punto)))->count(),
            'logs' => $p['module'] === 'estadisticas' ? AccessLog::with('user:id,name')->where('module', 'estadisticas')->latest('id')->limit(30)->get() : collect(),
            'gasto' => (float) $campaign->strategyRecords()->whereIn('libro', array_keys(StrategyBooks::ofPunto($punto)))->sum(DB::raw('coalesce(costo_real, costo_estimado, 0)')),
        ]);
    }

    public function index(Request $request, Campaign $campaign, string $libro): View
    {
        $book = $this->bookFor($request, $libro);
        $this->audit($book, 'consulta', 'Listado de '.$book['name']);
        $all = $campaign->strategyRecords()->where('libro', $libro)->get();

        return view('campaigns.strategy.index', [
            'campaign' => $campaign,
            'book' => $book,
            'punto' => StrategyBooks::punto($book['punto']),
            'records' => $this->query($request, $campaign, $libro)->paginate(50)->withQueryString(),
            'summary' => $this->summary($campaign, $book, $all),
            'lookups' => $this->lookups($campaign, $book),
            'communities' => Community::orderBy('name')->get(['id', 'name']),
            'sections' => Section::with('community:id,name')->orderBy('clave')->get(),
            'users' => User::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request, Campaign $campaign, string $libro): View
    {
        $book = $this->bookFor($request, $libro, 'capturar');

        return $this->form($campaign, $book, new StrategyRecord(['libro' => $libro, 'estatus' => 'Pendiente']));
    }

    public function store(Request $request, Campaign $campaign, string $libro): RedirectResponse
    {
        $book = $this->bookFor($request, $libro, 'capturar');
        $record = new StrategyRecord(['libro' => $libro]);
        $record->campaign()->associate($campaign);
        $record->created_by = $request->user()->id;
        $this->fill($request, $campaign, $book, $record);
        $record->save();

        // Cada reunión de un cuarto de guerra genera su minuta en Minutas
        if ($book['minuta'] ?? false) {
            $minute = Minute::create([
                'titulo' => 'Cuarto '.$record->dato('cuarto').': '.$record->dato('tema'),
                'tema' => $record->dato('tema'),
                'fecha' => $record->fecha ?? today(),
                'hora_inicio' => $record->hora,
                'lugar' => $record->dato('lugar'),
                'created_by' => $request->user()->id,
            ]);
            $record->datos = ($record->datos ?? []) + ['minuta' => $minute->id];
            $record->saveQuietly();
        }

        return redirect()->route('campaigns.strategy.index', [$campaign, $libro])
            ->with('status', 'Registro agregado.'.($record->es_evento ? ' Ya aparece en la Agenda IEEQ.' : '').(($book['minuta'] ?? false) ? ' Se creó su minuta en Minutas.' : ''));
    }

    public function edit(Request $request, Campaign $campaign, string $libro, StrategyRecord $record): View
    {
        $book = $this->bookFor($request, $libro);
        $this->ensure($campaign, $libro, $record);
        $this->audit($book, 'consulta', $book['name'].': '.$record->title());

        return $this->form($campaign, $book, $record);
    }

    public function update(Request $request, Campaign $campaign, string $libro, StrategyRecord $record): RedirectResponse
    {
        $book = $this->bookFor($request, $libro, 'capturar');
        $this->ensure($campaign, $libro, $record);
        $this->fill($request, $campaign, $book, $record);
        $record->save();

        return redirect()->route('campaigns.strategy.index', [$campaign, $libro])
            ->with('status', 'Registro actualizado.'.($record->ieeq_estatus === 'Modificado' ? ' Cambió un evento ya reportado: repórtalo de nuevo al IEEQ.' : ''));
    }

    public function destroy(Request $request, Campaign $campaign, string $libro, StrategyRecord $record): RedirectResponse
    {
        $this->bookFor($request, $libro, 'capturar');
        $this->ensure($campaign, $libro, $record);
        foreach ($record->archivos ?? [] as $path) {
            PhotoStorage::delete($path);
        }
        $record->delete();

        return redirect()->route('campaigns.strategy.index', [$campaign, $libro])->with('status', 'Registro eliminado.');
    }

    /** Bitácora de cuartos de guerra: «Subir al cuarto central». */
    public function escalate(Request $request, Campaign $campaign, string $libro, StrategyRecord $record): RedirectResponse
    {
        $book = $this->bookFor($request, $libro, 'capturar');
        $this->ensure($campaign, $libro, $record);
        abort_unless($book['escalate'] ?? false, 404);

        $datos = $record->datos ?? [];
        if (empty($datos['escalado'])) {
            $datos['cuarto_origen'] = $datos['cuarto'] ?? null;
            $datos['cuarto'] = 'Central';
            $datos['escalado'] = true;
            $datos['escalado_por'] = $request->user()->name;
            $datos['escalado_el'] = now()->format('d/m/Y H:i');
            $record->datos = $datos;
            $record->save();
        }

        return back()->with('status', '«'.$record->title().'» se subió al cuarto central.');
    }

    /** Descarga o muestra un archivo del registro (los archivos nunca son públicos). */
    public function file(Request $request, Campaign $campaign, string $libro, StrategyRecord $record, string $campo)
    {
        $book = $this->bookFor($request, $libro);
        $this->ensure($campaign, $libro, $record);
        $path = $record->archivo($campo);
        abort_unless(PhotoStorage::exists($path), 404);
        $this->audit($book, 'archivo', $book['name'].': '.$record->title().' ('.$campo.')');

        return PhotoStorage::response($path);
    }

    /** Formato de autorización para imprimir, firmar y subir (bardas y lonas). */
    public function formato(Request $request, Campaign $campaign, string $libro, ?StrategyRecord $record = null): View
    {
        $book = $this->bookFor($request, $libro);
        abort_unless(isset($book['formato']), 404);
        if ($record) {
            $this->ensure($campaign, $libro, $record);
        }

        return view('campaigns.strategy.formato', ['campaign' => $campaign, 'book' => $book, 'record' => $record]);
    }

    public function export(Request $request, Campaign $campaign, string $libro): StreamedResponse
    {
        $book = $this->bookFor($request, $libro, 'exportar');
        $this->audit($book, 'exportación', 'CSV de '.$book['name']);
        $rows = $this->query($request, $campaign, $libro)->get();
        $own = collect($book['fields'])->reject(fn ($f) => in_array($f['type'], ['file', 'photo', 'minuta'], true));
        $lk = $this->lookups($campaign, $book);

        return response()->streamDownload(function () use ($rows, $own, $book, $lk) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            Csv::put($out, ['#', 'Comunidad', 'Sección', 'Responsable', $book['fecha_label'] ?? 'Fecha', ...(($book['hora'] ?? false) ? ['Hora'] : []),
                'Estatus', 'Costo estimado', 'Costo real', ...$own->pluck('label')->all(), ...(($book['evento'] ?? false) ? ['Agenda IEEQ', 'Fecha límite', 'Folio'] : [])]);
            foreach ($rows as $i => $r) {
                Csv::put($out, [
                    $i + 1, $r->community?->name, $r->section?->clave, $r->responsable?->name, $r->fecha?->format('d/m/Y'),
                    ...(($book['hora'] ?? false) ? [$r->horaCorta()] : []),
                    $r->estatus, $r->costo_estimado, $r->costo_real,
                    ...$own->map(fn ($f, $k) => self::display($f, $r->dato($k), $lk))->values()->all(),
                    ...(($book['evento'] ?? false) ? [$r->ieeq_estatus, $r->ieeq_fecha_limite?->format('d/m/Y'), $r->ieeq_folio] : []),
                ]);
            }
            fclose($out);
        }, 'cuaderno-'.$libro.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Valor legible de un campo propio ($lk: nombres de casillas, promotores, comunidades, propuestas y mensajes). */
    public static function display(array $field, mixed $value, array $lk = []): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        return match ($field['type']) {
            'int' => number_format((int) $value),
            'money' => CampaignSheets::money((float) $value),
            'date' => \Carbon\Carbon::parse($value)->format('d/m/Y'),
            'bool' => $value ? 'Sí' : 'No',
            'multiselect' => collect((array) $value)->map(fn ($v) => StrategyBooks::options($field)[$v] ?? $v)->join(', '),
            'communities' => collect((array) $value)->map(fn ($id) => $lk['communities'][$id] ?? '¿?')->join(', '),
            'casilla', 'promotor', 'propuesta', 'mensaje' => $lk[$field['type']][$value] ?? '(eliminado)',
            'minuta' => 'Minuta #'.$value,
            default => (string) $value,
        };
    }

    // ── Privados ────────────────────────────────────────────────────────────────

    /** Libro + permiso del punto al que pertenece. */
    private function bookFor(Request $request, string $libro, string $action = 'ver'): array
    {
        $book = StrategyBooks::get($libro);
        $this->allow($request, StrategyBooks::module($libro), $action);

        return $book;
    }

    private function allow(Request $request, string $module, string $action = 'ver'): void
    {
        abort_unless($request->user()->puede($module, $action), 403, 'Tu perfil no tiene permiso para esto.');
    }

    /** Punto 16: registro de quién consulta o exporta. */
    private function audit(array $book, string $action, string $detail): void
    {
        if (StrategyBooks::PUNTOS[$book['punto']][2] === 'estadisticas') {
            AccessLog::record('estadisticas', $action, $detail);
        }
    }

    private function form(Campaign $campaign, array $book, StrategyRecord $record): View
    {
        return view('campaigns.strategy.form', [
            'campaign' => $campaign,
            'book' => $book,
            'punto' => StrategyBooks::punto($book['punto']),
            'record' => $record,
            'lookups' => $this->lookups($campaign, $book, $record),
            'communities' => Community::active()->orWhere('id', $record->community_id)->orderBy('name')->get(['id', 'name']),
            'sections' => Section::with('community:id,name')->where(fn ($q) => $q->where('active', true)->orWhere('id', $record->section_id))->orderBy('clave')->get(),
            'users' => User::where('active', true)->orWhere('id', $record->responsable_id)->orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }

    /**
     * Opciones de los campos que apuntan a otras tablas. Para «mensaje» solo se ofrecen los aprobados
     * o en uso (más el que ya tenga el registro, para no perderlo si después se retiró).
     */
    private function lookups(Campaign $campaign, array $book, ?StrategyRecord $record = null): array
    {
        $types = collect($book['fields'])->pluck('type')->unique()->all();
        $lk = [];
        if (in_array('casilla', $types, true)) {
            $lk['casilla'] = $campaign->pollingStations()->orderBy('seccion')->orderBy('casilla')->get(['id', 'seccion', 'casilla', 'tipo'])
                ->mapWithKeys(fn ($c) => [$c->id => trim($c->seccion.' '.$c->casilla).($c->tipo && $c->tipo !== 'Normal' ? ' ('.$c->tipo.')' : '')])->all();
        }
        if (in_array('promotor', $types, true)) {
            $lk['promotor'] = $campaign->promoters()->orderBy('name')->pluck('name', 'id')->all();
        }
        if (in_array('communities', $types, true)) {
            $lk['communities'] = Community::orderBy('name')->pluck('name', 'id')->all();
        }
        if (in_array('propuesta', $types, true)) {
            $lk['propuesta'] = $campaign->strategyRecords()->where('libro', 'propuestas')->get()->mapWithKeys(fn ($p) => [$p->id => $p->title()])->all();
        }
        if (in_array('mensaje', $types, true)) {
            $all = $campaign->strategyRecords()->where('libro', 'mensajes')->get();
            $lk['mensaje'] = $all->mapWithKeys(fn ($m) => [$m->id => mb_strimwidth((string) $m->dato('texto'), 0, 90, '…')])->all();
            $current = $record ? collect($book['fields'])->filter(fn ($f) => $f['type'] === 'mensaje')->keys()->map(fn ($k) => $record->dato($k))->filter()->all() : [];
            $lk['mensaje_ok'] = $all->filter(fn ($m) => in_array($m->dato('estatus_mensaje'), ['Aprobado', 'En uso'], true) || in_array($m->id, $current, true))
                ->mapWithKeys(fn ($m) => [$m->id => mb_strimwidth((string) $m->dato('texto'), 0, 90, '…')])->all();
        }
        foreach ($book['fields'] as $k => $f) {
            if ($f['type'] === 'catalog') {
                $lk['catalog'][$k] = StrategyCatalogItem::names($f['catalog'], $record?->dato($k));
            }
        }

        return $lk;
    }

    private function ensure(Campaign $campaign, string $libro, StrategyRecord $record): void
    {
        abort_unless($record->campaign_id === $campaign->id && $record->libro === $libro, 404);
    }

    private function query(Request $request, Campaign $campaign, string $libro): Builder
    {
        $book = StrategyBooks::get($libro);

        return $campaign->strategyRecords()->getQuery()
            ->where('libro', $libro)
            ->with(['community:id,name', 'section:id,clave', 'responsable:id,name'])
            ->when($request->filled('community_id'), fn ($q) => $q->where('community_id', $request->integer('community_id')))
            ->when($request->filled('section_id'), fn ($q) => $q->where('section_id', $request->integer('section_id')))
            ->when($request->filled('responsable_id'), fn ($q) => $q->where('responsable_id', $request->integer('responsable_id')))
            ->when(in_array($request->input('estatus'), StrategyBooks::ESTATUS, true), fn ($q) => $q->where('estatus', $request->input('estatus')))
            ->when($request->input('estatus') === 'vencidos', fn ($q) => $q->vencidos())
            ->when($request->input('estatus') === 'por_vencer', fn ($q) => $q->porVencer())
            ->when($request->filled('q'), function ($q) use ($request, $book) {
                $term = '%'.$request->string('q').'%';
                $text = collect($book['fields'])->filter(fn ($f) => in_array($f['type'], ['text', 'longtext', 'catalog', 'select'], true))->keys();
                $q->where(fn ($w) => $text->each(fn ($k) => $w->orWhere("datos->{$k}", 'like', $term)));
            })
            ->orderByRaw('fecha is null')
            ->orderByDesc('fecha')
            ->orderByDesc('hora')
            ->orderByDesc('id');
    }

    /** Tarjetas de resumen: registros, costos, totales propios del libro y porcentajes. */
    private function summary(Campaign $campaign, array $book, Collection $all): array
    {
        // Estampida: los totales salen del último corte de cada casilla/promotor, no de la suma de todos los cortes
        $base = $all;
        if (isset($book['latest_by'])) {
            $base = $all->sortByDesc(fn ($r) => ($r->fecha?->format('Y-m-d') ?? '').' '.($r->hora ?? '').' '.str_pad((string) $r->id, 10, '0', STR_PAD_LEFT))
                ->unique(fn ($r) => collect($book['latest_by'])->map(fn ($k) => $r->dato($k))->join('|'));
        }
        $sum = function (string $k) use ($base, $book, $campaign) {
            // Conteo propio: si la casilla no trae meta, se toma la del Plan por casilla
            if ($book['key'] === 'dd_conteo' && $k === 'meta') {
                $plan = $campaign->strategyRecords()->where('libro', 'dd_plan')->get()->mapWithKeys(fn ($p) => [$p->dato('casilla') => (int) $p->dato('meta_votos')]);

                return $base->sum(fn ($r) => (int) ($r->dato('meta') ?? $plan[$r->dato('casilla')] ?? 0));
            }

            return $base->sum(fn ($r) => (int) $r->dato($k));
        };

        $cards = [
            ['Registros', number_format($all->count()), number_format($all->where('estatus', 'Hecho')->count()).' hechos · '.number_format($all->where('estatus', 'En curso')->count()).' en curso'],
        ];
        foreach ($book['ratios'] ?? [] as [$label, $num, $den]) {
            $d = $sum($den);
            $cards[] = [$label, $d > 0 ? number_format($sum($num) / $d * 100, 1).' %' : '—', number_format($sum($num)).' de '.number_format($d)];
        }
        foreach ($book['totals'] ?? [] as $k) {
            $cards[] = [($book['fields'][$k]['label'] ?? $k).(isset($book['latest_by']) ? ' (último corte)' : ''), number_format($sum($k))];
        }
        if ($book['evento'] ?? false) {
            $pend = $all->filter(fn ($r) => in_array($r->ieeq_estatus, ['Por reportar', 'Modificado'], true))->count();
            $cards[] = ['Por reportar al IEEQ', number_format($pend), number_format($all->where('ieeq_estatus', 'Reportado')->count()).' reportados'];
        }
        $cards[] = ['Costo estimado', CampaignSheets::money((float) $all->sum('costo_estimado')), 'Real: '.CampaignSheets::money((float) $all->sum('costo_real'))];

        return array_slice($cards, 0, 4);
    }

    /** Valida y asigna campos comunes, propios y archivos. */
    private function fill(Request $request, Campaign $campaign, array $book, StrategyRecord $record): void
    {
        $evento = $book['evento'] ?? false;
        $rules = [
            'community_id' => [($book['community'] ?? null) === 'optional' ? 'nullable' : 'required', 'integer', 'exists:communities,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'responsable_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'fecha' => array_values(array_filter([$evento || isset($book['fecha_max']) ? 'required' : 'nullable', 'date', isset($book['fecha_max']) ? 'before_or_equal:'.$book['fecha_max'] : null])),
            'hora' => [($book['hora'] ?? false) === 'required' ? 'required' : 'nullable', 'date_format:H:i'],
            'estatus' => ['required', Rule::in(StrategyBooks::ESTATUS)],
            'costo_estimado' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'costo_real' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'foto' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,heic,heif'],
            'archivo' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,heic,heif,doc,docx,xls,xlsx'],
        ];
        $attributes = ['community_id' => 'comunidad', 'section_id' => 'sección', 'responsable_id' => 'responsable', 'fecha' => mb_strtolower($book['fecha_label'] ?? 'fecha'), 'costo_estimado' => 'costo estimado', 'costo_real' => 'costo real'];

        if ($book['mapa'] ?? false) {
            $rules['lat'] = ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'];
            $rules['lng'] = ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'];
        }

        $lk = $this->lookups($campaign, $book, $record->exists ? $record : null);
        foreach ($book['fields'] as $k => $f) {
            $req = ($f['required'] ?? false) ? 'required' : 'nullable';
            $attributes["d.$k"] = mb_strtolower($f['label']);
            match ($f['type']) {
                'text' => $rules["d.$k"] = [$req, 'string', 'max:'.($f['max'] ?? 255)],
                'longtext' => $rules["d.$k"] = [$req, 'string', 'max:'.($f['max'] ?? 2000)],
                'url' => $rules["d.$k"] = [$req, 'url:http,https', 'max:500'],
                'int' => $rules["d.$k"] = [$req, 'integer', 'min:0', 'max:100000000'],
                'money' => $rules["d.$k"] = [$req, 'numeric', 'min:0', 'max:999999999999'],
                'date' => $rules["d.$k"] = [$req, 'date'],
                'bool' => $rules["d.$k"] = ['nullable', 'boolean'],
                'select' => $rules["d.$k"] = [$req, Rule::in(array_keys(StrategyBooks::options($f)))],
                'multiselect' => [$rules["d.$k"] = ['nullable', 'array'], $rules["d.$k.*"] = [Rule::in(array_keys(StrategyBooks::options($f)))]],
                'communities' => [$rules["d.$k"] = ['nullable', 'array'], $rules["d.$k.*"] = ['integer', 'exists:communities,id']],
                'casilla' => $rules["d.$k"] = [$req, Rule::in(array_keys($lk['casilla'] ?? []))],
                'promotor' => $rules["d.$k"] = [$req, Rule::in(array_keys($lk['promotor'] ?? []))],
                'propuesta' => $rules["d.$k"] = [$req, Rule::in(array_keys($lk['propuesta'] ?? []))],
                'mensaje' => $rules["d.$k"] = [$req, Rule::in(array_keys($lk['mensaje_ok'] ?? []))],
                'catalog' => [$rules["d.$k"] = [$req, 'string', 'max:120'], $rules["nuevo.$k"] = ['nullable', 'string', 'max:120']],
                'photo' => $rules["f.$k"] = ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,heic,heif'],
                'file' => $rules["f.$k"] = ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,heic,heif,doc,docx'],
                'readonly', 'minuta' => null,
            };
            if (in_array($f['type'], ['photo', 'file'], true)) {
                $attributes["f.$k"] = mb_strtolower($f['label']);
            }
        }

        // Catálogo ampliable: «Otro…» + nombre nuevo
        $otros = [];
        foreach ($book['fields'] as $k => $f) {
            if ($f['type'] === 'catalog' && $request->input("d.$k") === '__otro') {
                $otros[] = $k;
                $request->merge(['d' => array_merge((array) $request->input('d'), [$k => trim((string) $request->input("nuevo.$k"))])]);
            }
        }

        $v = $request->validate($rules, [
            'community_id.required' => 'Elige la comunidad.',
            'responsable_id.required' => 'Elige al responsable.',
            'fecha.before_or_equal' => 'La fecha no puede pasar del '.(isset($book['fecha_max']) ? \Carbon\Carbon::parse($book['fecha_max'])->format('d/m/Y') : '').'.',
        ], $attributes);

        // La sección debe ser de la comunidad elegida (si la sección tiene comunidad)
        if (! empty($v['section_id']) && ! empty($v['community_id'])) {
            $sec = Section::find($v['section_id']);
            if ($sec->community_id && $sec->community_id !== (int) $v['community_id']) {
                throw ValidationException::withMessages(['section_id' => "La sección {$sec->clave} no es de esa comunidad."]);
            }
        }

        // Catálogos: un valor nuevo se agrega al catálogo; uno que no existe se rechaza
        foreach ($book['fields'] as $k => $f) {
            $val = $v['d'][$k] ?? null;
            if ($f['type'] !== 'catalog' || blank($val)) {
                continue;
            }
            if (in_array($k, $otros, true)) {
                StrategyCatalogItem::firstOrCreate(['catalog' => $f['catalog'], 'name' => $val]);
            } elseif (! in_array($val, $lk['catalog'][$k] ?? [], true) && ! StrategyCatalogItem::where('catalog', $f['catalog'])->where('name', $val)->exists()) {
                throw ValidationException::withMessages(["d.$k" => 'Elige una opción de la lista o «Otro…» para agregar una nueva.']);
            }
        }

        // Mensajes: solo quien puede «revisar» los aprueba; se registra quién lo aprobó
        if ($approval = $book['approval'] ?? null) {
            $before = $record->exists ? $record->dato($approval) : null;
            $after = $v['d'][$approval] ?? null;
            $needsReview = in_array($after, ['Aprobado', 'En uso'], true) && $after !== $before;
            if ($needsReview && ! $request->user()->puede(StrategyBooks::module($book['key']), 'revisar')) {
                throw ValidationException::withMessages(["d.$approval" => 'Tu perfil no puede aprobar mensajes: déjalo en «Borrador» para que lo revise quien tenga permiso.']);
            }
        }

        $record->fill([
            'community_id' => isset($v['community_id']) ? (int) $v['community_id'] : null,
            'section_id' => $v['section_id'] ?? null,
            'responsable_id' => (int) $v['responsable_id'],
            'fecha' => $v['fecha'] ?? null,
            'hora' => ($book['hora'] ?? false) ? ($v['hora'] ?? null) : null,
            'estatus' => $v['estatus'],
            'costo_estimado' => $v['costo_estimado'] ?? null,
            'costo_real' => $v['costo_real'] ?? null,
        ]);
        if ($book['mapa'] ?? false) {
            $record->lat = isset($v['lat']) ? round((float) $v['lat'], 7) : null;
            $record->lng = isset($v['lng']) ? round((float) $v['lng'], 7) : null;
        }

        $old = $record->datos ?? [];
        $datos = [];
        foreach ($book['fields'] as $k => $f) {
            $raw = $v['d'][$k] ?? null;
            $datos[$k] = match ($f['type']) {
                'text', 'longtext', 'url', 'catalog' => filled($raw) ? trim($raw) : null,
                'int', 'casilla', 'promotor', 'propuesta', 'mensaje' => $raw === null || $raw === '' ? null : (int) $raw,
                'money' => $raw === null ? null : (float) $raw,
                'bool' => $request->boolean("d.$k"),
                'multiselect' => array_values(array_unique((array) $raw)),
                'communities' => array_values(array_unique(array_map('intval', (array) $raw))),
                'photo', 'file' => null,
                'readonly', 'minuta' => $old[$k] ?? null,
                default => $raw ?? ($f['default'] ?? null),
            };
        }
        // Datos que guarda el sistema (subido al central, quién aprobó…) se conservan
        foreach (['cuarto_origen', 'escalado_por', 'escalado_el', 'aprobado_el'] as $sys) {
            if (isset($old[$sys])) {
                $datos[$sys] = $old[$sys];
            }
        }
        if (isset($approval) && $approval) {
            $after = $datos[$approval] ?? null;
            if (in_array($after, ['Aprobado', 'En uso'], true) && $after !== ($old[$approval] ?? null) && ($after === 'Aprobado' || empty($old['aprobado_por']))) {
                $datos['aprobado_por'] = $request->user()->name;
                $datos['aprobado_el'] = now()->format('d/m/Y H:i');
            }
        }
        $record->datos = array_filter($datos, fn ($x) => $x !== null);

        // Archivos: foto y archivo comunes + los del libro. «quitar[campo]» borra el actual.
        $archivos = $record->archivos ?? [];
        $dir = 'cuaderno/'.$record->campaign_id.'/'.$book['key'];
        $slots = ['foto' => $request->file('foto'), 'archivo' => $request->file('archivo')];
        foreach ($book['fields'] as $k => $f) {
            if (in_array($f['type'], ['photo', 'file'], true)) {
                $slots[$k] = $request->file("f.$k");
            }
        }
        foreach ($slots as $k => $upload) {
            if ($upload || $request->boolean("quitar.$k")) {
                PhotoStorage::delete($archivos[$k] ?? null);
                unset($archivos[$k]);
            }
            if ($upload) {
                $archivos[$k] = PhotoStorage::store($upload, $dir);
            }
        }
        $record->archivos = $archivos ?: null;
    }
}
