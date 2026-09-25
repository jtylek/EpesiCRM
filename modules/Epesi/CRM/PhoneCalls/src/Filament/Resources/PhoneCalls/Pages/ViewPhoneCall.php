<?php

namespace Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls\Pages;

use App\Support\CloneRecordAction;
use Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls\PhoneCallResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;

class ViewPhoneCall extends ViewRecord
{
    protected static string $resource = PhoneCallResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->icon(Heroicon::OutlinedPencil),
            CloneRecordAction::make(PhoneCallResource::class),
        ];
    }
}
