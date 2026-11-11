<?php

namespace Epesi\Modules\Mail\Filament\Resources\Mails;

use Closure;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\ContactResource;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ClosureValidationRule;
use Throwable;

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
            TextColumn::make('subject')
                ->label(__('E-mail'))
                ->state(fn (Mail $record): HtmlString => static::summary($record))
                ->html()
                ->description(fn (Mail $record): string => $record->snippet(560))
                ->wrap()
                ->grow()
                ->searchable(['subject', 'from', 'to', 'body_text']),
            TextColumn::make('attachments_count')
                ->label('')
                ->counts('attachments')
                ->icon(fn (int $state): ?Heroicon => $state > 0 ? Heroicon::OutlinedPaperClip : null)
                ->formatStateUsing(fn (int $state): string => $state > 0 ? (string) $state : '')
                ->width('1%'),
            $withLinks ? LinkedRecords::badges(TextColumn::make('linked'), fn (Mail $record): iterable => $record->links->pluck('linkable'))
                ->label('Linked to')
                ->listWithLineBreaks()
                ->limitList(3)
                ->grow(false) : null,
        ]));
    }

    protected static function summary(Mail $record): HtmlString
    {
        $label = static fn (string $value): string => '<span class="font-medium">'.e($value).':</span>';

        return new HtmlString(implode('', [
            '<div>'.$label(__('Date')).' '.e((string) RegionalSetting::display($record->date)).' &nbsp; '.$label(__('From')).' '.e((string) $record->from).'</div>',
            '<div>'.$label(__('To')).' '.e((string) $record->to).'</div>',
            '<div>'.$label(__('Subject')).' '.e($record->subject ?: __('(no subject)')).'</div>',
        ]));
    }

    /**
     * @return array<int, mixed>
     */
    public static function filters(): array
    {
        $dateInput = static function (string $name, string $label): DatePicker|TextInput {
            if (RegionalSetting::calendarSystem() === 'gregorian') {
                return DatePicker::make($name)->label($label);
            }

            return TextInput::make($name)
                ->label($label)
                ->placeholder('YYYY-MM-DD')
                ->inputMode('numeric')
                ->formatStateUsing(fn (mixed $state): ?string => RegionalSetting::formatDateInput($state))
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? RegionalSetting::parseDateInput($state) : null)
                ->rules([new ClosureValidationRule(function (string $attribute, mixed $value, Closure $fail): void {
                    try {
                        RegionalSetting::parseDateInput((string) $value);
                    } catch (Throwable) {
                        $fail(__('Enter a valid date in YYYY-MM-DD format.'));
                    }
                })]);
        };

        return [
            // The list's "My records": the same "mine" as the consultant's
            // limit, not just the mail filed under the user's own contact.
            Filter::make(ListRecords::MY_RECORDS_FILTER)
                ->label('My records')
                ->toggle()
                ->query(fn (Builder $query): Builder => $query->mine(Auth::user())),
            SelectFilter::make('employee')
                ->label('Employees')
                ->relationship('employee', 'last_name')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => ContactResource::searchOptions($search)),
            SelectFilter::make('direction')
                ->options([Mail::INCOMING => __('Received'), Mail::OUTGOING => 'Sent']),
            // Any part of the sender: a name or an address. Not a list of
            // senders to pick from, since one person's "Name <address>" comes
            // in as many forms as their mail clients write it.
            Filter::make('sender')
                ->schema([TextInput::make('from')->label('From')])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when(filled($data['from'] ?? null), fn (Builder $q): Builder => $q->where('from', 'like', '%'.$data['from'].'%')))
                ->indicateUsing(fn (array $data): ?string => filled($data['from'] ?? null) ? __('From').': '.$data['from'] : null),
            TernaryFilter::make('has_attachments')
                ->label('Has attachments')
                ->queries(
                    true: fn (Builder $q): Builder => $q->has('attachments'),
                    false: fn (Builder $q): Builder => $q->doesntHave('attachments'),
                ),
            Filter::make('date')
                ->schema([
                    $dateInput('from', 'Date from'),
                    $dateInput('until', 'Date until'),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $q, $v): Builder => $q->whereDate('date', '>=', $v))
                    ->when($data['until'] ?? null, fn (Builder $q, $v): Builder => $q->whereDate('date', '<=', $v))),
        ];
    }
}
