<?php

namespace Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts;

use App\Enums\RecordPermission;
use App\Models\User;
use BackedEnum;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Epesi\Modules\RecordBrowser\Models\Address;
use Epesi\Modules\RecordBrowser\Models\EmailAddress;
use Epesi\Modules\RecordBrowser\Models\OnlineAccount;
use Epesi\Modules\RecordBrowser\Models\PhoneNumber;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\FieldType;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use UnitEnum;

class ContactResource extends RecordsetResource
{
    protected static ?string $model = Contact::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

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

    /**
     * Create only, as in legacy Epesi: tick "Create company", name it, and the
     * new company is made with the contact in one step (CreateContact) and
     * takes the contact's addresses. Neither input is saved on the contact.
     */
    protected static function extendForm(Schema $schema): Schema
    {
        $components = $schema->getComponents();

        $createCompany = Grid::make(1)
            ->columnSpanFull()
            ->visible(fn (string $operation): bool => $operation === 'create' && Gate::allows('create', Company::class))
            ->schema([
                Checkbox::make('create_company')
                    ->label('Create company')
                    ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: __('Also creates a new company with this contact\'s addresses and sets it as the contact\'s company.'))
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(fn (Set $set, bool $state) => $state ? $set('company_id', null) : null),
                TextInput::make('new_company_name')
                    ->label('Company Name')
                    ->required()
                    ->maxLength(128)
                    ->visible(fn (Get $get): bool => (bool) $get('create_company'))
                    ->dehydrated(false),
            ]);

        array_splice($components, 1, 0, [$createCompany]);

        return $schema->components($components);
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
            Field::text('title')->maxLength(64)->notInTable(),
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
                // Taken by the company "Create company" makes (extendForm()).
                ->formUsing(fn (Select $component): Select => $component
                    ->disabled(fn (Get $get): bool => (bool) $get('create_company'))
                    ->dehydrated(fn (Get $get): bool => ! $get('create_company')))
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
                ->titleAttribute('company_name')
                ->notInTable(),
            Field::select('permission', RecordPermission::class)
                ->required()
                ->default(RecordPermission::Public)
                ->notInTable(),
            // The roles of the contact's login, View only. Like the Autonumber it
            // borrows its type from, it has no column of its own: it is read off
            // the linked user, and nothing is posted or saved for it.
            Field::make('roles', FieldType::Autonumber)
                ->inForm(false)
                ->notInTable()
                ->searchable(false)
                ->viewUsing(fn (TextEntry $entry): TextEntry => $entry
                    ->state(fn (Contact $record): array => $record->user?->getRoleNames()->map(fn (string $role): string => Str::headline($role))->all() ?? [])
                    ->badge()
                    ->color('warning')),
            Field::longText('memo')->notInTable(),

            // Short fields first, then Memo, then the Collections below —
            // RecordsetResource::flowIntoColumns()'s convention, so the
            // engine's own two-column flow reads the way this list is
            // written. An administrator can still drag any of these to
            // interleave them differently for their own installation.
            Field::collection('emails', EmailAddress::class)
                ->label('E-mail addresses')
                ->inTable(),
            // Work, mobile, home, fax and any other, each with the apps that
            // reach it. The list keeps a column for Work and one for Mobile.
            Field::collection('phones', PhoneNumber::class)
                ->label('Phone numbers')
                ->columnsForKinds(['work' => 'Work Phone', 'mobile' => 'Mobile Phone'])
                ->inTable(),
            // The website, LinkedIn and the like, each linking to its page.
            Field::collection('online_accounts', OnlineAccount::class)->label('Online accounts'),
            // Business, home and any other: the list shows the first one's city.
            // Filterable by City/Country only — Has/Kind stay off the panel.
            Field::collection('addresses', Address::class)->inTable()->filterable(itemFieldsOnly: true),

            // The linked login. Not shown in the Main panel at all — view,
            // form or table — for any role: a login is entirely an
            // Administration concern, managed in Administration → Users,
            // where one is made from a contact.
            Field::relation('user_id', User::class)
                ->label('Linked User')
                ->titleAttribute('email')
                ->inView(false)
                ->inForm(false)
                ->notInTable(),
        ];
    }
}
