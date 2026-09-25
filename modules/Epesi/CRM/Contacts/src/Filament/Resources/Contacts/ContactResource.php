<?php

namespace Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts;

use App\Enums\RecordPermission;
use App\Models\User;
use App\Support\AddressFields;
use BackedEnum;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\RelationManagers\MeetingsRelationManager;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\RelationManagers\PhoneCallsRelationManager;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\RelationManagers\TasksRelationManager;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ContactResource extends RecordsetResource
{
    protected static ?string $model = Contact::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    /**
     * The real column Filament searches and orders option lists by; what a
     * contact is *called* is getRecordTitle() below.
     */
    protected static ?string $recordTitleAttribute = 'last_name';

    protected static ?string $recordsetDefaultSort = 'last_name';

    protected static bool $recordsetFavorites = true;

    protected static int $recordsetRecent = 50;

    /**
     * A contact reads as "John Smith" everywhere it is named — breadcrumbs,
     * global search, select options, and the linked badge the RecordBrowser
     * engine renders for any relation field pointing here. `full_name` is an
     * accessor with no SQL equivalent, so it can't be the recordTitleAttribute
     * above; overriding the title method is how the two are kept apart.
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof Contact ? $record->full_name : parent::getRecordTitle($record);
    }

    public static function fields(): array
    {
        return [
            // First name breaks a tie between two people of the same surname.
            // Spelled out as a query: Filament applies a sortable([...]) list
            // last column first.
            Field::text('last_name')->required()->maxLength(64)->inTable()
                ->columnUsing(fn (TextColumn $column): TextColumn => $column->sortable(query: fn (Builder $query, string $direction): Builder => $query
                    ->orderBy('last_name', $direction)
                    ->orderBy('first_name', $direction))),
            Field::text('first_name')->required()->maxLength(64)->inTable(),
            Field::text('title')->maxLength(64),
            Field::commonData('groups', 'Contacts_Groups', multiple: true)->label('Group')->filterable(),

            // Searchable and sortable through the joined column rather than the
            // engine's derived record-title state, which has no SQL equivalent.
            // Cut short, with the full name on hover: one long legal name
            // ("... Spółka Jawna") otherwise pushes the list past the page.
            Field::relation('company_id', Company::class)
                ->label('Company')
                ->titleAttribute('company_name')
                ->inTable()
                ->filterable()
                ->columnUsing(fn (): TextColumn => LinkedRecords::style(
                    TextColumn::make('company.company_name')
                        ->label('Company')
                        ->limit(25, '…')
                        ->tooltip(fn (TextColumn $column, ?string $state): ?string => mb_strwidth((string) $state) > $column->getCharacterLimit() ? $state : null)
                        ->searchable()
                        ->sortable(),
                    fn (Contact $record): ?string => $record->company ? LinkedRecords::url($record->company) : null,
                )),
            Field::relations('relatedCompanies', Company::class)
                ->label('Related Companies')
                ->titleAttribute('company_name'),

            Field::phone('work_phone')->inTable(),
            Field::phone('mobile_phone')->inTable(),
            Field::text('fax')->maxLength(64)->inView(false),
            Field::email('email')
                ->inTable()
                ->formUsing(fn (TextInput $component): TextInput => $component->unique(ignoreRecord: true)),
            Field::url('web_address')->label('Web Address')->maxLength(64),
            Field::select('permission', RecordPermission::class)
                ->required()
                ->default(RecordPermission::Public)
                ->filterable(),
            Field::longText('memo'),

            ...AddressFields::block(collapsible: true),

            // Form-only: the View page shows these nowhere, and the list would
            // not benefit from a second set of address columns.
            Field::phone('home_phone')->label('Home Phone')->inView(false)->notInTable()
                ->section('Home Address', collapsible: true, collapsed: true),
            ...array_map(
                fn (Field $field): Field => $field->inView(false)->notInTable(),
                AddressFields::block('home_', 'Home Address', collapsible: true, collapsed: true),
            ),

            // The linked login. Read-only detail lives on the View page's own
            // Login tab (ContactLoginEntries), not in the infolist.
            Field::relation('user_id', User::class)
                ->label('Linked User')
                ->titleAttribute('email')
                ->inView(false)
                ->section('Login', description: 'Links this contact to a portal login. Password changes and role assignment happen via that user\'s account, not here — see the "Reset Password" action and the Shield Roles resource.')
                ->formUsing(fn (Select $component): Select => $component
                    ->relationship(
                        'user',
                        'email',
                        modifyQueryUsing: fn (Builder $query, ?Contact $record): Builder => $query
                            ->whereDoesntHave('contact', fn (Builder $query) => $query
                                ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))),
                    )
                    ->unique(ignoreRecord: true))
                ->columnUsing(fn (): TextColumn => TextColumn::make('user.email')
                    ->label('Login')
                    ->placeholder(__('-'))
                    ->toggleable(isToggledHiddenByDefault: true)),
        ];
    }

    public static function addons(): array
    {
        return [
            TasksRelationManager::class,
            PhoneCallsRelationManager::class,
            MeetingsRelationManager::class,
        ];
    }
}
