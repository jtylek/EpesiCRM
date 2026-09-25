<?php

namespace Epesi\Modules\Attachments\Filament\RelationManagers;

use App\Filament\Concerns\TranslatesRelationManagerLabels;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\AttachmentResource;
use Epesi\Modules\Attachments\Models\Attachment;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The "Notes" addon — the port of Utils_Attachment's addon, which Epesi puts
 * on every record listed in `utils_attachment_related`: the same list as the
 * sidebar's Notes, narrowed to this record.
 *
 * A note opens on AttachmentResource's own pages rather than in a modal —
 * the rich-text editor needs the whole page — with this record carried along
 * so the page reads as part of it and comes back to this tab.
 */
class NotesRelationManager extends RelationManager
{
    use TranslatesRelationManagerLabels;

    protected static string $relationship = 'attachments';

    protected static ?string $title = 'Notes';

    protected static ?string $modelLabel = 'note';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedPaperClip;

    public function table(Table $table): Table
    {
        return AttachmentResource::notesTable($table, attachedTo: false)
            ->recordTitle(fn (Attachment $record): string => $record->label())
            ->recordUrl(fn (Attachment $record): string => $this->noteUrl('view', $record))
            ->headerActions([
                CreateAction::make()
                    ->label('New note')
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(fn (): string => $this->noteUrl('create')),
            ])
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip(__('View'))
                    ->url(fn (Attachment $record): string => $this->noteUrl('view', $record)),
                EditAction::make()->iconButton()->tooltip(__('Edit'))
                    ->url(fn (Attachment $record): string => $this->noteUrl('edit', $record)),
                DeleteAction::make()->iconButton()->tooltip(__('Delete')),
            ]);
    }

    /**
     * Filament makes every addon on a View page read-only by default; notes
     * are written from the record's page, as in Epesi.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    protected function noteUrl(string $page, ?Attachment $record = null): string
    {
        return AttachmentResource::getUrl($page, [
            ...($record ? ['record' => $record] : []),
            ...AttachmentResource::ownerParameters($this->getOwnerRecord()),
        ]);
    }
}
