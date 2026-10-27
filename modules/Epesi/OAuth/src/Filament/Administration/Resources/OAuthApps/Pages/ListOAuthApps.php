<?php

namespace Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Pages;

use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\OAuthAppResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListOAuthApps extends ListRecords
{
    protected static string $resource = OAuthAppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->disabled(fn (): bool => OAuthAppResource::providersWithFreeEnvironments() === [])
                ->tooltip(fn (): ?string => OAuthAppResource::providersWithFreeEnvironments() === []
                    ? __('Every provider already has an app in each of its environments.')
                    : null),
        ];
    }
}
