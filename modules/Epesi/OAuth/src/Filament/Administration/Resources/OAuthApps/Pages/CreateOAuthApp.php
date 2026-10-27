<?php

namespace Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Pages;

use App\Support\Demo;
use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\OAuthAppResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\CreateRecord;

class CreateOAuthApp extends CreateRecord
{
    protected static string $resource = OAuthAppResource::class;

    /** Save is a form submit, not an action Demo::guard() could wrap. */
    protected function beforeValidate(): void
    {
        if (Demo::enabled()) {
            Demo::unavailable();
            $this->halt();
        }
    }
}
