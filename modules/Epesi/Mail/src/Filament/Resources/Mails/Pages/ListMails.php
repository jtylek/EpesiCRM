<?php

namespace Epesi\Modules\Mail\Filament\Resources\Mails\Pages;

use Epesi\Modules\Mail\Filament\Actions\ComposeAction;
use Epesi\Modules\Mail\Filament\Actions\UploadEmlAction;
use Epesi\Modules\Mail\Filament\Resources\Mails\MailResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;

class ListMails extends ListRecords
{
    protected static string $resource = MailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ComposeAction::make(),
            UploadEmlAction::make(),
        ];
    }
}
