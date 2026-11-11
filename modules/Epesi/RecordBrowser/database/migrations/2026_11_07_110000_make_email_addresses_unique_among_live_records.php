<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An address belongs to one live record: a deleted record no longer holds it
 * (legacy's get_record_by_email() looks at active=1 only), yet keeps it for
 * Restore. The unique index moves from `value` to `live_value`, which holds the
 * address while the owner holds it and is NULL otherwise (EmailAddress).
 *
 * A deleted contact's login address stays held: the account can still sign in
 * with it.
 */
return new class extends Migration
{
    private const TABLE = 'epesi_recordbrowser_email_addresses';

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->string('live_value', 255)->nullable()->after('value');
        });

        DB::table(self::TABLE)->update(['live_value' => DB::raw('value')]);

        foreach (DB::table(self::TABLE)->distinct()->pluck('owner_type') as $alias) {
            $this->releaseAddressesOfDeleted((string) $alias);
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropUnique(['value']);
            $table->index('value');
            $table->unique('live_value');
        });
    }

    public function down(): void
    {
        // Back to one row per address: a live holder wins, then the oldest row.
        foreach (DB::table(self::TABLE)->whereNull('live_value')->orderBy('id')->get(['id', 'value']) as $row) {
            $taken = DB::table(self::TABLE)
                ->where('value', $row->value)
                ->where(fn ($query) => $query->whereNotNull('live_value')->orWhere('id', '<', $row->id))
                ->exists();

            if ($taken) {
                DB::table(self::TABLE)->where('id', $row->id)->delete();
            }
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropUnique(['live_value']);
            $table->dropIndex(['value']);
            $table->dropColumn('live_value');
            $table->unique('value');
        });
    }

    private function releaseAddressesOfDeleted(string $alias): void
    {
        $class = Relation::getMorphedModel($alias);

        if ($class === null || ! class_exists($class) || ! in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
            return;
        }

        // Only the table's name: building a model would ask for its recordset's
        // fields, which may not be migrated yet.
        $table = (new ReflectionClass($class))->newInstanceWithoutConstructor()->getTable();

        if (! Schema::hasTable($table)) {
            return;
        }

        $columns = Schema::hasColumn($table, 'user_id') ? ['id', 'user_id'] : ['id'];

        DB::table($table)->whereNotNull('deleted_at')->orderBy('id')->select($columns)->chunk(500, function ($owners) use ($alias): void {
            foreach ($owners as $owner) {
                $login = isset($owner->user_id) ? DB::table('users')->where('id', $owner->user_id)->value('email') : null;

                DB::table(self::TABLE)
                    ->where('owner_type', $alias)
                    ->where('owner_id', $owner->id)
                    ->when($login !== null, fn ($query) => $query->where('value', '!=', mb_strtolower((string) $login)))
                    ->update(['live_value' => null]);
            }
        });
    }
};
