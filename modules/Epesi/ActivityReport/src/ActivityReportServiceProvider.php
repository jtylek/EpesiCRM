<?php

namespace Epesi\Modules\ActivityReport;

use Epesi\Modules\ActivityReport\Filament\RelationManagers\JournalRelationManager;
use Epesi\Modules\RecordBrowser\Extensions\RecordExtensions;
use Illuminate\Support\ServiceProvider;

class ActivityReportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'epesi-activity-report');

        // The Journal tab of a contact with a login (Epesi's contact addon).
        RecordExtensions::addon(JournalRelationManager::class, ['contact']);
    }
}
