<?php

namespace Epesi\Modules\MigrationWizard\Import;

use RuntimeException;

final class LegacyImporterRegistry
{
    /** @var array<string, LegacyImporter> */
    private array $importers = [];

    public function register(LegacyImporter $importer): void
    {
        $id = $importer->id();
        if (! preg_match('/^[a-z][a-z0-9_-]*$/D', $id) || isset($this->importers[$id])) {
            throw new RuntimeException('Invalid or duplicate legacy importer identifier.');
        }

        self::assertPrimaryTable($importer);

        $this->importers[$id] = $importer;
    }

    /**
     * The runner reads the first declared source table; an importer without one
     * (or whose sourceTable() disagrees) is a declaration error, not a crash later.
     */
    public static function assertPrimaryTable(LegacyImporter $importer): string
    {
        $tables = $importer->sourceTables();
        $first = $tables[0] ?? null;
        if (! is_string($first) || $first === '') {
            throw new RuntimeException("Legacy importer '{$importer->id()}' declares no source tables; the first source table is the one rows are read from.");
        }
        if ($importer->sourceTable() !== $first) {
            throw new RuntimeException("Legacy importer '{$importer->id()}' reports a primary source table that is not the first of its declared source tables.");
        }

        return $first;
    }

    /** @return list<LegacyImporter> */
    public function all(): array
    {
        return array_values($this->importers);
    }
}
