<?php

namespace Epesi\Modules\CRM\Meetings\Filament\Resources\Meetings\Pages;

use Epesi\Modules\CRM\Meetings\Filament\Resources\Meetings\MeetingResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListMeetings extends ListRecords
{
    protected static string $resource = MeetingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
