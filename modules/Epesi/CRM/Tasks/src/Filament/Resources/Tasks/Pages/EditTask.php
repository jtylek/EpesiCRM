<?php

namespace Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\Pages;

use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\TaskResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\EditRecord;

class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;
}
