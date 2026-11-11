<?php

namespace Epesi\Modules\RecordBrowser\Recordset;

use Epesi\Modules\CommonData\Facades\CommonData;
use Epesi\Modules\RecordBrowser\Models\CollectionItem;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renders a recordset's clipboard pattern against one record, and the
 * administrator-facing half: the keywords a pattern may use and validating
 * one before it is saved. See AI-shared/recordbrowser/clipboard.md for
 * the pattern syntax and keyword grammar this implements.
 *
 * Storage is `epesi_recordbrowser_clipboard_patterns` (`model_type` unique,
 * `pattern`): only an administrator's override is a row there. What a
 * module declares as the default (Clipboard::enableFor()) is code, read
 * when no row exists — the same two-layer shape as RecordsetFeatures.
 */
class ClipboardPattern
{
    public const TABLE = 'epesi_recordbrowser_clipboard_patterns';

    // ---------------------------------------------------------------- Storage --

    /** The administrator's own override for $alias, or null when there isn't one. */
    public static function storedPatternFor(string $alias): ?string
    {
        if (! Schema::hasTable(self::TABLE)) {
            return null;
        }

        return DB::table(self::TABLE)->where('model_type', $alias)->value('pattern');
    }

    /** The stored override, falling back to the module's default — what render() uses. */
    public static function patternFor(string $alias): ?string
    {
        $stored = static::storedPatternFor($alias);

        return $stored !== null && $stored !== '' ? $stored : Clipboard::defaultPattern($alias);
    }

    /**
     * Saves an administrator's override, or clears it (null or blank),
     * which restores the module's default. $pattern is validated by the
     * caller (Administration's Edit modal) before this is called.
     */
    public static function setPattern(string $alias, ?string $pattern): void
    {
        if ($pattern === null || trim($pattern) === '') {
            DB::table(self::TABLE)->where('model_type', $alias)->delete();

            return;
        }

        DB::table(self::TABLE)->upsert(
            [['model_type' => $alias, 'pattern' => $pattern, 'created_at' => now(), 'updated_at' => now()]],
            ['model_type'],
            ['pattern', 'updated_at'],
        );
    }

    // --------------------------------------------------------------- Rendering --

    /**
     * The pattern for $alias, rendered against $record as plain text: one
     * output line per kept pattern line, escaped and whitespace-collapsed.
     * Null when the recordset has no pattern at all (module default and
     * administrator override both empty) — the caller decides what that
     * means for the View page action's visibility.
     */
    public static function render(Model $record, string $alias): ?string
    {
        $pattern = static::patternFor($alias);

        return $pattern === null || trim($pattern) === '' ? null : static::renderPattern($pattern, $record, $alias);
    }

    /**
     * $pattern rendered against $record, whatever its source — the stored
     * or default one (render()), or, for Administration's live preview, text
     * an administrator is still typing and hasn't saved yet.
     */
    public static function renderPattern(string $pattern, Model $record, string $alias): string
    {
        $lines = [];

        foreach (preg_split('/\r\n|\r|\n/', $pattern) as $line) {
            $rendered = static::renderLine($line, $record, $alias);

            if ($rendered !== null) {
                $lines[] = $rendered;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * One pattern line, or null when it is dropped: it held a keyword
     * (plain or inside a %{...} block) and none of them had a value.
     */
    protected static function renderLine(string $line, Model $record, string $alias): ?string
    {
        $tokens = static::tokenize($line);
        [$text, $hasKeyword, $hasValue] = static::evaluate($tokens, $record, $alias);

        if ($hasKeyword && ! $hasValue) {
            return null;
        }

        // Legacy's HTML hid repeated spaces; plain text doesn't, so a block
        // dropped mid-line (or two kept side by side) is collapsed to one.
        return trim(preg_replace('/ {2,}/', ' ', $text));
    }

    /**
     * @param  array<int, array{type: string, value?: string, name?: string, children?: array}>  $tokens
     * @return array{0: string, 1: bool, 2: bool} rendered text, whether any keyword was in $tokens, whether any had a value
     */
    protected static function evaluate(array $tokens, Model $record, string $alias): array
    {
        $text = '';
        $hasKeyword = false;
        $hasValue = false;

        foreach ($tokens as $token) {
            if ($token['type'] === 'text') {
                $text .= $token['value'];

                continue;
            }

            if ($token['type'] === 'key') {
                $hasKeyword = true;
                $value = static::resolveKeyword($record, $alias, $token['name']);

                if ($value !== null) {
                    $hasValue = true;
                    $text .= static::escape($value);
                }

                continue;
            }

            // A block: keep it only when something inside it had a value —
            // an empty block contributes nothing, not even its literal text.
            [$innerText, $innerHasKeyword, $innerHasValue] = static::evaluate($token['children'], $record, $alias);
            $hasKeyword = $hasKeyword || $innerHasKeyword;

            if ($innerHasValue) {
                $text .= $innerText;
                $hasValue = true;
            }
        }

        return [$text, $hasKeyword, $hasValue];
    }

    /** A substituted value, flattened to one line and with no stray pattern syntax. */
    protected static function escape(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    /**
     * One keyword's value for $record, or null for nothing to show: a blank
     * field, a field that isn't on the View page, or a keyword nobody
     * recognizes (an unknown keyword renders empty rather than failing, so a
     * pattern written against a module that is later removed still copies
     * the rest of itself).
     */
    protected static function resolveKeyword(Model $record, string $alias, string $keyword): ?string
    {
        try {
            return static::doResolveKeyword($record, $alias, $keyword);
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function doResolveKeyword(Model $record, string $alias, string $keyword): ?string
    {
        $resource = static::resourceForAlias($alias);

        if ($resource === null) {
            return null;
        }

        $fields = collect($resource::resolvedFields())->keyBy(fn (Field $field): string => $field->name);

        if (! str_contains($keyword, '.')) {
            $field = $fields->get($keyword);

            if ($field === null || ! $field->isInView()) {
                return null;
            }

            if ($field->type !== FieldType::Collection) {
                return $field->plainValue($record);
            }

            // {emails}: the primary item's one-line summary, no dot needed.
            $items = $record->exists ? $record->{$keyword} : null;
            $primary = $items instanceof Collection ? $items->first() : null;

            if (! $primary instanceof CollectionItem) {
                return null;
            }

            $summary = $primary->summary();

            return $summary === '' ? null : $summary;
        }

        [$collectionName, $rest] = explode('.', $keyword, 2);
        $field = $fields->get($collectionName);

        if ($field === null || $field->type !== FieldType::Collection || ! $field->isInView()) {
            return null;
        }

        $itemType = $field->collectionType();

        if ($itemType === null) {
            return null;
        }

        $items = $record->exists ? $record->{$collectionName} : null;

        // The collection relation (HasCollections::collection()) is an
        // Eloquent Collection, itself a Support\Collection.
        if (! $items instanceof Collection) {
            return null;
        }

        return static::resolveCollectionKeyword($items, $itemType, $rest);
    }

    /**
     * @param  Collection<int, CollectionItem>  $items  primary first
     * @param  class-string<CollectionItem>  $itemType
     */
    protected static function resolveCollectionKeyword($items, string $itemType, string $rest): ?string
    {
        $itemFields = collect($itemType::resolvedFields())->keyBy(fn (Field $field): string => $field->name);
        [$first, $second] = [...explode('.', $rest, 2), null];

        $itemField = $itemFields->get($first);

        // An item field wins over a same-named kind — {addresses.city}, the
        // primary item's own field, not a kind key that happens to match.
        if ($second === null && $itemField !== null && $itemField->isInView() && $itemField->hasColumn() && $itemField->name !== 'kind') {
            $primary = $items->first();

            return $primary instanceof CollectionItem ? $primary->displayValue($itemField) : null;
        }

        // Else $first is a kind key: {phones.work} (the matching item's
        // summary) or {addresses.home.city} (one of its fields).
        $kind = $first;
        $match = $items->first(fn (CollectionItem $item): bool => $item->kind === $kind);

        if (! $match instanceof CollectionItem) {
            return null;
        }

        if ($second === null) {
            $summary = $match->summary();

            return $summary === '' ? null : $summary;
        }

        $subField = $itemFields->get($second);

        if ($subField === null || ! $subField->isInView() || ! $subField->hasColumn() || $subField->name === 'kind') {
            return null;
        }

        return $match->displayValue($subField);
    }

    // -------------------------------------------------------------- Keywords --

    /**
     * The keywords $alias's pattern may use, keyword => label, for
     * Administration's syntax help. Only fields shown on the View page
     * (Field::isInView()); a collection field offers its primary-item
     * keywords and one per kind, each with its own sub-field keywords.
     *
     * @return array<string, string>
     */
    public static function keywords(string $alias): array
    {
        $resource = static::resourceForAlias($alias);

        if ($resource === null) {
            return [];
        }

        $keywords = [];

        foreach ($resource::resolvedFields() as $field) {
            if (! $field->isInView()) {
                continue;
            }

            if ($field->type !== FieldType::Collection) {
                $keywords[$field->name] = $field->getLabel();

                continue;
            }

            $itemType = $field->collectionType();

            if ($itemType === null) {
                continue;
            }

            foreach (static::collectionKeywords($field, $itemType) as $keyword => $label) {
                $keywords[$keyword] = $label;
            }
        }

        return $keywords;
    }

    /**
     * @param  class-string<CollectionItem>  $itemType
     * @return array<string, string>
     */
    protected static function collectionKeywords(Field $field, string $itemType): array
    {
        $label = $field->getLabel();
        // {emails}: the primary item's own summary line, no dot needed.
        $keywords = [$field->name => $label];
        $itemFields = array_filter(
            $itemType::resolvedFields(),
            fn (Field $itemField): bool => $itemField->isInView() && $itemField->hasColumn() && $itemField->name !== 'kind',
        );

        foreach ($itemFields as $itemField) {
            $keywords["{$field->name}.{$itemField->name}"] = "{$label} – {$itemField->getLabel()}";
        }

        foreach (CommonData::array($itemType::kinds(), 'position') as $kindKey => $kindLabel) {
            $keywords["{$field->name}.{$kindKey}"] = "{$label} ({$kindLabel})";

            foreach ($itemFields as $itemField) {
                $keywords["{$field->name}.{$kindKey}.{$itemField->name}"] = "{$label} ({$kindLabel}) – {$itemField->getLabel()}";
            }
        }

        return $keywords;
    }

    /** Any one record of $alias's recordset, for Administration's live preview; null when it has none yet. */
    public static function sampleRecordFor(string $alias): ?Model
    {
        $resource = static::resourceForAlias($alias);

        return $resource === null ? null : $resource::getModel()::query()->first();
    }

    /** @return class-string<RecordsetResource>|null */
    protected static function resourceForAlias(string $alias): ?string
    {
        $panel = Filament::getPanel('main');

        foreach ($panel->getResources() as $resource) {
            if (! is_subclass_of($resource, RecordsetResource::class)) {
                continue;
            }

            if (Relation::getMorphAlias($resource::getModel()) === $alias) {
                return $resource;
            }
        }

        return null;
    }

    // --------------------------------------------------------------- Validation --

    /**
     * Unknown keywords and nested %{...%{...}...} blocks, which never worked
     * in Legacy either — rejected here with a clear message, rather than
     * silently flattened or rendering empty forever once saved.
     */
    public static function validate(string $alias, string $pattern): ?string
    {
        $known = array_keys(static::keywords($alias));
        $unknown = [];

        foreach (preg_split('/\r\n|\r|\n/', $pattern) as $line) {
            $tokens = static::tokenize($line);

            if (static::hasNestedBlock($tokens)) {
                return __('A %{…} block can\'t contain another %{…} block.');
            }

            foreach (static::collectKeywordNames($tokens) as $name) {
                if (! in_array($name, $known, true)) {
                    $unknown[$name] = true;
                }
            }
        }

        if ($unknown !== []) {
            return __('Unknown keyword(s): {keys}', ['keys' => implode(', ', array_map(fn (string $k): string => "{{$k}}", array_keys($unknown)))]);
        }

        return null;
    }

    /** @param  array<int, array{type: string}>  $tokens */
    protected static function hasNestedBlock(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if ($token['type'] !== 'block') {
                continue;
            }

            foreach ($token['children'] as $child) {
                if ($child['type'] === 'block') {
                    return true;
                }
            }

            if (static::hasNestedBlock($token['children'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{type: string}>  $tokens
     * @return array<int, string>
     */
    protected static function collectKeywordNames(array $tokens): array
    {
        $names = [];

        foreach ($tokens as $token) {
            if ($token['type'] === 'key') {
                $names[] = $token['name'];
            } elseif ($token['type'] === 'block') {
                $names = [...$names, ...static::collectKeywordNames($token['children'])];
            }
        }

        return $names;
    }

    // ------------------------------------------------------------------ Parsing --

    /**
     * One line into a flat token list: literal text, `{key}` and `%{...}`
     * blocks (whose children are parsed the same way, so a block may hold
     * `{key}` tokens but — per validate() above — never another block).
     * Matching only counts plain `{`/`}` depth, so `%{{a} {b}}`'s outer close
     * is found correctly past the two keys it contains.
     *
     * @return array<int, array{type: string, value?: string, name?: string, children?: array}>
     */
    protected static function tokenize(string $line): array
    {
        $tokens = [];
        $length = strlen($line);
        $i = 0;

        while ($i < $length) {
            if ($line[$i] === '%' && ($line[$i + 1] ?? '') === '{') {
                $close = static::matchingBrace($line, $i + 1);

                if ($close === null) {
                    $tokens[] = ['type' => 'text', 'value' => substr($line, $i)];

                    break;
                }

                $inner = substr($line, $i + 2, $close - $i - 2);
                $tokens[] = ['type' => 'block', 'children' => static::tokenize($inner)];
                $i = $close + 1;

                continue;
            }

            if ($line[$i] === '{') {
                $close = static::matchingBrace($line, $i);

                if ($close === null) {
                    $tokens[] = ['type' => 'text', 'value' => substr($line, $i)];

                    break;
                }

                $tokens[] = ['type' => 'key', 'name' => trim(substr($line, $i + 1, $close - $i - 1))];
                $i = $close + 1;

                continue;
            }

            $next = static::nextSpecial($line, $i);
            $tokens[] = ['type' => 'text', 'value' => substr($line, $i, $next - $i)];
            $i = $next;
        }

        return $tokens;
    }

    /** The index of "{" or "%{" at or after $from, or the line's length when there is none. */
    protected static function nextSpecial(string $line, int $from): int
    {
        $brace = strpos($line, '{', $from);

        return $brace === false ? strlen($line) : $brace;
    }

    /**
     * The index of the "}" that closes the "{" at $open, counting nested
     * braces (a block's own `{key}` children included); null when it never
     * closes.
     */
    protected static function matchingBrace(string $line, int $open): ?int
    {
        $depth = 0;

        for ($j = $open, $length = strlen($line); $j < $length; $j++) {
            if ($line[$j] === '{') {
                $depth++;
            } elseif ($line[$j] === '}') {
                $depth--;

                if ($depth === 0) {
                    return $j;
                }
            }
        }

        return null;
    }
}
