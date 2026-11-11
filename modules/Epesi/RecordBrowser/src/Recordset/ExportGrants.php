<?php

namespace Epesi\Modules\RecordBrowser\Recordset;

use App\Models\User;
use App\Support\Demo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Which roles may export a recordset. Epesi has no per-user permissions, so a
 * grant is always to a role (AI-shared/recordbrowser/permissions.md).
 */
class ExportGrants
{
    private const TABLE = 'epesi_recordbrowser_export_grants';

    public static function allowed(User $user, string $alias): bool
    {
        if (! $user->active || ! RecordsetFeatures::enabled('export', $alias)) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (! Schema::hasTable(self::TABLE)) {
            return false;
        }

        return DB::table(self::TABLE)->where('model_type', $alias)
            ->where('subject_type', 'role')
            ->whereIn('subject_id', $user->roles->pluck('id'))
            ->exists();
    }

    /** @return list<int> */
    public static function rolesFor(string $alias): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return [];
        }

        return DB::table(self::TABLE)->where('model_type', $alias)->where('subject_type', 'role')
            ->pluck('subject_id')->map(fn ($id): int => (int) $id)->all();
    }

    /** @param list<int|string> $roles */
    public static function save(string $alias, array $roles): void
    {
        abort_unless(auth()->user()?->active && auth()->user()?->hasRole('super_admin'), 403);
        abort_if(Demo::enabled(), 403);

        $data = Validator::make(['roles' => $roles], [
            'roles' => ['array'],
            'roles.*' => ['integer', 'distinct', Rule::exists('roles', 'id')],
        ])->validate();

        DB::transaction(function () use ($alias, $data): void {
            DB::table(self::TABLE)->where('model_type', $alias)->delete();

            $rows = array_map(
                fn ($id): array => ['model_type' => $alias, 'subject_type' => 'role', 'subject_id' => (int) $id],
                $data['roles'],
            );

            if ($rows !== []) {
                DB::table(self::TABLE)->insert($rows);
            }
        });
    }
}
