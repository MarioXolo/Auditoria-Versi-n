<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\CampaignMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cuaderno Maestro de Campaña y su calculadora de «Meta de Votos». Solo administradores
 * y gerentes. El sistema lleva UN solo cuaderno: la entrada del menú abre directamente
 * el que existe y, si aún no hay, pide configurarlo una única vez.
 * Los porcentajes se capturan como 55 (= 55 %) y se guardan como fracción (0.55).
 */
class CampaignController extends Controller
{
    private const PERCENT_FIELDS = ['participacion', 'objetivo', 'colchon'];

    /** Entrada del menú: abre el cuaderno, o su configuración inicial si aún no existe. */
    public function index(): RedirectResponse
    {
        $campaign = Campaign::current();

        return $campaign ? redirect()->route('campaigns.show', $campaign) : redirect()->route('campaigns.create');
    }

    public function create(): View|RedirectResponse
    {
        if ($campaign = Campaign::current()) {
            return redirect()->route('campaigns.show', $campaign);
        }

        return view('campaigns.create', ['campaign' => new Campaign([
            'listado_nominal' => 0, 'participacion' => 0.55, 'objetivo' => 0.5, 'colchon' => 0.1, 'votantes_por_promotor' => 10,
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($campaign = Campaign::current()) {
            return redirect()->route('campaigns.show', $campaign)->withErrors(['name' => 'El Cuaderno Maestro ya está configurado; solo se lleva uno.']);
        }

        $campaign = Campaign::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('campaigns.show', $campaign)
            ->with('status', 'Cuaderno Maestro listo. Entra a cada burbuja para ir llenándolo; puedes empezar por «2. Estructura Territorial».');
    }

    public function show(Campaign $campaign): View
    {
        $metrics = new CampaignMetrics($campaign);

        $summary = $metrics->summary();

        $steps = self::bubbles($campaign, $summary);

        return view('campaigns.show', [
            'campaign' => $campaign,
            'summary' => $summary,
            'kpis' => $metrics->kpis()->take(3),
            'steps' => $steps,
        ]);
    }

    public function edit(Campaign $campaign): View
    {
        return view('campaigns.edit', compact('campaign'));
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $campaign->update($this->validated($request));

        return redirect()->route('campaigns.show', $campaign)->with('status', 'Meta de votos actualizada.');
    }

    /**
     * Las 10 hojas del cuaderno (mismo índice que el Excel) como burbujas:
     * [número, título, descripción, URL, ¿ya tiene información?, dato de avance].
     */
    public static function bubbles(Campaign $campaign, ?object $summary = null): array
    {
        $summary ??= (new CampaignMetrics($campaign))->summary();
        $n = fn ($v) => number_format($v);
        $count = fn (int $c, string $one, string $many) => $n($c).' '.($c === 1 ? $one : $many);
        $sheet = fn (string $key) => route('campaigns.sheets.index', [$campaign, $key]);
        $actores = $campaign->actors()->count();
        $casillas = $campaign->pollingStations()->count();
        $tareas = $campaign->tasks()->count();
        $hechas = $campaign->tasks()->where('estatus', 'Hecho')->count();
        $logros = $campaign->achievements()->count();
        $kpis = $campaign->kpis()->where('valor', '>', 0)->count();

        return [
            [1, 'Meta de Votos', 'Calculadora: define tu número objetivo y cuántos promotores necesitas. Empieza aquí.', route('campaigns.edit', $campaign), $campaign->listado_nominal > 0, 'Meta: '.$n(round($campaign->metaConColchon())).' votos'],
            [2, 'Estructura Territorial', 'Metas y avance por delegación.', route('campaigns.territories.index', $campaign), $summary->comunidades > 0, $count($summary->comunidades, 'comunidad', 'comunidades')],
            [3, 'Base de Votantes', 'El padrón nominal propio: votante por votante identificado.', route('campaigns.voters.index', $campaign), $summary->votantes > 0, $count($summary->votantes, 'votante', 'votantes')],
            [4, 'Padrón de Promotores', 'Tu ejército: quién es, a cuántos moviliza, avance.', route('campaigns.promoters.index', $campaign), $summary->promotores > 0, $count($summary->promotores, 'promotor', 'promotores')],
            [5, 'Mapeo de Actores', 'Liderazgos por comunidad: afines, neutrales, adversos.', $sheet('actores'), $actores > 0, $count($actores, 'actor', 'actores')],
            [6, 'Defensa del Voto', 'Control de representantes de casilla (RC) y generales (RG).', $sheet('defensa-del-voto'), $casillas > 0, $count($casillas, 'casilla', 'casillas')],
            [7, 'Presupuesto', 'Rubros vs. tope legal, gasto ejercido y disponible.', $sheet('presupuesto'), $campaign->tope_gastos > 0, $campaign->tope_gastos > 0 ? 'Tope: '.\App\Services\CampaignSheets::money($campaign->tope_gastos) : 'Falta el tope'],
            [8, 'Cronograma', 'Fases y tareas rumbo a la jornada.', $sheet('cronograma'), $tareas > 0, $tareas > 0 ? $n($hechas).' de '.$count($tareas, 'tarea hecha', 'tareas hechas') : '0 tareas'],
            [9, 'Tablero KPI', 'Seguimiento semanal de los indicadores clave.', route('campaigns.kpis.index', $campaign), $kpis > 0, 'Semáforo de indicadores'],
            [10, 'Logros Comunicables', 'Inventario de obra del gobierno, munición para el mensaje.', $sheet('logros'), $logros > 0, $count($logros, 'logro', 'logros')],
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'municipality' => ['required', 'string', 'max:120'],
            'election_date' => ['nullable', 'date'],
            'listado_nominal' => ['required', 'integer', 'min:0', 'max:50000000'],
            'participacion' => ['required', 'numeric', 'min:0', 'max:100'],
            'objetivo' => ['required', 'numeric', 'min:0', 'max:100'],
            'colchon' => ['required', 'numeric', 'min:0', 'max:100'],
            'votantes_por_promotor' => ['required', 'integer', 'min:1', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'name' => 'nombre', 'municipality' => 'municipio', 'election_date' => 'fecha de la elección',
            'listado_nominal' => 'listado nominal', 'participacion' => 'participación', 'objetivo' => '% objetivo',
            'colchon' => 'colchón', 'votantes_por_promotor' => 'votantes por promotor', 'notes' => 'notas',
        ]);

        foreach (self::PERCENT_FIELDS as $field) {
            $data[$field] = round($data[$field] / 100, 4);
        }

        return $data;
    }
}
