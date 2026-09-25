<?php

namespace App\Filament\Administration\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Shared by both CreateUser and EditUser (via UserResource::form()). Password
 * is set here only on create — editing a user's password happens through the
 * "Reset Password" header action on ViewUser instead (matching
 * ContactLoginEntries::resetPasswordAction() for a Contact's linked login),
 * so an Edit visit never shows a blank password field that could be
 * mistaken for "leave blank to keep current".
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->confirmed()
                            ->visible(fn (string $operation): bool => $operation === 'create'),
                        TextInput::make('password_confirmation')
                            ->password()
                            ->revealable()
                            ->required()
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'create'),
                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
