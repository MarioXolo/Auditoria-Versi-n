<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catálogo de comunidades (lo administran admin y gerentes). */
class Community extends Model
{
    protected $fillable = ['name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /** ¿La usa alguna actividad o algún registro de campaña? (entonces no se puede borrar) */
    public function inUse(): bool
    {
        return $this->activities()->exists()
            || CampaignTerritory::where('community_id', $this->id)->exists()
            || CampaignPromoter::where('community_id', $this->id)->exists()
            || CampaignVoter::where('community_id', $this->id)->exists()
            || CampaignActor::where('community_id', $this->id)->exists();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
