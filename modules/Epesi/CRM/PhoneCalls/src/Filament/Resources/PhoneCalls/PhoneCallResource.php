<?php

namespace Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls;

use App\Enums\RecordPermission;
use App\Enums\RecordPriority;
use App\Support\StatusField;
use BackedEnum;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class PhoneCallResource extends RecordsetResource
{
    protected static ?string $model = PhoneCall::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?string $recordsetDefaultSort = 'called_at';

    protected static string $recordsetDefaultSortDirection = 'desc';

    protected static bool $recordsetFavorites = true;

    protected static int $recordsetRecent = 50;

    public static function fields(): array
    {
        return [
            Field::text('subject')->required()->maxLength(64)->fullWidth()->inTable()->tooltipFrom('description'),

            // A call is either against a record in the system or against a
            // name typed in by hand; this toggle swaps which of the two the
            // form asks for, so it is live and the three fields below read it.
            Field::boolean('other_customer')
                ->label('Other Customer (not in system)')
                ->inView(false)
                ->notInTable()
                ->formUsing(fn (Toggle $component): Toggle => $component->live()),

            Field::text('other_customer_name')
                ->label('Other Customer Name')
                ->maxLength(64)
                ->formUsing(fn (TextInput $component): TextInput => $component
                    ->required(fn (Get $get): bool => (bool) $get('other_customer'))
                    ->visible(fn (Get $get): bool => (bool) $get('other_customer')))
                ->viewUsing(fn (TextEntry $entry): TextEntry => $entry
                    ->label('Customer')
                    ->visible(fn (PhoneCall $record): bool => $record->other_customer)),

            Field::relation('contact_id', Contact::class)
                ->label('Contact')
                ->inTable()
                ->filterable()
                ->formUsing(fn (Select $component): Select => $component
                    ->searchable(['first_name', 'last_name'])
                    ->visible(fn (Get $get): bool => ! $get('other_customer')))
                ->viewUsing(fn (TextEntry $entry): TextEntry => $entry
                    ->visible(fn (PhoneCall $record): bool => ! $record->other_customer))
                ->columnUsing(fn (TextColumn $column): TextColumn => $column
                    ->searchable(['contacts.first_name', 'contacts.last_name'])),

            Field::relation('company_id', Company::class)
                ->label('Company')
                ->filterable()
                ->formUsing(fn (Select $component): Select => $component
                    ->visible(fn (Get $get): bool => ! $get('other_customer')))
                ->viewUsing(fn (TextEntry $entry): TextEntry => $entry
                    ->visible(fn (PhoneCall $record): bool => ! $record->other_customer))
                ->columnUsing(fn (TextColumn $column): TextColumn => $column->searchable()),

            Field::phone('phone_number')->label('Phone Number'),

            Field::dateTime('called_at')
                ->label('Date and Time')
                ->required()
                ->inTable()
                ->filterable()
                ->formUsing(fn (DateTimePicker $component): DateTimePicker => $component
                    ->default(fn (): string => request()->query('called_at') ?: now()->toDateTimeString())),

            // Scoped to the acting user's own company's staff — Epesi's
            // employees_crits().
            Field::relations('employees', Contact::class)
                ->required()
                ->crits(fn (Builder $query): Builder => $query->ofCompany(auth()->user()?->companyId())),

            StatusField::make(),
            Field::select('priority', RecordPriority::class)
                ->required()
                ->default(RecordPriority::Medium)
                ->filterable(),
            Field::select('permission', RecordPermission::class)
                ->required()
                ->default(RecordPermission::Public)
                ->filterable(),

            Field::longText('description'),
        ];
    }
}
