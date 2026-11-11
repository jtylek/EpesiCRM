<?php

namespace Epesi\Modules\MigrationWizard\Import;

interface LegacyImporter
{
    public function id(): string;

    /** @return list<string> */
    public function dependencies(): array;

    /** @return non-empty-list<string> The first table is the primary table the importer reads rows from. */
    public function sourceTables(): array;

    /** @return list<string> */
    public function sourceFileAreas(): array;

    public function sourceTable(): string;

    public function sourceIdColumn(): string;

    /** @return array<string, string> */
    public function fieldMapping(): array;

    /** @return list<class-string> */
    public function destinationModels(): array;

    /** @return list<string> */
    public function verificationRules(): array;

    /** @return list<string> */
    public function deferredMappings(): array;
}
