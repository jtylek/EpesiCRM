<?php

namespace App\Filament\Dashboard;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Locked;

/**
 * What the Dashboard mounts an Applet with, and the gear that opens its
 * settings. The settings form itself belongs to the Dashboard page
 * (Dashboard::configureAppletAction()), which the gear reaches with a
 * `configure-applet` event: a table applet puts configureAppletAction() in
 * its header actions, a section applet includes the
 * filament.dashboard.configure-applet view in its header.
 */
trait IsApplet
{
    /** The dashboard_applets row; null wherever the widget isn't on the Dashboard. */
    #[Locked]
    public ?int $appletId = null;

    /** @var array<string, mixed> */
    #[Locked]
    public array $appletSettings = [];

    public static function getAppletDescription(): ?string
    {
        return null;
    }

    public static function getAppletSettingsSchema(): array
    {
        return [];
    }

    public static function getAppletSettingsDefaults(): array
    {
        return [];
    }

    public function appletSetting(string $name): mixed
    {
        return array_key_exists($name, $this->appletSettings)
            ? $this->appletSettings[$name]
            : static::getAppletSettingsDefaults()[$name] ?? null;
    }

    protected function configureAppletAction(): Action
    {
        return Action::make('configureApplet')
            ->label('Configure')
            ->tooltip(__('Configure'))
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->iconButton()
            ->color('gray')
            ->dispatch('configure-applet', ['applet' => $this->appletId])
            ->visible($this->appletId !== null);
    }
}
