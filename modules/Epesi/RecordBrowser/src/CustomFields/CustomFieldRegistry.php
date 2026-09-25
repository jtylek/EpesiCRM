<?php

namespace Epesi\Modules\RecordBrowser\CustomFields;

use Epesi\Modules\RecordBrowser\Models\Concerns\HasCustomFields;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Active field definitions, keyed by morph alias.
 *
 * Consulted on **every instantiation** of a model that uses HasCustomFields, so
 * it reads a generated PHP file rather than the database — the same pattern and
 * the same reasons as App\Support\Modules\ModuleRegistry, and the same failure
 * behaviour: an unreadable table means "no custom fields" for this request
 * rather than a broken application, and that answer is never cached.
 *
 * Rewritten by CustomField's model events on every save and delete. Never read
 * or written by the test suite (see usesCacheFile()).
 */
class CustomFieldRegistry
{
    /** @var array<string, array<int, array<string, mixed>>>|null */
    protected static ?array $definitions = null;

    /** @var array<class-string, array<int, Field>> */
    protected static array $fields = [];

    /**
     * @return array<string, array<int, array<string, mixed>>> morph alias => definitions
     */
    public static function all(): array
    {
        if (static::$definitions !== null) {
            return static::$definitions;
        }

        $cached = static::readCache();

        if ($cached !== null) {
            return static::$definitions = $cached;
        }

        $fromDatabase = static::readDatabase();

        if ($fromDatabase === null) {
            return static::$definitions = [];
        }

        static::writeCache($fromDatabase);

        return static::$definitions = $fromDatabase;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forAlias(string $alias): array
    {
        return static::all()[$alias] ?? [];
    }

    /**
     * @param  class-string<Model>  $model
     * @return array<int, array<string, mixed>>
     */
    public static function forModel(string $model): array
    {
        $alias = static::aliasFor($model);

        return $alias === null ? [] : static::forAlias($alias);
    }

    /**
     * The definitions as Field objects, ready to be concatenated with a
     * recordset's own `fields()` — the single code path that makes an
     * administrator's field indistinguishable from a shipped one.
     *
     * @param  class-string<Model>  $model
     * @return array<int, Field>
     */
    public static function fieldsFor(string $model): array
    {
        return static::$fields[$model] ??= array_map(
            fn (array $definition): Field => static::toField($definition),
            static::forModel($model),
        );
    }

    /**
     * @param  class-string<Model>  $model
     * @return array<int, string> the `cf_*` columns, for mergeFillable()
     */
    public static function columnsFor(string $model): array
    {
        return array_column(static::forModel($model), 'column');
    }

    /**
     * @param  class-string<Model>  $model
     * @return array<string, string> column => cast, for mergeCasts()
     */
    public static function castsFor(string $model): array
    {
        $casts = [];

        foreach (static::fieldsFor($model) as $field) {
            if ($cast = $field->cast()) {
                $casts[$field->name] = $cast;
            }
        }

        return $casts;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function toField(array $definition): Field
    {
        $type = FieldType::from((string) $definition['type']);

        return Field::make((string) $definition['column'], $type)
            ->params((array) ($definition['params'] ?? []))
            ->label((string) $definition['label'])
            ->required((bool) $definition['required'])
            ->section($definition['section'] ?? null)
            ->help($definition['help'] ?? null)
            ->inForm((bool) $definition['show_in_form'])
            ->inView((bool) $definition['show_in_view'])
            ->inTable((bool) $definition['show_in_table'])
            ->filterable((bool) $definition['filterable']);
    }

    /**
     * Class name to morph alias **without instantiating the model** — this is
     * reached from a model's own constructor (HasCustomFields' initializer), so
     * `(new $model)->getMorphClass()` would recurse forever.
     *
     * @param  class-string<Model>  $model
     */
    public static function aliasFor(string $model): ?string
    {
        $alias = array_search($model, Relation::morphMap(), true);

        return is_string($alias) ? $alias : null;
    }

    /**
     * Recordsets an administrator may add fields to: every model in the morph
     * map that uses HasCustomFields. Derived rather than configured — the morph
     * map is mandatory anyway (it is enforced), and a model opting in with one
     * `use` statement and then having to be listed somewhere else too is exactly
     * the kind of second register that goes stale.
     *
     * @return array<string, string> morph alias => human label
     */
    public static function participatingModels(): array
    {
        $models = [];

        foreach (Relation::morphMap() as $alias => $class) {
            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }

            if (in_array(HasCustomFields::class, class_uses_recursive($class), true)) {
                $models[$alias] = (string) Str::of(class_basename($class))->headline();
            }
        }

        asort($models);

        return $models;
    }

    public static function refresh(): void
    {
        static::$definitions = null;
        static::$fields = [];

        $fromDatabase = static::readDatabase() ?? [];

        static::writeCache($fromDatabase);

        static::$definitions = $fromDatabase;
    }

    public static function cachePath(): string
    {
        return base_path('bootstrap/cache/epesi-custom-fields.php');
    }

    /** For tests: forget the per-process answer, which was the previous test's database. */
    public static function flush(): void
    {
        static::$definitions = null;
        static::$fields = [];
    }

    /**
     * The cache file belongs to the real install sharing this checkout, not to
     * the test suite's in-memory database. Reading it would hand tests the
     * install's fields; writing it would leave the install with the test
     * database's — `cf_*` columns its own tables don't have, which every model
     * then treats as fillable and every save then fails on. Same reasoning as
     * ModuleRegistry::refresh().
     */
    protected static function usesCacheFile(): bool
    {
        return ! app()->runningUnitTests();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>|null
     */
    protected static function readCache(): ?array
    {
        if (! static::usesCacheFile()) {
            return null;
        }

        $path = static::cachePath();

        if (! is_file($path)) {
            return null;
        }

        $cached = @include $path;

        return is_array($cached) ? $cached : null;
    }

    /**
     * The query builder rather than Eloquent, for the same reason ModuleRegistry
     * uses it: this can run before Model::setConnectionResolver().
     *
     * @return array<string, array<int, array<string, mixed>>>|null
     */
    protected static function readDatabase(): ?array
    {
        try {
            $rows = DB::table('custom_fields')
                ->where('active', true)
                ->orderBy('model_type')
                ->orderBy('position')
                ->orderBy('id')
                ->get();
        } catch (Throwable) {
            return null;
        }

        $definitions = [];

        foreach ($rows as $row) {
            $definitions[$row->model_type][] = [
                'id' => (int) $row->id,
                'column' => $row->column,
                'name' => $row->name,
                'label' => $row->label,
                'type' => $row->type,
                'params' => json_decode((string) $row->params, true) ?: [],
                'required' => (bool) $row->required,
                'section' => $row->section,
                'help' => $row->help,
                'show_in_form' => (bool) $row->show_in_form,
                'show_in_view' => (bool) $row->show_in_view,
                'show_in_table' => (bool) $row->show_in_table,
                'filterable' => (bool) $row->filterable,
                'exportable' => (bool) $row->exportable,
            ];
        }

        return $definitions;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $definitions
     */
    protected static function writeCache(array $definitions): void
    {
        if (! static::usesCacheFile()) {
            return;
        }

        $path = static::cachePath();

        try {
            $temporary = $path.'.'.getmypid().'.tmp';

            file_put_contents($temporary, '<?php return '.var_export($definitions, true).';'.PHP_EOL);
            rename($temporary, $path);

            // Read back with include, so OPcache would otherwise keep serving
            // the old list until it next checks the file (every 2 seconds by
            // default) — right after setup, a page without the new modules.
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($path, true);
            }
        } catch (Throwable) {
            // A read-only bootstrap/cache just means every request pays for the
            // query instead; not worth failing the request over.
        }
    }
}
