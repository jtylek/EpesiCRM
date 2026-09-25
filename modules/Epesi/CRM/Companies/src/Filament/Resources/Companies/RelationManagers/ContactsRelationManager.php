<?php

namespace Epesi\Modules\CRM\Companies\Filament\Resources\Companies\RelationManagers;

use App\Filament\Concerns\TranslatesRelationManagerLabels;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\ContactResource;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Extensions\RecordExtensions;
use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;

class ContactsRelationManager extends RelationManager
{
    use TranslatesRelationManagerLabels;

    protected static string $relationship = 'contacts';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')
                    ->required()
                    ->maxLength(64),
                TextInput::make('last_name')
                    ->required()
                    ->maxLength(64),
                TextInput::make('title')
                    ->maxLength(64),
                TextInput::make('email')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('work_phone')
                    ->tel()
                    ->maxLength(64),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('last_name')
            ->columns([
                TextColumn::make('first_name')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->searchable(),
                TextColumn::make('title'),
                // As the Contacts list shows it — see RecordExtensions::emailLink().
                LinkedRecords::style(
                    TextColumn::make('email'),
                    fn (Contact $record, ?string $state): ?string => filled($state) ? RecordExtensions::emailUrlFor($record, $state) : null,
                ),
                TextColumn::make('work_phone'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                AssociateAction::make(),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record): string => ContactResource::getUrl('view', ['record' => $record]))
                    ->iconButton()
                    ->tooltip(__('View')),
                EditAction::make()
                    ->iconButton()
                    ->tooltip(__('Edit')),
                DissociateAction::make()
                    ->iconButton()
                    ->tooltip(__('Dissociate')),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip(__('Delete')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
