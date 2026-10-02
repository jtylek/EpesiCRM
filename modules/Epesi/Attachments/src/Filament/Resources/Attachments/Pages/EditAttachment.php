<?php

namespace Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages;

use App\Enums\NoteFormat;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\AttachmentResource;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\Concerns\AlertsWhenNotAttached;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\Concerns\BelongsToOwnerRecord;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\RecordBrowser\Filament\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class EditAttachment extends EditRecord
{
    use AlertsWhenNotAttached, BelongsToOwnerRecord;

    protected static string $resource = AttachmentResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->mountOwnerRecord($this->getRecord());
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Attachment $note */
        $note = $this->getRecord();

        $data['attach_to'] = AttachmentResource::attachedToState($note);
        $data['editor'] = ($note->format ?? NoteFormat::Html)->value;
        $data['note_markdown'] = $note->format === NoteFormat::Markdown ? $note->note : '';

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $rows = Arr::pull($data, 'attach_to') ?? [];
        $data = AttachmentResource::foldNoteState($data, $this->data['note_markdown'] ?? '');

        /** @var Attachment $note */
        $note = parent::handleRecordUpdate($record, $data);

        AttachmentResource::syncAttachedTo($note, $rows);

        // Taken off the record it was opened from: the page no longer belongs
        // to that record, and a note page can't be opened as part of one the
        // note isn't on (mountOwnerRecord()).
        if ($this->ownerRecord && ! $note->links()
            ->where('attachable_type', $this->ownerRecord->getMorphClass())
            ->where('attachable_id', $this->ownerRecord->getKey())
            ->exists()) {
            $this->ownerRecord = null;
        }

        return $note;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl('view');
    }
}
