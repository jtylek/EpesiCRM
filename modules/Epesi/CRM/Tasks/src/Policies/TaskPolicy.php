<?php

namespace Epesi\Modules\CRM\Tasks\Policies;

use App\Enums\RecordPermission;
use App\Models\User;
use Epesi\Modules\CRM\Tasks\Models\Task;

/**
 * Ported from CRM_TasksInstall::install()'s ACL rules — see
 * PhoneCallPolicy's docblock for why the unconditional employee-delete rule
 * Epesi also registered isn't replicated here.
 */
class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(User::CRM_ROLES);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(User::CRM_ROLES);
    }

    public function update(User $user, Task $task): bool
    {
        if ($user->hasAnyRole(['super_admin', 'manager'])) {
            return true;
        }

        // Not because it's Public: only what he created or works on.
        if ($user->isConsultant()) {
            return $task->created_by === $user->id
                || $task->employees()->withoutGlobalScope('ownership')->where('user_id', $user->id)->exists();
        }

        if (! $user->hasRole('employee')) {
            return false;
        }

        if ($task->created_by === $user->id || $task->permission === RecordPermission::Public) {
            return true;
        }

        return $task->employees()->where('user_id', $user->id)->exists()
            || $task->customers()->where('user_id', $user->id)->exists();
    }

    public function delete(User $user, Task $task): bool
    {
        if ($user->hasAnyRole(['super_admin', 'manager'])) {
            return true;
        }

        return $user->hasAnyRole(['employee', 'consultant']) && $task->created_by === $user->id;
    }

    public function restore(User $user, Task $task): bool
    {
        return $this->delete($user, $task);
    }

    public function forceDelete(User $user, Task $task): bool
    {
        return $user->hasRole('super_admin');
    }
}
