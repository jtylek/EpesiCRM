<?php

namespace Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Pages;

use App\Support\Demo;
use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\Actions\TestAppAction;
use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\OAuthAppResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\EditRecord;
use Filament\Actions\DeleteAction;

class EditOAuthApp extends EditRecord
{
    protected static string $resource = OAuthAppResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TestAppAction::make(onEditPage: true),
            ...array_map(
                fn ($action) => $action instanceof DeleteAction ? OAuthAppResource::configureDeleteAction($action) : $action,
                parent::getHeaderActions(),
            ),
        ];
    }

    /**
     * The app settings on the form replace only their own keys: anything else
     * stored (a field the provider no longer declares) stays.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('settings', $data)) {
            $data['settings'] = array_merge((array) ($this->getRecord()->settings ?? []), (array) ($data['settings'] ?? [])) ?: null;
        }

        return $data;
    }

    /** Save is a form submit, not an action Demo::guard() could wrap. */
    protected function beforeValidate(): void
    {
        if (Demo::enabled()) {
            Demo::unavailable();
            $this->halt();
        }
    }
}
