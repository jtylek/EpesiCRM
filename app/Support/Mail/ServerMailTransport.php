<?php

namespace App\Support\Mail;

use Closure;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * "This server's mail system": what Epesi called "local php.ini settings",
 * the way PHP's own mail() reaches a mail server. That is php.ini's
 * `sendmail_path`; and on Windows, where XAMPP leaves that empty, its `SMTP`
 * and `smtp_port` (localhost:25 by default, which is where a development mail
 * catcher such as Papercut listens).
 *
 * Laravel's own "sendmail" mailer runs one fixed Linux command instead
 * (/usr/sbin/sendmail -bs -i), which doesn't exist on Windows and isn't
 * always where a Linux host keeps it. That command stays the last resort, and
 * MAIL_SENDMAIL_PATH still overrides everything with a command of your own.
 */
class ServerMailTransport
{
    /** The transport name config/mail.php's "sendmail" mailer uses. */
    public const NAME = 'native';

    public const FALLBACK_COMMAND = '/usr/sbin/sendmail -bs -i';

    public static function register(): void
    {
        Mail::extend(self::NAME, fn (array $config): TransportInterface => self::make($config));
    }

    /**
     * @param  array<string, mixed>  $config  the mailer's config: "path" (MAIL_SENDMAIL_PATH), "timeout"
     * @param  Closure(string): (string|false)|null  $ini  reads a php.ini setting (a test's own)
     * @param  bool|null  $windows  whether this is a Windows host (a test's own)
     */
    public static function make(array $config, ?Closure $ini = null, ?bool $windows = null): TransportInterface
    {
        $ini ??= fn (string $key): string|false => ini_get($key);
        $windows ??= DIRECTORY_SEPARATOR === '\\';

        if (filled($config['path'] ?? null)) {
            return new SendmailTransport($config['path']);
        }

        if (filled($command = $ini('sendmail_path'))) {
            try {
                return new SendmailTransport($command);
            } catch (InvalidArgumentException) {
                // A command with neither -bs nor -t, which PHP itself would run
                // but Symfony can't talk to.
            }
        }

        if ($windows && filled($host = $ini('SMTP')) && ($port = (int) $ini('smtp_port')) > 0) {
            $stream = new SocketStream;
            $stream->setHost($host);
            $stream->setPort($port);

            // Implicit TLS only on the SMTPS port, as PHP's own mail() does.
            if ($port !== 465) {
                $stream->disableTls();
            }

            if (filled($config['timeout'] ?? null)) {
                $stream->setTimeout((float) $config['timeout']);
            }

            return new SmtpTransport($stream);
        }

        return new SendmailTransport(self::FALLBACK_COMMAND);
    }
}
