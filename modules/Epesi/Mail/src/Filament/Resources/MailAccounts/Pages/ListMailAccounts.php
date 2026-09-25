<?php

namespace Epesi\Modules\Mail\Filament\Resources\MailAccounts\Pages;

use App\Filament\Concerns\HasResourceIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use Epesi\Modules\Mail\Filament\Resources\MailAccounts\MailAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMailAccounts extends ListRecords
{
    use HasResourceIconBreadcrumb;
    use HidesPageHeading;

    protected static string $resource = MailAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
