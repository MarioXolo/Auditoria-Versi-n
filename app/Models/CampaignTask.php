<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pestaña «8. Cronograma»: tarea de una fase rumbo a la jornada. */
class CampaignTask extends Model
{
    protected $guarded = ['id', 'campaign_id'];

    protected function casts(): array
    {
        return ['fecha_limite' => 'date'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function vencida(): bool
    {
        return $this->fecha_limite && $this->estatus !== 'Hecho' && $this->fecha_limite->isBefore(today());
    }
}
