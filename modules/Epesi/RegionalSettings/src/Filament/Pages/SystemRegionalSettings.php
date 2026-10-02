<?php

namespace Epesi\Modules\RegionalSettings\Filament\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use BackedEnum;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Administration → Regional Settings: the defaults for the application — the
 * language of the login page and of everyone who hasn't chosen their own, and
 * the timezone, formats and location a new user starts with. The same row the
 * setup wizard writes (RegionalSetting::defaults()); each user's own page
 * (RegionalSettings) overrides it.
 */
class SystemRegionalSettings extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;

    protected const FIELDS = ['language', 'timezone', 'date_format', 'time_format', 'country', 'state'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Regional Settings';

    protected static ?string $title = 'Regional Settings';

    protected static ?string $slug = 'regional-settings';

    protected static ?int $navigationSort = 85;

    protected string $view = 'epesi-regional-settings::regional-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill(RegionalSetting::defaults()->only(self::FIELDS));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components(RegionalSettings::formComponents(__('Location'), systemDefaults: true));
    }

    public function save(): void
    {
        RegionalSetting::query()->updateOrCreate(
            ['user_id' => null],
            collect($this->form->getState())->only(self::FIELDS)->all(),
        );

        Notification::make()->success()->title(__('Regional settings saved'))->send();

        $this->redirect(static::getUrl());
    }
}
