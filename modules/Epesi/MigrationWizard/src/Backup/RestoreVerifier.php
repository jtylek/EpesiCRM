<?php

namespace Epesi\Modules\MigrationWizard\Backup;

use PDO;
use RuntimeException;

/**
 * Restore rehearsal against an EXISTING, isolated, empty database.
 * Never creates, drops, truncates or touches the source database.
 */
final class RestoreVerifier
{
    public function __construct(private readonly BackupVerifier $verifier) {}

    public function rehearse(string $dumpPath, string $expectedSha256, array $target, string $client = 'mysql'): array
    {
        $this->verifier->verify($dumpPath, $expectedSha256);
        $preflight = (new SqlDumpSafety)->inspect($dumpPath);
        if (! $preflight['target_neutral_preflight']) {
            throw new RuntimeException('Dump contains database-selection statements; restore refused.');
        }
        foreach (['host', 'port', 'database', 'user', 'password'] as $key) {
            if (! isset($target[$key])) {
                throw new RuntimeException('Missing restore target setting.');
            }
        }
        if (! preg_match('/^[a-zA-Z0-9_]+$/D', $target['database'])) {
            throw new RuntimeException('Invalid isolated database identifier.');
        }
        if (isset($target['source_database']) && $target['database'] === $target['source_database']) {
            throw new RuntimeException('Restore target must differ from source database.');
        }
        if (! isset($target['source_database']) || $target['source_database'] === '') {
            throw new RuntimeException('Explicit source database is required for restore safety.');
        }
        $pdo = new PDO('mysql:host='.$target['host'].';port='.$target['port'].';dbname='.$target['database'].';charset=utf8mb4',
            $target['user'], $target['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $existing = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn();
        if ($existing !== 0) {
            throw new RuntimeException('Restore rehearsal requires an empty database.');
        }
        // A line-oriented preflight is insufficient to authorize SQL execution.
        // Keep restoration disabled until a sandboxed restore runner and tests exist.
        throw new RuntimeException('Automatic restore rehearsal remains disabled; no SQL executed.');
    }
}
