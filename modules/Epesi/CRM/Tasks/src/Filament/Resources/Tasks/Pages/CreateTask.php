<?php

namespace Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages;

use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\TaskResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\CreateRecord;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;
}
