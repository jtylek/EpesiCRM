<?php

namespace Epesi\Modules\CRM\Tasks\Filament\Resources\Tasks;

use App\Enums\RecordPermission;
use App\Enums\RecordPriority;
use App\Enums\RecordStatus;
use App\Support\StatusField;
use BackedEnum;
use Carbon\Carbon;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class TaskResource extends RecordsetResource
{
    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

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
                ->label('Timeless (no specific deadline time)')
                ->inView(false)
                ->notInTable()
                ->formUsing(fn (Toggle $component): Toggle => $component
                    ->live()
                    ->default(fn (): bool => request()->boolean('timeless'))),

            Field::dateTime('deadline')
                ->inTable()
                ->filterable()
                ->formUsing(fn (DateTimePicker $component): DateTimePicker => $component
                    ->displayFormat(fn (Get $get): string => $get('timeless') ? 'Y-m-d' : 'Y-m-d H:i')
                    ->default(fn (): ?string => request()->query('deadline')))
                ->viewUsing(fn (TextEntry $entry): TextEntry => $entry
                    ->formatStateUsing(fn (Task $record, ?Carbon $state): string => static::formatDeadline($record, $state)))
                ->columnUsing(fn (TextColumn $column): TextColumn => $column
                    ->formatStateUsing(fn (Task $record, ?Carbon $state): string => static::formatDeadline($record, $state))
                    ->color(fn (Task $record): ?string => $record->deadline instanceof Carbon
                        && $record->deadline->isPast()
                        && $record->status !== RecordStatus::Closed ? 'danger' : null)),

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

    /** A timeless task shows a bare date; anything else shows the time too. */
    protected static function formatDeadline(Task $record, ?Carbon $state): string
    {
        if ($state === null) {
            return '-';
        }

        return $record->timeless ? $state->format('Y-m-d') : $state->format('Y-m-d H:i');
    }
}
