<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipos de actividad. owner_id nulo = lista común (la administran admin y gerentes en Catálogos).
 * Con owner_id = tipo privado que esa persona escribió con «Otro…»: solo le aparece a ella.
 */
class ActivityType extends Model
{
    protected $fillable = ['name', 'owner_id', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'owner_id' => 'integer'];
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function inUse(): bool
    {
        return $this->activities()->exists();
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Lista común. */
    public function scopeShared(Builder $query): Builder
    {
        return $query->whereNull('owner_id');
    }

    /** Lo que esta persona puede elegir: la lista común y sus tipos privados. */
    public function scopeAvailableTo(Builder $query, User $user): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('owner_id')->orWhere('owner_id', $user->id));
    }

    /**
     * Para filtros: quien ve todas las actividades ve todos los tipos;
     * los demás, la lista común y los suyos.
     */
    public function scopeFilterableBy(Builder $query, User $user): Builder
    {
        return $user->seesEverything() ? $query : $query->availableTo($user);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
