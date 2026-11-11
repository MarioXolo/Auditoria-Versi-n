<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pestaña «10. Logros Comunicables»: obra o programa que sirve de mensaje. */
class CampaignAchievement extends Model
{
    protected $guarded = ['id', 'campaign_id'];

    protected function casts(): array
    {
        return ['recurso' => 'float', 'evidencia' => 'boolean'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
