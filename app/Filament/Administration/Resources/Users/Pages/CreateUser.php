<?php

namespace App\Filament\Administration\Resources\Users\Pages;

use App\Filament\Administration\Resources\Users\UserResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
