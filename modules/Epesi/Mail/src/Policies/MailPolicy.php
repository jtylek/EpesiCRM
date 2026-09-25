<?php

namespace Epesi\Modules\Mail\Policies;

use App\Models\User;
use Epesi\Modules\Mail\Models\Mail;

/**
 * CRM_MailInstall's rc_mails access: employees view, link and delete
 * archived mail. There is no create/edit of a message's content — it
 * arrives by archiving (upload, fetch or sending), and only its links change.
 */
class MailPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager', 'employee']);
    }

    public function view(User $user, Mail $mail): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Mail $mail): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Mail $mail): bool
    {
        return $this->viewAny($user);
    }

    public function restore(User $user, Mail $mail): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager']);
    }

    public function forceDelete(User $user, Mail $mail): bool
    {
        return $user->hasRole('super_admin');
    }
}
