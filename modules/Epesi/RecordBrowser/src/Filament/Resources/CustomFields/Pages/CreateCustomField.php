<?php

namespace Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages;

use Epesi\Modules\RecordBrowser\Filament\Pages\CreateRecord;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\CustomFieldResource;

class CreateCustomField extends CreateRecord
{
    protected static string $resource = CustomFieldResource::class;
}
