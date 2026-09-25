<?php

namespace Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages;

use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\CompanyResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListCompanies extends ListRecords
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
