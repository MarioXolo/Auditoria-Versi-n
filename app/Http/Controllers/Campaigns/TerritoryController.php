<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignTerritory;
use App\Models\Community;
use App\Services\CampaignMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** «Estructura Territorial»: metas y avance por comunidad. */
class TerritoryController extends Controller
{
    public function index(Campaign $campaign): View
    {
        $data = (new CampaignMetrics($campaign))->territoryRows();
        $used = $campaign->territories()->pluck('community_id');

        return view('campaigns.territories', [
            'campaign' => $campaign,
            'rows' => $data['rows'],
            'totals' => $data['totals'],
            'available' => Community::active()->whereNotIn('id', $used)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, Campaign $campaign): RedirectResponse
    {
        $data = $this->validated($request, $campaign);
        $data['community_id'] = CatalogResolver::community($data['community_id'], $data['community_new'] ?? null);
        unset($data['community_new']);

        if ($campaign->territories()->where('community_id', $data['community_id'])->exists()) {
            return back()->withErrors(['community_id' => 'Esa comunidad ya está en la estructura de esta campaña.'])->withInput();
        }

        $campaign->territories()->create($data);

        return back()->with('status', 'Comunidad agregada a la estructura.');
    }

    public function update(Request $request, Campaign $campaign, CampaignTerritory $territory): RedirectResponse
    {
        $territory->update($request->validate([
            'responsable' => ['nullable', 'string', 'max:150'],
            'listado_nominal' => ['required', 'integer', 'min:0'],
            'meta_votos' => ['required', 'integer', 'min:0'],
        ], [], ['listado_nominal' => 'listado nominal', 'meta_votos' => 'meta de votos']));

        return back()->with('status', 'Comunidad «'.$territory->community->name.'» actualizada.');
    }

    public function destroy(Campaign $campaign, CampaignTerritory $territory): RedirectResponse
    {
        $territory->delete();

        return back()->with('status', 'Comunidad quitada de la estructura (sus votantes y promotores se conservan).');
    }

    private function validated(Request $request, Campaign $campaign): array
    {
        return $request->validate([
            'community_id' => ['required', Rule::in([...Community::active()->pluck('id')->all(), 'otro'])],
            'community_new' => ['nullable', 'required_if:community_id,otro', 'string', 'max:120'],
            'responsable' => ['nullable', 'string', 'max:150'],
            'listado_nominal' => ['required', 'integer', 'min:0'],
            'meta_votos' => ['required', 'integer', 'min:0'],
        ], ['community_new.required_if' => 'Escribe el nombre de la comunidad.'], [
            'community_id' => 'comunidad', 'listado_nominal' => 'listado nominal', 'meta_votos' => 'meta de votos',
        ]);
    }
}
