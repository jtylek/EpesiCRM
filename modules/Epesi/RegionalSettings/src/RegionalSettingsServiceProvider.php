<?php

namespace Epesi\Modules\RegionalSettings;

use App\Models\User;
use App\Support\Locale\Locales;
use App\Support\Setup\SetupSteps;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Epesi\Modules\RegionalSettings\Setup\RegionalSettingsDefaultsStep;
use Illuminate\Support\ServiceProvider;

class RegionalSettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'epesi-regional-settings');

        SetupSteps::register('regional-settings-defaults', RegionalSettingsDefaultsStep::class, 20);

        Locales::resolveUserLocaleUsing(fn (User $user): ?string => RegionalSetting::query()
            ->where('user_id', $user->getKey())
            ->value('language'));
        Locales::resolveDefaultLocaleUsing(fn (): ?string => RegionalSetting::query()
            ->whereNull('user_id')
            ->value('language'));
    }
}
