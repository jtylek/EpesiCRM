<?php

namespace Epesi\Modules\MigrationWizard\Detection;

/** Deterministic fingerprint of table names and their columns. */
final class SchemaFingerprint
{
    public function hash(array $tables): string
    {
        $normalized = [];
        foreach ($tables as $table => $columns) {
            $columns = array_values(array_unique(array_map('strval', $columns)));
            sort($columns, SORT_STRING);
            $normalized[(string) $table] = $columns;
        }
        ksort($normalized, SORT_STRING);

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    /** Fingerprint of one table's column list (order independent). */
    public static function tableHash(array $columns): string
    {
        $columns = array_values(array_unique(array_map('strval', $columns)));
        sort($columns, SORT_STRING);

        return hash('sha256', json_encode($columns, JSON_THROW_ON_ERROR));
    }

    public function hasLegacySignature(array $tables): bool
    {
        foreach (['variables', 'user_login', 'user_password', 'modules'] as $required) {
            if (! array_key_exists($required, $tables)) {
                return false;
            }
        }

        return true;
    }
}
