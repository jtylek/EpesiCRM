<?php

namespace App\Support\Modules;

/**
 * Minimal constraint matching for a module manifest's "epesi_core" field.
 *
 * Deliberately not composer/semver: that package isn't a dependency of this
 * app, and the only constraints a module manifest needs to express are a
 * caret range, a plain comparator, or "*". Anything more elaborate than that
 * belongs in a real resolver, not here.
 *
 * Supports: "*", "^1.2", ">=1.0", "<2.0", "1.2.3", and comma-separated ANDs.
 *
 * One exception to plain caret semantics: a "^2.x" constraint also accepts
 * 3.x. The Laravel epesi was renumbered from 2.0 to 3.0 before 2.0 ever had a
 * final release (legacy epesi 1.x derivatives already use 2.x), so 3.0 is the
 * same line as the 2.0 release candidates, and modules published for them
 * must keep installing.
 */
class VersionConstraint
{
    /** The major version 3.0 renumbered, and the one that renumbered it. */
    protected const RENUMBERED_MAJOR = 2;

    protected const RENUMBERED_TO = 3;

    public static function satisfies(string $version, ?string $constraint): bool
    {
        $constraint = trim((string) $constraint);

        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        foreach (explode(',', $constraint) as $part) {
            if (! static::satisfiesOne($version, trim($part))) {
                return false;
            }
        }

        return true;
    }

    protected static function satisfiesOne(string $version, string $constraint): bool
    {
        if (str_starts_with($constraint, '^')) {
            $min = substr($constraint, 1);

            if (static::major($min) === self::RENUMBERED_MAJOR && static::major($version) === self::RENUMBERED_TO) {
                return true;
            }

            return version_compare($version, $min, '>=')
                && version_compare($version, static::caretUpperBound($min), '<');
        }

        if (preg_match('/^(>=|<=|!=|>|<|==|=)\s*(.+)$/', $constraint, $matches)) {
            $operator = match ($matches[1]) {
                '==' => '=',
                default => $matches[1],
            };

            return version_compare($version, trim($matches[2]), $operator);
        }

        return version_compare($version, $constraint, '=');
    }

    protected static function major(string $version): int
    {
        return (int) $version;
    }

    /**
     * Composer's caret semantics: ^1.2 allows <2.0.0, but ^0.3 only <0.4.0.
     */
    protected static function caretUpperBound(string $min): string
    {
        $numeric = preg_replace('/[^0-9.].*$/', '', $min);
        $parts = array_map('intval', explode('.', trim((string) $numeric, '.')));

        if (($parts[0] ?? 0) > 0 || count($parts) === 1) {
            return (($parts[0] ?? 0) + 1).'.0.0';
        }

        return '0.'.(($parts[1] ?? 0) + 1).'.0';
    }
}
