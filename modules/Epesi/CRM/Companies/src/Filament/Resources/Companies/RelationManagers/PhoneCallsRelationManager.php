<?php

namespace Epesi\Modules\CRM\Companies\Filament\Resources\Companies\RelationManagers;

use App\Filament\Concerns\TranslatesRelationManagerLabels;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls\PhoneCallResource;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;

/**
 * Read-only "linked activities" addon — phone calls logged against this
 * company. See TasksRelationManager's docblock (same directory) for why this
 * now uses a real relationship (PhoneCall::company()) instead of a
 * hand-built reach-through query.
 */
class PhoneCallsRelationManager extends RelationManager
{
    use TranslatesRelationManagerLabels;

    protected static string $relationship = 'phoneCallsAsCustomer';

    protected static ?string $title = 'Phone Calls';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->defaultSort('called_at', 'desc')
            ->columns([
                TextColumn::make('subject')
                    ->searchable(),
                LinkedRecords::badges(TextColumn::make('contact'), fn (PhoneCall $record): ?Contact => $record->contact)
                    ->label('Contact')
                    ->placeholder(__('-')),
                TextColumn::make('called_at')
                    ->label('Date and Time')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('priority')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                LinkedRecords::badges(TextColumn::make('employees'), fn (PhoneCall $record): iterable => $record->employees)
                    ->label('Employees')
                    ->placeholder(__('-')),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                ViewAction::make()
                    ->url(fn (PhoneCall $record): string => PhoneCallResource::getUrl('view', ['record' => $record]))
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
