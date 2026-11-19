<?php

namespace App\Policies;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\User;

/**
 * Reglas de acceso a actividades (versión 3: Admin, Gerente y Encargado):
 * - Administrador y gerente: ven, editan y marcan todas; asignan a los encargados.
 * - Encargado: ve solo las suyas (las que le asignaron y las que se cargó a sí mismo), nunca
 *   las de otros encargados; edita las que él se cargó y cambia el estado manual
 *   (No iniciado, En proceso, Suspendida) de las suyas. No marca verde/rojo.
 * - Administrador y gerente marcan cualquier actividad, también las suyas.
 * - «Concluido» es definitivo: ya nadie la edita, le cambia el estado ni le quita la marca.
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->seesEverything()
            || $activity->assigned_to === $user->id
            || $activity->created_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->canCreateActivities();
    }

    /** Editar datos de la actividad (no marcarla). */
    public function update(User $user, Activity $activity): bool
    {
        if ($activity->status === ActivityStatus::Concluido) {
            return false;
        }
        if ($user->seesEverything()) {
            return true;
        }

        // El encargado edita lo que él mismo se cargó, mientras no la hayan revisado
        return $activity->created_by === $user->id
            && $activity->assigned_to === $user->id
            && ! $activity->status->isMarked();
    }

    public function delete(User $user, Activity $activity): bool
    {
        // Una concluida solo la puede borrar el administrador (por si se cargó por error)
        return $user->isAdmin() || $this->update($user, $activity);
    }

    /**
     * Cambiar solo el estado manual (No iniciado, En proceso, Suspendida).
     * Lo puede hacer quien edita la actividad y también la persona asignada,
     * mientras un superior no la haya marcado como Concluido o No completada.
     */
    public function changeStatus(User $user, Activity $activity): bool
    {
        if ($activity->status->isMarked()) {
            return false;
        }

        return $activity->assigned_to === $user->id || $this->update($user, $activity);
    }

    /** Checkbox de un superior: ✓ Concluido (verde) o ✗ No completada (rojo). */
    public function mark(User $user, Activity $activity): bool
    {
        if (in_array($activity->status, [ActivityStatus::Suspendida, ActivityStatus::Concluido], true)) {
            return false;
        }

        return $user->seesEverything();
    }
}
