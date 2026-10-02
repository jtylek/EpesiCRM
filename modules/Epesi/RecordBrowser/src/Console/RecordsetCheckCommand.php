<?php

namespace Epesi\Modules\RecordBrowser\Console;

use Epesi\Modules\RecordBrowser\Files\StoredFileIds;
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

            if ($field->type === FieldType::Customer) {
                $displayed[] = "{$field->name}_type";
                $displayed[] = "{$field->name}_id";
            }

            // Derived from the key, not stored — no column to be missing.
            if ($field->type === FieldType::Autonumber) {
                continue;
            }

            // Kept in the shared link table, which the model reaches through
            // HasRecordLinks (HasCustomFields brings it) — Customers is the
            // same storage, just restricted to Field::customers()'s $models.
            if (in_array($field->type, [FieldType::Related, FieldType::Customers], true)) {
                if (! method_exists($model, 'recordLinks')) {
                    $this->components->twoColumnDetail(
                        "{$resource}::fields() {$field->name}",
                        '<fg=red>a "link to any record" field needs the model using HasRecordLinks (or HasCustomFields)</>',
                    );
                    $problems++;
                }

                continue;
            }

            // Its items are rows of the collection type's own table, which
            // the model reaches through HasCollections.
            if ($field->type === FieldType::Collection) {
                $problems += $this->checkCollection($resource, $model, $field);

                continue;
            }

            // A morphTo pair (`{name}_type`/`{name}_id`), not one column —
            // and the model needs the relation itself, the same as
            // Relations below.
            if ($field->type === FieldType::Customer) {
                if (! method_exists($model, $field->name)) {
                    $this->components->twoColumnDetail(
                        "{$resource}::fields() {$field->name}",
                        '<fg=red>a Customer field needs a matching morphTo() relationship on the model</>',
                    );
                    $problems++;
                }

                foreach (["{$field->name}_type", "{$field->name}_id"] as $column) {
                    if (! in_array($column, $columns, true)) {
                        $this->components->twoColumnDetail(
                            "{$resource}::fields() {$field->name}",
                            "<fg=red>no column `{$table}`.`{$column}` — is the migration missing?</>",
                        );
                        $problems++;
                    }
                }

                continue;
            }

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

            // Without the cast the column isn't recognised as holding files:
            // they're never released, and the download route won't serve them.
            if ($field->type === FieldType::File
                && (($model->getCasts()[$field->name] ?? null) !== StoredFileIds::class || ! method_exists($model, 'fileColumns'))) {
                $this->components->twoColumnDetail(
                    "{$resource}::fields() {$field->name}",
                    '<fg=red>a file field needs the column cast to StoredFileIds and the model using HasFileFields (or HasCustomFields)</>',
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
     * A collection field needs the model using HasCollections, and its type
     * a table holding every field it declares.
     *
     * @param  class-string<RecordsetResource>  $resource
     */
    protected function checkCollection(string $resource, Model $model, Field $field): int
    {
        $problems = 0;
        $type = $field->collectionType();

        if (! method_exists($model, 'syncCollection')) {
            $this->components->twoColumnDetail(
                "{$resource}::fields() {$field->name}",
                '<fg=red>a collection field needs the model using HasCollections (or HasCustomFields)</>',
            );
            $problems++;
        }

        if ($type === null) {
            $this->components->twoColumnDetail(
                "{$resource}::fields() {$field->name}",
                '<fg=red>its collection type is not a CollectionItem in the morph map</>',
            );

            return $problems + 1;
        }

        $table = (new $type)->getTable();

        if (! Schema::hasTable($table)) {
            $this->components->twoColumnDetail(
                "{$resource}::fields() {$field->name}",
                "<fg=red>no table `{$table}` for {$type} — is the migration missing?</>",
            );

            return $problems + 1;
        }

        $columns = Schema::getColumnListing($table);

        foreach (['owner_type', 'owner_id', 'field', 'kind', 'position', ...array_map(fn (Field $one): string => $one->name, array_filter($type::fields(), fn (Field $one): bool => $one->type->hasColumn()))] as $column) {
            if (! in_array($column, $columns, true)) {
                $this->components->twoColumnDetail(
                    "{$type}::fields() {$column}",
                    "<fg=red>no column `{$table}`.`{$column}` — is the migration missing?</>",
                );
                $problems++;
            }
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
