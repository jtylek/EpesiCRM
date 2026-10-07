<?php

namespace App\Filament\UserSettings\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Filament\Concerns\UsesEpesiFormLayout;
use App\Support\Calendar\ListRange;
use App\Support\Calendar\WorkingHours;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Personal calendar preferences (User Settings → Calendar): the working hours
 * the Day and Week views show in full. See App\Support\Calendar\WorkingHours.
 */
class CalendarSettings extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;
    use UsesEpesiFormLayout;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected string $view = 'filament.pages.calendar-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string
    {
        return __('Calendar');
    }

    public static function getNavigationLabel(): string
    {
        return __('Calendar');
    }

    public function mount(): void
    {
        $this->form->fill([...WorkingHours::get(), 'list_range' => ListRange::get()]);
    }

    public function form(Schema $schema): Schema
    {
        $hour = fn (int $h): string => sprintf('%02d:00', $h);

        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('Working hours'))
                    ->description(__('The Day and Week views show these hours in full; earlier and later hours collapse into one row with an event count, which you can expand.'))
                    ->columns(2)
                    ->schema([
                        Select::make('start')
                            ->label(__('Start'))
                            ->options(collect(range(0, 23))->mapWithKeys(fn (int $h): array => [$h => $hour($h)])->all())
                            ->native(false)
                            ->required()
                            ->lt('end'),
                        Select::make('end')
                            ->label(__('End'))
                            ->options(collect(range(1, 24))->mapWithKeys(fn (int $h): array => [$h => $hour($h)])->all())
                            ->native(false)
                            ->required()
                            ->gt('start'),
                    ]),
                Section::make(__('List view'))
                    ->schema([
                        Select::make('list_range')
                            ->label(__('Period shown'))
                            ->options(ListRange::options())
                            ->native(false)
                            ->required(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        WorkingHours::set((int) $state['start'], (int) $state['end']);
        ListRange::set($state['list_range']);

        Notification::make()->success()->title(__('Calendar settings saved'))->send();
    }
}
