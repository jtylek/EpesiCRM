<?php

declare(strict_types=1);

namespace Epesi\Modules\StoreServer\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Epesi\Modules\StoreServer\Models\Licence;
use Illuminate\Auth\Access\HandlesAuthorization;

class LicencePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Licence');
    }

    public function view(AuthUser $authUser, Licence $licence): bool
    {
        return $authUser->can('View:Licence');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Licence');
    }

    public function update(AuthUser $authUser, Licence $licence): bool
    {
        return $authUser->can('Update:Licence');
    }

    public function delete(AuthUser $authUser, Licence $licence): bool
    {
        return $authUser->can('Delete:Licence');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Licence');
    }

    public function restore(AuthUser $authUser, Licence $licence): bool
    {
        return $authUser->can('Restore:Licence');
    }

    public function forceDelete(AuthUser $authUser, Licence $licence): bool
    {
        return $authUser->can('ForceDelete:Licence');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Licence');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Licence');
    }

    public function replicate(AuthUser $authUser, Licence $licence): bool
    {
        return $authUser->can('Replicate:Licence');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Licence');
    }

}