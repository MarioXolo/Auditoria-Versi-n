<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catálogo de secciones electorales; cada una pertenece (opcionalmente) a una comunidad. */
class Section extends Model
{
    protected $fillable = ['clave', 'community_id', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'community_id' => 'integer'];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function strategyRecords(): HasMany
    {
        return $this->hasMany(StrategyRecord::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /** «0512 · San Pedrito» */
    public function label(): string
    {
        return $this->clave.($this->community ? ' · '.$this->community->name : '');
    }

    public function inUse(): bool
    {
        return $this->strategyRecords()->exists();
    }
}
