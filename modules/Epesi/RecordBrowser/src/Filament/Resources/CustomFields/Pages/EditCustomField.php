<?php

namespace Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages;

use Epesi\Modules\RecordBrowser\Filament\Pages\EditRecord;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\CustomFieldResource;

class EditCustomField extends EditRecord
{
    use LeadsBackToRecordset;

    protected static string $resource = CustomFieldResource::class;

    protected function breadcrumbRecordset(): ?string
    {
        return $this->getRecord()->model_type;
    }
}
