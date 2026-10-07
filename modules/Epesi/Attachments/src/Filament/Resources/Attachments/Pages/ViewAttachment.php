<?php

namespace Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages;

use App\Enums\NoteFormat;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\AttachmentResource;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\Concerns\BelongsToOwnerRecord;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\Attachments\Services\LegacyNoteCipher;
use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

class ViewAttachment extends ViewRecord
{
    use BelongsToOwnerRecord;

    protected static string $resource = AttachmentResource::class;

    #[Locked]
    public ?string $decryptedLegacyNote = null;

    #[Locked]
    public ?int $decryptedLegacyNoteId = null;

    public function getDecryptedLegacyNoteHtml(): ?string
    {
        if ($this->decryptedLegacyNoteId !== $this->getRecord()->getKey() || $this->decryptedLegacyNote === null) {
            return null;
        }

        return NoteFormat::Html->toHtml($this->decryptedLegacyNote);
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->mountOwnerRecord($this->getRecord());
    }

    /** Called by the Encryption row's Decrypt note action once its password field validated. */
    public function showDecryptedLegacyNote(Attachment $record, string $password): void
    {
        abort_unless($record->is($this->getRecord()) && Gate::allows('view', $record), 403);

        $plain = app(LegacyNoteCipher::class)->decrypt((string) $record->note, $password);
        abort_if($plain === null, 403);

        $this->decryptedLegacyNote = $plain;
        $this->decryptedLegacyNoteId = (int) $record->getKey();
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->icon(Heroicon::OutlinedPencil),
        ];
    }
}
