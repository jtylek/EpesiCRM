<?php

namespace Epesi\Modules\Mail\Filament\Resources\MailAccounts\Pages;

use App\Filament\Concerns\HasResourceIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use Epesi\Modules\Mail\Filament\Resources\MailAccounts\MailAccountResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateMailAccount extends CreateRecord
{
    use HasResourceIconBreadcrumb;
    use HidesPageHeading;

    protected static string $resource = MailAccountResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'user_id' => Auth::id()];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
