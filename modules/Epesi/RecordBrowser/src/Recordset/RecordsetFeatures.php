<?php

namespace Epesi\Modules\RecordBrowser\Recordset;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which recordsets an addon module attaches itself to — E-mails, Notes —
 * as Administration → Recordsets lets an administrator choose.
 *
 * A module declares its feature with define() and the recordsets it comes on
 * by default; an administrator's later choice is a row that wins over that
 * default, in either direction. So a recordset added by a module installed
 * afterwards starts with what the modules declared (nothing, unless they
 * name it) and the administrator turns features on from there.
 *
 * Read when the application boots (relations, tabs) and on each request that
 * offers a record type, so a change applies from the next request.
 */
class RecordsetFeatures
{
    /**
     * @var array<string, array{label: string, defaults: array<int, string>}>
     */
    protected static array $features = [];

    /** @var array<string, bool>|null "alias|feature" => enabled, once the table is read */
    protected static ?array $choices = null;

    /**
     * @param  array<int, string>  $defaults  morph aliases it is on for until an administrator decides
     */
    public static function define(string $feature, string $label, array $defaults = []): void
    {
        static::$features[$feature] = ['label' => $label, 'defaults' => array_values(array_unique([
            ...(static::$features[$feature]['defaults'] ?? []),
            ...$defaults,
        ]))];
    }

    /** Another module's recordset coming with the feature on, as Attachments::enableFor() does. */
    public static function enableByDefault(string $feature, string $alias): void
    {
        static::define($feature, static::$features[$feature]['label'] ?? $feature, [$alias]);
    }

    /**
     * @return array<string, string> feature => label
     */
    public static function definitions(): array
    {
        return array_map(fn (array $feature): string => $feature['label'], static::$features);
    }

    /**
     * @return array<int, string> morph aliases the feature is on for
     */
    public static function aliasesFor(string $feature): array
    {
        $choices = static::choices();
        $aliases = static::$features[$feature]['defaults'] ?? [];

        foreach ($choices as $key => $enabled) {
            [$alias, $name] = explode('|', $key, 2);

            if ($name === $feature && $enabled) {
                $aliases[] = $alias;
            }
        }

        return array_values(array_filter(
            array_unique($aliases),
            fn (string $alias): bool => $choices["{$alias}|{$feature}"] ?? true,
        ));
    }

    public static function enabled(string $feature, string $alias): bool
    {
        return in_array($alias, static::aliasesFor($feature), true);
    }

    public static function set(string $feature, string $alias, bool $enabled): void
    {
        DB::table('epesi_recordbrowser_recordset_features')->upsert(
            [['model_type' => $alias, 'feature' => $feature, 'enabled' => $enabled, 'created_at' => now(), 'updated_at' => now()]],
            ['model_type', 'feature'],
            ['enabled', 'updated_at'],
        );

        static::flush();
    }

    /** For tests, and after a change: forget what was read. Registered features stay. */
    public static function flush(): void
    {
        static::$choices = null;
    }

    /** For tests: forget every registered feature as well. */
    public static function reset(): void
    {
        static::$features = [];
        static::$choices = null;
    }

    /**
     * @return array<string, bool>
     */
    protected static function choices(): array
    {
        if (static::$choices !== null) {
            return static::$choices;
        }

        // Not read before the table exists (a fresh install, a core module
        // migrating): the defaults stand, and nothing is remembered.
        if (! Schema::hasTable('epesi_recordbrowser_recordset_features')) {
            return [];
        }

        $choices = [];

        foreach (DB::table('epesi_recordbrowser_recordset_features')->get() as $row) {
            $choices["{$row->model_type}|{$row->feature}"] = (bool) $row->enabled;
        }

        return static::$choices = $choices;
    }
}
