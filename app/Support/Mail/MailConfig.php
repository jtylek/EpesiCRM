<?php

namespace App\Support\Mail;

use App\Services\Setup\InstallOptions;
use App\Support\Setup\EnvFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * How epesi sends its own e-mail, kept in .env's MAIL_* keys. Epesi kept these
 * in its variables table (Base/Mail); here they are the application mailer's
 * own settings, written by the setup wizard and by Administration → Mail
 * Server through this one class.
 */
class MailConfig
{
    /** Setup's "Security" choices. STARTTLS and none both use the "smtp" scheme, which negotiates STARTTLS when the server offers it. */
    public const SECURITY_TLS = 'tls';

    public const SECURITY_SSL = 'ssl';

    public const SECURITY_NONE = 'none';

    /**
     * @return array<string, string|int|null> the .env keys for these choices
     */
    public static function envValues(
        string $method,
        ?string $host,
        ?int $port,
        string $security,
        ?string $username,
        ?string $password,
        ?string $fromAddress,
        ?string $fromName,
    ): array {
        $values = match ($method) {
            InstallOptions::MAIL_SMTP => [
                'MAIL_MAILER' => 'smtp',
                'MAIL_SCHEME' => $security === self::SECURITY_SSL ? 'smtps' : 'smtp',
                'MAIL_HOST' => $host,
                'MAIL_PORT' => $port ?: ($security === self::SECURITY_SSL ? 465 : 587),
                'MAIL_USERNAME' => $username,
                'MAIL_PASSWORD' => $password,
            ],
            InstallOptions::MAIL_LOG => ['MAIL_MAILER' => 'log'],
            default => ['MAIL_MAILER' => 'sendmail'],
        };

        return $values + [
            'MAIL_FROM_ADDRESS' => $fromAddress,
            'MAIL_FROM_NAME' => $fromName,
        ];
    }

    /**
     * The choices the current configuration stands for, as the Mail Server
     * form shows them. "smtp" doesn't say whether the administrator chose
     * STARTTLS or none, so it reads back as STARTTLS, the usual one.
     *
     * @return array{method: string, host: ?string, port: ?int, security: string, username: ?string, password: ?string, from_address: ?string, from_name: ?string}
     */
    public static function current(): array
    {
        $smtp = (array) config('mail.mailers.smtp');

        return [
            'method' => match (config('mail.default')) {
                'smtp' => InstallOptions::MAIL_SMTP,
                'log' => InstallOptions::MAIL_LOG,
                default => InstallOptions::MAIL_SENDMAIL,
            },
            'host' => $smtp['host'] ?? null,
            'port' => isset($smtp['port']) ? (int) $smtp['port'] : null,
            'security' => ($smtp['scheme'] ?? null) === 'smtps' ? self::SECURITY_SSL : self::SECURITY_TLS,
            'username' => $smtp['username'] ?? null,
            'password' => $smtp['password'] ?? null,
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ];
    }

    /**
     * Uses the values for the rest of this request only, without saving them,
     * so a configuration can be tried before it is kept.
     *
     * @param  array<string, string|int|null>  $values  as from envValues()
     */
    public static function apply(array $values): void
    {
        $map = [
            'MAIL_MAILER' => 'mail.default',
            'MAIL_SCHEME' => 'mail.mailers.smtp.scheme',
            'MAIL_HOST' => 'mail.mailers.smtp.host',
            'MAIL_PORT' => 'mail.mailers.smtp.port',
            'MAIL_USERNAME' => 'mail.mailers.smtp.username',
            'MAIL_PASSWORD' => 'mail.mailers.smtp.password',
            'MAIL_FROM_ADDRESS' => 'mail.from.address',
            'MAIL_FROM_NAME' => 'mail.from.name',
        ];

        foreach ($map as $key => $configKey) {
            if (array_key_exists($key, $values)) {
                config([$configKey => $values[$key]]);
            }
        }

        // A mailer built earlier in the request still has the old settings.
        Mail::purge();
    }

    /**
     * Writes the values to .env and drops the config cache so they apply.
     * When .env can't be written (a locked-down host) nothing is changed and
     * the returned text says what to set by hand.
     *
     * @param  array<string, string|int|null>  $values
     * @return string|null a warning, or null when the settings were saved
     */
    public static function write(array $values): ?string
    {
        $env = new EnvFile;

        if (! $env->writable()) {
            return __('The mail settings could not be saved because .env is not writable. Set these in .env yourself: :settings', [
                'settings' => collect($values)->map(fn ($v, $k): string => $k.'='.($k === 'MAIL_PASSWORD' ? '…' : $v))->implode(', '),
            ]);
        }

        $env->set($values);

        try {
            Artisan::call('config:clear');
        } catch (Throwable) {
            // A config cache that can't be cleared only delays the change.
        }

        return null;
    }
}
