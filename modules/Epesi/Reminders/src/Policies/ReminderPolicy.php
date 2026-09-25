<?php

namespace Epesi\Modules\Reminders\Policies;

use App\Models\User;
use Epesi\Modules\Reminders\Models\Reminder;
use Epesi\Modules\Reminders\Reminders;

/**
 * Staff may set reminders (Epesi's "Messenger Alerts" permission, granted to
 * ACCESS:employee). A reminder belongs to whoever set it; managers and super
 * admins may change anyone's.
 */
class ReminderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(Reminders::ROLES);
    }

    public function view(User $user, Reminder $reminder): bool
    {
        return $this->update($user, $reminder)
            || $reminder->recipientRows()->where('user_id', $user->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(Reminders::ROLES);
    }

    public function update(User $user, Reminder $reminder): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager'])
            || ($user->hasRole('employee') && $reminder->created_by === $user->getKey());
    }

    public function delete(User $user, Reminder $reminder): bool
    {
        return $this->update($user, $reminder);
    }
}
