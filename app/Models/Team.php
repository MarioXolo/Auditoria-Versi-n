<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = [
        'number',
        'name',
        'description',
        'leader_id',
    ];

    protected function casts(): array
    {
        return ['number' => 'integer'];
    }

    protected static function booted(): void
    {
        // Cada equipo nuevo toma el número libre más bajo (1 a max_equipos)
        static::creating(function (Team $team) {
            $team->number ??= self::freeNumber()
                ?? throw new \DomainException('Se alcanzó el máximo de '.self::maxTeams().' equipos.');
        });
    }

    public static function maxTeams(): int
    {
        return (int) config('auditoria.max_equipos', 30);
    }

    /** Número libre más bajo, o null si ya se ocuparon todos. */
    public static function freeNumber(): ?int
    {
        $used = self::pluck('number')->all();

        foreach (range(1, self::maxTeams()) as $n) {
            if (! in_array($n, $used, true)) {
                return $n;
            }
        }

        return null;
    }

    public static function limitReached(): bool
    {
        return self::freeNumber() === null;
    }

    /** Prefijo de folio del equipo: «ID3». */
    public function getPrefixAttribute(): string
    {
        return 'ID'.$this->number;
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
