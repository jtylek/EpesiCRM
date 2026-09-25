<?php

namespace Epesi\Modules\Mail\Filament\RelationManagers;

use App\Filament\Concerns\TranslatesRelationManagerLabels;
use Epesi\Modules\Mail\Models\MailAddress;
use Epesi\Modules\Mail\Services\ContactMatcher;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

/**
 * "E-mail addresses" — the rc_multiple_emails addon: more addresses for a
 * contact or company, all used to recognise their mail. Adding one files the
 * already-archived mail from it here too (CRM_MailCommon::reload_mails()).
 */
class MailAddressesRelationManager extends RelationManager
{
    use TranslatesRelationManagerLabels;

    protected static string $relationship = 'mailAddresses';

    protected static ?string $title = 'E-mail addresses';

    protected static ?string $modelLabel = 'e-mail address';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255)
                ->rule(fn (?MailAddress $record) => Rule::unique('epesi_mail_addresses', 'email')->ignore($record?->getKey()))
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                TextColumn::make('email')->copyable()->searchable(),
                TextColumn::make('created_at')->label('Added')->date(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->icon(Heroicon::OutlinedPlus)
                    ->after(fn (MailAddress $record) => app(ContactMatcher::class)
                        ->relinkExisting($this->getOwnerRecord(), $record->email)),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                EditAction::make()->iconButton()->tooltip(__('Edit'))
                    ->after(fn (MailAddress $record) => app(ContactMatcher::class)
                        ->relinkExisting($this->getOwnerRecord(), $record->email)),
                DeleteAction::make()->iconButton()->tooltip(__('Delete')),
            ]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
