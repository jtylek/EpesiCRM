<?php

namespace Epesi\Modules\CRM\Meetings\Policies;

use App\Enums\RecordPermission;
use App\Models\User;
use Epesi\Modules\CRM\Meetings\Models\Meeting;

/**
 * Ported from CRM_MeetingInstall::install()'s ACL rules — see
 * PhoneCallPolicy's docblock for why the unconditional employee-delete rule
 * Epesi also registered isn't replicated here.
 */
class MeetingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager', 'employee']);
    }

    public function view(User $user, Meeting $meeting): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager', 'employee']);
    }

    public function update(User $user, Meeting $meeting): bool
    {
        if ($user->hasAnyRole(['super_admin', 'manager'])) {
            return true;
        }

        if (! $user->hasRole('employee')) {
            return false;
        }

        if ($meeting->created_by === $user->id || $meeting->permission === RecordPermission::Public) {
            return true;
        }

        return $meeting->employees()->where('user_id', $user->id)->exists()
            || $meeting->customers()->where('user_id', $user->id)->exists();
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        if ($user->hasAnyRole(['super_admin', 'manager'])) {
            return true;
        }

        return $user->hasRole('employee') && $meeting->created_by === $user->id;
    }

    public function restore(User $user, Meeting $meeting): bool
    {
        return $this->delete($user, $meeting);
    }

    public function forceDelete(User $user, Meeting $meeting): bool
    {
        return $user->hasRole('super_admin');
    }
}
