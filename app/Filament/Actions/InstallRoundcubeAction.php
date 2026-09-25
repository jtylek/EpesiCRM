<?php

namespace App\Filament\Actions;

use App\Services\Setup\RoundcubeSetup;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Throwable;

/**
 * "Download and install Roundcube" — the setup wizard's Webmail question,
 * asked again for whoever said no then (or whose download failed): on the
 * Mailbox page and under Administration → Modules. Administrators only.
 */
class InstallRoundcubeAction
{
    public static function make(string $name = 'installRoundcube'): Action
    {
        return Action::make($name)
            ->label('Download and install Roundcube')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->visible(fn (): bool => (Auth::user()?->hasRole('super_admin') ?? false) && app(RoundcubeSetup::class)->available())
            ->modalHeading(__('Install the Roundcube webmail?'))
            ->modalDescription(RoundcubeSetup::notice())
            ->modalSubmitActionLabel(__('Yes, download and install'))
            ->modalIcon(Heroicon::OutlinedInbox)
            ->action(function (Component $livewire): void {
                // Checked again here: visible() only hides the button.
                if (! Auth::user()?->hasRole('super_admin')) {
                    return;
                }

                try {
                    app(RoundcubeSetup::class)->install();
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()
                        ->title(__('Roundcube could not be installed'))
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('Roundcube is installed'))
                    ->body(__('Open Mailbox in the menu, and add your mail account under Settings → Mail accounts if you haven\'t yet.'))
                    ->success()
                    ->send();

                // The menu (and the Mailbox page itself) only picks up a newly
                // turned-on module on the next page load.
                $livewire->js('window.location.reload()');
            });
    }
}
