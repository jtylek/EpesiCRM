<?php

namespace Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages;

use Epesi\Modules\Attachments\Filament\Resources\Attachments\AttachmentResource;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\Concerns\BelongsToOwnerRecord;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\RecordBrowser\Filament\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CreateAttachment extends CreateRecord
{
    use BelongsToOwnerRecord;

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
        $type = Arr::pull($data, 'attach_to_type');
        $id = Arr::pull($data, 'attach_to_id');
        $owner = $this->ownerRecord;

        if ($owner === null && filled($type)) {
            $owner = AttachmentResource::findRecord($type, $id)
                ?? throw ValidationException::withMessages(['data.attach_to_id' => 'Choose a record you can see.']);
        }

        /** @var Attachment $note */
        $note = parent::handleRecordCreation($data);

        if ($owner) {
            $note->attachTo($owner);
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
