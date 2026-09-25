<?php

namespace Epesi\Modules\CRM\Companies\Policies;

use App\Enums\RecordPermission;
use App\Models\User;
use Epesi\Modules\CRM\Companies\Models\Company;

/**
 * Ported from CRM_ContactsInstall::install_permissions()'s company rules —
 * role gates which resource types a user can touch at all; row-level
 * ownership (handled by Company::applyExtraVisibility() for reads) is
 * re-checked here for writes, since the global scope only filters queries.
 *
 * Not ported: per-field `blocked_fields` (e.g. non-managers can't touch
 * `group`/`permission` on someone else's company) — that's a genuine gap,
 * flagged rather than silently dropped.
 */
class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager', 'employee']);
    }

    public function view(User $user, Company $company): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager', 'employee']);
    }

    public function update(User $user, Company $company): bool
    {
        if ($user->hasAnyRole(['super_admin', 'manager'])) {
            return true;
        }

        if ($company->id === $user->companyId()) {
            return true;
        }

        return $user->hasRole('employee')
            && ($company->created_by === $user->id || $company->permission === RecordPermission::Public);
    }

    public function delete(User $user, Company $company): bool
    {
        if ($user->hasAnyRole(['super_admin', 'manager'])) {
            return true;
        }

        return $user->hasRole('employee') && $company->created_by === $user->id;
    }

    public function restore(User $user, Company $company): bool
    {
        return $this->delete($user, $company);
    }

    public function forceDelete(User $user, Company $company): bool
    {
        return $user->hasRole('super_admin');
    }
}
