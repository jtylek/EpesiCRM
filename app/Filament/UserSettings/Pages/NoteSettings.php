<?php

namespace App\Filament\UserSettings\Pages;

use App\Enums\NoteFormat;
use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Filament\Concerns\UsesEpesiFormLayout;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Personal note preferences (User Settings → Notes): the format a new note
 * starts in. It can be switched in the editor, and each note keeps the format
 * it was written in (App\Enums\NoteFormat).
 */
class NoteSettings extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;
    use UsesEpesiFormLayout;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected string $view = 'filament.pages.calendar-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public function getTitle(): string
    {
        return __('Notes');
    }

    public static function getNavigationLabel(): string
    {
        return __('Notes');
    }

    public function mount(): void
    {
        $this->form->fill(['format' => NoteFormat::default()->value]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('Editor'))
                    ->description(__('The format a new note starts in. You can switch it while writing, and every note keeps the format it was written in.'))
                    ->schema([
                        Select::make('format')
                            ->label(__('Default format'))
                            ->options(NoteFormat::class)
                            ->native(false)
                            ->required(),
                    ]),
            ]);
    }

    public function save(): void
    {
        NoteFormat::setDefault(NoteFormat::fromState($this->form->getState()['format']) ?? NoteFormat::Html);

        Notification::make()->success()->title(__('Note settings saved'))->send();
    }
}
