<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Fila de «Estructura Territorial»: una comunidad dentro de una campaña. */
class CampaignTerritory extends Model
{
    protected $fillable = ['campaign_id', 'community_id', 'responsable', 'listado_nominal', 'meta_votos'];

    protected function casts(): array
    {
        return ['listado_nominal' => 'integer', 'meta_votos' => 'integer'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
}
