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
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
            // Business, home and any other: the list shows the first one's city.
            // Filterable by City/Country only — Has/Kind stay off the panel.
            Field::collection('addresses', Address::class)->inTable()->filterable(itemFieldsOnly: true),
            // The website, LinkedIn and the like, each linking to its page.
            Field::collection('online_accounts', OnlineAccount::class)->label('Online accounts'),

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
