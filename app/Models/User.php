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

    /** Todos pueden cargar actividades; el empleado solo para sí mismo. */
    public function canCreateActivities(): bool
    {
        return true;
    }

    public function canManageActivities(): bool
    {
        return $this->hasRole(Role::Admin, Role::Gerente, Role::Encargado);
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
     * - Gerente: encargados y empleados.
     * - Encargado: empleados de sus equipos.
     */
    public function assignableUsers(): Builder
    {
        $query = static::query()->where('active', true)->orderBy('name');

        return match ($this->role) {
            Role::Admin => $query,
            Role::Gerente => $query->whereIn('role', [Role::Encargado->value, Role::Empleado->value]),
            Role::Encargado => $query->where('role', Role::Empleado->value)
                ->whereIn('team_id', $this->ledTeamIds() ?: [0]),
            // Los empleados solo se cargan actividades a sí mismos
            Role::Empleado => $query->whereKey($this->id),
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
