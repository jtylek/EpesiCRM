<?php

namespace App\Filament\Administration\Resources\LoginAudits\Pages;

use App\Filament\Administration\Resources\LoginAudits\LoginAuditResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;

class ListLoginAudits extends ListRecords
{
    protected static string $resource = LoginAuditResource::class;
}
