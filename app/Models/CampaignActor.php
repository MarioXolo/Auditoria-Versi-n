<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pestaña «5. Mapeo de Actores»: quién mueve votos en cada comunidad. */
class CampaignActor extends Model
{
    protected $guarded = ['id', 'campaign_id'];

    protected function casts(): array
    {
        return ['votos_influye' => 'integer', 'contactado' => 'boolean'];
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
