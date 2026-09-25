<?php

namespace Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages;

use Epesi\Modules\Attachments\Filament\Resources\Attachments\AttachmentResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Filament\Actions\CreateAction;
use Filament\Support\Icons\Heroicon;

class ListAttachments extends ListRecords
{
    protected static string $resource = AttachmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New note')->icon(Heroicon::OutlinedPlus),
        ];
    }
}
