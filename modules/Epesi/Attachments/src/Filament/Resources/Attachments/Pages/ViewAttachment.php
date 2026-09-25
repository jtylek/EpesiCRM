<?php

namespace Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages;

use Epesi\Modules\Attachments\Filament\Resources\Attachments\AttachmentResource;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\Concerns\BelongsToOwnerRecord;
use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;

class ViewAttachment extends ViewRecord
{
    use BelongsToOwnerRecord;

    protected static string $resource = AttachmentResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->mountOwnerRecord($this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->icon(Heroicon::OutlinedPencil),
        ];
    }
}
