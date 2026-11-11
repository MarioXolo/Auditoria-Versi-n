<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

/**
 * Reglas de acceso a actividades:
 * - Administrador y gerente: ven, editan y marcan todas.
 * - Encargado: ve las que asignó, las que le asignaron y las que sus empleados se cargaron;
 *   edita las que él asignó y marca las de su equipo.
 * - Empleado: ve solo las suyas y puede cargarse actividades a sí mismo; no edita ni marca.
 * - Nadie marca una actividad asignada a sí mismo: siempre la marca alguien con más privilegios.
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Activity $activity): bool
    {
        if ($user->seesEverything() || $activity->assigned_to === $user->id) {
            return true;
        }

        return $user->isEncargado() && (
            $activity->created_by === $user->id
            || $this->isTeamSelfAssigned($user, $activity)
        );
    }

    public function create(User $user): bool
    {
        return $user->canCreateActivities();
    }

    /** Editar datos de la actividad (no marcarla). */
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

    /** Marcar como completada o incompleta (checkbox). */
    public function mark(User $user, Activity $activity): bool
    {
        if ($activity->assigned_to === $user->id || $activity->status === \App\Enums\ActivityStatus::Cancelada) {
            return false;
        }

        if ($user->seesEverything()) {
            return true;
        }

        return $user->isEncargado() && (
            $activity->created_by === $user->id
            || $this->isTeamSelfAssigned($user, $activity)
        );
    }

    /** Actividad que un empleado del equipo del encargado se cargó a sí mismo. */
    private function isTeamSelfAssigned(User $leader, Activity $activity): bool
    {
        return $activity->isSelfAssigned()
            && in_array($activity->assigned_to, $leader->teamMemberIds(), true);
    }
}
