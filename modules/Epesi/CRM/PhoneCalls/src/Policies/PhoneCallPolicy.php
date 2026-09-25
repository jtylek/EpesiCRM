<?php

namespace Epesi\Modules\CRM\PhoneCalls\Policies;

use App\Enums\RecordPermission;
use App\Models\User;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;

/**
 * Ported from CRM_PhoneCallInstall::install()'s ACL rules — see
 * CompanyPolicy's docblock for the general shape this follows. Epesi also
 * granted delete to *any* employee unconditionally (a second, unrestricted
 * add_access('phonecall','delete',['ACCESS:employee','ACCESS:manager']) call
 * layered on top of the created_by-only one) — not replicated here, kept
 * restrictive like Company/Contact's delete instead, since it reads like an
 * overly broad rule rather than an intentional design.
 */
class PhoneCallPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager', 'employee']);
    }

    public function view(User $user, PhoneCall $phoneCall): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager', 'employee']);
    }

    public function update(User $user, PhoneCall $phoneCall): bool
    {
        if ($user->hasAnyRole(['super_admin', 'manager'])) {
            return true;
        }

        if (! $user->hasRole('employee')) {
            return false;
        }

        if ($phoneCall->created_by === $user->id || $phoneCall->permission === RecordPermission::Public) {
            return true;
        }

        return $phoneCall->employees()->where('user_id', $user->id)->exists()
            || $phoneCall->contact?->user_id === $user->id;
    }

    public function delete(User $user, PhoneCall $phoneCall): bool
    {
        if ($user->hasAnyRole(['super_admin', 'manager'])) {
            return true;
        }

        return $user->hasRole('employee') && $phoneCall->created_by === $user->id;
    }

    public function restore(User $user, PhoneCall $phoneCall): bool
    {
        return $this->delete($user, $phoneCall);
    }

    public function forceDelete(User $user, PhoneCall $phoneCall): bool
    {
        return $user->hasRole('super_admin');
    }
}
