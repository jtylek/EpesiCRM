<?php

namespace Epesi\Modules\CRM\Companies\Filament\Resources\Companies\RelationManagers;

use App\Filament\Concerns\TranslatesRelationManagerLabels;
use Epesi\Modules\CRM\Meetings\Filament\Resources\Meetings\MeetingResource;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;

/**
 * Read-only "linked activities" addon — meetings this company is the
 * customer of. See TasksRelationManager's docblock (same directory) for why
 * this now uses a real relationship (Company::meetingsAsCustomer()) instead
 * of a hand-built reach-through query.
 */
class MeetingsRelationManager extends RelationManager
{
    use TranslatesRelationManagerLabels;

    protected static string $relationship = 'meetingsAsCustomer';

    protected static ?string $title = 'Meetings';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('date')
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('time')
                    ->time('H:i'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('priority')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                LinkedRecords::badges(TextColumn::make('employees'), fn (Meeting $record): iterable => $record->employees)
                    ->label('Employees')
                    ->placeholder(__('-')),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Meeting $record): string => MeetingResource::getUrl('view', ['record' => $record]))
                    ->iconButton()
                    ->tooltip(__('View')),
            ])
            ->toolbarActions([]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
