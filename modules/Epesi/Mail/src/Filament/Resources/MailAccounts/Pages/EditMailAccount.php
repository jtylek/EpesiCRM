<?php

namespace Epesi\Modules\Mail\Filament\Resources\MailAccounts\Pages;

use App\Filament\Concerns\HasResourceIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use Epesi\Modules\Mail\Filament\Resources\MailAccounts\MailAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditMailAccount extends EditRecord
{
    use HasResourceIconBreadcrumb;
    use HidesPageHeading;

    protected static string $resource = MailAccountResource::class;

    /**
     * Cancel, Save changes and Delete account, all in the header.
     * getSaveFormAction() renders type="submit"; outside the <form> it needs
     * formId('form') or clicking it does nothing.
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction()->icon(Heroicon::OutlinedXMark)->color('gray')->extraAttributes(MailAccountResource::FILLED_GRAY),
            $this->getSaveFormAction()->label('Save changes')->icon(Heroicon::OutlinedCheck)->color('success')->formId('form'),
            DeleteAction::make()->label('Delete account')->icon(Heroicon::OutlinedTrash),
        ];
    }

    /** No second Save/Cancel pair under the form. */
    protected function getFormActions(): array
    {
        return [];
    }

    protected function afterSave(): void
    {
        MailAccountResource::fillJunkFolder($this->getRecord());
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
