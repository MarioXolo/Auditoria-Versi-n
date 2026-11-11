<?php

namespace App\Services;

use App\Models\Campaign;
use Illuminate\Support\Collection;

/**
 * Cálculos del Cuaderno de Campaña: avance por comunidad, por promotor y Tablero KPI.
 * Replica las fórmulas del Excel, pero los «votos identificados» y «promotores
 * reclutados» se cuentan solos desde la base de votantes y el padrón de promotores.
 */
class CampaignMetrics
{
    private ?array $votersByCommunity = null;
    private ?array $promotersByCommunity = null;

    public function __construct(public readonly Campaign $campaign)
    {
    }

    /** Semáforo del Excel: «—» sin meta, Verde ≥ 90 %, Ámbar ≥ 60 %, Rojo debajo. */
    public static function status(float $meta, float $valor): array
    {
        if ($meta == 0.0) {
            return ['label' => '—', 'class' => 'bg-slate-100 text-slate-500', 'ratio' => null];
        }

        $ratio = $valor / $meta;

        return match (true) {
            $ratio >= 0.9 => ['label' => 'Verde', 'class' => 'bg-emerald-100 text-emerald-800', 'ratio' => $ratio],
            $ratio >= 0.6 => ['label' => 'Ámbar', 'class' => 'bg-amber-100 text-amber-800', 'ratio' => $ratio],
            default => ['label' => 'Rojo', 'class' => 'bg-rose-100 text-rose-800', 'ratio' => $ratio],
        };
    }

    /**
     * Gasto: aquí gastar más no es mejor. Verde por debajo del 90 % del tope,
     * Ámbar del 90 al 100 % y Rojo si lo rebasa (el Excel solo dice «¡REBASA TOPE!»).
     */
    public static function spendStatus(float $tope, float $ejercido): array
    {
        if ($tope == 0.0) {
            return ['label' => '—', 'class' => 'bg-slate-100 text-slate-500', 'ratio' => null];
        }

        $ratio = $ejercido / $tope;

        return match (true) {
            $ratio > 1 => ['label' => 'Rebasa tope', 'class' => 'bg-rose-100 text-rose-800', 'ratio' => $ratio],
            $ratio >= 0.9 => ['label' => 'Ámbar', 'class' => 'bg-amber-100 text-amber-800', 'ratio' => $ratio],
            default => ['label' => 'Verde', 'class' => 'bg-emerald-100 text-emerald-800', 'ratio' => $ratio],
        };
    }

    /** Defensa del voto: casillas registradas y cubiertas (propietario + suplente). */
    public function pollingStations(): object
    {
        $q = fn () => $this->campaign->pollingStations();

        return (object) [
            'casillas' => $q()->count(),
            'cubiertas' => $q()->whereNotNull('rc_propietario')->where('rc_propietario', '!=', '')
                ->whereNotNull('rc_suplente')->where('rc_suplente', '!=', '')->count(),
        ];
    }

    public function votersByCommunity(): array
    {
        return $this->votersByCommunity ??= $this->campaign->voters()
            ->selectRaw('community_id, COUNT(*) as total')->groupBy('community_id')
            ->pluck('total', 'community_id')->map(fn ($v) => (int) $v)->all();
    }

    public function promotersByCommunity(): array
    {
        return $this->promotersByCommunity ??= $this->campaign->promoters()
            ->selectRaw('community_id, COUNT(*) as total')->groupBy('community_id')
            ->pluck('total', 'community_id')->map(fn ($v) => (int) $v)->all();
    }

    /** Filas de «Estructura Territorial» con sus cálculos, más la fila de totales. */
    public function territoryRows(): array
    {
        $vpp = $this->campaign->votantes_por_promotor;
        $voters = $this->votersByCommunity();
        $promoters = $this->promotersByCommunity();

        $rows = $this->campaign->territories()->with('community:id,name')->get()
            ->sortBy(fn ($t) => $t->community?->name)
            ->map(function ($t) use ($vpp, $voters, $promoters) {
                $identificados = $voters[$t->community_id] ?? 0;
                $necesarios = ($vpp > 0 && $t->meta_votos > 0) ? (int) ceil($t->meta_votos / $vpp) : 0;
                $reclutados = $promoters[$t->community_id] ?? 0;

                return (object) [
                    'model' => $t,
                    'identificados' => $identificados,
                    'avance' => $t->meta_votos > 0 ? $identificados / $t->meta_votos : 0,
                    'necesarios' => $necesarios,
                    'reclutados' => $reclutados,
                    'faltan' => max($necesarios - $reclutados, 0),
                    'completa' => $necesarios > 0 && $reclutados >= $necesarios,
                ];
            })->values();

        $meta = $rows->sum(fn ($r) => $r->model->meta_votos);
        $identificados = $rows->sum('identificados');

        return [
            'rows' => $rows,
            'totals' => (object) [
                'listado' => $rows->sum(fn ($r) => $r->model->listado_nominal),
                'meta' => $meta,
                'identificados' => $identificados,
                'avance' => $meta > 0 ? $identificados / $meta : 0,
                'necesarios' => $rows->sum('necesarios'),
                'reclutados' => $rows->sum('reclutados'),
                'faltan' => $rows->sum('faltan'),
            ],
        ];
    }

    public function summary(): object
    {
        $metaColchon = $this->campaign->metaConColchon();
        $votantes = $this->campaign->voters()->count();
        $promotores = $this->campaign->promoters()->count();
        $verificados = $this->campaign->promoters()->where('verificado', true)->count();
        $territorial = $this->territoryRows()['totals'];

        return (object) [
            'meta' => $metaColchon,
            'votantes' => $votantes,
            'avance' => $metaColchon > 0 ? $votantes / $metaColchon : 0,
            'promotores' => $promotores,
            'verificados' => $verificados,
            'necesarios' => $this->campaign->promotoresNecesarios(),
            'comunidades' => $this->campaign->territories()->count(),
            'metaTerritorial' => $territorial->meta,
            'sinPromotor' => $this->campaign->voters()->whereNull('promoter_id')->count(),
            'afinidad' => $this->campaign->voters()->selectRaw('afinidad, COUNT(*) as total')
                ->groupBy('afinidad')->pluck('total', 'afinidad')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /**
     * Indicadores calculados solos con los datos del cuaderno, seguidos de los
     * que se capturan a mano.
     */
    public function kpis(): Collection
    {
        $s = $this->summary();
        $territorios = $this->territoryRows();
        $completas = $territorios['rows']->where('completa', true)->count();
        $rc = $this->pollingStations();
        $gasto = (object) ['tope' => $this->campaign->tope_gastos, 'ejercido' => (float) $this->campaign->budgetItems()->sum('ejercido')];

        $auto = collect([
            ['nombre' => 'Votos identificados (base de votantes)', 'meta' => round($s->meta), 'valor' => $s->votantes, 'pct' => false, 'fuente' => 'Base de votantes vs. meta con colchón'],
            ['nombre' => 'Comunidades con estructura completa', 'meta' => $territorios['rows']->count(), 'valor' => $completas, 'pct' => false, 'fuente' => 'Promotores reclutados ≥ necesarios'],
            ['nombre' => 'Promotores reclutados y verificados', 'meta' => $s->necesarios, 'valor' => $s->verificados, 'pct' => false, 'fuente' => 'Padrón de promotores (verificados)'],
            ['nombre' => '% casillas con RC propietario + suplente', 'meta' => $rc->casillas, 'valor' => $rc->cubiertas, 'pct' => false, 'fuente' => 'Defensa del voto: casillas registradas vs. cubiertas'],
            ['nombre' => 'Gasto ejercido (% del tope)', 'meta' => $gasto->tope, 'valor' => $gasto->ejercido, 'pct' => false, 'money' => true, 'fuente' => 'Presupuesto: ejercido vs. tope del IEEQ', 'gasto' => true],
        ])->map(fn ($k) => (object) ($k + [
            'auto' => true,
            'money' => false,
            'status' => ($k['gasto'] ?? false) ? self::spendStatus((float) $k['meta'], (float) $k['valor']) : self::status((float) $k['meta'], (float) $k['valor']),
        ]));

        $manual = $this->campaign->kpis()->get()->map(fn ($k) => (object) [
            'auto' => false,
            'model' => $k,
            'nombre' => $k->nombre,
            'meta' => $k->meta,
            'valor' => $k->valor,
            'pct' => $k->es_porcentaje,
            'fuente' => $k->fecha_corte ? 'Corte: '.$k->fecha_corte : 'Captura manual',
            'status' => self::status($k->meta, $k->valor),
        ]);

        // Mismo orden que el «Tablero KPI» del Excel; los indicadores extra que se agreguen van al final
        $take = function (string $prefix) use (&$manual) {
            $found = $manual->first(fn ($k) => str_starts_with($k->nombre, $prefix));
            $manual = $manual->reject(fn ($k) => $k === $found);

            return $found;
        };

        return collect([
            $auto[0], $auto[1], $auto[2], $auto[3],
            $take('Aprobación'),
            $auto[4],
            $take('Alcance digital'),
            $take('Incidencias'),
        ])->filter()->concat($manual)->values();
    }
}
