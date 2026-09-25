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
 */
class VersionConstraint
{
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
