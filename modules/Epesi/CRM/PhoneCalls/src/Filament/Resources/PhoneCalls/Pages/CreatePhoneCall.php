<?php

namespace Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls\Pages;

use Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls\PhoneCallResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\CreateRecord;

class CreatePhoneCall extends CreateRecord
{
    protected static string $resource = PhoneCallResource::class;
}
