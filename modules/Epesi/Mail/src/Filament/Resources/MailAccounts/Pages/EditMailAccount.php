<?php

namespace Epesi\Modules\Mail\Filament\Resources\MailAccounts\Pages;

use App\Filament\Concerns\HasResourceIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use Epesi\Modules\Mail\Filament\Resources\MailAccounts\MailAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMailAccount extends EditRecord
{
    use HasResourceIconBreadcrumb;
    use HidesPageHeading;

    protected static string $resource = MailAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
