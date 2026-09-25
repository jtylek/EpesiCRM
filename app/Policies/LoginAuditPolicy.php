<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LoginAudit;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LoginAuditPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LoginAudit');
    }

    public function view(AuthUser $authUser, LoginAudit $loginAudit): bool
    {
        return $authUser->can('View:LoginAudit');
    }
}
