<?php

namespace Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages;

use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\TaskResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
