<?php

namespace Epesi\Modules\Attachments\Policies;

use App\Enums\RecordPermission;
use App\Models\User;
use Epesi\Modules\Attachments\Models\Attachment;

/**
 * Ported from Utils_AttachmentInstall::install()'s ACL rules:
 *
 *   add_access('utils_attachment', 'view',   'ACCESS:employee', ['(!permission' => 2, '|:Created_by' => 'USER_ID']);
 *   add_access('utils_attachment', 'edit',   'ACCESS:employee', ['(permission' => 0, '|:Created_by' => 'USER_ID']);
 *   add_access('utils_attachment', 'delete', 'ACCESS:employee', [':Created_by' => 'USER_ID']);
 *   add_access('utils_attachment', 'delete', ['ACCESS:employee', 'ACCESS:manager']);
 *
 * The private-note half of `view` is enforced by the model's ownership scope,
 * so a private note someone else wrote never loads in the first place.
 */
class AttachmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager', 'employee']);
    }

    public function view(User $user, Attachment $attachment): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $attachment->permission !== RecordPermission::Private
            || $attachment->created_by === $user->id
            || $user->hasAnyRole(['super_admin', 'manager']);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Attachment $attachment): bool
    {
        if ($user->hasAnyRole(['super_admin', 'manager'])) {
            return true;
        }

        return $user->hasRole('employee')
            && ($attachment->created_by === $user->id || $attachment->permission === RecordPermission::Public);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        if ($user->hasAnyRole(['super_admin', 'manager'])) {
            return true;
        }

        return $user->hasRole('employee') && $attachment->created_by === $user->id;
    }

    public function restore(User $user, Attachment $attachment): bool
    {
        return $this->delete($user, $attachment);
    }

    public function forceDelete(User $user, Attachment $attachment): bool
    {
        return $user->hasRole('super_admin');
    }
}
