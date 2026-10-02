<?php

namespace Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages;

use Epesi\Modules\Attachments\Filament\Resources\Attachments\AttachmentResource;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\Concerns\AlertsWhenNotAttached;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\Concerns\BelongsToOwnerRecord;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\RecordBrowser\Filament\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateAttachment extends CreateRecord
{
    use AlertsWhenNotAttached, BelongsToOwnerRecord;

    protected static string $resource = AttachmentResource::class;

    protected static ?string $title = 'New note';

    protected static ?string $breadcrumb = 'New note';

    public function mount(): void
    {
        $this->mountOwnerRecord();

        parent::mount();
    }

    protected function handleRecordCreation(array $data): Model
    {
        $rows = Arr::pull($data, 'attach_to') ?? [];
        $data = AttachmentResource::foldNoteState($data, $this->data['note_markdown'] ?? '');

        /** @var Attachment $note */
        $note = parent::handleRecordCreation($data);

        if ($this->ownerRecord) {
            $note->attachTo($this->ownerRecord);
        } else {
            AttachmentResource::syncAttachedTo($note, $rows);
        }

        return $note;
    }

    /**
     * Back to the record's Notes tab when started from one; from the Notes
     * list, to the new note, as every other Create page does.
     */
    protected function getRedirectUrl(): string
    {
        return $this->ownerRecord ? $this->getResourceUrl() : parent::getRedirectUrl();
    }
}
