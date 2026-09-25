<?php

namespace App\Filament\Administration\Resources\Users;

use App\Filament\Administration\Resources\Users\Pages\CreateUser;
use App\Filament\Administration\Resources\Users\Pages\EditUser;
use App\Filament\Administration\Resources\Users\Pages\ListUsers;
use App\Filament\Administration\Resources\Users\Pages\ViewUser;
use App\Filament\Administration\Resources\Users\Schemas\UserForm;
use App\Filament\Administration\Resources\Users\Schemas\UserInfolist;
use App\Filament\Administration\Resources\Users\Tables\UsersTable;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Manages panel logins (name/email/password/roles) — lives in the
 * "administration" panel alongside Login Audit, not the main CRM one, since
 * this is account administration rather than everyday CRM work. See
 * AdministrationPanelProvider's own docblock and User::canAccessPanel()
 * (gated to super_admin).
 */
class UserResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
