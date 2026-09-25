<?php

declare(strict_types=1);

namespace Epesi\Modules\CommonData\Policies;

use Epesi\Modules\CommonData\Models\CommonDataNode;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Reaching the administration panel at all already requires super_admin, and
 * filament-shield gives super_admin a Gate::before bypass — so this gates the
 * resource for any other role that is ever granted panel access, and the
 * readonly rule that must hold for everyone lives in CommonDataNode instead.
 */
class CommonDataNodePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CommonDataNode');
    }

    public function view(AuthUser $authUser, CommonDataNode $commonDataNode): bool
    {
        return $authUser->can('View:CommonDataNode');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CommonDataNode');
    }

    public function update(AuthUser $authUser, CommonDataNode $commonDataNode): bool
    {
        return ! $commonDataNode->readonly && $authUser->can('Update:CommonDataNode');
    }

    public function delete(AuthUser $authUser, CommonDataNode $commonDataNode): bool
    {
        return ! $commonDataNode->readonly && $authUser->can('Delete:CommonDataNode');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CommonDataNode');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CommonDataNode');
    }
}
