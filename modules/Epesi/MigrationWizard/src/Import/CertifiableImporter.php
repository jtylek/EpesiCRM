<?php

namespace Epesi\Modules\MigrationWizard\Import;

/**
 * A LegacyImporter that also declares what it covers, so ModuleCoverage can
 * classify its tables. An importer that does not implement this (or whose
 * declaration is incomplete, see ImporterDeclarations) is unsupported: its
 * tables are reported as known-but-not-migrated at best and are preserved.
 */
interface CertifiableImporter extends LegacyImporter
{
    /** The legacy `modules` registry name that owns the source tables, e.g. CRM_Contacts. */
    public function legacyModule(): string;

    /**
     * Source columns the importer reads and maps, per claimed source table.
     * Every claimed table must list at least one column.
     *
     * @return array<string, list<string>>
     */
    public function requiredColumns(): array;

    /**
     * Source columns deliberately left behind, per table. Anything neither
     * required nor ignored makes the table unsupported (data would be lost silently).
     *
     * @return array<string, list<string>>
     */
    public function ignoredColumns(): array;

    /**
     * Exact legacy releases the importer was verified against. Empty means not
     * certified for any release. Certification never carries over to another
     * version, schema fingerprint or module version.
     *
     * @return list<array{version: string, fingerprint: string, module_version: int|string}>
     */
    public function certifiedFor(): array;
}
