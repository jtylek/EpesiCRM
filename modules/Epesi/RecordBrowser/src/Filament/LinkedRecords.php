<?php

namespace Epesi\Modules\RecordBrowser\Filament;

use Closure;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * How a linked record reads wherever it is shown — a relation field, the
 * employees on a task, the records a note is attached to or an e-mail is
 * linked to, in a list, an addon or on a View page: a badge per record with a
 * link icon after it, opening the record's View page. A record with nowhere to
 * open (no View page in this panel) keeps the badge but not the icon, so the
 * icon always means the badge can be clicked.
 */
class LinkedRecords
{
    /**
     * A badge for each record $records returns for the row, named by $label
     * (by default the record's title) and linked to its View page.
     *
     * @template T of TextColumn|TextEntry
     *
     * @param  T  $component
     * @param  Closure(Model): (iterable<Model>|Model|null)  $records
     * @param  (Closure(Model): string)|null  $label
     * @return T
     */
    public static function badges(TextColumn|TextEntry $component, Closure $records, ?Closure $label = null): TextColumn|TextEntry
    {
        $label ??= static::title(...);
        $all = fn (Model $record): Collection => collect(($related = $records($record)) instanceof Model ? [$related] : ($related ?? []))->filter();

        return static::style(
            $component->state(fn (Model $record): array => $all($record)->map(fn (Model $related): string => $label($related))->values()->all()),
            fn (Model $record, mixed $state): ?string => ($related = $all($record)->first(fn (Model $related): bool => $label($related) === $state))
                ? static::url($related)
                : null,
        );
    }

    /**
     * The badge and link icon for a component that sets its own state; $url
     * gives each badge's address, or null for one with nowhere to go. Also for
     * a link that isn't a record, such as an e-mail address, so it reads the
     * same.
     *
     * @template T of TextColumn|TextEntry
     *
     * @param  T  $component
     * @param  Closure(Model, mixed): ?string  $url  the row and one badge's state
     * @return T
     */
    public static function style(TextColumn|TextEntry $component, Closure $url): TextColumn|TextEntry
    {
        return $component
            ->badge()
            ->url(fn (Model $record, mixed $state): ?string => $url($record, $state))
            ->icon(fn (Model $record, mixed $state): ?Heroicon => filled($url($record, $state)) ? Heroicon::OutlinedLink : null)
            ->iconPosition(IconPosition::After);
    }

    public static function title(Model $record): string
    {
        $resource = static::resource($record);
        $title = $resource ? $resource::getRecordTitle($record) : null;

        return filled($title) ? strip_tags((string) $title) : Str::headline($record->getMorphClass()).' #'.$record->getKey();
    }

    public static function url(Model $record): ?string
    {
        $resource = static::resource($record);

        return $resource && $resource::hasPage('view') ? $resource::getUrl('view', ['record' => $record]) : null;
    }

    /** @return class-string<\Filament\Resources\Resource>|null */
    protected static function resource(Model $record): ?string
    {
        return Filament::getCurrentPanel()?->getModelResource($record::class);
    }
}
