<?php

namespace Epesi\Modules\Mail\Filament\Resources\Mails;

use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Columns and filters shared by the E-mails list and every record's E-mails
 * tab, so the two read the same.
 */
class MailTable
{
    /**
     * @return array<int, mixed>
     */
    public static function columns(bool $withLinks = true): array
    {
        return array_values(array_filter([
            IconColumn::make('direction')
                ->label('')
                ->icon(fn (?string $state): Heroicon => $state === Mail::OUTGOING ? Heroicon::OutlinedArrowUpRight : Heroicon::OutlinedArrowDownLeft)
                ->color(fn (?string $state): string => $state === Mail::OUTGOING ? 'success' : 'info')
                ->tooltip(fn (?string $state): string => $state === Mail::OUTGOING ? __('Sent') : __('Received'))
                ->size(IconSize::Small)
                ->grow(false)
                ->width('1%'),
            TextColumn::make('date')
                ->formatStateUsing(fn (Mail $record): ?string => $record->date?->format('Y-m-d'))
                ->description(fn (Mail $record): ?string => $record->date?->format('H:i'))
                ->grow(false)
                ->sortable(),
            TextColumn::make('subject')
                ->weight('medium')
                ->description(fn (Mail $record): string => $record->snippet(90))
                ->placeholder(__('(no subject)'))
                ->wrap()
                ->grow()
                ->searchable(['subject', 'from', 'to', 'body_text']),
            TextColumn::make('from')
                ->limit(40)
                ->tooltip(fn (Mail $record): ?string => $record->from)
                ->grow(false),
            TextColumn::make('attachments_count')
                ->label('')
                ->counts('attachments')
                ->icon(fn (int $state): ?Heroicon => $state > 0 ? Heroicon::OutlinedPaperClip : null)
                ->formatStateUsing(fn (int $state): string => $state > 0 ? (string) $state : '')
                ->width('1%'),
            $withLinks ? LinkedRecords::badges(TextColumn::make('linked'), fn (Mail $record): iterable => $record->links->pluck('linkable'))
                ->label('Linked to')
                ->listWithLineBreaks()
                ->grow(false)
                ->toggleable() : null,
        ]));
    }

    /**
     * @return array<int, mixed>
     */
    public static function filters(): array
    {
        return [
            SelectFilter::make('direction')
                ->options([Mail::INCOMING => __('Received'), Mail::OUTGOING => 'Sent']),
            TernaryFilter::make('has_attachments')
                ->label('Has attachments')
                ->queries(
                    true: fn (Builder $q): Builder => $q->has('attachments'),
                    false: fn (Builder $q): Builder => $q->doesntHave('attachments'),
                ),
            Filter::make('date')
                ->schema([
                    DatePicker::make('from')->label('Date from'),
                    DatePicker::make('until')->label('Date until'),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $q, $v): Builder => $q->whereDate('date', '>=', $v))
                    ->when($data['until'] ?? null, fn (Builder $q, $v): Builder => $q->whereDate('date', '<=', $v))),
        ];
    }
}
