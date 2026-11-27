<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Persona del organigrama (punto 19). No necesita cuenta; si la tiene, se muestra su rol en el sistema. */
class OrgMember extends Model
{
    protected $fillable = ['nombre', 'puesto', 'reporta_a_id', 'team_id', 'delegacion', 'section_id', 'telefono', 'user_id', 'servidor_publico', 'notas', 'orden'];

    protected function casts(): array
    {
        return ['servidor_publico' => 'boolean', 'reporta_a_id' => 'integer', 'user_id' => 'integer', 'orden' => 'integer'];
    }

    public function jefe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reporta_a_id');
    }

    public function subordinados(): HasMany
    {
        return $this->hasMany(self::class, 'reporta_a_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** «Gerente · Coordinación territorial», o null si no tiene cuenta. */
    public function rolEnSistema(): ?string
    {
        if (! $this->user) {
            return null;
        }

        return $this->user->role->label().($this->user->accessProfile ? ' · '.$this->user->accessProfile->name : '').($this->user->active ? '' : ' (inactivo)');
    }

    /** IDs de esta persona y de todos los que dependen de ella (para no crear ciclos al elegir a quién reporta). */
    public static function descendantIds(int $id): array
    {
        $ids = [$id];
        $frontier = [$id];
        while ($frontier) {
            $frontier = self::whereIn('reporta_a_id', $frontier)->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }
}
