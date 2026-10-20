<?php

namespace Epesi\Modules\RecordBrowser\Filament\RelationManagers;

use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Epesi\Modules\RecordBrowser\Recordset\IncomingLinks;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

class LinkedRecordsRelationManager extends RelationManager
{
    #[Locked]
    public string $sourceResource;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $pageClass::getResource()::canView($ownerRecord);
    }

    protected function makeTable(): Table
    {
        return Table::make($this);
    }

    public function table(Table $table): Table
    {
        $resource = $this->sourceResource;
        $fields = IncomingLinks::for($this->ownerRecord)[$resource] ?? [];
        // No links (the viewed record or a permission changed since the tab was
        // rendered): an empty table, not an exception — a throw here leaves
        // `$this->table` uninitialized and the re-render fails on it.
        $table = $resource::table($table)->query(IncomingLinks::query($resource, $this->ownerRecord, $fields));
        $hidden = [];

        foreach ($fields as $field) {
            if ($field->type === FieldType::Relation) {
                $hidden[] = $field->name;
                $hidden[] = $field->getParam('relationship');
            }

            if (in_array($field->type, [FieldType::Customer, FieldType::Customers], true)) {
                $hidden[] = $field->name;
            }
        }

        $table->columns(array_values(array_filter($table->getColumns(), fn ($column) => ! in_array($column->getName(), $hidden, true))));

        return $table->heading(IncomingLinks::tabLabel($resource, $this->ownerRecord))->filters([])
            ->recordUrl(fn (Model $record) => $resource::getUrl('view', ['record' => $record], panel: 'main'))
            ->recordActions([
                Action::make('view')->label('View')->icon('heroicon-o-eye')->iconButton()
                    ->url(fn (Model $record) => $resource::getUrl('view', ['record' => $record], panel: 'main')),
                Action::make('edit')->label('Edit')->icon('heroicon-o-pencil-square')->iconButton()
                    ->visible(fn (Model $record) => $resource::hasPage('edit') && $resource::canEdit($record))
                    ->url(fn (Model $record) => $resource::getUrl('edit', ['record' => $record], panel: 'main')),
            ])->toolbarActions([])->headerActions([
                Action::make('new')->label('New')->icon('heroicon-o-plus')->visible(fn () => $resource::hasPage('create') && $resource::canCreate())
                    ->url(fn () => $resource::getUrl('create', ['link' => $this->ownerRecord->getMorphClass().':'.$this->ownerRecord->getKey()], panel: 'main')),
            ]);
    }
}
