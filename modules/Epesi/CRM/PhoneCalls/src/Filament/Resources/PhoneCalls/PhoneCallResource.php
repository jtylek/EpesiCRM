<?php

namespace Epesi\Modules\CRM\PhoneCalls\Filament\Resources\PhoneCalls;

use App\Enums\RecordPermission;
use App\Enums\RecordPriority;
use App\Models\User;
use App\Support\StatusField;
use BackedEnum;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Epesi\Modules\RecordBrowser\Models\RecordLink;
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
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class PhoneCallResource extends RecordsetResource
{
    protected static ?string $model = PhoneCall::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?string $recordsetDefaultSort = 'called_at';

    protected static string $recordsetDefaultSortDirection = 'desc';

    protected static bool $recordsetFavorites = true;

    protected static int $recordsetRecent = 50;

    public static function fields(): array
    {
        return [
            Field::text('subject')->required()->maxLength(64)->fullWidth()->inTable()->tableOrder(1)->tooltipFrom('description'),

            // A call is either against a record in the system or against a
            // name typed in by hand; this toggle swaps which of the two the
            // form asks for, so it is live and the two fields below read it.
            Field::boolean('other_customer')
                ->label('Other Customer (not in system)')
                ->inView(false)
                ->notInTable()
                ->formUsing(fn (Toggle $component): Toggle => $component->live()),

            Field::text('other_customer_name')
                ->label('Other Customer Name')
                ->maxLength(64)
                ->notInTable()
                ->formUsing(fn (TextInput $component): TextInput => $component
                    ->required(fn (Get $get): bool => (bool) $get('other_customer'))
                    ->visible(fn (Get $get): bool => (bool) $get('other_customer')))
                ->viewUsing(fn (TextEntry $entry): TextEntry => $entry
                    ->label('Customer')
                    ->visible(fn (PhoneCall $record): bool => $record->other_customer)),

            // Epesi's Company-or-Contact "Customer" field type: one
            // typeahead over both. Autofills Phone Number from whichever is
            // picked, and warns when it has none on file.
            Field::customer('customer', [Contact::class, Company::class])
                ->label('Customer')
                ->inTable()
                ->tableOrder(2)
                // One column for both kinds of call: the typed name when the
                // customer isn't in the system (it has no link or icon then).
                ->columnUsing(fn (TextColumn $column): TextColumn => $column
                    ->state(fn (PhoneCall $record): ?string => $record->other_customer
                        ? $record->other_customer_name
                        : ($record->customer ? LinkedRecords::title($record->customer) : null)))
                ->suggestPhoneNumbers('phone_number')
                ->formUsing(fn (Select $component): Select => $component
                    ->required(fn (Get $get): bool => ! (bool) $get('other_customer'))
                    ->visible(fn (Get $get): bool => ! $get('other_customer')))
                ->viewUsing(fn (TextEntry $entry): TextEntry => $entry
                    ->visible(fn (PhoneCall $record): bool => ! $record->other_customer)),

            // Two fields sharing one column: a free-text number for "Other
            // Customer" (typed, since there's no record to have numbers),
            // and Epesi's chained phone-number select for an in-system
            // Customer — its options are whichever contact or company is
            // picked above, so it stays live and rebuilds when that
            // changes. Only one is ever visible; the hidden one still
            // saves normally (Filament collapses a hidden field's own grid
            // space rather than leaving a gap), and both read/write the
            // same `phone_number` value, so switching Customer re-picks
            // from the datalist Field::customer()'s suggestPhoneNumbers()
            // already put there.
            Field::phone('phone_number')->label('Phone Number')
                ->notInTable()
                ->formUsing(fn (TextInput $component): TextInput => $component
                    ->visible(fn (Get $get): bool => (bool) $get('other_customer'))
                    ->required(fn (Get $get): bool => (bool) $get('other_customer'))),

            Field::phone('phone_number')->label('Phone Number')
                ->inView(false)
                ->inTable()
                ->tableOrder(3)
                ->formUsing(fn (TextInput $component, Field $field): Select => Select::make($field->name)
                    ->label($field->getLabel())
                    ->native(false)
                    ->live()
                    ->options(fn (Get $get): array => static::customerPhoneOptions($get('customer')))
                    ->visible(fn (Get $get): bool => ! $get('other_customer'))
                    ->required(fn (Get $get): bool => ! $get('other_customer'))),

            Field::dateTime('called_at')
                ->label('Date and Time')
                ->tableOrder(4)
                ->required()
                ->inTable()
                ->filterable()
                ->formUsing(fn (DateTimePicker $component): DateTimePicker => $component
                    ->default(fn (): string => now()->parse(request()->query('called_at') ?: now())->roundMinutes(5)->toDateTimeString())),

            // Scoped to the acting user's own company's staff — Epesi's
            // employees_crits().
            Field::relations('employees', Contact::class)
                ->tableOrder(6)
                ->inTable()
                ->filterable()
                ->filterUsing(fn (SelectFilter $filter): SelectFilter => $filter
                    ->multiple()
                    ->default(function (): array {
                        $user = Auth::user();
                        $contactId = $user instanceof User ? $user->contact?->getKey() : null;

                        return $contactId === null ? [] : [(string) $contactId];
                    }))
                ->default(function (?PhoneCall $record): array {
                    if ($record?->exists) {
                        return [];
                    }

                    $user = Auth::user();
                    $contactId = $user instanceof User ? $user->contact?->getKey() : null;

                    return $contactId === null ? [] : [(string) $contactId];
                })
                ->required()
                ->crits(fn (Builder $query): Builder => $query->ofCompany(Auth::user()?->companyId())),
            // Any other record the call is about — Epesi's `__RECORDSETS__`
            // Related field, restricted to Companies and Contacts: unrestricted
            // it offers every recordset with a View page, which put Phone
            // Calls on Mail's and Notes' own "linked from" tabs too.
            Field::related('related', [Company::class, Contact::class])->label('Related'),

            StatusField::make()->tableOrder(5),
            Field::select('priority', RecordPriority::class)
                ->required()
                ->tableOrder(7)
                ->inTable()
                ->default(RecordPriority::Medium)
                ->filterable(),
            Field::select('permission', RecordPermission::class)
                ->required()
                ->default(RecordPermission::Public)
                ->filterable()
                ->notInTable(),

            Field::longText('description')->notInTable(),
        ];
    }

    /**
     * The customer token's every phone number, kind-labelled — the options
     * for the phone-number select, Epesi's chained select re-populated off
     * whichever Contact or Company is picked.
     *
     * @return array<string, string>
     */
    protected static function customerPhoneOptions(?string $token): array
    {
        [$alias, $id] = RecordLink::parseToken($token) ?? [null, null];
        $class = $alias !== null ? Relation::getMorphedModel($alias) : null;
        $record = $class && filled($id) ? $class::query()->find($id) : null;
        $options = [];

        foreach ($record?->phones ?? [] as $phone) {
            if (filled($phone->value)) {
                $options[$phone->value] = trim($phone->kindLabel().': '.$phone->value);
            }
        }

        return $options;
    }
}
