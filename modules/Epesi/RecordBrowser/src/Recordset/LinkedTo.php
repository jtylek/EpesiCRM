<?php

namespace Epesi\Modules\RecordBrowser\Recordset;

use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Which recordsets a Linked to field (Field::linkedTo()) offers, in three
 * layers:
 *
 * - the module's default: the field's own list, or ANY for every linkable
 *   recordset, including one installed later;
 * - other modules joining in: offer() adds a recordset to another module's
 *   field (Projects making itself linkable from Tasks) without that module
 *   naming it;
 * - the administrator: a `recordsets` override in FieldOverrides
 *   (Administration → Recordsets), which wins over both, in either direction.
 *
 * FieldOverrides::resolve() applies the second and third to a recordset's
 * fields; Field::relatedRecordsets() reads the result.
 */
class LinkedTo
{
    /** Every linkable recordset — as an argument, a stored override, or a field's `recordsets` param of null. */
    public const ANY = '*';

    /** @var array<string, list<string>> "source alias|field" => target aliases */
    protected static array $offers = [];

    /**
     * Makes $target (a model class or morph alias) linkable from $field of
     * the recordset $source by default.
     */
    public static function offer(string $source, string $field, string $target): void
    {
        $key = static::alias($source).'|'.$field;
        static::$offers[$key] = array_values(array_unique([...static::$offers[$key] ?? [], static::alias($target)]));
    }

    /**
     * @return list<string> the morph aliases other modules offered to $field of $source
     */
    public static function offered(string $source, string $field): array
    {
        return static::$offers[static::alias($source).'|'.$field] ?? [];
    }

    /** For tests: forget every offer. */
    public static function reset(): void
    {
        static::$offers = [];
    }

    /**
     * A field's `recordsets` param from what a module or administrator gave:
     * ANY (or null) for every recordset, else a list of morph aliases.
     *
     * @param  list<string>|string|null  $recordsets
     * @return list<string>|null
     */
    public static function normalize(array|string|null $recordsets): ?array
    {
        if ($recordsets === null || $recordsets === self::ANY) {
            return null;
        }

        return array_values(array_unique(array_map(static::alias(...), (array) $recordsets)));
    }

    protected static function alias(string $one): string
    {
        return class_exists($one) ? Relation::getMorphAlias($one) : $one;
    }
}
