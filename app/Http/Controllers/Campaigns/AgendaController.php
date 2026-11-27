<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\StrategyRecord;
use App\Services\StrategyBooks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «20. Agenda reportada al IEEQ». No se captura aquí: cada evento creado en los libros
 * aparece solo. Aquí se lleva su control: fecha límite, si ya se reportó, folio y estatus.
 */
class AgendaController extends Controller
{
    public function index(Request $request, Campaign $campaign): View
    {
        $events = $campaign->strategyRecords()
            ->where('es_evento', true)
            ->with(['community:id,name', 'responsable:id,name'])
            ->when(in_array($request->input('estatus'), StrategyRecord::IEEQ_ESTATUS, true), fn ($q) => $q->where('ieeq_estatus', $request->input('estatus')))
            ->when($request->input('estatus') === 'pendientes', fn ($q) => $q->pendientesIeeq())
            ->when(StrategyBooks::exists((string) $request->input('libro')), fn ($q) => $q->where('libro', $request->input('libro')))
            ->orderByRaw('fecha is null')
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();

        $all = $campaign->strategyRecords()->where('es_evento', true)->get();
        $semaforo = $all->map->agendaSemaforo()->countBy();

        return view('campaigns.agenda.index', [
            'campaign' => $campaign,
            'events' => $events,
            'cards' => [
                'total' => $all->count(),
                'pendientes' => $all->filter(fn ($e) => in_array($e->ieeq_estatus, ['Por reportar', 'Modificado'], true))->count(),
                'vencidos' => $semaforo['vencido'] ?? 0,
                'por_vencer' => $semaforo['por_vencer'] ?? 0,
                'reportados' => $all->where('ieeq_estatus', 'Reportado')->count(),
                'gasto' => (float) $all->where('ieeq_estatus', '!=', 'Cancelado')->sum('costo_estimado'),
            ],
            'eventBooks' => collect(StrategyBooks::all())->filter(fn ($b) => $b['evento'] ?? false)->map(fn ($b) => $b['name']),
        ]);
    }

    /** Registra el reporte al IEEQ: estatus, fecha en que se reportó, folio o acuse y, si hace falta, otra fecha límite. */
    public function update(Request $request, Campaign $campaign, StrategyRecord $record): RedirectResponse
    {
        abort_unless($record->campaign_id === $campaign->id && $record->es_evento, 404);

        $v = $request->validate([
            'ieeq_estatus' => ['required', Rule::in(StrategyRecord::IEEQ_ESTATUS)],
            'ieeq_reportado_el' => ['nullable', 'date', 'required_if:ieeq_estatus,Reportado'],
            'ieeq_folio' => ['nullable', 'string', 'max:80'],
            'ieeq_fecha_limite' => ['nullable', 'date'],
        ], ['ieeq_reportado_el.required_if' => 'Pon la fecha en que se reportó.'], ['ieeq_folio' => 'folio o acuse', 'ieeq_fecha_limite' => 'fecha límite']);

        $record->forceFill([
            'ieeq_estatus' => $v['ieeq_estatus'],
            'ieeq_reportado_el' => $v['ieeq_reportado_el'] ?? null,
            'ieeq_folio' => filled($v['ieeq_folio'] ?? null) ? trim($v['ieeq_folio']) : null,
        ]);
        if (! empty($v['ieeq_fecha_limite'])) {
            $record->ieeq_fecha_limite = $v['ieeq_fecha_limite'];
        }
        $record->saveQuietly();

        return back()->with('status', '«'.$record->title().'»: '.mb_strtolower($record->ieeq_estatus).'.');
    }
}
