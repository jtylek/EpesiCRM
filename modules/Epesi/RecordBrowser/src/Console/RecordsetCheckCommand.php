<?php

namespace Epesi\Modules\RecordBrowser\Console;

use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Closes the one gap the port deliberately keeps open.
 *
 * Epesi's `install_new_recordset()` creates the table as a side effect of
 * declaring the fields, so the two can't disagree. Here a module ships a
 * migration — reviewable, versioned, replayable, and the reason the module
 * system works at all — which means `fields()` and the schema are two lists that
 * *can* drift. The classic mistake is adding a field and forgetting the
 * migration; this finds it, and the reverse (a column nothing displays).
 */
class RecordsetCheckCommand extends Command
{
    protected $signature = 'recordset:check {--strict : also report columns no field displays}';

    protected $description = 'Check every recordset\'s fields() against its table columns';

    public function handle(): int
    {
        $resources = $this->recordsetResources();

        if ($resources === []) {
            $this->components->warn('No RecordsetResource found in any panel.');

            return self::SUCCESS;
        }

        $problems = 0;

        foreach ($resources as $resource) {
            $problems += $this->checkResource($resource);
        }

        if ($problems === 0) {
            $this->components->info(count($resources).' recordset(s) checked, no problems found.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->error("{$problems} problem(s) found.");

        return self::FAILURE;
    }

    /**
     * @param  class-string<RecordsetResource>  $resource
     */
    protected function checkResource(string $resource): int
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $resource::getModel();
        $model = new $modelClass;
        $table = $model->getTable();

        if (! Schema::hasTable($table)) {
            $this->components->twoColumnDetail($resource, "<fg=red>no table `{$table}`</>");

            return 1;
        }

        $columns = Schema::getColumnListing($table);
        $problems = 0;
        $displayed = [];

        foreach ($resource::fields() as $field) {
            $displayed[] = $field->getStateName();

            // A belongsToMany has no column of its own; the pivot is its
            // storage, and a missing relationship shows up as an Eloquent
            // error long before this would help.
            if ($field->type === FieldType::Relations) {
                if (! method_exists($model, (string) $field->getStateName())) {
                    $this->components->twoColumnDetail(
                        "{$resource}::fields() {$field->name}",
                        '<fg=red>no such relationship on the model</>',
                    );
                    $problems++;
                }

                continue;
            }

            if (! in_array($field->name, $columns, true)) {
                $this->components->twoColumnDetail(
                    "{$resource}::fields() {$field->name}",
                    "<fg=red>no column `{$table}`.`{$field->name}` — is the migration missing?</>",
                );
                $problems++;
            }
        }

        if ($this->option('strict')) {
            $problems += $this->reportUndisplayedColumns($resource, $table, $columns, $displayed);
        }

        return $problems;
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $displayed
     */
    protected function reportUndisplayedColumns(string $resource, string $table, array $columns, array $displayed): int
    {
        // Housekeeping columns are never fields; `cf_*` belongs to the
        // administrator, not to fields().
        $ignored = ['id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'legacy_id'];

        $missing = array_filter(
            $columns,
            fn (string $column): bool => ! in_array($column, $displayed, true)
                && ! in_array($column, $ignored, true)
                && ! str_starts_with($column, 'cf_'),
        );

        foreach ($missing as $column) {
            $this->components->twoColumnDetail(
                "{$resource} `{$table}`.`{$column}`",
                '<fg=yellow>no field displays this column</>',
            );
        }

        return count($missing);
    }

    /**
     * Every registered resource of every panel that is built on the engine.
     *
     * @return array<int, class-string<RecordsetResource>>
     */
    protected function recordsetResources(): array
    {
        $resources = [];

        foreach (Filament::getPanels() as $panel) {
            try {
                foreach ($panel->getResources() as $resource) {
                    if (is_subclass_of($resource, RecordsetResource::class)) {
                        $resources[$resource] = $resource;
                    }
                }
            } catch (Throwable $exception) {
                $this->components->warn("Could not read panel {$panel->getId()}: {$exception->getMessage()}");
            }
        }

        ksort($resources);

        return array_values($resources);
    }
}
