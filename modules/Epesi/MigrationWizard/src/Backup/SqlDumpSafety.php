<?php

namespace Epesi\Modules\MigrationWizard\Backup;

use RuntimeException;

/**
 * Conservative preflight for logical SQL dumps. This is not a SQL parser and
 * cannot authorize executing a dump against a production server.
 */
final class SqlDumpSafety
{
    public function inspect(string $path): array
    {
        if (! is_file($path) || is_link($path) || ! is_readable($path)) {
            throw new RuntimeException('SQL dump is inaccessible.');
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('SQL dump cannot be opened.');
        }
        $lines = 0;
        $hazards = [];
        try {
            while (($line = fgets($handle)) !== false) {
                $lines++;
                // Scan plain statements and executable MySQL versioned comments.
                if (preg_match('/^\s*(?:USE\s+|CREATE\s+DATABASE\b|DROP\s+DATABASE\b|\\\\connect\b|--\s*Current Database:)/i', $line) ||
                    preg_match('/\/\*!\d*\s*(?:USE\s+|CREATE\s+DATABASE\b|DROP\s+DATABASE\b)/i', $line)) {
                    $hazards[] = $lines;
                }
            }
        } finally {
            fclose($handle);
        }
        return [
            'lines_scanned' => $lines,
            'database_selection_hazards' => $hazards,
            'target_neutral_preflight' => $hazards === [],
            'restore_authorized' => false,
        ];
    }
}
