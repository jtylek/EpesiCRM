<?php

/*
 * Creates or removes a logged-in session for screenshots, without a password.
 * Run through tinker with its input closed, or tinker waits for more:
 *
 *   EPESI_SESSION_FILE=/path/s.json php artisan tinker --execute="require '.../session.php';" < /dev/null
 *
 * EPESI_SESSION_MODE=create (default): signs in EPESI_SESSION_USER (an e-mail;
 * default the first super_admin) and writes {name, value, id, user} to
 * EPESI_SESSION_FILE: the cookie a browser needs.
 *
 * EPESI_SESSION_MODE=remove: deletes that session and the login_audits row its
 * first request opened (App\Http\Middleware\TrackLoginAudit), so the
 * screenshots leave no trace in Administration > Login audit.
 */

use App\Models\LoginAudit;
use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\DB;

$file = getenv('EPESI_SESSION_FILE') ?: throw new RuntimeException('Set EPESI_SESSION_FILE.');

if (getenv('EPESI_SESSION_MODE') === 'remove') {
    $id = json_decode((string) @file_get_contents($file), true)['id'] ?? null;
    $row = $id ? DB::table('sessions')->where('id', $id)->first() : null;

    if ($row) {
        $payload = unserialize(base64_decode($row->payload), ['allowed_classes' => false]);
        $audit = is_array($payload) ? ($payload['login_audit_id'] ?? null) : null;

        if ($audit) {
            LoginAudit::whereKey($audit)->delete();
        }

        DB::table('sessions')->where('id', $id)->delete();
    }

    @unlink($file);
    echo 'session removed', PHP_EOL;

    return;
}

$email = getenv('EPESI_SESSION_USER');
$user = $email
    ? User::query()->where('email', $email)->firstOrFail()
    : User::role('super_admin')->orderBy('id')->firstOrFail();

// What SessionGuard::login() and Filament's AuthenticateSession leave behind.
$session = app('session')->driver();
$session->start();
$session->put('login_web_'.sha1(SessionGuard::class), $user->getKey());
$session->put('password_hash_web', $user->getAuthPassword());
$session->save();

$name = config('session.cookie');
$value = app('encrypter')->encrypt(CookieValuePrefix::create($name, app('encrypter')->getKey()).$session->getId(), false);

file_put_contents($file, json_encode(['name' => $name, 'value' => $value, 'id' => $session->getId(), 'user' => $user->email]));
echo 'session created for ', $user->email, PHP_EOL;
