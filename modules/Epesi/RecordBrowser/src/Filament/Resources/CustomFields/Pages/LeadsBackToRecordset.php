<?php

namespace Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages;

use Epesi\Modules\RecordBrowser\Filament\Administration\Pages\Recordsets;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\CustomFieldResource;

/**
 * Field pages opened from a recordset lead back to it:
 * Recordsets > Company > Fields, not to the list of every recordset's fields.
 */
trait LeadsBackToRecordset
{
    abstract protected function breadcrumbRecordset(): ?string;

    public function getResourceBreadcrumbs(): array
    {
        $alias = $this->breadcrumbRecordset();

        if (blank($alias) || ! array_key_exists($alias, Recordsets::recordsets())) {
            return parent::getResourceBreadcrumbs();
        }

        return [
            ...Recordsets::breadcrumbsFor($alias),
            CustomFieldResource::fieldsUrl($alias) => __('Fields'),
        ];
    }
}
