<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Fila del «Padrón de Promotores». Los identificados se cuentan de la base de votantes. */
class CampaignPromoter extends Model
{
    protected $fillable = ['campaign_id', 'name', 'phone', 'community_id', 'secciones', 'coordinador', 'meta_votos', 'verificado'];

    protected function casts(): array
    {
        return ['meta_votos' => 'integer', 'verificado' => 'boolean'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function voters(): HasMany
    {
        return $this->hasMany(CampaignVoter::class, 'promoter_id');
    }

    /** % de avance = identificados ÷ meta (requiere withCount('voters')). */
    public function avance(): float
    {
        return $this->meta_votos > 0 ? ($this->voters_count ?? 0) / $this->meta_votos : 0;
    }
}
