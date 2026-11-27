<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Perfil de acceso (punto 19): qué puede hacer en cada módulo quien lo tiene asignado. */
class AccessProfile extends Model
{
    protected $fillable = ['name', 'description', 'permisos', 'solo_sus_votantes'];

    protected function casts(): array
    {
        return ['permisos' => 'array', 'solo_sus_votantes' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function allows(string $module, string $action = 'ver'): bool
    {
        return in_array($action, $this->permisos[$module] ?? [], true);
    }
}
