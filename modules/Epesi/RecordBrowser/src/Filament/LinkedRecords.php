<?php

namespace Epesi\Modules\RecordBrowser\Filament;

use Closure;
use Epesi\Modules\RecordBrowser\Recordset\LinkableRecordsets;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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
     * $label may return an Htmlable (e.g. the recordset's icon before the
     * title, LinkableRecordsets::iconLabel() wrapped in a HtmlString) instead
     * of a plain string: Filament sanitizes a badge's text when it's given as
     * a plain string under ->html() (Str::sanitizeHtml(), which drops an
     * inline <svg>), but trusts — unsanitized — a state item that already
     * arrives as an Htmlable, the same bypass FieldType::File/Collection's
     * HtmlString state already relies on. Matched by value, not identity
     * (Htmlable is cast to string), since $label builds a fresh instance each
     * call.
     *
     * @template T of TextColumn|TextEntry
     *
     * @param  T  $component
     * @param  Closure(Model): (iterable<Model>|Model|null)  $records
     * @param  (Closure(Model): (string|Htmlable))|null  $label
     * @return T
     */
    public static function badges(TextColumn|TextEntry $component, Closure $records, ?Closure $label = null): TextColumn|TextEntry
    {
        $label ??= static::title(...);
        $all = fn (Model $record): Collection => collect(($related = $records($record)) instanceof Model ? [$related] : ($related ?? []))->filter();
        $matches = fn (Model $related, mixed $state): bool => (string) $label($related) === (string) $state;

        return static::style(
            $component->state(fn (Model $record): array => $all($record)->map(fn (Model $related): string|Htmlable => $label($related))->values()->all()),
            fn (Model $record, mixed $state): ?string => ($related = $all($record)->first(fn (Model $related): bool => $matches($related, $state)))
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
            // Medium size, as every other link badge (a collection item's, an
            // e-mail's or a web address's) is.
            ->size(TextSize::Medium)
            ->url(fn (Model $record, mixed $state): ?string => $url($record, $state))
            ->icon(fn (Model $record, mixed $state): ?Heroicon => filled($url($record, $state)) ? Heroicon::OutlinedLink : null)
            ->iconPosition(IconPosition::After);
    }

    public static function title(Model $record): string
    {
        $resource = static::resource($record);
        $title = $resource ? $resource::getRecordTitle($record) : null;

        return filled($title) ? strip_tags((string) $title) : LinkableRecordsets::label($record->getMorphClass()).' #'.$record->getKey();
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
