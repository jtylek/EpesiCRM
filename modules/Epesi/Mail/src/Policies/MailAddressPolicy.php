<?php

namespace Epesi\Modules\Mail\Policies;

use App\Models\User;
use Epesi\Modules\Mail\Models\MailAddress;

/** rc_multiple_emails: every employee may view, add, edit and delete. */
class MailAddressPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager', 'employee']);
    }

    public function view(User $user, MailAddress $address): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, MailAddress $address): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, MailAddress $address): bool
    {
        return $this->viewAny($user);
    }
}
