<?php

namespace Epesi\Modules\CRM\Meetings\Filament\Resources\Meetings;

use App\Enums\RecordPermission;
use App\Enums\RecordPriority;
use App\Models\User;
use App\Support\StatusField;
use BackedEnum;
use Carbon\Carbon;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class MeetingResource extends RecordsetResource
{
    protected static ?string $model = Meeting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $recordsetDefaultSort = 'date';

    protected static bool $recordsetFavorites = true;

    protected static int $recordsetRecent = 50;

    public static function fields(): array
    {
        return [
            Field::text('title')->required()->fullWidth()->inTable()->tooltipFrom('description'),

            // Keep the real columns for imports, history and calendar queries;
            // the form combines them and splits the submitted value on save.
            Field::date('date')
                ->label('Date and Time')
                ->required()
                ->inTable()
                ->filterable()
                ->formUsing(fn (DatePicker $component): DateTimePicker => DateTimePicker::make($component->getName())
                    ->label($component->getLabel())
                    ->required()
                    ->seconds(false)
                    ->default(fn (): string => Carbon::parse(
                        (request()->query('date') ?: now()->toDateString()).' '.(request()->query('time') ?: now()->format('H:i')),
                    )->roundMinutes(5)->toDateTimeString())
                    ->formatStateUsing(fn (?Meeting $record, ?string $state): ?string => $record?->starts_at?->toDateTimeString() ?? $state)
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Carbon::parse($state)->toDateString() : null))
                ->columnUsing(fn (TextColumn $column): TextColumn => $column
                    ->state(fn (Meeting $record): ?Carbon => $record->starts_at)
                    ->dateTime()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('date', $direction)->orderBy('time', $direction)))
                ->viewUsing(fn (TextEntry $entry): TextEntry => $entry
                    ->label('Date and Time')
                    ->state(fn (Meeting $record): ?Carbon => $record->starts_at)
                    ->dateTime()),
            Field::time('time')
                ->required()
                ->inView(false)
                ->formUsing(fn (): Hidden => Hidden::make('time')
                    ->dehydrateStateUsing(fn (Get $get): ?string => filled($get('date')) ? Carbon::parse($get('date'))->format('H:i:s') : null)),

            Field::select('duration_minutes', [
                15 => '15 min',
                30 => '30 min',
                60 => '1 hour',
                120 => '2 hours',
                240 => '4 hours',
                480 => '8 hours',
            ])
                ->label('Duration'),

            StatusField::make(),
            Field::select('priority', RecordPriority::class)
                ->required()
                ->default(RecordPriority::Medium)
                ->filterable(),
            Field::select('permission', RecordPermission::class)
                ->required()
                ->default(RecordPermission::Public)
                ->filterable()
                ->notInTable(),

            // Scoped to the acting user's own company's staff — Epesi's
            // employees_crits().
            Field::relations('employees', Contact::class)
                ->inTable()
                ->filterable()
                ->filterUsing(fn (SelectFilter $filter): SelectFilter => $filter
                    ->multiple()
                    ->default(function (): array {
                        $user = Auth::user();
                        $contactId = $user instanceof User ? $user->contact?->getKey() : null;

                        return $contactId === null ? [] : [(string) $contactId];
                    }))
                ->default(function (?Meeting $record): array {
                    if ($record?->exists) {
                        return [];
                    }

                    $user = Auth::user();
                    $contactId = $user instanceof User ? $user->contact?->getKey() : null;

                    return $contactId === null ? [] : [(string) $contactId];
                })
                ->required()
                ->crits(function (Builder $query): Builder {
                    $user = Auth::user();

                    return $query->ofCompany($user instanceof User ? $user->companyId() : null);
                }),
            Field::customers('customers', [Contact::class, Company::class])->label('Customers')->inTable(),
            // Any other record the meeting is about — Epesi's `__RECORDSETS__`
            // Related field, restricted to Companies and Contacts: unrestricted
            // it offers every recordset with a View page, which put Meetings
            // on Mail's and Notes' own "linked from" tabs too.
            Field::related('related', [Company::class, Contact::class])->label('Related'),

            Field::longText('description')->notInTable(),
        ];
    }
}
