<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Fila de la «Base de Votantes» identificados. */
class CampaignVoter extends Model
{
    public const AFINIDADES = ['Duro', 'Blando', 'Indeciso'];

    protected $fillable = [
        'campaign_id', 'nombre', 'seccion', 'casilla', 'domicilio', 'telefono',
        'community_id', 'promoter_id', 'afinidad', 'recibe_apoyo', 'notas', 'created_by',
    ];

    protected function casts(): array
    {
        return ['recibe_apoyo' => 'boolean'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(CampaignPromoter::class, 'promoter_id');
    }
}
