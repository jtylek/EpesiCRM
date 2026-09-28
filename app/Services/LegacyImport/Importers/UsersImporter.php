<?php

namespace App\Services\LegacyImport\Importers;

use App\Models\User;
use App\Services\LegacyImport\ImportSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Identity import from `user_login`/`user_password` — these aren't a
 * RecordBrowser tab (no `<tab>_field`/`<tab>_data_1`, no edit history), so
 * this importer doesn't extend Importer; it reads the two legacy auth
 * tables directly and creates one `users` row per login.
 *
 * Password strategy: legacy `user_password.password`'s hash algorithm was
 * never confirmed bcrypt-compatible, so it is deliberately NOT carried
 * forward.
 * Every imported user gets an unusable random password — a super_admin
 * must issue a real password-reset link (Laravel's own forgot-password
 * flow) before an imported account can log in.
 *
 * Role assignment (super_admin/manager/employee) isn't decided here — it
 * depends on the linked Contact's "Access" field and the login's admin
 * level, both only available once ContactsImporter processes that contact —
 * see ContactsImporter::assignRoles().
 */
class UsersImporter
{
    public function run(): ImportSummary
    {
        $summary = new ImportSummary;

        $logins = DB::connection('legacy')->table('user_login')->orderBy('id')->get();
        $seenEmails = User::query()->pluck('email')->flip()->all();

        foreach ($logins as $login) {
            $mail = trim((string) DB::connection('legacy')->table('user_password')
                ->where('user_login_id', $login->id)
                ->value('mail'));

            $user = User::query()->firstOrNew(['legacy_id' => $login->id]);
            $isNew = ! $user->exists;

            if ($isNew) {
                $user->name = $login->login;
                $user->password = Hash::make(Str::random(40));
            }

            // An existing user keeps whatever email this app already has
            // unless legacy actually offers a real (non-blank) one to
            // replace it with — legacy having no mail on file is not a
            // reason to downgrade an address already confirmed to work
            // (found 2026-09-27: a re-import silently turned real working
            // logins like j@epe.si into jasiek@imported.invalid, since
            // legacy's own user_password.mail was blank for them).
            if ($isNew || $mail !== '') {
                $user->email = $this->uniqueEmail($mail, $login->login, $seenEmails);
            }

            $seenEmails[$user->email] = true;
            $user->legacy_id = $login->id;
            $user->save();

            $isNew ? $summary->created++ : $summary->updated++;
        }

        return $summary;
    }

    /**
     * @param  array<string, bool>  $seenEmails  emails already used, this run or before — kept
     *                                           by reference-equivalent return value since PHP arrays are by-value
     */
    private function uniqueEmail(?string $mail, string $login, array &$seenEmails): string
    {
        $mail = trim((string) $mail);
        $candidate = $mail !== '' && ! isset($seenEmails[$mail]) ? $mail : null;

        if ($candidate) {
            return $candidate;
        }

        $slug = Str::slug($login, '.') ?: 'user';
        $candidate = "{$slug}@imported.invalid";
        $suffix = 1;
        while (isset($seenEmails[$candidate])) {
            $candidate = "{$slug}+{$suffix}@imported.invalid";
            $suffix++;
        }

        return $candidate;
    }
}
