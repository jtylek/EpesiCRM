<?php

namespace Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\Pages;

use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\ContactResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\CreateRecord;

class CreateContact extends CreateRecord
{
    protected static string $resource = ContactResource::class;
}
