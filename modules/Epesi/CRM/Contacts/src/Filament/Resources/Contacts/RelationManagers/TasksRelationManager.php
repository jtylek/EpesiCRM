<?php

namespace Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\RelationManagers;

use App\Filament\Concerns\TranslatesRelationManagerLabels;
use Carbon\Carbon;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks\TaskResource;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;

/**
 * Read-only "linked activities" addon — tasks this contact is the customer
 * of (Epesi's Companies/Contacts modules showed the same kind of tab; see
 * Task's own docblock for why this is a Contact-only relationship rather
 * than Epesi's Company-or-Contact Customer type).
 */
class TasksRelationManager extends RelationManager
{
    use TranslatesRelationManagerLabels;

    protected static string $relationship = 'tasksAsCustomer';

    protected static ?string $title = 'Tasks';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('deadline')
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('deadline')
                    ->formatStateUsing(fn (Task $record, ?Carbon $state): string => $state === null
                        ? '-'
                        : ($record->timeless ? $state->format('Y-m-d') : $state->format('Y-m-d H:i')))
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('priority')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                LinkedRecords::badges(TextColumn::make('employees'), fn (Task $record): iterable => $record->employees)
                    ->label('Employees')
                    ->placeholder(__('-')),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Task $record): string => TaskResource::getUrl('view', ['record' => $record]))
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
