<?php

namespace App\Services;

use App\Enums\ActivityStatus;
use App\Enums\Role;
use App\Http\Controllers\Campaigns\CampaignController;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Campaign;
use App\Models\Community;
use App\Models\MinuteCommitment;
use App\Models\PresidentActivity;
use App\Models\StrategyRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Paneles del dashboard que miran "hacia afuera" de las actividades: el Cuaderno Maestro,
 * lo que viene en los próximos días, quién no se mueve, qué comunidades nadie atiende
 * y los compromisos de las minutas. Todo sale de datos que ya existen; nadie captura nada extra.
 */
class DashboardPulse
{
    public const FECHA_CIERRE = '2027-06-02';

    public function __construct(private readonly User $viewer, private readonly DashboardMetrics $metrics)
    {
    }

    /** Semáforo y metas del Cuaderno Maestro (si existe y el perfil lo deja ver). */
    public function cuaderno(): ?object
    {
        $campaign = Campaign::current();
        if (! $campaign || ! $this->viewer->puedeVerCuaderno()) {
            return null;
        }

        $steps = CampaignController::bubbles($campaign);
        $sem = (new CuadernoSemaforo($campaign))->burbujas(collect($steps)->mapWithKeys(fn ($b) => [$b[0] => $b[4]])->all());
        $alertas = collect($steps)
            ->filter(fn ($b) => in_array($sem[$b[0]]['color'] ?? null, ['rojo', 'amarillo'], true))
            ->map(fn ($b) => (object) ['num' => $b[0], 'name' => $b[1], 'url' => $b[3], 'color' => $sem[$b[0]]['color'], 'motivo' => $sem[$b[0]]['motivo']])
            ->sortBy(fn ($b) => ($b->color === 'rojo' ? 0 : 1).str_pad((string) $b->num, 2, '0', STR_PAD_LEFT))
            ->values();

        $summary = $this->viewer->puede('cuaderno') ? (new CampaignMetrics($campaign))->summary() : null;
        $cierre = CarbonImmutable::parse(self::FECHA_CIERRE);

        return (object) [
            'campaign' => $campaign,
            'resumen' => CuadernoSemaforo::resumen($sem),
            'alertas' => $alertas,
            'summary' => $summary,
            'dias_cierre' => today()->lte($cierre) ? (int) today()->diffInDays($cierre) : null,
        ];
    }

    /** Lo que viene en los próximos 7 días (más lo ya vencido al IEEQ). */
    public function upcoming(int $days = 7): object
    {
        $hasta = today()->addDays($days);

        $actividades = Activity::query()
            ->tap(fn ($q) => $this->metrics->scope($q))
            ->whereIn('status', [ActivityStatus::NoIniciado->value, ActivityStatus::EnProceso->value])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', today())
            ->whereDate('end_date', '<=', $hasta)
            ->with(['assignee:id,name,folio_number,role', 'type:id,name'])
            ->orderBy('end_date')
            ->get();

        $campaign = Campaign::current();
        $eventos = collect();
        $ieeq = 0;
        if ($campaign && $this->viewer->puedeVerCuaderno()) {
            $eventos = $campaign->strategyRecords()
                ->where('estatus', '!=', 'Hecho')
                ->whereNotNull('fecha')
                ->whereDate('fecha', '>=', today())
                ->whereDate('fecha', '<=', $hasta)
                ->with('community:id,name')
                ->orderBy('fecha')->orderBy('hora')
                ->get()
                ->filter(fn (StrategyRecord $r) => StrategyBooks::exists($r->libro) && $this->viewer->puede(StrategyBooks::module($r->libro)))
                ->values();
            if ($this->viewer->puede('agenda')) {
                $ieeq = $campaign->strategyRecords()->pendientesIeeq()->whereNotNull('ieeq_fecha_limite')
                    ->whereDate('ieeq_fecha_limite', '<=', $hasta)->count();
            }
        }

        $presidente = collect();
        if ($this->viewer->hasRole(Role::Admin, Role::Gerente)) {
            $presidente = PresidentActivity::query()
                ->whereNotIn('status', [ActivityStatus::Concluido->value, ActivityStatus::Suspendida->value, ActivityStatus::NoCompletada->value])
                ->whereDate('start_date', '<=', $hasta)
                ->where(fn ($q) => $q->whereDate('end_date', '>=', today())->orWhere(fn ($w) => $w->whereNull('end_date')->whereDate('start_date', '>=', today())))
                ->with(['type:id,name', 'community:id,name'])
                ->orderBy('start_date')
                ->get();
        }

        return (object) compact('actividades', 'eventos', 'ieeq', 'presidente', 'days');
    }

    /**
     * Encargados sin movimiento: días desde lo último que hicieron (cargar o concluir una
     * actividad, que se la cargaran, o checar asistencia). Los más quietos primero.
     */
    public function ghosts(): Collection
    {
        $limite = (int) config('auditoria.alertas.dias_sin_movimiento', 5);
        $staff = User::active()->where('role', Role::Encargado->value)
            ->when($this->metrics->encargadoId, fn ($q) => $q->whereKey($this->metrics->encargadoId))
            ->get(['id', 'name', 'folio_number', 'created_at', 'role']);
        if ($staff->isEmpty()) {
            return collect();
        }
        $ids = $staff->pluck('id');

        $porAsignado = Activity::whereIn('assigned_to', $ids)->selectRaw('assigned_to as u, max(updated_at) as m')->groupBy('assigned_to')->pluck('m', 'u');
        $porCreador = Activity::whereIn('created_by', $ids)->selectRaw('created_by as u, max(created_at) as m')->groupBy('created_by')->pluck('m', 'u');
        $checado = Attendance::whereIn('user_id', $ids)->selectRaw('user_id as u, max(check_in_at) as m')->groupBy('user_id')->pluck('m', 'u');

        return $staff->map(function (User $u) use ($porAsignado, $porCreador, $checado) {
            $ultimo = collect([$porAsignado[$u->id] ?? null, $porCreador[$u->id] ?? null, $checado[$u->id] ?? null])
                ->filter()->map(fn ($d) => CarbonImmutable::parse($d))->max();
            $desde = $ultimo ?? CarbonImmutable::parse($u->created_at);

            return (object) [
                'user' => $u,
                'ultimo' => $ultimo,
                'dias' => (int) $desde->startOfDay()->diffInDays(today()),
                'nunca' => $ultimo === null,
            ];
        })->filter(fn ($g) => $g->dias >= $limite)->sortByDesc('dias')->values();
    }

    /** Comunidades activas sin actividades ni registros del Cuaderno en los últimos N días. */
    public function forgottenCommunities(): object
    {
        $dias = (int) config('auditoria.alertas.dias_comunidad_olvidada', 15);
        $communities = Community::where('active', true)->orderBy('name')->get(['id', 'name']);

        $act = Activity::whereNotNull('community_id')->selectRaw('community_id as c, max(created_at) as m')->groupBy('community_id')->pluck('m', 'c');
        $reg = StrategyRecord::whereNotNull('community_id')->selectRaw('community_id as c, max(updated_at) as m')->groupBy('community_id')->pluck('m', 'c');

        $olvidadas = $communities->map(function (Community $c) use ($act, $reg) {
            $ultimo = collect([$act[$c->id] ?? null, $reg[$c->id] ?? null])->filter()->map(fn ($d) => CarbonImmutable::parse($d))->max();

            return (object) ['community' => $c, 'ultimo' => $ultimo, 'dias' => $ultimo ? (int) $ultimo->startOfDay()->diffInDays(today()) : null];
        })->filter(fn ($o) => $o->dias === null || $o->dias >= (int) config('auditoria.alertas.dias_comunidad_olvidada', 15))
            ->sortByDesc(fn ($o) => $o->dias ?? PHP_INT_MAX)
            ->values();

        return (object) ['dias' => $dias, 'total' => $communities->count(), 'olvidadas' => $olvidadas];
    }

    /** Compromisos de minutas abiertos con fecha límite vencida. */
    public function commitments(): ?Collection
    {
        if (! $this->viewer->puede('minutas')) {
            return null;
        }

        return MinuteCommitment::with('minute:id,titulo,fecha')
            ->whereNotNull('fecha_limite')
            ->get()
            ->filter(fn (MinuteCommitment $c) => $c->vencido())
            ->sortBy(fn ($c) => $c->fechaLimiteDate())
            ->values();
    }
}
