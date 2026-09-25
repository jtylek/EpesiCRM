<?php

namespace Epesi\Modules\Mail\Policies;

use App\Models\User;
use Epesi\Modules\Mail\Models\MailAccount;

/**
 * Accounts are personal — rc_accounts' `epesi_user = USER_ID` for view,
 * edit and delete, with no administrator override: nobody else gets to read
 * or send from your mailbox.
 */
class MailAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager', 'employee']);
    }

    public function view(User $user, MailAccount $account): bool
    {
        return $account->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, MailAccount $account): bool
    {
        return $account->user_id === $user->id;
    }

    public function delete(User $user, MailAccount $account): bool
    {
        return $account->user_id === $user->id;
    }
}
