<?php

namespace Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages;

use App\Support\CloneRecordAction;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\ContactResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;

/**
 * No Login tab: a contact's login (password, username) is managed in
 * Administration → Users, where a user is made from a contact.
 */
class ViewContact extends ViewRecord
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->icon(Heroicon::OutlinedPencil),
            CloneRecordAction::make(ContactResource::class, ['user_id']),
        ];
    }
}
