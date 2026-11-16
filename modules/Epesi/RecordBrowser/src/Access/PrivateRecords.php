<?php

namespace Epesi\Modules\RecordBrowser\Access;

use App\Enums\RecordPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\RegisterErrorViewPaths;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A Private record is its author's: nobody else sees it, a manager or a super
 * admin neither, in any panel (the Administration panel's Records tab too).
 * Only the model's own "yours" clauses (your own company, your own contact,
 * a task you are an employee of; HasOwnershipVisibility::applyExtraVisibility())
 * still let someone else reach it. The rule lives in the ownership scope
 * (HasOwnershipVisibility); this is the same question asked of one record that
 * was loaded some other way, for the policies: Shield lets a super admin past
 * every policy, so the scope is what keeps the record from him, but a manager's
 * update/delete must not depend on how the record was loaded.
 */
class PrivateRecords
{
    private static bool $suspended = false;

    /**
     * Runs $callback with the ownership scope off, for code that fills the
     * database as the signed-in user but is no user's reading of it (the demo
     * seeder). Never for a request: nothing a person does goes through here.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function suspended(callable $callback): mixed
    {
        $before = self::$suspended;
        self::$suspended = true;

        try {
            return $callback();
        } finally {
            self::$suspended = $before;
        }
    }

    /**
     * The scope hides someone else's Private record, so opening it by URL ends
     * in "not found". For a signed-in user that record does exist, and saying
     * so is kinder than a 404: a 403 page, only when the model was found
     * without its scopes and is Private to someone else.
     */
    public static function forbiddenResponse(NotFoundHttpException $e, Request $request): ?Response
    {
        $previous = $e->getPrevious();
        $user = Auth::user();

        if (! $previous instanceof ModelNotFoundException || ! $user instanceof User || count($previous->getIds()) !== 1) {
            return null;
        }

        $class = $previous->getModel();

        if (! is_string($class) || ! is_subclass_of($class, Model::class) || ! method_exists($class, 'applyOwnershipVisibility')) {
            return null;
        }

        $record = $class::query()->withoutGlobalScopes()->find($previous->getIds()[0]);

        if ($record === null || self::reachableBy($user, $record)) {
            return null;
        }

        // The "errors" view namespace is only registered once the handler renders an HTTP exception itself.
        (new RegisterErrorViewPaths)();

        return response()->view('errors::403', [
            'exception' => new AccessDeniedHttpException(__("You don't have permissions to access this record")),
        ], 403);
    }

    public static function isSuspended(): bool
    {
        return self::$suspended;
    }

    /**
     * Whether $user may reach $record as far as its Private permission goes.
     * Public and read-only records, and those $user created, need no query;
     * someone else's Private one asks the model's own clause.
     */
    public static function reachableBy(User $user, Model $record): bool
    {
        // HasOwnershipVisibility's, on every model that has the scope.
        if (! method_exists($record, 'applyOwnershipVisibility')) {
            return true;
        }

        $permission = $record->getAttribute('permission');
        $permission = $permission instanceof RecordPermission ? $permission : RecordPermission::tryFrom((int) $permission);

        if ($permission !== RecordPermission::Private || (int) $record->getAttribute('created_by') === (int) $user->getKey()) {
            return true;
        }

        if (! $record->exists) {
            return false;
        }

        // Without scopes: a trashed record is asked about too (Restore).
        $query = $record->newQueryWithoutScopes()->whereKey($record->getKey());
        $record::applyOwnershipVisibility($query, $user);

        return $query->exists();
    }
}
