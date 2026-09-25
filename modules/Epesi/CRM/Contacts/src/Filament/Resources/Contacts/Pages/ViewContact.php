<?php

namespace Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages;

use App\Support\CloneRecordAction;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\ContactResource;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Schemas\ContactLoginEntries;
use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class ViewContact extends ViewRecord
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->icon(Heroicon::OutlinedPencil),
            CloneRecordAction::make(ContactResource::class, ['email', 'user_id']),
        ];
    }

    protected function getAdditionalContentTabs(Model $ownerRecord): array
    {
        return [
            static::entriesTab(
                'Login',
                $ownerRecord,
                ContactLoginEntries::components(),
                [ContactLoginEntries::changeUsernameAction(), ContactLoginEntries::resetPasswordAction()],
                inlineLabel: true,
            ),
        ];
    }
}
