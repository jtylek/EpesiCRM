<?php

namespace Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages;

use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\CustomFieldActions;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\CustomFieldResource;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;

class ViewCustomField extends ViewRecord
{
    use LeadsBackToRecordset;

    protected static string $resource = CustomFieldResource::class;

    protected function breadcrumbRecordset(): ?string
    {
        return $this->getRecord()->model_type;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->icon(Heroicon::OutlinedPencil),
            CustomFieldActions::dropColumn(),
        ];
    }
}
