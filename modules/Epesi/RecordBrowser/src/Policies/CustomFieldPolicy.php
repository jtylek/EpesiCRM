<?php

declare(strict_types=1);

namespace Epesi\Modules\RecordBrowser\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomFieldPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CustomField');
    }

    public function view(AuthUser $authUser, CustomField $customField): bool
    {
        return $authUser->can('View:CustomField');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CustomField');
    }

    public function update(AuthUser $authUser, CustomField $customField): bool
    {
        return $authUser->can('Update:CustomField');
    }

    public function delete(AuthUser $authUser, CustomField $customField): bool
    {
        return $authUser->can('Delete:CustomField');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CustomField');
    }

    public function restore(AuthUser $authUser, CustomField $customField): bool
    {
        return $authUser->can('Restore:CustomField');
    }

    public function forceDelete(AuthUser $authUser, CustomField $customField): bool
    {
        return $authUser->can('ForceDelete:CustomField');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CustomField');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CustomField');
    }

    public function replicate(AuthUser $authUser, CustomField $customField): bool
    {
        return $authUser->can('Replicate:CustomField');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CustomField');
    }

}