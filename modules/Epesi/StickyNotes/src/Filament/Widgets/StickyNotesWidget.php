<?php

namespace Epesi\Modules\StickyNotes\Filament\Widgets;

use App\Filament\Dashboard\Applet;
use App\Filament\Dashboard\IsApplet;
use Epesi\Modules\StickyNotes\Models\StickyNote;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Renderless;

/**
 * The Notes applet: a user's own small notes, like Post-its. Each has a title
 * and a Markdown body and one of four very light background colors. A note
 * can be deactivated and brought back; only an inactive one can be deleted,
 * and then for good.
 */
class StickyNotesWidget extends Widget implements Applet, HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use IsApplet;

    /** The Dashboard gives an applet that sets this the whole row instead of one column. */
    public const APPLET_FULL_WIDTH = true;

    /** The Dashboard puts it on the Notes tab alone: Add applet doesn't offer it, and it has no gear to move or remove it. */
    public const APPLET_ONLY_ON_NOTES_TAB = true;

    protected string $view = 'epesi-sticky-notes::widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 6;

    /** How many columns the board has: the same as the Dashboard, so a note is as wide as another applet. */
    public const COLUMNS = 3;

    /** "active" or "inactive": which notes are shown. */
    public string $status = 'active';

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole(['super_admin', 'manager', 'employee']) ?? false;
    }

    public static function getAppletCaption(): string
    {
        return __('Notes');
    }

    public static function getAppletDescription(): ?string
    {
        return __('Small colored notes, like Post-its');
    }

    public function setStatus(string $status): void
    {
        $this->status = $status === 'inactive' ? 'inactive' : 'active';
    }

    /**
     * @return Collection<int, StickyNote>
     */
    public function notes(): Collection
    {
        return $this->mine()
            ->where('active', $this->status !== 'inactive')
            ->orderBy('col')
            ->orderBy('position')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The shown notes by board column, top to bottom.
     *
     * @return array<int, Collection<int, StickyNote>>
     */
    public function columns(): array
    {
        $notes = $this->notes();

        return array_map(
            fn (int $col): Collection => $notes->filter(fn (StickyNote $note): bool => min($note->col, self::COLUMNS - 1) === $col)->values(),
            range(0, self::COLUMNS - 1),
        );
    }

    /**
     * wire:sort: $item (a note's id) was dropped at $position in board column
     * $column. The shown notes are numbered afresh in that order. Renderless:
     * SortableJS has already moved the element.
     */
    #[Renderless]
    public function reorder(int|string $item, int $position, string|int $column = 0): void
    {
        $columns = array_map(
            fn (Collection $notes): array => $notes->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            $this->columns(),
        );

        if (! in_array((int) $item, array_merge(...$columns), true)) {
            return;
        }

        $columns = array_map(fn (array $ids): array => array_values(array_diff($ids, [(int) $item])), $columns);
        array_splice($columns[max(0, min((int) $column, self::COLUMNS - 1))], max(0, $position), 0, [(int) $item]);

        foreach ($columns as $col => $ids) {
            foreach ($ids as $index => $id) {
                $this->mine()->whereKey($id)->update(['col' => $col, 'position' => $index]);
            }
        }
    }

    public function addNoteAction(): Action
    {
        return Action::make('addNote')
            ->label('Add note')
            ->tooltip(__('Add note'))
            ->icon(Heroicon::OutlinedPlus)
            ->iconButton()
            ->color('gray')
            ->modalHeading(__('Add note'))
            ->modalSubmitActionLabel(__('Save'))
            ->fillForm(['color' => 'yellow'])
            ->schema($this->noteForm())
            ->action(function (array $data): void {
                // First in line: one before the lowest position.
                StickyNote::query()->create([...$data, 'user_id' => Auth::id(), 'active' => true, 'col' => 0, 'position' => (int) $this->mine()->min('position') - 1]);

                $this->status = 'active';
            });
    }

    public function editNoteAction(): Action
    {
        return Action::make('editNote')
            ->label('Edit')
            ->tooltip(__('Edit'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->iconButton()
            ->size('sm')
            ->color('gray')
            ->modalHeading(__('Edit note'))
            ->modalSubmitActionLabel(__('Save'))
            ->fillForm(fn (array $arguments): array => $this->mine()->findOrFail($arguments['id'])->only(['title', 'body', 'color']))
            ->schema($this->noteForm())
            ->action(fn (array $data, array $arguments) => $this->mine()->findOrFail($arguments['id'])->update($data));
    }

    public function setActiveAction(): Action
    {
        return Action::make('setActive')
            ->label(fn (array $arguments): string => $arguments['active'] ? 'Activate' : 'Deactivate')
            ->tooltip(fn (array $arguments): string => $arguments['active'] ? __('Activate') : __('Deactivate'))
            ->icon(fn (array $arguments): Heroicon => $arguments['active'] ? Heroicon::OutlinedArrowUturnLeft : Heroicon::OutlinedArchiveBox)
            ->iconButton()
            ->size('sm')
            ->color('gray')
            ->action(fn (array $arguments) => $this->mine()->findOrFail($arguments['id'])->update(['active' => (bool) $arguments['active']]));
    }

    /** Only an inactive note: an active one has to be deactivated first. */
    public function deleteNoteAction(): Action
    {
        return Action::make('deleteNote')
            ->label('Delete permanently')
            ->tooltip(__('Delete permanently'))
            ->icon(Heroicon::OutlinedTrash)
            ->iconButton()
            ->size('sm')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('Delete this note permanently?'))
            ->action(fn (array $arguments) => $this->mine()->where('active', false)->findOrFail($arguments['id'])->delete());
    }

    /**
     * @return array<int, mixed>
     */
    protected function noteForm(): array
    {
        return [
            TextInput::make('title')
                ->label('Title')
                ->required()
                ->maxLength(120),
            MarkdownEditor::make('body')
                ->label('Body')
                ->toolbarButtons(['bold', 'italic', 'strike', 'link', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'undo', 'redo'])
                ->minHeight('8rem'),
            ToggleButtons::make('color')
                ->hiddenLabel()
                // Each button in the tint it stands for (see the widget view's style).
                ->extraAttributes(['class' => 'sticky-note-colors'])
                ->options(collect(array_keys(StickyNote::COLORS))->mapWithKeys(fn (string $color): array => [$color => __(ucfirst($color))])->all())
                ->inline()
                ->required(),
        ];
    }

    /**
     * @return Builder<StickyNote>
     */
    protected function mine(): Builder
    {
        return StickyNote::query()->where('user_id', Auth::id());
    }
}
