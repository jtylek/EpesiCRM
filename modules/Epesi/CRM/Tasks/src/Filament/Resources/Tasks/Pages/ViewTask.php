<?php

namespace Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages;

use App\Support\CloneRecordAction;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\TaskResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;

class ViewTask extends ViewRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->icon(Heroicon::OutlinedPencil),
            CloneRecordAction::make(TaskResource::class),
        ];
    }
}
