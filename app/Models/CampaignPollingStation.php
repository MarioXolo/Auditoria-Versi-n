<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pestaña «6. Defensa del Voto»: una fila por casilla con sus RC. */
class CampaignPollingStation extends Model
{
    protected $guarded = ['id', 'campaign_id'];

    protected function casts(): array
    {
        return ['acreditado' => 'boolean', 'capacitado' => 'boolean', 'reporto_acta' => 'boolean'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** Casilla cubierta: tiene RC propietario y suplente. */
    public function cubierta(): bool
    {
        return filled($this->rc_propietario) && filled($this->rc_suplente);
    }
}
