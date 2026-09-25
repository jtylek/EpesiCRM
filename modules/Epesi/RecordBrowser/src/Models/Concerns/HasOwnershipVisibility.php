<?php

namespace Epesi\Modules\RecordBrowser\Models\Concerns;

use App\Enums\RecordPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Row-level visibility, ported from CRM_ContactsInstall::install_permissions()'s
 * view crits rather than translated field-by-field:
 *
 *   add_access('company', 'view', 'ACCESS:employee', ['(!permission' => 2, '|:Created_by' => 'USER_ID']);
 *   add_access('company', 'view', 'ALL', ['id' => 'USER_COMPANY']);
 *
 * i.e. a record is visible if it isn't Private, OR you created it, OR
 * (per-model, via applyExtraVisibility()) it's "yours" some other way — your
 * own company, or your own linked contact. Managers and super admins bypass
 * this entirely, mirroring Epesi's unrestricted ACCESS:manager/SUPERADMIN
 * grants. This is the AureusERP-style OwnershipScope pattern the port
 * analysis recommended over hand-rolling something bespoke.
 */
trait HasOwnershipVisibility
{
    protected static function bootHasOwnershipVisibility(): void
    {
        static::addGlobalScope('ownership', function (Builder $builder) {
            /** @var User|null $user */
            $user = Auth::user();

            if (! $user || $user->hasAnyRole(['super_admin', 'manager'])) {
                return;
            }

            $builder->where(function (Builder $query) use ($user) {
                $query->where('permission', '!=', RecordPermission::Private->value)
                    ->orWhere('created_by', $user->id);

                static::applyExtraVisibility($query, $user);
            });
        });

        static::creating(function ($model) {
            $model->created_by ??= Auth::id();
        });
    }

    /**
     * Per-model "this is yours regardless of permission/created_by" clause —
     * e.g. Company adds "your own company", Contact adds "your own login".
     */
    protected static function applyExtraVisibility(Builder $query, User $user): void
    {
        //
    }
}
