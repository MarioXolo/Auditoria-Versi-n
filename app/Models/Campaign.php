<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Campaña (V2 · Cuaderno Maestro de Campaña). Las fórmulas de la calculadora
 * replican la pestaña «Meta de Votos» del Excel original.
 */
class Campaign extends Model
{
    /** Indicadores capturados a mano que trae cada campaña nueva (pestaña «Tablero KPI»). */
    public const DEFAULT_KPIS = [
        ['nombre' => 'Aprobación del candidato (encuesta)', 'es_porcentaje' => true],
        ['nombre' => 'Alcance digital semanal', 'es_porcentaje' => false],
        ['nombre' => 'Incidencias jurídicas abiertas', 'es_porcentaje' => false],
    ];

    /** Rubros y % sugeridos de la pestaña «Presupuesto» del Excel; cada campaña los recibe en ceros. */
    public const DEFAULT_BUDGET = [
        ['rubro' => 'Territorio y movilización', 'porcentaje' => 0.40],
        ['rubro' => 'Comunicación y digital', 'porcentaje' => 0.25],
        ['rubro' => 'Operación día D', 'porcentaje' => 0.12],
        ['rubro' => 'Estructura y administración', 'porcentaje' => 0.08],
        ['rubro' => 'Eventos y logística', 'porcentaje' => 0.05],
        ['rubro' => 'Reserva de contingencia', 'porcentaje' => 0.10],
    ];

    protected $fillable = [
        'name', 'municipality', 'election_date', 'listado_nominal', 'participacion',
        'objetivo', 'colchon', 'votantes_por_promotor', 'tope_gastos', 'notes', 'active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'election_date' => 'date',
            'listado_nominal' => 'integer',
            'participacion' => 'float',
            'objetivo' => 'float',
            'colchon' => 'float',
            'votantes_por_promotor' => 'integer',
            'tope_gastos' => 'float',
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Campaign $campaign) {
            foreach (self::DEFAULT_KPIS as $i => $kpi) {
                $campaign->kpis()->create($kpi + ['orden' => $i + 1]);
            }
            foreach (self::DEFAULT_BUDGET as $item) {
                $campaign->budgetItems()->create($item);
            }
        });
    }

    // ---- Calculadora «Meta de Votos» ----

    /** Votos totales esperados = listado nominal × participación. */
    public function votosEsperados(): float
    {
        return $this->listado_nominal * $this->participacion;
    }

    /** Meta base = votos esperados × % objetivo. */
    public function metaBase(): float
    {
        return $this->votosEsperados() * $this->objetivo;
    }

    /** Meta con colchón (el «norte» de la campaña) = meta base × (1 + colchón). */
    public function metaConColchon(): float
    {
        return $this->metaBase() * (1 + $this->colchon);
    }

    /** Promotores necesarios = REDONDEAR.MAS(meta con colchón ÷ votantes por promotor). */
    public function promotoresNecesarios(): int
    {
        return $this->votantes_por_promotor > 0
            ? (int) ceil(round($this->metaConColchon() / $this->votantes_por_promotor, 6))
            : 0;
    }

    // ---- Relaciones ----

    public function territories(): HasMany
    {
        return $this->hasMany(CampaignTerritory::class);
    }

    public function promoters(): HasMany
    {
        return $this->hasMany(CampaignPromoter::class);
    }

    public function voters(): HasMany
    {
        return $this->hasMany(CampaignVoter::class);
    }

    public function kpis(): HasMany
    {
        return $this->hasMany(CampaignKpi::class)->orderBy('orden');
    }

    /** El sistema lleva un solo Cuaderno Maestro: el activo más antiguo (o el primero que haya). */
    public static function current(): ?self
    {
        return static::orderByDesc('active')->orderBy('id')->first();
    }

    public function actors(): HasMany
    {
        return $this->hasMany(CampaignActor::class);
    }

    public function pollingStations(): HasMany
    {
        return $this->hasMany(CampaignPollingStation::class);
    }

    public function budgetItems(): HasMany
    {
        return $this->hasMany(CampaignBudgetItem::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CampaignTask::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(CampaignAchievement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
