<?php

namespace Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Pages;

use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Actions\TestAppAction;
use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\OAuthAppResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;

class ViewOAuthApp extends ViewRecord
{
    protected static string $resource = OAuthAppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TestAppAction::make(),
            EditAction::make()->icon(Heroicon::OutlinedPencil),
        ];
    }

    /** The kebab's Delete, too, warns about the connections and is off in demo mode. */
    protected function recordInfoAndHistoryKebab(): ActionGroup
    {
        $kebab = parent::recordInfoAndHistoryKebab();

        foreach ($kebab->getActions() as $action) {
            if ($action instanceof DeleteAction) {
                OAuthAppResource::configureDeleteAction($action);
            }
        }

        return $kebab;
    }
}
