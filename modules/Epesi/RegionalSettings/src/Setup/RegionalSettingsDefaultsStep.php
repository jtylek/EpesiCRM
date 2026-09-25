<?php

namespace Epesi\Modules\RegionalSettings\Setup;

use App\Models\User;
use App\Support\Setup\SetupStep;
use Epesi\Modules\RegionalSettings\Filament\Pages\RegionalSettings;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;

/**
 * Base_RegionalSettingsInstall::post_install(): the date and time formats,
 * timezone and location everyone starts from. The administrator gets them as
 * their own settings too, as Epesi's admin defaults applied to the admin.
 */
class RegionalSettingsDefaultsStep implements SetupStep
{
    protected const FIELDS = ['language', 'timezone', 'date_format', 'time_format', 'country', 'state'];

    public function label(): string
    {
        return __('Regional settings');
    }

    public function description(): ?string
    {
        return __('Defaults for everyone; each user can change their own under Settings.');
    }

    public function schema(): array
    {
        return RegionalSettings::formComponents(__('Location'), systemDefaults: true);
    }

    public function defaults(): array
    {
        return [
            ...RegionalSetting::defaults()->only(self::FIELDS),
            'language' => RegionalSetting::defaults()->language ?? app()->getLocale(),
        ];
    }

    public function handle(array $data, User $admin): void
    {
        $values = collect($data)->only(self::FIELDS)->all();

        RegionalSetting::query()->updateOrCreate(['user_id' => null], $values);
        RegionalSetting::query()->updateOrCreate(['user_id' => $admin->id], $values);
    }
}
