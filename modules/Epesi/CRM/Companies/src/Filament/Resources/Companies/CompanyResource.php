<?php

namespace Epesi\Modules\CRM\Companies\Filament\Resources\Companies;

use App\Enums\RecordPermission;
use BackedEnum;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\RecordBrowser\Models\Address;
use Epesi\Modules\RecordBrowser\Models\EmailAddress;
use Epesi\Modules\RecordBrowser\Models\OnlineAccount;
use Epesi\Modules\RecordBrowser\Models\PhoneNumber;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use UnitEnum;

class CompanyResource extends RecordsetResource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?string $recordTitleAttribute = 'company_name';

    protected static ?string $recordsetDefaultSort = 'company_name';

    protected static bool $recordsetFavorites = true;

    protected static int $recordsetRecent = 50;

    public static function fields(): array
    {
        return [
            // Cut short in the list, with the full name on hover — same as the
            // Company column on Contacts, so a long legal name can't push the
            // list past the page.
            Field::text('company_name')->required()->maxLength(128)->fullWidth()->inTable()
                ->columnUsing(fn (TextColumn $column): TextColumn => $column
                    ->limit(25, '…')
                    ->tooltip(fn (TextColumn $column, ?string $state): ?string => mb_strwidth((string) $state) > $column->getCharacterLimit() ? $state : null)),
            Field::text('short_name')->maxLength(64),
            Field::commonData('groups', 'Companies_Groups', multiple: true)->label('Group')->inTable()->filterable(),
            Field::collection('emails', EmailAddress::class)
                ->label('E-mail addresses')
                ->inTable(),
            Field::text('tax_id')->label('Tax ID')->maxLength(64),
            Field::select('permission', RecordPermission::class)
                ->required()
                ->default(RecordPermission::Public),
            Field::longText('memo'),

            // The switchboard, fax and any other: the list shows the first.
            Field::collection('phones', PhoneNumber::class)->label('Phone numbers')->inTable(),
            // Offices, billing, shipping: the list shows the first one's city.
            // Filterable by City/Country only — Has/Kind stay off the panel.
            Field::collection('addresses', Address::class)->inTable()->filterable(itemFieldsOnly: true),
            // The website, LinkedIn and the like, each linking to its page.
            Field::collection('online_accounts', OnlineAccount::class)->label('Online accounts'),
        ];
    }
}
