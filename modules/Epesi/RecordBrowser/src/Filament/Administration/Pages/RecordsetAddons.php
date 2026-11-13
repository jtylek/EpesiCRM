<?php

namespace Epesi\Modules\RecordBrowser\Filament\Administration\Pages;

use Filament\Forms\Components\CheckboxList;
use Illuminate\Support\Facades\DB;

/**
 * The addon modules attached to the recordset (E-mails, Notes and any module
 * that declares a feature). The engine's own switches are on Settings.
 */
class RecordsetAddons extends RecordsetPage
{
    protected static ?string $slug = 'recordsets/{alias}/addons';

    public static function tabLabel(): string
    {
        return 'Addons';
    }

    public function hasSave(): bool
    {
        return true;
    }

    protected function formData(): array
    {
        return ['features' => array_values(array_intersect(
            Recordsets::enabledFeatures($this->alias),
            array_keys($this->featureOptions(false)),
        ))];
    }

    protected function formComponents(): array
    {
        return [
            CheckboxList::make('features')
                ->label(__('Addons'))
                ->options(fn (): array => $this->featureOptions(false))
                ->helperText(__('Links already made stay in place when a module is turned off; they show again when it is turned back on.')),
        ];
    }

    public function save(): void
    {
        $this->authorizeSave();

        $data = $this->form->getState();

        DB::transaction(fn () => $this->saveFeatures(array_keys($this->featureOptions(false)), $data['features'] ?? []));

        $this->saved();
    }
}
