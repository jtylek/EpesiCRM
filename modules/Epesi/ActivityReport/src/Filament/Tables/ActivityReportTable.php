<?php

namespace Epesi\Modules\ActivityReport\Filament\Tables;

use Epesi\Modules\ActivityReport\ActivityReport;
use Epesi\Modules\ActivityReport\ActivityReportRow;
use Epesi\Modules\RecordBrowser\History\HistoryChanges;
use Epesi\Modules\RecordBrowser\Recordset\LinkableRecordsets;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Spatie\Activitylog\Models\Activity;

/**
 * The report's columns and its own filter (record types, kinds of action,
 * dates), shared by the User Activity Report page and the Journal tab of a
 * contact. Who is reported on is not decided here: each sets its own query.
 */
class ActivityReportTable
{
    public static function configure(Table $table, bool $withUser): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->columns(array_values(array_filter([
                TextColumn::make('created_at')
                    ->label('When')
                    ->state(fn (Activity $record): ?string => RegionalSetting::display($record->created_at))
                    ->sortable()
                    ->width('1%'),
                $withUser
                    ? TextColumn::make('causer')
                        ->label('User')
                        ->state(fn (Activity $record): string => HistoryChanges::causerName($record))
                        ->width('1%')
                    : null,
                TextColumn::make('type')
                    ->label('Type')
                    ->state(fn (Activity $record): string => LinkableRecordsets::label(ActivityReportRow::for($record)['alias']))
                    ->badge()
                    ->width('1%'),
                TextColumn::make('record')
                    ->label('Record')
                    ->state(function (Activity $record): string {
                        $row = ActivityReportRow::for($record);

                        return match ($row['state']) {
                            'restricted' => __('Access restricted'),
                            'removed' => __('Record #:id (removed)', ['id' => $record->subject_id]),
                            default => (string) $row['title'],
                        };
                    })
                    ->limit(60)
                    ->tooltip(fn (TextColumn $column): ?string => mb_strlen((string) $column->getState()) > $column->getCharacterLimit() ? (string) $column->getState() : null)
                    ->color(fn (Activity $record): ?string => ActivityReportRow::for($record)['state'] === 'ok' ? null : 'gray')
                    ->url(fn (Activity $record): ?string => ActivityReportRow::for($record)['url']),
                TextColumn::make('action')
                    ->label('Actions taken')
                    ->state(fn (Activity $record): string => ActivityReportRow::action($record))
                    ->width('1%'),
                TextColumn::make('changes')
                    ->label('Changes')
                    ->state(fn (Activity $record): string|HtmlString|null => self::changes($record, $table))
                    ->wrap()
                    ->placeholder('-'),
            ])))
            ->filters([self::filter()], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(['default' => 1, 'lg' => 4])
            ->persistFiltersInSession()
            ->recordActions([])
            ->toolbarActions([]);
    }

    /**
     * What an edit changed, as History tells it; nothing for a record the
     * viewer may not see.
     */
    private static function changes(Activity $record, Table $table): string|HtmlString|null
    {
        if ($record->event !== 'updated' || $record->subject_type === ActivityReport::attachmentAlias()) {
            return null;
        }

        if (ActivityReportRow::for($record)['state'] !== 'ok') {
            return null;
        }

        $summary = HistoryChanges::summary($record, HistoryChanges::fieldsForSubject($record->subject_type), $table);

        return filled((string) $summary) ? $summary : null;
    }

    /** The filter grid is four columns wide; the report's own filter takes $span of them (the User filter, where there is one, the rest). */
    public static function filter(int $span = 4): Filter
    {
        // The defaults sit on the fields: a schema filter's own default() does
        // not reach them, and an empty kinds list reports nothing.
        $defaults = ActivityReport::defaultFilter();

        return Filter::make('report')
            ->columnSpan(['default' => 1, 'lg' => $span])
            ->schema([
                Select::make('recordsets')
                    ->label('Record Type')
                    ->options(fn (): array => LinkableRecordsets::options())
                    ->multiple()
                    ->placeholder(__('All')),
                DatePicker::make('from')->label('Start Date')->default($defaults['from']),
                DatePicker::make('until')->label('End Date')->default($defaults['until']),
                // Across the whole width, so a label stays on one line.
                CheckboxList::make('kinds')
                    ->label('Actions taken')
                    ->options([
                        'new' => __('New record'),
                        'edit' => __('Record edit'),
                        'delete_restore' => __('Record Delete/restore'),
                        ActivityReport::FILES => __('Notes and files'),
                    ])
                    ->default($defaults['kinds'])
                    ->columns(['default' => 1, 'sm' => 4])
                    ->columnSpanFull(),
            ])
            ->columns(['default' => 1, 'sm' => 3])
            ->query(fn (Builder $query, array $data): Builder => ActivityReport::apply($query, $data));
    }
}
