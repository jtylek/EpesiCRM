<?php

namespace App\Services\Setup;

/**
 * Everything the setup wizard (or `epesi:install --admin-email=...`) collects —
 * FirstRun's setup type, administrator and mail settings pages, and whether
 * to download the Roundcube webmail. `development` (epesi:install --dev)
 * keeps .env's APP_ENV/APP_DEBUG instead of switching to production.
 */
final class InstallOptions
{
    public const MAIL_SENDMAIL = 'sendmail';

    public const MAIL_SMTP = 'smtp';

    public const MAIL_LOG = 'log';

    public function __construct(
        public readonly string $profile,
        public readonly string $adminName,
        public readonly string $adminEmail,
        public readonly string $adminPassword,
        public readonly string $mailMethod = self::MAIL_SENDMAIL,
        public readonly ?string $smtpHost = null,
        public readonly ?int $smtpPort = null,
        public readonly string $smtpSecurity = 'tls',
        public readonly ?string $smtpUsername = null,
        public readonly ?string $smtpPassword = null,
        public readonly bool $demoData = false,
        public readonly bool $roundcube = false,
        public readonly bool $development = false,
    ) {}
}
