<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Indicador del «Tablero KPI» que se captura a mano (encuestas, alcance digital, etc.). */
class CampaignKpi extends Model
{
    protected $fillable = ['campaign_id', 'nombre', 'meta', 'valor', 'es_porcentaje', 'fecha_corte', 'orden'];

    protected function casts(): array
    {
        return ['meta' => 'float', 'valor' => 'float', 'es_porcentaje' => 'boolean', 'orden' => 'integer'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
