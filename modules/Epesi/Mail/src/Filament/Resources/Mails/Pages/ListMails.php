<?php

namespace Epesi\Modules\Mail\Filament\Resources\Mails\Pages;

use App\Filament\Concerns\HasResourceIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use Epesi\Modules\Mail\Filament\Actions\ComposeAction;
use Epesi\Modules\Mail\Filament\Actions\UploadEmlAction;
use Epesi\Modules\Mail\Filament\Resources\Mails\MailResource;
use Filament\Resources\Pages\ListRecords;

class ListMails extends ListRecords
{
    use HasResourceIconBreadcrumb;
    use HidesPageHeading;

    protected static string $resource = MailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ComposeAction::make(),
            UploadEmlAction::make(),
        ];
    }
}
