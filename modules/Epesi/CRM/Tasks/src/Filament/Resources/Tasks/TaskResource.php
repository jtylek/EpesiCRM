<?php

namespace Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks;

use App\Enums\RecordPermission;
use App\Enums\RecordPriority;
use App\Enums\RecordStatus;
use App\Models\User;
use App\Support\StatusField;
use BackedEnum;
use Carbon\Carbon;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TaskResource extends RecordsetResource
{
    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $recordsetDefaultSort = 'deadline';

    protected static bool $recordsetFavorites = true;

    protected static int $recordsetRecent = 50;

    public static function fields(): array
    {
        return [
            Field::text('title')->required()->fullWidth()->inTable()->tooltipFrom('description'),

            // Live, because `deadline` below reads it to decide whether to show
            // a time alongside the date.
            Field::boolean('timeless')
                ->label('Timeless')
                ->inView(false)
                ->notInTable()
                ->formUsing(fn (Toggle $component): Toggle => $component
                    ->live()
                    // The picker's state format differs (a bare date vs date and
                    // time), so carry the chosen day over instead of losing it.
                    ->afterStateUpdated(function (Get $get, Set $set, ?bool $state): void {
                        $deadline = $get('deadline');

                        if (blank($deadline)) {
                            return;
                        }

                        $set('deadline', $state
                            // Timed → timeless: the day as the user saw it.
                            ? RegionalSetting::toUser(Carbon::parse($deadline, config('app.timezone')))->format('Y-m-d')
                            // Timeless → timed: that day's midnight on the user's clock.
                            : RegionalSetting::fromUser(Carbon::parse($deadline))->format('Y-m-d H:i:s'));
                    })
                    ->default(fn (): bool => request()->boolean('timeless'))),

            StatusField::make(),
            Field::dateTime('deadline')
                ->inTable()
                ->filterable()
                ->formUsing(fn (DateTimePicker $component): DateTimePicker => $component
                    // A saved timeless deadline is a bare calendar day, the format
                    // the date-only picker below reads.
                    ->formatStateUsing(fn (DateTimePicker $component, mixed $state): mixed => $component->getRecord()?->timeless && filled($state)
                        ? Carbon::parse($state)->format('Y-m-d')
                        : $state)
                    // Timeless: a date only, no time selector.
                    ->time(fn (Get $get): bool => ! $get('timeless'))
                    ->displayFormat(fn (Get $get): string => $get('timeless') ? RegionalSetting::dateFormat() : RegionalSetting::dateTimeFormat())
                    // A timeless deadline is a calendar day, not an instant: never shift it.
                    ->timezone(fn (Get $get): ?string => $get('timeless') ? config('app.timezone') : null)
                    ->default(fn (): ?string => request()->query('deadline')))
                ->viewUsing(fn (TextEntry $entry): TextEntry => $entry
                    ->formatStateUsing(fn (Task $record, ?Carbon $state): string => static::formatDeadline($record, $state)))
                ->columnUsing(fn (TextColumn $column): TextColumn => $column
                    ->formatStateUsing(fn (Task $record, ?Carbon $state): string => static::formatDeadline($record, $state))
                    ->color(fn (Task $record): ?string => $record->deadline instanceof Carbon
                        && $record->deadline->isPast()
                        && $record->status !== RecordStatus::Closed ? 'danger' : null)),

            Field::select('priority', RecordPriority::class)
                ->required()
                ->inTable()
                ->default(RecordPriority::Medium)
                ->filterable(),
            Field::select('permission', RecordPermission::class)
                ->required()
                ->default(RecordPermission::Public)
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
                ->default(function (?Task $record): array {
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
            Field::customers('customers', [Contact::class, Company::class])
                ->label('Customers')
                ->inTable(),
            // Any other record the task is about — Epesi's `__RECORDSETS__`
            // Related field, restricted to Companies and Contacts: unrestricted
            // it offers every recordset with a View page, which put Tasks on
            // Mail's and Notes' own "linked from" tabs too.
            Field::related('related', [Company::class, Contact::class])->label('Related')->notInTable(),

            Field::longText('description')->notInTable(),
        ];
    }

    /** A timeless task shows a bare date; anything else shows the time too. */
    protected static function formatDeadline(Task $record, ?Carbon $state): string
    {
        if ($state === null) {
            return '-';
        }

        return $record->timeless
            ? $state->format(RegionalSetting::dateFormat())
            : RegionalSetting::toUser($state)->format(RegionalSetting::dateTimeFormat());
    }
}
