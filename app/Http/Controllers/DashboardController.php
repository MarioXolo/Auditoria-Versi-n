<?php

namespace App\Http\Controllers;

use App\Support\Csv;
use App\Models\Activity;
use App\Models\Team;
use App\Services\ActivityDurations;
use App\Services\DashboardMetrics;
use App\Enums\ActivityStatus;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dashboard. Administradores y gerentes ven el de auditoría (App\Services\DashboardMetrics);
 * cada encargado ve el suyo, solo con sus actividades.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        if (! $request->user()->seesEverything()) {
            return $this->personal($request);
        }

        $metrics = $this->metrics($request);
        $summary = $metrics->summary();

        $charts = [
            'trend' => $metrics->activityTrend(),
            'status' => $metrics->byStatus(),
            'types' => $metrics->byType(),
            'communities' => $metrics->byCommunity(),
            'goals' => config('auditoria.metas'),
        ];

        return view('dashboard', [
            'metrics' => $metrics,
            'periods' => DashboardMetrics::PERIODS,
            'period' => $metrics->period,
            'teams' => Team::orderBy('name')->get(['id', 'name']),
            'teamId' => $metrics->teamId,
            'kpi' => $summary['now'],
            'delta' => $summary['delta'],
            'hasPrev' => $summary['prev'] !== null,
            'charts' => $charts,
            'people' => $metrics->people(),
            'durations' => $metrics->durations(),
            'alerts' => $metrics->alerts(),
            'quality' => $metrics->evidenceAndReview(),
            'definitions' => DashboardMetrics::definitions(),
            'recent' => Activity::with(['assignee:id,name', 'creator:id,name', 'type:id,name', 'community:id,name'])
                ->when($metrics->teamId, fn ($q) => $q->where('team_id', $metrics->teamId))
                ->latest('updated_at')
                ->limit(8)
                ->get(),
        ]);
    }

    /** Descarga en CSV los indicadores por persona del periodo filtrado. */
    public function export(Request $request): StreamedResponse
    {
        $metrics = $this->metrics($request);
        $people = $metrics->people();
        $file = 'kpi-personal-'.$metrics->from->format('Ymd').'-'.$metrics->to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($people, $metrics) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel respete los acentos

            Csv::put($out, ['Periodo', $metrics->from->format('d/m/Y').' - '.$metrics->to->format('d/m/Y')]);
            Csv::put($out, []);
            Csv::put($out, [
                'Nombre', 'Correo', 'Rol', 'Equipo', 'Actividades', 'Se las cargó él', 'Concluidas', 'No completadas', 'Sin revisar', 'Vencidas',
                'Cumplimiento %', 'A tiempo %', 'Días promedio de cierre',
            ]);

            foreach ($people as $p) {
                Csv::put($out, [
                    $p->user->name, $p->user->email, $p->user->role->label(), $p->user->team?->name,
                    $p->assigned, $p->propias, $p->completed, $p->incompletas, $p->open, $p->overdue,
                    $p->cumplimiento, $p->a_tiempo, $p->dias_cierre,
                ]);
            }

            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Dashboard del encargado: solo sus actividades (las que le asignaron y las que se cargó). */
    private function personal(Request $request): View
    {
        $user = $request->user();
        $periods = ['all' => 'Todo', '7' => 'Últimos 7 días', '30' => 'Últimos 30 días', '90' => 'Últimos 90 días'];
        $period = array_key_exists($request->input('period'), $periods) ? $request->input('period') : 'all';
        $from = $period === 'all' ? null : today()->subDays((int) $period - 1);

        $mine = Activity::query()
            ->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhere('created_by', $user->id))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->with(['type:id,name', 'community:id,name', 'creator:id,name'])
            ->get();

        $assignedToMe = $mine->where('assigned_to', $user->id);
        $propias = $assignedToMe->filter(fn ($a) => $a->created_by === $user->id);
        $asignadas = $assignedToMe->filter(fn ($a) => $a->created_by !== $user->id);
        $concluded = $assignedToMe->filter(fn ($a) => $a->status === ActivityStatus::Concluido);
        $effective = $assignedToMe->filter(fn ($a) => $a->status !== ActivityStatus::Suspendida)->count();
        $open = $assignedToMe->filter(fn ($a) => $a->status->isOpen());
        $overdue = $open->filter(fn ($a) => $a->end_date && $a->end_date->lt(today()));

        $byStatus = collect(ActivityStatus::cases())->map(fn ($s) => [
            'key' => $s->value,
            'label' => $s->label(),
            'value' => $assignedToMe->filter(fn ($a) => $a->status === $s)->count(),
        ])->all();

        $durations = ActivityDurations::summarize(
            ActivityDurations::query($from ?? Carbon::create(2000), now())
                ->where('assigned_to', $user->id)
                ->with(['type:id,name'])
                ->get()
        );

        return view('dashboard.personal', [
            'periods' => $periods,
            'period' => $period,
            'total' => $assignedToMe->count(),
            'propias' => $propias->count(),
            'asignadas' => $asignadas->count(),
            'asignadasPor' => $asignadas->groupBy(fn ($a) => $a->creator?->name ?? '—')->map->count()->sortDesc(),
            'byStatus' => $byStatus,
            'cumplimiento' => $effective > 0 ? round($concluded->count() / $effective * 100, 1) : null,
            'overdue' => $overdue->sortBy('end_date')->values(),
            'upcoming' => $open->filter(fn ($a) => $a->end_date && $a->end_date->betweenIncluded(today(), today()->addDays(7)))->sortBy('end_date')->values(),
            'durations' => $durations,
        ]);
    }

    private function metrics(Request $request): DashboardMetrics
    {
        return new DashboardMetrics(
            (string) $request->input('period', '30'),
            $request->integer('team_id') ?: null,
        );
    }
}
