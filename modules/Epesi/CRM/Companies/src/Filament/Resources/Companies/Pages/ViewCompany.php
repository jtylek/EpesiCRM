<?php

namespace Epesi\Modules\CRM\Companies\Filament\Resources\Companies\Pages;

use App\Support\CloneRecordAction;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\CompanyResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;

class ViewCompany extends ViewRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->icon(Heroicon::OutlinedPencil),
            CloneRecordAction::make(CompanyResource::class, ['email']),
        ];
    }
}
