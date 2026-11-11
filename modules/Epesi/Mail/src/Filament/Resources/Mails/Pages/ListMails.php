<?php

namespace Epesi\Modules\Mail\Filament\Resources\Mails\Pages;

use Epesi\Modules\Mail\Filament\Actions\ComposeAction;
use Epesi\Modules\Mail\Filament\Actions\UploadEmlAction;
use Epesi\Modules\Mail\Filament\Resources\Mails\MailResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ListMails extends ListRecords
{
    protected static string $resource = MailResource::class;

    /** "My records" is MailTable's own filter here, not one Employees value. */
    protected function myRecordsEmployeesFilter(?Table $table = null): ?SelectFilter
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            ComposeAction::make(),
            UploadEmlAction::make(),
        ];
    }
}
