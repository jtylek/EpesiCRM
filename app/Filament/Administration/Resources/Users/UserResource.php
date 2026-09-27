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
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\ContactResource;
use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Epesi\Modules\RecordBrowser\Filament\RelationManagers\HistoryRelationManager;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

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

    /**
     * A user reads as its contact's name ("Ann Kowalska"), not the login's
     * own: the View and Edit breadcrumbs, as the Users list's Contact column.
     * `displayName()` isn't a column, so it can't be the recordTitleAttribute
     * above (ContactResource does the same for `full_name`).
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof User ? $record->displayName() : parent::getRecordTitle($record);
    }

    /**
     * A user's contact as everywhere else a related record is shown: a badge
     * that links to it. Contacts live in the main panel, not this one, so the
     * link is built for that panel. An account with no contact shows its own
     * name, as plain text (User::displayName()).
     *
     * @template T of TextColumn|TextEntry
     *
     * @param  T  $component
     * @return T
     */
    public static function contactBadge(TextColumn|TextEntry $component): TextColumn|TextEntry
    {
        return LinkedRecords::style(
            $component->state(fn (User $record): string => $record->displayName()),
            fn (User $record): ?string => $record->contact
                ? ContactResource::getUrl('view', ['record' => $record->contact], panel: 'main')
                : null,
        )->badge(fn (User $record): bool => $record->contact !== null);
    }

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

    /**
     * The History addon every record has: edits, password changes, roles.
     * A user is not a recordset, so its logged columns are described here
     * for their labels and values (the same hook as AttachmentResource's).
     * "roles" and "contact" aren't columns: UserActivity logs them.
     */
    public static function getRelations(): array
    {
        return [HistoryRelationManager::class];
    }

    /**
     * @return array<int, Field>
     */
    public static function historyFields(): array
    {
        return [
            Field::text('name'),
            Field::text('email'),
            Field::boolean('active'),
            Field::text('roles'),
            Field::text('contact'),
        ];
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
