<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

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

        if ($user->isEncargado()) {
            return $this->isInEncargadoScope($user, $activity);
        }

        return false;
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

        // Los encargados editan lo de su equipo, pero no actividades asignadas a sí mismos
        return $user->isEncargado()
            && $activity->assigned_to !== $user->id
            && $this->isInEncargadoScope($user, $activity);
    }

    public function delete(User $user, Activity $activity): bool
    {
        if ($user->seesEverything()) {
            return true;
        }

        return $user->isEncargado() && $activity->created_by === $user->id;
    }

    private function isInEncargadoScope(User $user, Activity $activity): bool
    {
        return $activity->created_by === $user->id
            || in_array($activity->assigned_to, $user->teamMemberIds(), true)
            || ($activity->team_id !== null && in_array($activity->team_id, $user->ledTeamIds(), true));
    }
}
