<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\CampaignMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Campañas y su calculadora de «Meta de Votos». Solo administradores y gerentes.
 * Los porcentajes se capturan como 55 (= 55 %) y se guardan como fracción (0.55).
 */
class CampaignController extends Controller
{
    private const PERCENT_FIELDS = ['participacion', 'objetivo', 'colchon'];

    public function index(): View
    {
        $campaigns = Campaign::withCount(['voters', 'promoters', 'territories'])
            ->orderByDesc('active')->orderByDesc('election_date')->orderBy('name')->get();

        return view('campaigns.index', compact('campaigns'));
    }

    public function create(): View
    {
        return view('campaigns.create', ['campaign' => new Campaign([
            'listado_nominal' => 0, 'participacion' => 0.55, 'objetivo' => 0.5, 'colchon' => 0.1, 'votantes_por_promotor' => 10,
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $campaign = Campaign::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('campaigns.show', $campaign)
            ->with('status', 'Campaña creada. Siguiente paso: agrega las comunidades en «Estructura territorial».');
    }

    public function show(Campaign $campaign): View
    {
        $metrics = new CampaignMetrics($campaign);

        $summary = $metrics->summary();

        // Pestañas del cuaderno (mismo índice que el Excel) y si ya tienen información
        $steps = [
            ['1. Meta de Votos', 'Calculadora: define tu número objetivo y cuántos promotores necesitas. Empieza aquí.', 'campaigns.edit', $campaign->listado_nominal > 0],
            ['2. Estructura Territorial', 'Metas y avance por delegación.', 'campaigns.territories.index', $summary->comunidades > 0],
            ['3. Base de Votantes', 'El padrón nominal propio: votante por votante identificado.', 'campaigns.voters.index', $summary->votantes > 0],
            ['4. Padrón de Promotores', 'Tu ejército: quién es, a cuántos moviliza, avance.', 'campaigns.promoters.index', $summary->promotores > 0],
            ['5. Mapeo de Actores', 'Liderazgos por comunidad: afines, neutrales, adversos.', null, false],
            ['6. Defensa del Voto', 'Control de representantes de casilla (RC) y generales (RG).', null, false],
            ['7. Presupuesto', 'Rubros vs. tope legal, gasto ejercido y disponible.', null, false],
            ['8. Cronograma', 'Fases y tareas rumbo a la jornada.', null, false],
            ['9. Tablero KPI', 'Seguimiento semanal de los indicadores clave.', 'campaigns.kpis.index', $campaign->kpis()->where('valor', '>', 0)->exists()],
            ['10. Logros Comunicables', 'Inventario de obra del gobierno, munición para el mensaje.', null, false],
        ];

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

    public function toggle(Campaign $campaign): RedirectResponse
    {
        $campaign->update(['active' => ! $campaign->active]);

        return back()->with('status', $campaign->active ? 'Campaña reactivada.' : 'Campaña archivada; sus datos se conservan.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        if ($campaign->voters()->exists() || $campaign->promoters()->exists()) {
            return back()->withErrors(['name' => 'La campaña ya tiene votantes o promotores. Archívala en lugar de eliminarla.']);
        }

        $campaign->delete();

        return redirect()->route('campaigns.index')->with('status', 'Campaña eliminada.');
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
