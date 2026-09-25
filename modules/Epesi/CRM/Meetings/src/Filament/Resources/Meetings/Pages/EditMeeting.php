<?php

namespace Epesi\Modules\CRM\Meetings\Filament\Resources\Meetings\Pages;

use Epesi\Modules\CRM\Meetings\Filament\Resources\Meetings\MeetingResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\EditRecord;

class EditMeeting extends EditRecord
{
    protected static string $resource = MeetingResource::class;
}
