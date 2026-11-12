<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Team;
use App\Services\DashboardMetrics;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dashboard de auditoría. Solo administradores y gerentes (ver routes/web.php).
 * Los cálculos viven en App\Services\DashboardMetrics.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $metrics = $this->metrics($request);
        $summary = $metrics->summary();

        $charts = [
            'trend' => $metrics->activityTrend(),
            'attendance' => $metrics->attendanceTrend(),
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
            'leaders' => $metrics->leaders(),
            'alerts' => $metrics->alerts(),
            'absent' => $metrics->absentToday(),
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

            fputcsv($out, ['Periodo', $metrics->from->format('d/m/Y').' - '.$metrics->to->format('d/m/Y')]);
            fputcsv($out, []);
            fputcsv($out, [
                'Nombre', 'Correo', 'Rol', 'Equipo', 'Asignadas', 'Concluidas', 'No completadas', 'Sin revisar', 'Vencidas',
                'Cumplimiento %', 'A tiempo %', 'Días promedio de cierre', 'Días con entrada', 'Faltas', 'Retardos', 'Asistencia %',
            ]);

            foreach ($people as $p) {
                fputcsv($out, [
                    $p->user->name, $p->user->email, $p->user->role->label(), $p->user->team?->name,
                    $p->assigned, $p->completed, $p->incompletas, $p->open, $p->overdue,
                    $p->cumplimiento, $p->a_tiempo, $p->dias_cierre, $p->present, $p->absences, $p->late, $p->asistencia,
                ]);
            }

            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function metrics(Request $request): DashboardMetrics
    {
        return new DashboardMetrics(
            (string) $request->input('period', '30'),
            $request->integer('team_id') ?: null,
        );
    }
}
