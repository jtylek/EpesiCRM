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
        return $user->hasAnyRole(User::CRM_ROLES);
    }

    public function view(User $user, Meeting $meeting): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(User::CRM_ROLES);
    }

    public function update(User $user, Meeting $meeting): bool
    {
        if ($user->hasAnyRole(['super_admin', 'manager'])) {
            return true;
        }

        // Not because it's Public: only what he created or works on.
        if ($user->isConsultant()) {
            return $meeting->created_by === $user->id
                || $meeting->employees()->withoutGlobalScope('ownership')->where('user_id', $user->id)->exists();
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

        return $user->hasAnyRole(['employee', 'consultant']) && $meeting->created_by === $user->id;
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
