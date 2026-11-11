<?php

namespace Epesi\Modules\MigrationWizard\Backup;

use RuntimeException;

final class BackupVerifier
{
    public function verify(string $path, string $expectedSha256): array
    {
        if (! is_file($path) || is_link($path) || ! is_readable($path)) {
            throw new RuntimeException('Backup file is missing or inaccessible.');
        }
        if (! preg_match('/^[a-f0-9]{64}$/D', $expectedSha256)) {
            throw new RuntimeException('Invalid expected checksum.');
        }
        $actual = hash_file('sha256', $path);
        if (! hash_equals($expectedSha256, $actual)) {
            throw new RuntimeException('Backup checksum mismatch.');
        }
        return ['sha256' => $actual, 'bytes' => filesize($path), 'checksum_verified' => true,
            'restore_tested' => false];
    }
}
