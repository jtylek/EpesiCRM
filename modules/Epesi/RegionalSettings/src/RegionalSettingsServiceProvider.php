<?php

namespace Epesi\Modules\RegionalSettings;

use App\Models\User;
use App\Support\Locale\Locales;
use App\Support\Setup\SetupSteps;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Epesi\Modules\RegionalSettings\Setup\RegionalSettingsDefaultsStep;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

class RegionalSettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(RegionalSetting::RESOLVED_BINDING, fn (): \ArrayObject => new \ArrayObject);
    }

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

        $this->applyToFilament();
    }

    /**
     * Filament shows and reads every date-time in FilamentTimezone (the app's
     * UTC unless told otherwise) and formats them with its own defaults, so
     * point both at the signed-in user's regional settings. Closures, because
     * the user is only known once a request is being served.
     */
    protected function applyToFilament(): void
    {
        FilamentTimezone::set(fn (): string => RegionalSetting::timezoneName());

        Table::configureUsing(fn (Table $table): Table => $table
            ->defaultDateDisplayFormat(fn (): string => RegionalSetting::dateFormat())
            ->defaultDateTimeDisplayFormat(fn (): string => RegionalSetting::dateTimeFormat())
            ->defaultTimeDisplayFormat(fn (): string => RegionalSetting::timeFormat()));

        Schema::configureUsing(fn (Schema $schema): Schema => $schema
            ->defaultDateDisplayFormat(fn (): string => RegionalSetting::dateFormat())
            ->defaultDateTimeDisplayFormat(fn (): string => RegionalSetting::dateTimeFormat())
            ->defaultTimeDisplayFormat(fn (): string => RegionalSetting::timeFormat()));

        DateTimePicker::configureUsing(fn (DateTimePicker $picker): DateTimePicker => $picker
            ->defaultDateDisplayFormat(fn (): string => RegionalSetting::dateFormat())
            ->defaultDateTimeDisplayFormat(fn (): string => RegionalSetting::dateTimeFormat())
            ->defaultDateTimeWithSecondsDisplayFormat(fn (): string => RegionalSetting::dateTimeFormat(true))
            ->defaultTimeDisplayFormat(fn (): string => RegionalSetting::timeFormat())
            ->defaultTimeWithSecondsDisplayFormat(fn (): string => RegionalSetting::timeFormat(true)));
    }
}
