<?php

namespace Epesi\Modules\RecordBrowser\Models\Concerns;

use App\Enums\RecordPermission;
use App\Models\User;
use Epesi\Modules\RecordBrowser\Access\ConsultantLinks;
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
 *
 * A consultant (no legacy counterpart) is narrower than an employee: Public
 * doesn't count for him. He sees what he created, plus what
 * applyConsultantVisibility() says is tied to him.
 */
trait HasOwnershipVisibility
{
    use HasRecordAuthors;

    protected static function bootHasOwnershipVisibility(): void
    {
        static::addGlobalScope('ownership', function (Builder $builder) {
            /** @var User|null $user */
            $user = Auth::user();

            if (! $user || $user->hasAnyRole(['super_admin', 'manager'])) {
                return;
            }

            if ($user->isConsultant()) {
                $builder->where(function (Builder $query) use ($user) {
                    $query->where($query->qualifyColumn('created_by'), $user->id);

                    static::applyConsultantVisibility($query, $user);
                });

                return;
            }

            $builder->where(function (Builder $query) use ($user) {
                $query->where('permission', '!=', RecordPermission::Private->value)
                    ->orWhere('created_by', $user->id);

                static::applyExtraVisibility($query, $user);
            });
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

    /**
     * What a consultant sees besides what he created. By default the
     * contacts and companies linked to his records (ConsultantLinks) and the
     * model's "yours" clause. A model whose "yours" clause reaches through
     * another scoped model (Task's employees are Contacts) overrides this and
     * leaves that model's scope out: Contact's consultant clause asks for the
     * consultant's tasks, which would ask for his contacts again, for ever.
     */
    protected static function applyConsultantVisibility(Builder $query, User $user): void
    {
        static::applyExtraVisibility($query, $user);

        ConsultantLinks::orWhereLinked($query);
    }
}
