<?php

namespace Epesi\Modules\Mail;

use Epesi\Modules\Mail\Filament\Resources\MailAccounts\MailAccountResource;
use Epesi\Modules\Mail\Filament\Resources\Mails\MailResource;
use Epesi\Modules\Mail\Filament\Widgets\UnreadMailWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

class MailPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-mail';
    }

    public function register(Panel $panel): void
    {
        // A user's own IMAP/SMTP accounts are a personal preference, like
        // Regional Settings, so they live in the Settings panel; the archive
        // and its applet stay in the CRM.
        if ($panel->getId() === 'user-settings') {
            $panel->resources([MailAccountResource::class]);

            return;
        }

        $panel->resources([MailResource::class]);

        // The Mail applet on the dashboard.
        $panel->widgets([UnreadMailWidget::class]);
    }

    public function boot(Panel $panel): void {}
}
