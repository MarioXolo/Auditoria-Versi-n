<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignKpi;
use App\Services\CampaignMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** «Tablero KPI»: indicadores calculados solos + los que se capturan cada semana. */
class KpiController extends Controller
{
    public function index(Campaign $campaign): View
    {
        return view('campaigns.kpis', [
            'campaign' => $campaign,
            'kpis' => (new CampaignMetrics($campaign))->kpis(),
        ]);
    }

    public function store(Request $request, Campaign $campaign): RedirectResponse
    {
        $data = $this->validated($request);
        $data['orden'] = (int) $campaign->kpis()->max('orden') + 1;
        $campaign->kpis()->create($data);

        return back()->with('status', 'Indicador agregado.');
    }

    public function update(Request $request, Campaign $campaign, CampaignKpi $kpi): RedirectResponse
    {
        $kpi->update($this->validated($request));

        return back()->with('status', "Indicador «{$kpi->nombre}» actualizado.");
    }

    public function destroy(Campaign $campaign, CampaignKpi $kpi): RedirectResponse
    {
        $kpi->delete();

        return back()->with('status', 'Indicador eliminado.');
    }

    /** Los porcentajes se capturan como 82 (= 82 %) y se guardan como 0.82. */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'meta' => ['required', 'numeric', 'min:0'],
            'valor' => ['required', 'numeric', 'min:0'],
            'es_porcentaje' => ['nullable', 'boolean'],
            'fecha_corte' => ['nullable', 'string', 'max:60'],
        ], [], ['fecha_corte' => 'fecha de corte']);

        $data['es_porcentaje'] = $request->boolean('es_porcentaje');
        if ($data['es_porcentaje']) {
            $data['meta'] /= 100;
            $data['valor'] /= 100;
        }

        return $data;
    }
}
