<?php

namespace Epesi\Modules\MigrationWizard\Orchestration;

use Epesi\Modules\MigrationWizard\Verification\MigrationVerifier;
use InvalidArgumentException;

/**
 * The operator's explicit decisions about legacy data that no importer migrates,
 * as kept in the migration state (key `operator_choices`):
 *
 *  preserve   exact legacy table names / file areas ("data/<dir>") to keep, or the
 *             token UNKNOWN, which preserves everything the coverage report
 *             classifies as unknown (non-destructive: nothing is ever removed for it)
 *  discard    exact table names / file areas approved for removal at cleanup. Never
 *             a default, never a pattern, never a token; needs its own confirmation
 *  core_only  continue with Epesi Core only: known-but-not-migrated data is preserved
 *
 * Premium and Custom module code and data are never discarded by default: they are
 * preserved unless the operator names an exact table or file area to discard.
 */
final class OperatorChoices
{
    /** Preserve every table/area the coverage report classifies as unknown. */
    public const UNKNOWN = '@unknown';

    /** @return array{preserve: list<string>, discard: list<string>, core_only: bool} */
    public static function none(): array
    {
        return ['preserve' => [], 'discard' => [], 'core_only' => false];
    }

    /**
     * Parse "a,b,c" option values and validate them. Names are exact: letters,
     * digits, underscore, dollar, dot, slash and star only for the loose data
     * file area "data/*"; no wildcard other than that literal area is accepted.
     *
     * @return array{preserve: list<string>, discard: list<string>, core_only: bool}
     */
    public static function fromOptions(?string $preserve, ?string $discard, bool $coreOnly): array
    {
        $choices = [
            'preserve' => self::names($preserve, true),
            'discard' => self::names($discard, false),
            'core_only' => $coreOnly,
        ];

        return self::validate($choices);
    }

    /**
     * Merge newly given choices over the stored ones: an option that was not given
     * keeps its stored value, a given one replaces it.
     *
     * @param  array<string, mixed>  $stored
     * @param  array{preserve: ?string, discard: ?string, core_only: bool}  $given
     * @return array{preserve: list<string>, discard: list<string>, core_only: bool}
     */
    public static function merge(array $stored, array $given): array
    {
        $base = self::normalise($stored);
        $choices = [
            'preserve' => $given['preserve'] === null ? $base['preserve'] : self::names($given['preserve'], true),
            'discard' => $given['discard'] === null ? $base['discard'] : self::names($given['discard'], false),
            'core_only' => $given['core_only'] || $base['core_only'],
        ];

        return self::validate($choices);
    }

    /** Stored value, tolerant of a missing or odd value (no choices). @return array{preserve: list<string>, discard: list<string>, core_only: bool} */
    public static function normalise(mixed $stored): array
    {
        if (! is_array($stored)) {
            return self::none();
        }

        return [
            'preserve' => array_values(array_map('strval', (array) ($stored['preserve'] ?? []))),
            'discard' => array_values(array_map('strval', (array) ($stored['discard'] ?? []))),
            'core_only' => (bool) ($stored['core_only'] ?? false),
        ];
    }

    /**
     * @param  array{preserve: list<string>, discard: list<string>, core_only: bool}  $choices
     * @return array{preserve: list<string>, discard: list<string>, core_only: bool}
     */
    private static function validate(array $choices): array
    {
        $overlap = array_values(array_intersect($choices['preserve'], $choices['discard']));
        if ($overlap !== []) {
            throw new InvalidArgumentException('Cannot both preserve and discard: '.implode(', ', $overlap).'.');
        }

        return $choices;
    }

    /** @return list<string> */
    private static function names(?string $value, bool $allowToken): array
    {
        if ($value === null) {
            return [];
        }
        $names = [];
        foreach (explode(',', $value) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            if (! $allowToken && in_array($name, MigrationVerifier::PROTECTED_AREAS, true)) {
                throw new InvalidArgumentException("{$name} holds business files that must be migrated; it cannot be discarded.");
            }
            if ($name === self::UNKNOWN) {
                if (! $allowToken) {
                    throw new InvalidArgumentException('Only exact table or file-area names can be discarded; '.self::UNKNOWN.' is not accepted.');
                }
            } elseif (preg_match('#^[A-Za-z0-9_$]+(/(?!\.+$)[A-Za-z0-9_$.\-]+)?$#D', $name) !== 1 && $name !== 'data/*') {
                throw new InvalidArgumentException("Invalid table or file-area name '{$name}'. Use exact names such as company_data_1 or data/Premium_Foo.");
            }
            $names[$name] = true;
        }

        return array_keys($names);
    }
}
