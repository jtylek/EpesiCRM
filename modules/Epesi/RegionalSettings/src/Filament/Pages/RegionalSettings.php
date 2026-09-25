<?php

namespace Epesi\Modules\RegionalSettings\Filament\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Support\Countries;
use App\Support\Locale\Locales;
use App\Support\Zones;
use BackedEnum;
use Carbon\Carbon;
use DateTimeZone;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * A personal preferences page, not a resource: every user edits only their
 * own row via RegionalSetting::current(), so there is no list, no policy, and
 * nothing to authorize beyond being logged in. The port of legacy's
 * Base_RegionalSettingsCommon::user_settings() panel.
 */
class RegionalSettings extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected string $view = 'epesi-regional-settings::regional-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string
    {
        return __('Regional Settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('Regional Settings');
    }

    public function mount(): void
    {
        $this->form->fill(RegionalSetting::current()->only([
            'language',
            'timezone', 'date_format', 'time_format', 'country', 'state',
        ]));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components(static::formComponents());
    }

    /**
     * Shared with the setup wizard, which asks for the system-wide defaults
     * with the same fields (RegionalSettingsDefaultsStep).
     *
     * @return array<int, Section>
     */
    public static function formComponents(?string $locationHeading = null, bool $systemDefaults = false): array
    {
        $now = Carbon::now();
        $locationHeading ??= __('Your Location');

        return [
            Section::make(__('Language'))
                ->schema([
                    Select::make('language')
                        ->options(Locales::available())
                        ->native(false)
                        // A user who hasn't chosen follows the system default,
                        // including when an administrator later changes it.
                        ->placeholder($systemDefaults ? null : __('Same as the system (:language)', [
                            'language' => Locales::available()[Locales::systemDefault()] ?? Locales::systemDefault(),
                        ]))
                        ->required($systemDefaults),
                ]),
            Section::make(__('Date & Time'))
                ->columns(3)
                ->schema([
                    Select::make('timezone')
                        ->options(array_combine(
                            DateTimeZone::listIdentifiers(),
                            DateTimeZone::listIdentifiers(),
                        ))
                        ->searchable()
                        ->native(false)
                        ->required(),
                    Select::make('date_format')
                        ->label('Date format')
                        ->options(fn (): array => collect(RegionalSetting::DATE_FORMATS)
                            ->mapWithKeys(fn ($label, $format): array => [$format => $now->format($format)])
                            ->all())
                        ->native(false)
                        ->required(),
                    Select::make('time_format')
                        ->label('Time format')
                        ->options(fn (): array => collect(RegionalSetting::TIME_FORMATS)
                            ->mapWithKeys(fn ($label, $format): array => [$format => "{$now->format($format)} ({$label})"])
                            ->all())
                        ->native(false)
                        ->required(),
                ]),
            Section::make($locationHeading)
                ->columns(2)
                ->schema([
                    Select::make('country')
                        ->options(Countries::options())
                        ->searchable()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('state', null)),
                    // Mirrors AddressFields::zone(): a Select when the
                    // chosen country has a known state/province list,
                    // a free-text input otherwise.
                    Group::make([
                        Select::make('state')
                            ->label('State / Province')
                            ->options(fn (Get $get): array => Zones::forCountry($get('country')))
                            ->searchable()
                            ->native(false)
                            ->visible(fn (Get $get): bool => Zones::hasZones($get('country'))),
                        TextInput::make('state')
                            ->label('State / Province')
                            ->maxLength(64)
                            ->visible(fn (Get $get): bool => ! Zones::hasZones($get('country'))),
                    ])->columns(1),
                ]),
        ];
    }

    public function save(): void
    {
        $settings = RegionalSetting::current();
        $settings->update($this->form->getState());

        Notification::make()->success()->title(__('Regional settings saved'))->send();

        // The page was drawn in the old language; reload it in the new one.
        if ($settings->wasChanged('language')) {
            $this->redirect(static::getUrl());
        }
    }
}
