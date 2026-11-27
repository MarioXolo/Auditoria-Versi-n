<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'access_profile_id',
        'team_id',
        'phone',
        'active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'team_id' => 'integer',
            'active' => 'boolean',
        ];
    }

    // ---------- Relaciones ----------

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** Equipos donde este usuario es el encargado. */
    public function ledTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'leader_id');
    }

    public function assignedActivities(): HasMany
    {
        return $this->hasMany(Activity::class, 'assigned_to');
    }

    public function createdActivities(): HasMany
    {
        return $this->hasMany(Activity::class, 'created_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    // ---------- Roles ----------

    public function hasRole(Role|string ...$roles): bool
    {
        foreach ($roles as $role) {
            $value = $role instanceof Role ? $role->value : $role;
            if ($this->role->value === $value) {
                return true;
            }
        }

        return false;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isGerente(): bool
    {
        return $this->role === Role::Gerente;
    }

    public function isEncargado(): bool
    {
        return $this->role === Role::Encargado;
    }

    public function isEmpleado(): bool
    {
        return $this->role === Role::Empleado;
    }

    /** Administradores y gerentes ven todo y tienen dashboard. */
    public function seesEverything(): bool
    {
        return $this->hasRole(Role::Admin, Role::Gerente);
    }

    public function accessProfile(): BelongsTo
    {
        return $this->belongsTo(AccessProfile::class);
    }

    /**
     * Punto 19 · ¿Puede hacer esta acción en este módulo? (ver App\Support\Access)
     * Administrador: todo. Con perfil: lo que diga el perfil. Sin perfil: el gerente conserva
     * el acceso completo que ya tenía y el encargado no entra.
     */
    public function puede(string $module, string $action = 'ver'): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        if ($this->access_profile_id) {
            return (bool) $this->accessProfile?->allows($module, $action);
        }

        // Gerente sin perfil: todo lo de antes, menos los módulos restringidos (campaña de apoyo y estadísticas)
        return $this->isGerente() && ! in_array($module, \App\Support\Access::RESTRICTED, true);
    }

    /** Promotor: en la base de votantes solo ve y captura a los suyos. */
    public function soloSusVotantes(): bool
    {
        return ! $this->isAdmin() && (bool) $this->accessProfile?->solo_sus_votantes;
    }

    /** ¿Ve al menos un módulo del Cuaderno Maestro? */
    public function puedeVerCuaderno(): bool
    {
        foreach (['cuaderno', 'promotores', 'votantes', 'estrategia', 'diad', 'apoyo', 'cuartos', 'mensajes', 'estadisticas', 'cierre', 'sectores', 'agenda', 'organigrama'] as $m) {
            if ($this->puede($m)) {
                return true;
            }
        }

        return false;
    }

    /** Todos pueden cargar actividades; el encargado solo para sí mismo. */
    public function canCreateActivities(): bool
    {
        return true;
    }

    /** Asignar a otras personas, revisar (verde/rojo) y ver la actividad de todos: administradores y gerentes. */
    public function canManageActivities(): bool
    {
        return $this->seesEverything();
    }

    /** El encargado (y las cuentas anteriores de empleado) solo se carga actividades a sí mismo. */
    public function selfAssignOnly(): bool
    {
        return ! $this->seesEverything();
    }

    /** IDs de los equipos que encabeza (para encargados). */
    public function ledTeamIds(): array
    {
        return $this->ledTeams()->pluck('id')->all();
    }

    /** IDs de los miembros de los equipos que encabeza. */
    public function teamMemberIds(): array
    {
        $teamIds = $this->ledTeamIds();

        if ($teamIds === []) {
            return [];
        }

        return static::whereIn('team_id', $teamIds)->pluck('id')->all();
    }

    /**
     * Usuarios a los que este usuario puede asignar actividades.
     * - Administrador: cualquiera activo.
     * - Gerente: encargados.
     * - Encargado: solo a sí mismo.
     */
    public function assignableUsers(): Builder
    {
        $query = static::query()->where('active', true)->orderBy('name');

        return match ($this->role) {
            Role::Admin => $query,
            Role::Gerente => $query->where('role', Role::Encargado->value),
            Role::Encargado, Role::Empleado => $query->whereKey($this->id),
        };
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials ?: '?';
    }
}
