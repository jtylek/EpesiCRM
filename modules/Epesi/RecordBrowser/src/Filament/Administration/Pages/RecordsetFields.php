<?php

namespace Epesi\Modules\RecordBrowser\Filament\Administration\Pages;

use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\CustomFieldResource;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Pages\ReordersFields;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Tables\FieldCatalogue;
use Filament\Actions\Action;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Attributes\Locked;

/**
 * The recordset's default tab: its field list, shipped and custom, as the
 * field catalogue shows it, locked to this recordset (reorder, view, edit and
 * reset all work here). "Add custom field" is a table header action.
 */
class RecordsetFields extends RecordsetPage implements HasTable
{
    use InteractsWithTable, ReordersFields {
        ReordersFields::reorderTable insteadof InteractsWithTable;
    }

    protected static ?string $slug = 'recordsets/{alias}';

    protected string $view = 'filament-panels::pages.page';

    /** The recordset the catalogue is locked to; set from the URL's alias on mount only. */
    #[Locked]
    public ?string $recordset = null;

    public static function tabLabel(): string
    {
        return 'Fields';
    }

    public function mount(string $alias): void
    {
        parent::mount($alias);

        $this->recordset = $alias;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        // A header action, not a toolbar one: the toolbar is hidden when a filter leaves no
        // rows or reordering is off, and the way to a new field must always stay.
        return FieldCatalogue::configure($table)->headerActions([
            Action::make('addField')
                ->label(__('Add custom field'))
                ->icon(Heroicon::OutlinedPlus)
                ->url(fn (): string => CustomFieldResource::getUrl('create', ['recordset' => $this->alias])),
        ]);
    }
}
