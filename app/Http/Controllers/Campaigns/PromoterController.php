<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignPromoter;
use App\Models\Community;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** «Padrón de Promotores». Los identificados se cuentan de la base de votantes. */
class PromoterController extends Controller
{
    public function index(Request $request, Campaign $campaign): View
    {
        $promoters = $this->query($request, $campaign)->paginate(50)->withQueryString();

        return view('campaigns.promoters.index', [
            'campaign' => $campaign,
            'promoters' => $promoters,
            'communities' => $this->communitiesFor($campaign),
            'totals' => (object) [
                'meta' => (int) $campaign->promoters()->sum('meta_votos'),
                'identificados' => $campaign->voters()->whereNotNull('promoter_id')->count(),
                'total' => $campaign->promoters()->count(),
                'verificados' => $campaign->promoters()->where('verificado', true)->count(),
            ],
        ]);
    }

    public function create(Campaign $campaign): View
    {
        return view('campaigns.promoters.create', [
            'campaign' => $campaign,
            'promoter' => new CampaignPromoter(['meta_votos' => $campaign->votantes_por_promotor]),
            'communities' => Community::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, Campaign $campaign): RedirectResponse
    {
        $promoter = $campaign->promoters()->create($this->validated($request));

        return redirect()->route('campaigns.promoters.index', $campaign)->with('status', "Promotor «{$promoter->name}» registrado.");
    }

    public function edit(Campaign $campaign, CampaignPromoter $promoter): View
    {
        return view('campaigns.promoters.edit', [
            'campaign' => $campaign,
            'promoter' => $promoter,
            'communities' => Community::active()->orWhere('id', $promoter->community_id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Campaign $campaign, CampaignPromoter $promoter): RedirectResponse
    {
        $promoter->update($this->validated($request, $promoter));

        return redirect()->route('campaigns.promoters.index', $campaign)->with('status', "Promotor «{$promoter->name}» actualizado.");
    }

    public function destroy(Campaign $campaign, CampaignPromoter $promoter): RedirectResponse
    {
        $promoter->delete(); // sus votantes quedan sin promotor asignado

        return redirect()->route('campaigns.promoters.index', $campaign)->with('status', 'Promotor eliminado; sus votantes quedaron sin promotor.');
    }

    public function export(Request $request, Campaign $campaign): StreamedResponse
    {
        $rows = $this->query($request, $campaign)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['#', 'Nombre del promotor', 'Tel / WhatsApp', 'Comunidad', 'Sección(es)', 'Coordinador seccional', 'Meta (votos)', 'Identificados', '% avance', '¿Verificado?']);
            foreach ($rows as $i => $p) {
                fputcsv($out, [$i + 1, $p->name, $p->phone, $p->community?->name, $p->secciones, $p->coordinador, $p->meta_votos, $p->voters_count, round($p->avance() * 100, 1), $p->verificado ? 'Sí' : 'No']);
            }
            fclose($out);
        }, 'promotores-'.str($campaign->name)->slug().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function query(Request $request, Campaign $campaign)
    {
        return $campaign->promoters()
            ->with('community:id,name')
            ->withCount('voters')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('phone', 'like', '%'.$request->string('q').'%')
                ->orWhere('secciones', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('community_id'), fn ($q) => $q->where('community_id', $request->integer('community_id')))
            ->when($request->filled('verificado'), fn ($q) => $q->where('verificado', $request->input('verificado') === 'si'))
            ->orderBy('name');
    }

    private function communitiesFor(Campaign $campaign)
    {
        return Community::whereIn('id', $campaign->promoters()->whereNotNull('community_id')->distinct()->pluck('community_id'))
            ->orderBy('name')->get(['id', 'name']);
    }

    private function validated(Request $request, ?CampaignPromoter $promoter = null): array
    {
        $communityIds = Community::active()->pluck('id')->push($promoter?->community_id)->filter()->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'community_id' => ['nullable', Rule::in([...$communityIds, 'otro'])],
            'community_new' => ['nullable', 'required_if:community_id,otro', 'string', 'max:120'],
            'secciones' => ['nullable', 'string', 'max:120'],
            'coordinador' => ['nullable', 'string', 'max:150'],
            'meta_votos' => ['required', 'integer', 'min:0', 'max:100000'],
            'verificado' => ['nullable', 'boolean'],
        ], ['community_new.required_if' => 'Escribe el nombre de la comunidad.'], [
            'name' => 'nombre', 'phone' => 'teléfono', 'community_id' => 'comunidad', 'meta_votos' => 'meta de votos',
        ]);

        $data['community_id'] = CatalogResolver::community($data['community_id'] ?? null, $data['community_new'] ?? null);
        $data['verificado'] = $request->boolean('verificado');
        unset($data['community_new']);

        return $data;
    }
}
