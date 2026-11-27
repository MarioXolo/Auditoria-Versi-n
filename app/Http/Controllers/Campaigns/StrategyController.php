<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Community;
use App\Models\Section;
use App\Models\StrategyRecord;
use App\Models\User;
use App\Services\CampaignSheets;
use App\Services\PhotoStorage;
use App\Services\StrategyBooks;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * «11. Estrategia de campaña»: un solo controlador para todos los libros. Cada libro se
 * define en StrategyBooks; aquí se listan, capturan, guardan sus archivos y se exportan.
 */
class StrategyController extends Controller
{
    /** Portada de Estrategia: los libros como burbujas. */
    public function hub(Campaign $campaign): View
    {
        $counts = $campaign->strategyRecords()->selectRaw('libro, count(*) as n')->groupBy('libro')->pluck('n', 'libro');
        $casillas = $campaign->pollingStations()->selectRaw('tipo, count(*) as n')->groupBy('tipo')->pluck('n', 'tipo');

        return view('campaigns.strategy.hub', [
            'campaign' => $campaign,
            'counts' => $counts,
            'casillas' => $casillas,
            'pendientesIeeq' => $campaign->strategyRecords()->pendientesIeeq()->count(),
        ]);
    }

    public function index(Request $request, Campaign $campaign, string $libro): View
    {
        $book = StrategyBooks::get($libro);
        $all = $campaign->strategyRecords()->where('libro', $libro)->get();

        return view('campaigns.strategy.index', [
            'campaign' => $campaign,
            'book' => $book,
            'records' => $this->query($request, $campaign, $libro)->paginate(50)->withQueryString(),
            'summary' => $this->summary($book, $all),
            'communities' => Community::orderBy('name')->get(['id', 'name']),
            'sections' => Section::with('community:id,name')->orderBy('clave')->get(),
            'users' => User::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Campaign $campaign, string $libro): View
    {
        return $this->form($campaign, StrategyBooks::get($libro), new StrategyRecord(['libro' => $libro, 'estatus' => 'Pendiente']));
    }

    public function store(Request $request, Campaign $campaign, string $libro): RedirectResponse
    {
        $book = StrategyBooks::get($libro);
        $record = new StrategyRecord(['libro' => $libro]);
        $record->campaign()->associate($campaign);
        $record->created_by = $request->user()->id;
        $this->fill($request, $book, $record);
        $record->save();

        return redirect()->route('campaigns.strategy.index', [$campaign, $libro])
            ->with('status', 'Registro agregado.'.($record->es_evento ? ' Ya aparece en la Agenda IEEQ.' : ''));
    }

    public function edit(Campaign $campaign, string $libro, StrategyRecord $record): View
    {
        $this->ensure($campaign, $libro, $record);

        return $this->form($campaign, StrategyBooks::get($libro), $record);
    }

    public function update(Request $request, Campaign $campaign, string $libro, StrategyRecord $record): RedirectResponse
    {
        $this->ensure($campaign, $libro, $record);
        $this->fill($request, StrategyBooks::get($libro), $record);
        $record->save();

        return redirect()->route('campaigns.strategy.index', [$campaign, $libro])
            ->with('status', 'Registro actualizado.'.($record->ieeq_estatus === 'Modificado' ? ' Cambió un evento ya reportado: repórtalo de nuevo al IEEQ.' : ''));
    }

    public function destroy(Campaign $campaign, string $libro, StrategyRecord $record): RedirectResponse
    {
        $this->ensure($campaign, $libro, $record);
        foreach ($record->archivos ?? [] as $path) {
            PhotoStorage::delete($path);
        }
        $record->delete();

        return redirect()->route('campaigns.strategy.index', [$campaign, $libro])->with('status', 'Registro eliminado.');
    }

    /** Descarga o muestra un archivo del registro (los archivos nunca son públicos). */
    public function file(Campaign $campaign, string $libro, StrategyRecord $record, string $campo)
    {
        $this->ensure($campaign, $libro, $record);
        $path = $record->archivo($campo);
        abort_unless(PhotoStorage::exists($path), 404);

        return PhotoStorage::response($path);
    }

    /** Formato de autorización para imprimir, firmar y subir (bardas y lonas). */
    public function formato(Campaign $campaign, string $libro, ?StrategyRecord $record = null): View
    {
        $book = StrategyBooks::get($libro);
        abort_unless(isset($book['formato']), 404);
        if ($record) {
            $this->ensure($campaign, $libro, $record);
        }

        return view('campaigns.strategy.formato', ['campaign' => $campaign, 'book' => $book, 'record' => $record]);
    }

    public function export(Request $request, Campaign $campaign, string $libro): StreamedResponse
    {
        $book = StrategyBooks::get($libro);
        $rows = $this->query($request, $campaign, $libro)->get();
        $own = collect($book['fields'])->reject(fn ($f) => in_array($f['type'], ['file', 'photo'], true));

        return response()->streamDownload(function () use ($rows, $own, $book) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            Csv::put($out, ['#', 'Comunidad', 'Sección', 'Responsable', $book['fecha_label'] ?? 'Fecha', ...(($book['hora'] ?? false) ? ['Hora'] : []),
                'Estatus', 'Costo estimado', 'Costo real', ...$own->pluck('label')->all(), ...(($book['evento'] ?? false) ? ['Agenda IEEQ', 'Fecha límite', 'Folio'] : [])]);
            foreach ($rows as $i => $r) {
                Csv::put($out, [
                    $i + 1, $r->community?->name, $r->section?->clave, $r->responsable?->name, $r->fecha?->format('d/m/Y'),
                    ...(($book['hora'] ?? false) ? [$r->horaCorta()] : []),
                    $r->estatus, $r->costo_estimado, $r->costo_real,
                    ...$own->map(fn ($f, $k) => self::display($f, $r->dato($k)))->values()->all(),
                    ...(($book['evento'] ?? false) ? [$r->ieeq_estatus, $r->ieeq_fecha_limite?->format('d/m/Y'), $r->ieeq_folio] : []),
                ]);
            }
            fclose($out);
        }, 'estrategia-'.$libro.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Valor legible de un campo propio. */
    public static function display(array $field, mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        return match ($field['type']) {
            'int' => number_format((int) $value),
            'money' => CampaignSheets::money((float) $value),
            'date' => \Carbon\Carbon::parse($value)->format('d/m/Y'),
            'multiselect' => collect((array) $value)->map(fn ($v) => StrategyBooks::options($field)[$v] ?? $v)->join(', '),
            default => (string) $value,
        };
    }

    // ── Privados ────────────────────────────────────────────────────────────────

    private function form(Campaign $campaign, array $book, StrategyRecord $record): View
    {
        return view('campaigns.strategy.form', [
            'campaign' => $campaign,
            'book' => $book,
            'record' => $record,
            'communities' => Community::active()->orWhere('id', $record->community_id)->orderBy('name')->get(['id', 'name']),
            'sections' => Section::with('community:id,name')->where(fn ($q) => $q->where('active', true)->orWhere('id', $record->section_id))->orderBy('clave')->get(),
            'users' => User::where('active', true)->orWhere('id', $record->responsable_id)->orderBy('name')->get(['id', 'name', 'role']),
        ]);
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
            ->when($request->filled('q'), function ($q) use ($request, $book) {
                $term = '%'.$request->string('q').'%';
                $text = collect($book['fields'])->filter(fn ($f) => in_array($f['type'], ['text', 'longtext'], true))->keys();
                $q->where(fn ($w) => $text->each(fn ($k) => $w->orWhere("datos->{$k}", 'like', $term)));
            })
            ->orderByRaw('fecha is null')
            ->orderByDesc('fecha')
            ->orderByDesc('id');
    }

    /** Tarjetas de resumen: registros, hechos, costos y los totales propios del libro. */
    private function summary(array $book, $all): array
    {
        $cards = [
            ['Registros', number_format($all->count()), number_format($all->where('estatus', 'Hecho')->count()).' hechos · '.number_format($all->where('estatus', 'En curso')->count()).' en curso'],
            ['Costo estimado', CampaignSheets::money((float) $all->sum('costo_estimado')), 'Real: '.CampaignSheets::money((float) $all->sum('costo_real'))],
        ];
        foreach ($book['totals'] ?? [] as $k) {
            $cards[] = [$book['fields'][$k]['label'], number_format($all->sum(fn ($r) => (int) $r->dato($k)))];
        }
        if ($book['evento'] ?? false) {
            $pend = $all->filter(fn ($r) => in_array($r->ieeq_estatus, ['Por reportar', 'Modificado'], true))->count();
            $cards[] = ['Por reportar al IEEQ', number_format($pend), number_format($all->where('ieeq_estatus', 'Reportado')->count()).' reportados'];
        }

        return array_slice($cards, 0, 4);
    }

    /** Valida y asigna campos comunes, propios y archivos. */
    private function fill(Request $request, array $book, StrategyRecord $record): void
    {
        $rules = [
            'community_id' => ['required', 'integer', 'exists:communities,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'responsable_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'fecha' => [($book['evento'] ?? false) ? 'required' : 'nullable', 'date'],
            'hora' => ['nullable', 'date_format:H:i'],
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

        foreach ($book['fields'] as $k => $f) {
            $req = ($f['required'] ?? false) ? 'required' : 'nullable';
            $attributes["d.$k"] = mb_strtolower($f['label']);
            match ($f['type']) {
                'text' => $rules["d.$k"] = [$req, 'string', 'max:'.($f['max'] ?? 255)],
                'longtext' => $rules["d.$k"] = [$req, 'string', 'max:'.($f['max'] ?? 2000)],
                'int' => $rules["d.$k"] = [$req, 'integer', 'min:0', 'max:100000000'],
                'money' => $rules["d.$k"] = [$req, 'numeric', 'min:0', 'max:999999999999'],
                'date' => $rules["d.$k"] = [$req, 'date'],
                'select' => $rules["d.$k"] = [$req, Rule::in(array_keys(StrategyBooks::options($f)))],
                'multiselect' => [$rules["d.$k"] = ['nullable', 'array'], $rules["d.$k.*"] = [Rule::in(array_keys(StrategyBooks::options($f)))]],
                'photo' => $rules["f.$k"] = ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,heic,heif'],
                'file' => $rules["f.$k"] = ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,heic,heif,doc,docx'],
            };
            if (in_array($f['type'], ['photo', 'file'], true)) {
                $attributes["f.$k"] = mb_strtolower($f['label']);
            }
        }

        $v = $request->validate($rules, [
            'community_id.required' => 'Elige la comunidad.',
            'responsable_id.required' => 'Elige al responsable.',
        ], $attributes);

        // La sección debe ser de la comunidad elegida (si la sección tiene comunidad)
        if (! empty($v['section_id'])) {
            $sec = Section::find($v['section_id']);
            if ($sec->community_id && $sec->community_id !== (int) $v['community_id']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['section_id' => "La sección {$sec->clave} no es de esa comunidad."]);
            }
        }

        $record->fill([
            'community_id' => (int) $v['community_id'],
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

        $datos = [];
        foreach ($book['fields'] as $k => $f) {
            $raw = $v['d'][$k] ?? null;
            $datos[$k] = match ($f['type']) {
                'text', 'longtext' => filled($raw) ? trim($raw) : null,
                'int' => $raw === null ? null : (int) $raw,
                'money' => $raw === null ? null : (float) $raw,
                'multiselect' => array_values(array_unique((array) $raw)),
                'photo', 'file' => null,
                default => $raw ?? ($f['default'] ?? null),
            };
        }
        $record->datos = array_filter($datos, fn ($x) => $x !== null);

        // Archivos: foto y archivo comunes + los del libro. «quitar[campo]» borra el actual.
        $archivos = $record->archivos ?? [];
        $dir = 'estrategia/'.$record->campaign_id.'/'.$book['key'];
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
