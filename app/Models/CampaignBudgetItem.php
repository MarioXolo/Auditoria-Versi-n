<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pestaña «7. Presupuesto»: un rubro con lo asignado y lo ejercido. */
class CampaignBudgetItem extends Model
{
    protected $guarded = ['id', 'campaign_id'];

    protected function casts(): array
    {
        return ['porcentaje' => 'float', 'asignado' => 'float', 'ejercido' => 'float'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function disponible(): float
    {
        return $this->asignado - $this->ejercido;
    }
}
