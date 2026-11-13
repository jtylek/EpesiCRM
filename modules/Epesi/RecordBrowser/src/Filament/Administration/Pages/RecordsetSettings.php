<?php

namespace Epesi\Modules\RecordBrowser\Filament\Administration\Pages;

use Epesi\Modules\RecordBrowser\Recordset\ExportGrants;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * The engine's own switches (Copy to clipboard, Export) and, until the
 * Permissions tab exists, the roles that may export.
 */
class RecordsetSettings extends RecordsetPage
{
    protected static ?string $slug = 'recordsets/{alias}/settings';

    public static function tabLabel(): string
    {
        return 'Settings';
    }

    public function hasSave(): bool
    {
        return true;
    }

    protected function formData(): array
    {
        return [
            'features' => array_values(array_intersect(
                Recordsets::enabledFeatures($this->alias),
                array_keys($this->featureOptions(true)),
            )),
            'export_roles' => ExportGrants::rolesFor($this->alias),
        ];
    }

    protected function formComponents(): array
    {
        return [
            CheckboxList::make('features')
                ->label(__('Features'))
                ->live()
                ->options(fn (): array => $this->featureOptions(true)),
            Select::make('export_roles')
                ->label(__('Export roles'))
                ->multiple()
                ->options(fn (): array => Role::query()->where('name', '!=', 'super_admin')->orderBy('name')->pluck('name', 'id')->all())
                ->visible(fn (Get $get): bool => in_array('export', $get('features') ?? [], true))
                ->helperText(__('Super admins can export by default. These roles may export this recordset when they can view it.')),
        ];
    }

    public function save(): void
    {
        $this->authorizeSave();

        $data = $this->form->getState();
        $features = $data['features'] ?? [];

        DB::transaction(function () use ($features, $data): void {
            if (in_array('export', $features, true)) {
                ExportGrants::save($this->alias, $data['export_roles'] ?? []);
            }

            $this->saveFeatures(array_keys($this->featureOptions(true)), $features);
        });

        $this->saved();
    }
}
