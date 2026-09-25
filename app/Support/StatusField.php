<?php

namespace App\Support;

use App\Enums\RecordStatus;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Status field Tasks, Phone Calls and Meetings share, and the list filter
 * that goes with it: until the user picks something else, a list shows only
 * what is still to be done. The port of Epesi's "[Not closed]" status filter
 * (CRM_CommonCommon::status_filter()), which leaves out Closed and Canceled
 * and was the default on its task and phone call lists.
 */
class StatusField
{
    public const NOT_CLOSED = 'not_closed';

    public static function make(): Field
    {
        return Field::select('status', RecordStatus::class)
            ->required()
            ->default(RecordStatus::Open)
            ->inTable()
            ->filterable()
            ->filterUsing(fn (SelectFilter $filter): SelectFilter => $filter
                ->options([self::NOT_CLOSED => __('Not closed')] + $filter->getOptions())
                ->default(self::NOT_CLOSED)
                ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                    null, '' => $query,
                    self::NOT_CLOSED => $query->whereNotIn($query->qualifyColumn('status'), RecordStatus::finished()),
                    default => $query->where($query->qualifyColumn('status'), $data['value']),
                }));
    }
}
