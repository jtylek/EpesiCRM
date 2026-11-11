<?php

namespace Epesi\Modules\MigrationWizard\Admin;

use LogicException;

/**
 * The chosen legacy administrator and the new password, held in memory only.
 * The password is never written to state, logs or dumps: debugging output is
 * redacted and the object refuses to be serialised.
 */
final class AdminCredentials
{
    public function __construct(
        public readonly int $legacyId,
        private readonly string $password,
        public readonly ?string $email = null,
    ) {}

    public function password(): string
    {
        return $this->password;
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['legacyId' => $this->legacyId, 'email' => $this->email, 'password' => '[redacted]'];
    }

    public function __serialize(): array
    {
        throw new LogicException('Administrator credentials must not be serialised.');
    }

    public function __sleep(): array
    {
        throw new LogicException('Administrator credentials must not be serialised.');
    }
}
