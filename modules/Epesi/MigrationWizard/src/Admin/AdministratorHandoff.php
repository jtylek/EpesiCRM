<?php

namespace Epesi\Modules\MigrationWizard\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * Hands the upgraded installation to one active legacy administrator.
 *
 * Legacy password hashes are never imported (the Users importer gives every
 * imported login an unusable random password). The operator picks one active
 * legacy administrator (user_login.admin >= 1, active = 1); this sets a new
 * password on the already imported Epesi 3 user of that login (matched by
 * users.legacy_id), makes sure the account is active, e-mail verified and a
 * super_admin. Other users keep the normal password-setup/reset flow.
 *
 * The password exists only in the AdminCredentials object and the Eloquent
 * `hashed` cast; nothing here logs, returns or stores it, and the migration
 * state records only the legacy id.
 */
final class AdministratorHandoff
{
    public const MIN_PASSWORD_LENGTH = 8;

    /**
     * Active legacy administrators the operator can choose from.
     *
     * @param  string  $table  the legacy user table under its namespaced name
     * @return list<array{id: int, login: string, level: int}>
     */
    public function candidates(PDO $legacy, string $table = 'legacy_user_login'): array
    {
        $rows = $legacy->query('SELECT id, login, admin FROM '.$this->quote($table).' WHERE active = 1 AND admin >= 1 ORDER BY admin DESC, id ASC');
        $result = [];
        foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[] = ['id' => (int) $row['id'], 'login' => (string) $row['login'], 'level' => (int) $row['admin']];
        }

        return $result;
    }

    /**
     * @return array{user_id: int, legacy_id: int, login: string} never contains the password
     */
    public function apply(AdminCredentials $credentials, PDO $legacy, string $table = 'legacy_user_login'): array
    {
        if (mb_strlen($credentials->password()) < self::MIN_PASSWORD_LENGTH) {
            throw new RuntimeException('The administrator password must have at least '.self::MIN_PASSWORD_LENGTH.' characters.');
        }
        $chosen = null;
        foreach ($this->candidates($legacy, $table) as $candidate) {
            if ($candidate['id'] === $credentials->legacyId) {
                $chosen = $candidate;
            }
        }
        if ($chosen === null) {
            throw new RuntimeException('The chosen account is not an active legacy administrator.');
        }
        $user = User::query()->where('legacy_id', $credentials->legacyId)->first();
        if ($user === null) {
            throw new RuntimeException('The chosen administrator has not been imported yet; run the users import first.');
        }

        $email = $credentials->email;
        if ($email !== null) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('The administrator e-mail address is not valid.');
            }
            if (User::query()->where('email', $email)->whereKeyNot($user->getKey())->exists()) {
                throw new RuntimeException('Another account already uses that e-mail address.');
            }
        }

        DB::transaction(function () use ($user, $credentials, $email): void {
            if ($email !== null) {
                $user->email = $email;
            }
            $user->password = $credentials->password();
            $user->active = true;
            $user->email_verified_at = now();
            $user->save();
            if (! $user->hasRole('super_admin')) {
                $user->assignRole('super_admin');
            }
        });

        return ['user_id' => (int) $user->id, 'legacy_id' => $credentials->legacyId, 'login' => $chosen['login']];
    }

    private function quote(string $identifier): string
    {
        if (! preg_match('/^[A-Za-z0-9_$]+$/D', $identifier)) {
            throw new RuntimeException('Unsafe table identifier.');
        }

        return '`'.$identifier.'`';
    }
}
