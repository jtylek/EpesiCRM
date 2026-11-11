<?php

namespace Epesi\Modules\MigrationWizard\Orchestration;

use Epesi\Modules\MigrationWizard\Admin\AdminCredentials;

/**
 * The phase work MigrationRunner delegates. Every method must be idempotent:
 * resume calls it again after an interruption. Each throws on failure; the
 * runner never advances the recorded phase past a method that threw.
 */
interface MigrationPhases
{
    /**
     * Confirm the source may be frozen: the confirmed pre-upgrade backup,
     * an approved legacy schema. Throws when it is not, before anything is changed.
     *
     * @param  array<string, mixed>  $state  the migration state (read-only)
     */
    public function freeze(array $state): void;

    /** @return list<string> every legacy base table to namespace (exact, never a prefix guess) */
    public function legacyTables(): array;

    /**
     * Create the Epesi 3 schema and modules in the freed namespace.
     *
     * @param  array<string, string>  $renameMap
     * @return list<string> installed module ids
     */
    public function install(array $renameMap): array;

    /**
     * Run the importers that have not completed yet, in order.
     *
     * @param  list<string>  $completed  importers that already finished (skipped)
     * @param  callable(string, array<string, int>): void  $record  called right after each importer finished
     * @param  array<string, string>  $renameMap  original => current legacy table names; the modules whose
     *                                            own tables do not match their approved shape are not imported
     */
    public function import(array $completed, callable $record, array $renameMap): void;

    /**
     * Set the new password on the chosen legacy administrator's imported account.
     *
     * @param  array<string, string>  $renameMap
     * @return array{user_id: int, legacy_id: int, login: string} no password
     */
    public function handOffAdministrator(AdminCredentials $credentials, array $renameMap): array;

    /**
     * Build the verification report (MigrationVerifier::verify() output).
     *
     * @param  array<string, string>  $renameMap
     * @param  array<string, mixed>  $state  the migration state (completed importers, operator choices); read-only
     * @return array<string, mixed>
     */
    public function verify(array $renameMap, array $state): array;

    /**
     * Final allowlisted cleanup (LegacyCleanup::execute()).
     *
     * @param  array<string, mixed>  $verification
     * @param  array<string, string>  $renameMap
     * @param  array<string, mixed>  $state  the migration state (read-only)
     * @return array<string, mixed>
     */
    public function cleanup(array $verification, array $renameMap, array $state): array;
}
