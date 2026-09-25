<?php

declare(strict_types=1);

namespace Epesi\Modules\Notes\Policies;

use Epesi\Modules\Notes\Models\Note;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class NotePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Note');
    }

    public function view(AuthUser $authUser, Note $note): bool
    {
        return $authUser->can('View:Note');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Note');
    }

    public function update(AuthUser $authUser, Note $note): bool
    {
        return $authUser->can('Update:Note');
    }

    public function delete(AuthUser $authUser, Note $note): bool
    {
        return $authUser->can('Delete:Note');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Note');
    }

    public function restore(AuthUser $authUser, Note $note): bool
    {
        return $authUser->can('Restore:Note');
    }

    public function forceDelete(AuthUser $authUser, Note $note): bool
    {
        return $authUser->can('ForceDelete:Note');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Note');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Note');
    }

    public function replicate(AuthUser $authUser, Note $note): bool
    {
        return $authUser->can('Replicate:Note');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Note');
    }
}
