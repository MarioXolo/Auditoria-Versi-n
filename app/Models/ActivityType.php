<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catálogo de tipos de actividad (lo administran admin y gerentes). */
class ActivityType extends Model
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
