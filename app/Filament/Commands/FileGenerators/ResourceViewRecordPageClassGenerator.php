<?php

namespace App\Filament\Commands\FileGenerators;

use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;
use Filament\Commands\FileGenerators\Resources\Pages\ResourceViewRecordPageClassGenerator as BaseResourceViewRecordPageClassGenerator;

/**
 * Bound over the vendor generator in AppServiceProvider so `php artisan
 * make:filament-resource` scaffolds a View page extending our shared
 * Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord (Record Info tab, row-click-to-view, ...)
 * instead of Filament's own — every new resource gets the same rendering
 * without anyone having to remember to change the `extends` by hand.
 */
class ResourceViewRecordPageClassGenerator extends BaseResourceViewRecordPageClassGenerator
{
    public function getExtends(): string
    {
        return ViewRecord::class;
    }
}
