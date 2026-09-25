<?php

namespace Epesi\Modules\CRM\Meetings\Filament\Resources\Meetings;

use App\Enums\RecordPermission;
use App\Enums\RecordPriority;
use App\Support\StatusField;
use BackedEnum;
use Carbon\Carbon;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class MeetingResource extends RecordsetResource
{
    protected static ?string $model = Meeting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $recordsetDefaultSort = 'date';

    protected static bool $recordsetFavorites = true;

    protected static int $recordsetRecent = 50;

    public static function fields(): array
    {
        return [
            Field::text('title')->required()->fullWidth()->inTable()->tooltipFrom('description'),

            // Stored and edited as two columns, but read as one: the view shows
            // a single "Date and Time" built from the model's `starts_at`
            // accessor. That accessor is deliberately not its own Field —
            // a Field means a real column (recordset:check enforces it), and
            // `starts_at` has none.
            Field::date('date')
                ->required()
                ->inTable()
                ->filterable()
                ->formUsing(fn (DatePicker $component): DatePicker => $component
                    ->default(fn (): string => request()->query('date') ?: now()->toDateString()))
                ->viewUsing(fn (TextEntry $entry): TextEntry => $entry
                    ->label('Date and Time')
                    ->state(fn (Meeting $record): ?Carbon => $record->starts_at)
                    ->dateTime()),
            Field::time('time')
                ->required()
                ->inTable()
                ->inView(false)
                ->formUsing(fn (TimePicker $component): TimePicker => $component
                    ->default(fn (): ?string => request()->query('time')))
                ->columnUsing(fn (TextColumn $column): TextColumn => $column->time('H:i')),

            Field::integer('duration_minutes')
                ->label('Duration (minutes)')
                ->formUsing(fn (TextInput $component): TextInput => $component->minValue(0)),

            StatusField::make(),
            Field::select('priority', RecordPriority::class)
                ->required()
                ->default(RecordPriority::Medium)
                ->filterable(),
            Field::select('permission', RecordPermission::class)
                ->required()
                ->default(RecordPermission::Public)
                ->filterable(),

            // Scoped to the acting user's own company's staff — Epesi's
            // employees_crits().
            Field::relations('employees', Contact::class)
                ->required()
                ->crits(fn (Builder $query): Builder => $query->ofCompany(auth()->user()?->companyId())),
            Field::relations('customers', Contact::class)->label('Contacts'),
            Field::relations('customerCompanies', Company::class)->label('Companies'),

            Field::longText('description'),
        ];
    }
}
