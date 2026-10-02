<?php

namespace App\Filament\UserSettings\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Support\QuickAccess;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Personal Quick Access preferences (User Settings → Quick Access): which
 * modules get an icon in the main panel's top bar (App\Support\QuickAccess).
 * The choices are the main panel's sidebar items, so a module installed later
 * shows up here without any change.
 */
class QuickAccessSettings extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected string $view = 'filament.pages.calendar-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string
    {
        return __('Quick Access');
    }

    public static function getNavigationLabel(): string
    {
        return __('Quick Access');
    }

    public function mount(): void
    {
        $this->form->fill(['items' => QuickAccess::selected()]);
    }

    public function form(Schema $schema): Schema
    {
        $options = collect(QuickAccess::available())
            ->map(fn ($item): string => (string) $item->getLabel())
            ->all();

        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('Quick Access'))
                    ->schema([
                        CheckboxList::make('items')
                            ->label(__('Modules'))
                            ->options($options)
                            ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->live()
                            ->afterStateUpdated(function (CheckboxList $component, $state, $old): void {
                                if (count((array) $state) <= QuickAccess::MAX) {
                                    return;
                                }

                                // Put back what was there: the box just ticked is undone.
                                $component->state((array) $old);

                                Notification::make()
                                    ->warning()
                                    ->title(__('Quick Access is limited to :max icons', ['max' => QuickAccess::MAX]))
                                    ->body(__('Untick a module before adding another.'))
                                    ->send();
                            }),
                    ]),
            ]);
    }

    public function save(): void
    {
        $chosen = (array) ($this->form->getState()['items'] ?? []);

        if (count($chosen) > QuickAccess::MAX) {
            Notification::make()
                ->warning()
                ->title(__('Quick Access is limited to :max icons', ['max' => QuickAccess::MAX]))
                ->send();

            return;
        }

        QuickAccess::save($chosen);

        Notification::make()->success()->title(__('Quick Access saved'))->send();
    }
}
