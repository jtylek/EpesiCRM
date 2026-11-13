<?php

namespace Epesi\Modules\Mail\Filament\Resources\MailAccounts\Pages;

use App\Filament\Concerns\HasResourceIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use Epesi\Modules\Mail\Filament\Resources\MailAccounts\MailAccountResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class CreateMailAccount extends CreateRecord
{
    use HasResourceIconBreadcrumb;
    use HidesPageHeading;

    protected static string $resource = MailAccountResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * Cancel and Save in the header, in the same order as on the edit page.
     * getCreateFormAction() renders type="submit"; outside the <form> it needs
     * formId('form') or clicking it does nothing.
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction()->icon(Heroicon::OutlinedXMark)->color('gray')->extraAttributes(MailAccountResource::FILLED_GRAY),
            $this->getCreateFormAction()->label('Save')->icon(Heroicon::OutlinedCheck)->color('success')->formId('form'),
        ];
    }

    /** No second Save/Cancel pair under the form. */
    protected function getFormActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'user_id' => Auth::id()];
    }

    protected function afterCreate(): void
    {
        MailAccountResource::fillJunkFolder($this->getRecord());
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
