<?php

namespace Epesi\Modules\CRM\Contacts\Policies;

use App\Enums\RecordPermission;
use App\Models\User;
use Epesi\Modules\CRM\Contacts\Models\Contact;

/**
 * Ported from CRM_ContactsInstall::install_permissions()'s contact rules —
 * see CompanyPolicy's docblock for the same caveat: per-field `blocked_fields`
 * (e.g. only managers may touch `access`/`login`/`company_name` on someone
 * else's contact) is not ported, only the record-level view/edit/delete gates.
 */
class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager', 'employee']);
    }

    public function view(User $user, Contact $contact): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager', 'employee']);
    }

    public function update(User $user, Contact $contact): bool
    {
        if ($user->hasAnyRole(['super_admin', 'manager'])) {
            return true;
        }

        if ($contact->user_id === $user->id) {
            return true;
        }

        return $user->hasRole('employee')
            && ($contact->created_by === $user->id || $contact->permission === RecordPermission::Public);
    }

    public function delete(User $user, Contact $contact): bool
    {
        if ($user->hasAnyRole(['super_admin', 'manager'])) {
            return true;
        }

        return $user->hasRole('employee') && $contact->created_by === $user->id;
    }

    public function restore(User $user, Contact $contact): bool
    {
        return $this->delete($user, $contact);
    }

    public function forceDelete(User $user, Contact $contact): bool
    {
        return $user->hasRole('super_admin');
    }
}
