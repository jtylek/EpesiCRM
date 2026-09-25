<?php

namespace Epesi\Modules\CRM\Companies\Filament\Resources\Companies;

use App\Enums\RecordPermission;
use App\Support\AddressFields;
use BackedEnum;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\RelationManagers\ContactsRelationManager;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\RelationManagers\MeetingsRelationManager;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\RelationManagers\PhoneCallsRelationManager;
use Epesi\Modules\CRM\Companies\Filament\Resources\Companies\RelationManagers\TasksRelationManager;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;

class CompanyResource extends RecordsetResource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

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
            Field::phone('phone')->inTable(),
            Field::text('fax')->maxLength(64),
            Field::email('email')
                ->inTable()
                ->formUsing(fn (TextInput $component): TextInput => $component->unique(ignoreRecord: true)),
            Field::url('web_address')->label('Web Address')->maxLength(64),
            Field::text('tax_id')->label('Tax ID')->maxLength(64),
            Field::select('permission', RecordPermission::class)
                ->required()
                ->default(RecordPermission::Public)
                ->filterable(),
            Field::longText('memo'),

            ...AddressFields::block(),
        ];
    }

    public static function addons(): array
    {
        return [
            ContactsRelationManager::class,
            TasksRelationManager::class,
            PhoneCallsRelationManager::class,
            MeetingsRelationManager::class,
        ];
    }
}
