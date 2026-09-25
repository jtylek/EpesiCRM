<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rewrites every stored polymorphic type from a model FQCN to the morph alias
 * registered in AppServiceProvider::registerMorphAliases().
 *
 * This has to run in the same deploy as the map itself: from the moment the map
 * is enforced, new rows are written with `contact`/`user`/… while old rows still
 * say `App\Models\Contact` (the namespace those models had at the time). Nothing
 * errors — the History addon just goes empty
 * and, far worse, `model_has_roles` rows stop matching, so every user silently
 * loses their roles. There is no code path that repairs either afterwards.
 *
 * The pairs below are every morph column in the schema: spatie/laravel-activitylog's
 * two and spatie/laravel-permission's two.
 *
 * `Relation::morphMap()` is read at run time rather than hard-coding the list,
 * so an enabled module's own aliases (registered from its service provider) are
 * rewritten by the same pass.
 */
return new class extends Migration
{
    /** @var array<int, array{0: string, 1: string}> */
    protected array $columns = [
        ['activity_log', 'subject_type'],
        ['activity_log', 'causer_type'],
        ['model_has_roles', 'model_type'],
        ['model_has_permissions', 'model_type'],
    ];

    public function up(): void
    {
        $this->rewrite(fn (string $alias, string $class): array => [$class, $alias]);
    }

    public function down(): void
    {
        $this->rewrite(fn (string $alias, string $class): array => [$alias, $class]);
    }

    /**
     * @param  callable(string, class-string): array{0: string, 1: string}  $direction
     */
    protected function rewrite(callable $direction): void
    {
        foreach ($this->columns as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            foreach (Relation::morphMap() as $alias => $class) {
                [$from, $to] = $direction($alias, $class);

                DB::table($table)->where($column, $from)->update([$column => $to]);
            }
        }
    }
};
