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
        return $user->hasAnyRole(['super_admin', 'manager', 'employee']);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager', 'employee']);
    }

    public function update(User $user, Task $task): bool
    {
        if ($user->hasAnyRole(['super_admin', 'manager'])) {
            return true;
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

        return $user->hasRole('employee') && $task->created_by === $user->id;
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
