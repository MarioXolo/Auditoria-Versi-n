<?php

namespace App\Http\Controllers\Campaigns;

use App\Support\Csv;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignVoter;
use App\Models\Community;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * «Base de Votantes» identificados. Datos personales: entra quien tenga permiso del módulo «votantes».
 * Un perfil de promotor («solo sus votantes») solo ve, captura, edita y exporta a los de su(s) promotor(es).
 */
class VoterController extends Controller
{
    public function index(Request $request, Campaign $campaign): View
    {
        $own = $this->ownPromoterIds($request, $campaign);
        $base = $campaign->voters()->when($own !== null, fn ($q) => $q->whereIn('promoter_id', $own));

        return view('campaigns.voters.index', [
            'campaign' => $campaign,
            'voters' => $this->query($request, $campaign)->paginate(50)->withQueryString(),
            'communities' => Community::whereIn('id', (clone $base)->whereNotNull('community_id')->distinct()->pluck('community_id'))->orderBy('name')->get(['id', 'name']),
            'promoters' => $this->promotersFor($request, $campaign),
            'total' => $base->count(),
            'soloLosSuyos' => $own !== null,
            'sinPromotor' => $own === [],
        ]);
    }

    public function create(Request $request, Campaign $campaign): View
    {
        // Para capturar varios seguidos: se conservan comunidad, promotor y sección del último registro
        return view('campaigns.voters.create', $this->formData($campaign, new CampaignVoter([
            'community_id' => $request->integer('community_id') ?: null,
            'promoter_id' => $request->integer('promoter_id') ?: null,
            'seccion' => $request->input('seccion'),
        ])));
    }

    public function store(Request $request, Campaign $campaign): RedirectResponse
    {
        $data = $this->validated($request, $campaign);

        // Aviso de posible duplicado (mismo teléfono, o mismo nombre en la misma sección).
        // A un promotor no se le muestran datos de votantes que no son suyos.
        if (! $request->boolean('confirmar_duplicado') && ($dup = $this->possibleDuplicate($campaign, $data))
            && $this->ownPromoterIds($request, $campaign) !== null && ! in_array($dup->promoter_id, $this->ownPromoterIds($request, $campaign), true)) {
            return back()->withInput()->withErrors(['duplicado' => 'Ya hay en la base una persona con ese teléfono, o con ese nombre en la misma sección. Confírmalo con tu coordinador; si es otra persona, marca «Es otra persona» y guarda de nuevo.']);
        }
        if (! $request->boolean('confirmar_duplicado') && ($dup = $this->possibleDuplicate($campaign, $data))) {
            return back()->withInput()->withErrors(['duplicado' => "Posible duplicado: «{$dup->nombre}»"
                .($dup->seccion ? " (sección {$dup->seccion})" : '').($dup->telefono ? ", tel. {$dup->telefono}" : '')
                .' ya está en la base. Si es otra persona, marca «Es otra persona» y guarda de nuevo.']);
        }

        $voter = $campaign->voters()->create($data + ['created_by' => $request->user()->id]);

        if ($request->boolean('otro')) {
            return redirect()->route('campaigns.voters.create', [$campaign,
                'community_id' => $voter->community_id, 'promoter_id' => $voter->promoter_id, 'seccion' => $voter->seccion,
            ])->with('status', "«{$voter->nombre}» registrado. Captura el siguiente.");
        }

        return redirect()->route('campaigns.voters.index', $campaign)->with('status', "«{$voter->nombre}» registrado en la base de votantes.");
    }

    public function edit(Request $request, Campaign $campaign, CampaignVoter $voter): View
    {
        $this->ensureOwn($request, $campaign, $voter);

        return view('campaigns.voters.edit', $this->formData($campaign, $voter));
    }

    public function update(Request $request, Campaign $campaign, CampaignVoter $voter): RedirectResponse
    {
        $this->ensureOwn($request, $campaign, $voter);
        $voter->update($this->validated($request, $campaign, $voter));

        return redirect()->route('campaigns.voters.index', $campaign)->with('status', "«{$voter->nombre}» actualizado.");
    }

    public function destroy(Request $request, Campaign $campaign, CampaignVoter $voter): RedirectResponse
    {
        $this->ensureOwn($request, $campaign, $voter);
        $voter->delete();

        return redirect()->route('campaigns.voters.index', $campaign)->with('status', 'Registro eliminado de la base de votantes.');
    }

    public function export(Request $request, Campaign $campaign): StreamedResponse
    {
        $rows = $this->query($request, $campaign)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            Csv::put($out, ['#', 'Nombre completo', 'Sección', 'Casilla', 'Domicilio', 'Tel / WhatsApp', 'Comunidad', 'Promotor responsable', 'Afinidad', '¿Recibe apoyo gob?', 'Notas']);
            foreach ($rows as $i => $v) {
                Csv::put($out, [$i + 1, $v->nombre, $v->seccion, $v->casilla, $v->domicilio, $v->telefono, $v->community?->name, $v->promoter?->name,
                    $v->afinidad, $v->recibe_apoyo === null ? '' : ($v->recibe_apoyo ? 'Sí' : 'No'), $v->notas]);
            }
            fclose($out);
        }, 'base-votantes-'.str($campaign->name)->slug().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Promotores cuyos votantes puede ver el usuario: null = todos; [] = ninguno (perfil de promotor sin promotor ligado).
     */
    private function ownPromoterIds(Request $request, Campaign $campaign): ?array
    {
        if (! $request->user()->soloSusVotantes()) {
            return null;
        }

        return $campaign->promoters()->where('user_id', $request->user()->id)->pluck('id')->all();
    }

    private function promotersFor(Request $request, Campaign $campaign)
    {
        $own = $this->ownPromoterIds($request, $campaign);

        return $campaign->promoters()->when($own !== null, fn ($q) => $q->whereIn('id', $own))->orderBy('name')->get(['id', 'name']);
    }

    private function ensureOwn(Request $request, Campaign $campaign, CampaignVoter $voter): void
    {
        $own = $this->ownPromoterIds($request, $campaign);
        abort_if($own !== null && ! in_array($voter->promoter_id, $own, true), 404);
    }

    private function query(Request $request, Campaign $campaign)
    {
        $own = $this->ownPromoterIds($request, $campaign);

        return $campaign->voters()
            ->when($own !== null, fn ($q) => $q->whereIn('promoter_id', $own))
            ->with(['community:id,name', 'promoter:id,name'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('nombre', 'like', '%'.$request->string('q').'%')
                ->orWhere('telefono', 'like', '%'.$request->string('q').'%')
                ->orWhere('domicilio', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('community_id'), fn ($q) => $q->where('community_id', $request->integer('community_id')))
            ->when($request->filled('seccion'), fn ($q) => $q->where('seccion', $request->input('seccion')))
            ->when($request->input('promoter_id') === 'sin', fn ($q) => $q->whereNull('promoter_id'))
            ->when(ctype_digit((string) $request->input('promoter_id')), fn ($q) => $q->where('promoter_id', $request->integer('promoter_id')))
            ->when($request->filled('afinidad'), fn ($q) => $q->where('afinidad', $request->input('afinidad')))
            ->orderBy('nombre');
    }

    private function possibleDuplicate(Campaign $campaign, array $data): ?CampaignVoter
    {
        $phone = trim((string) ($data['telefono'] ?? ''));
        $name = mb_strtolower(trim($data['nombre']));

        return $campaign->voters()
            ->where(function ($q) use ($phone, $name, $data) {
                if ($phone !== '') {
                    $q->orWhere('telefono', $phone);
                }
                $q->orWhere(fn ($w) => $w->whereRaw('LOWER(nombre) = ?', [$name])
                    ->where('seccion', $data['seccion'] ?? null));
            })
            ->first();
    }

    private function formData(Campaign $campaign, CampaignVoter $voter): array
    {
        return [
            'campaign' => $campaign,
            'voter' => $voter,
            'communities' => Community::active()->orWhere('id', $voter->community_id)->orderBy('name')->get(['id', 'name']),
            'promoters' => $this->promotersFor(request(), $campaign),
        ];
    }

    private function validated(Request $request, Campaign $campaign, ?CampaignVoter $voter = null): array
    {
        $communityIds = Community::active()->pluck('id')->push($voter?->community_id)->filter()->all();

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'seccion' => ['nullable', 'string', 'max:10'],
            'casilla' => ['nullable', 'string', 'max:10'],
            'domicilio' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'community_id' => ['nullable', Rule::in([...$communityIds, 'otro'])],
            'community_new' => ['nullable', 'required_if:community_id,otro', 'string', 'max:120'],
            'promoter_id' => ($own = $this->ownPromoterIds($request, $campaign)) !== null
                ? ['required', Rule::in($own)]
                : ['nullable', Rule::exists('campaign_promoters', 'id')->where('campaign_id', $campaign->id)],
            'afinidad' => ['nullable', Rule::in(CampaignVoter::AFINIDADES)],
            'recibe_apoyo' => ['nullable', 'in:0,1'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ], ['community_new.required_if' => 'Escribe el nombre de la comunidad.'], [
            'nombre' => 'nombre completo', 'seccion' => 'sección', 'community_id' => 'comunidad',
            'promoter_id' => 'promotor', 'recibe_apoyo' => '¿recibe apoyo gob?',
        ]);

        $data['community_id'] = CatalogResolver::community($data['community_id'] ?? null, $data['community_new'] ?? null);
        $data['recibe_apoyo'] = isset($data['recibe_apoyo']) ? (bool) $data['recibe_apoyo'] : null;
        unset($data['community_new']);

        return $data;
    }
}
