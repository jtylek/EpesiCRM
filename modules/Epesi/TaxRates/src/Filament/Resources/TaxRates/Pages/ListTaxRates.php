<?php

namespace Epesi\Modules\TaxRates\Filament\Resources\TaxRates\Pages;

use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Epesi\Modules\TaxRates\Filament\Resources\TaxRates\TaxRateResource;
use Filament\Actions\CreateAction;

class ListTaxRates extends ListRecords
{
    protected static string $resource = TaxRateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
