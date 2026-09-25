<?php

namespace Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages;

use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\ContactResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\EditRecord;

class EditContact extends EditRecord
{
    protected static string $resource = ContactResource::class;
}
