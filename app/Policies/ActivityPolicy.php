<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

/**
 * Reglas de acceso a actividades:
 * - Administrador y gerente: ven y editan todas.
 * - Encargado: ve las que él asignó y las que le asignaron; edita y borra solo las que él asignó.
 * - Empleado: ve solo las que le asignaron; no edita.
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Activity $activity): bool
    {
        if ($user->seesEverything()) {
            return true;
        }

        if ($activity->assigned_to === $user->id) {
            return true;
        }

        return $user->isEncargado() && $activity->created_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->canManageActivities();
    }

    public function update(User $user, Activity $activity): bool
    {
        if ($user->seesEverything()) {
            return true;
        }

        return $user->isEncargado() && $activity->created_by === $user->id;
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $this->update($user, $activity);
    }
}
