<?php

namespace Epesi\Modules\CommonData\Filament\Resources\CommonData\Tables;

use Epesi\Modules\CommonData\Filament\Resources\CommonData\CommonDataResource;
use Epesi\Modules\CommonData\Models\CommonDataNode;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;

class CommonDataTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            // Epesi wires the same drag-to-reorder up by hand
            // (sort_nodes.js plus change_node_position()'s position shuffling);
            // the query is already scoped to one parent, so this reorders
            // siblings only.
            ->reorderable('position')
            ->columns([
                TextColumn::make('key')
                    ->searchable()
                    ->sortable()
                    ->url(fn (CommonDataNode $record): string => static::drillDownUrl($record))
                    ->tooltip(__('Open')),

                TextColumn::make('value')
                    ->searchable()
                    ->sortable()
                    ->placeholder(__('—'))
                    ->wrap(),

                TextColumn::make('children_count')
                    ->counts('children')
                    ->label('Entries')
                    ->badge()
                    ->color('gray')
                    ->url(fn (CommonDataNode $record): string => static::drillDownUrl($record)),

                IconColumn::make('readonly')
                    ->label('Locked')
                    ->icon(fn (bool $state): ?Heroicon => $state ? Heroicon::OutlinedLockClosed : null)
                    ->color('gray')
                    ->tooltip(fn (bool $state): ?string => $state ? 'Owned by a module — edit it in code, not here.' : null),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                Action::make('open')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedChevronRight)
                    ->tooltip(__('Open'))
                    ->url(fn (CommonDataNode $record): string => static::drillDownUrl($record)),

                EditAction::make()
                    ->iconButton()
                    ->tooltip(__('Edit'))
                    ->hidden(fn (CommonDataNode $record): bool => $record->readonly),

                DeleteAction::make()
                    ->iconButton()
                    ->tooltip(__('Delete'))
                    ->hidden(fn (CommonDataNode $record): bool => $record->readonly)
                    // Epesi deletes the subtree silently; anything selecting one
                    // of those descendants keeps a value that no longer resolves.
                    ->modalDescription(function (CommonDataNode $record): string {
                        $descendants = static::descendantCount($record);

                        return $descendants === 0
                            ? "Delete \"{$record->path}\"?"
                            : "Delete \"{$record->path}\" and the {$descendants} entries below it?";
                    }),
            ]);
    }

    protected static function drillDownUrl(CommonDataNode $record): string
    {
        return CommonDataResource::getUrl('index', ['path' => $record->path]);
    }

    protected static function descendantCount(CommonDataNode $record): int
    {
        return CommonDataNode::query()
            ->where('path', 'like', CommonDataNode::escapeLike($record->path).'/%')
            ->count();
    }
}
