<?php

namespace Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages;

use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Epesi\Modules\RecordBrowser\Filament\Pages\CreateRecord;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\CustomFieldResource;
use Livewire\Attributes\Locked;

class CreateCustomField extends CreateRecord
{
    use LeadsBackToRecordset;

    protected static string $resource = CustomFieldResource::class;

    /** The recordset this page was opened from, if any. */
    #[Locked]
    public ?string $recordset = null;

    protected function afterFill(): void
    {
        parent::afterFill();

        $alias = request()->query('recordset');
        if (is_string($alias) && array_key_exists($alias, CustomFieldRegistry::participatingModels())) {
            $this->data['model_type'] = $alias;
            $this->recordset = $alias;
        }
    }

    protected function breadcrumbRecordset(): ?string
    {
        return $this->recordset;
    }
}
