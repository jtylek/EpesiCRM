<?php

namespace Epesi\Modules\RecordBrowser\History;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/**
 * A long text's change as the History addon shows it: the words removed
 * (struck through, on red) and added (on green), with a few unchanged words
 * on each side and "…" for the rest — a word diff, as a wiki's history shows
 * one, instead of the whole old text next to the whole new one. Words are
 * compared, not characters and not markup: rich text is reduced to its text
 * first (plainText()).
 *
 * Returns HTML; every word in it is escaped.
 */
final class TextDiff
{
    /** Unchanged words kept on each side of a change. */
    public const CONTEXT = 8;

    /** Words of one removed or added run shown before it is cut short. */
    public const RUN = 30;

    /** Unchanged runs this short between two changes join them into one. */
    private const JOIN = 1;

    /** A line break, one token of its own so that it is compared too. */
    private const BREAK = "\n";

    /**
     * Rich text as plain text. A block's end becomes a line break rather than
     * nothing, so "<p>inline.</p><p>Removing" is two lines, not
     * "inline.Removing", and an image is the word "[image]" rather than
     * nothing, so adding one is a change. (Swapping one image for another is
     * not: both are "[image]".)
     */
    public static function plainText(?string $html): string
    {
        $text = preg_replace(
            ['~<br\s*/?>|</(?:p|div|li|h[1-6]|blockquote|pre|tr)>~i', '~<img\b[^>]*>~i'],
            ["\n", ' '.__('[image]').' '],
            (string) $html,
        );

        return self::normalize(html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Plain text with its spaces run together and its lines trimmed. A
     * leading or trailing blank line is dropped; one in the middle is kept,
     * as a single marker rather than one per blank line — a paragraph break,
     * which render() turns into a blank-line gap instead of an ordinary one.
     */
    public static function normalize(?string $text): string
    {
        $lines = preg_split('/\R/u', str_replace("\u{00A0}", ' ', (string) $text)) ?: [];
        $lines = array_map(fn (string $line): string => trim((string) preg_replace('/\s+/u', ' ', $line)), $lines);

        $kept = [];
        $blank = false;

        foreach ($lines as $line) {
            if ($line === '') {
                $blank = true;

                continue;
            }

            if ($kept !== [] && $blank) {
                $kept[] = '';
            }

            $kept[] = $line;
            $blank = false;
        }

        return implode("\n", $kept);
    }

    /**
     * The change from $old to $new, both plain text; null when they read the
     * same (rich text whose formatting alone changed). $whole keeps every
     * word, for the Show modal, instead of a line's worth around each change.
     */
    public static function render(string $old, string $new, bool $whole = false): ?string
    {
        $segments = self::segments(
            (new Differ(new UnifiedDiffOutputBuilder))->diffToArray(self::tokens($old), self::tokens($new)),
        );

        if (! in_array('change', array_column($segments, 0), true)) {
            return null;
        }

        $last = count($segments) - 1;
        $pieces = [];

        foreach ($segments as $i => $segment) {
            if ($segment[0] === 'change') {
                // A break at either end of a run is where the run sits, not
                // what changed: an added paragraph is a line break, then the
                // paragraph on green; a removed one leaves no break behind.
                // Two breaks (a paragraph gap) come back out as two, not one.
                [$removed] = self::trimBreaks($segment[1]);
                [$added, $breaksBefore, $breaksAfter] = self::trimBreaks($segment[2]);

                if ($removed !== []) {
                    $pieces[] = '<del class="epesi-history-old">'.self::run($removed, $whole).'</del>';
                }

                for ($b = 0; $b < $breaksBefore; $b++) {
                    $pieces[] = self::BREAK;
                }

                if ($added !== []) {
                    $pieces[] = '<ins class="epesi-history-new">'.self::run($added, $whole).'</ins>';
                }

                for ($b = 0; $b < $breaksAfter; $b++) {
                    $pieces[] = self::BREAK;
                }

                continue;
            }

            $pieces[] = self::context($segment[1], first: $i === 0, last: $i === $last, whole: $whole);
        }

        return (string) preg_replace('/ ?\n ?/', '<br>', implode(' ', $pieces));
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $text): array
    {
        return preg_split('/ |('.self::BREAK.')/', self::normalize($text), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * The diff as alternating runs: ['same', tokens] and ['change', removed,
     * added]. A very short unchanged run between two changes goes into both
     * sides of one change, so a rewritten sentence reads as one change rather
     * than as words picked out around each "the" it kept.
     *
     * @param  array<int, array{0: string, 1: int}>  $diff
     * @return list<array{0: 'same', 1: list<string>}|array{0: 'change', 1: list<string>, 2: list<string>}>
     */
    private static function segments(array $diff): array
    {
        $segments = [];

        foreach ($diff as [$token, $type]) {
            $open = count($segments) - 1;

            if ($type === Differ::OLD) {
                if ($open >= 0 && $segments[$open][0] === 'same') {
                    $segments[$open][1][] = $token;
                } else {
                    $segments[] = ['same', [$token]];
                }
            } elseif ($type === Differ::REMOVED || $type === Differ::ADDED) {
                if ($open < 0 || $segments[$open][0] !== 'change') {
                    $segments[] = ['change', [], []];
                    $open++;
                }

                $segments[$open][$type === Differ::REMOVED ? 1 : 2][] = $token;
            }
        }

        $joined = [];

        foreach ($segments as $segment) {
            $count = count($joined);

            // A line break alone between them keeps two changes apart: it
            // is where one paragraph ends, not a word the rewrite kept.
            if ($segment[0] === 'change' && $count >= 2 && $joined[$count - 2][0] === 'change'
                && $joined[$count - 1][0] === 'same' && count($joined[$count - 1][1]) <= self::JOIN
                && $joined[$count - 1][1] !== [self::BREAK]) {
                [, $kept] = array_pop($joined);
                $joined[$count - 2][1] = [...$joined[$count - 2][1], ...$kept, ...$segment[1]];
                $joined[$count - 2][2] = [...$joined[$count - 2][2], ...$kept, ...$segment[2]];

                continue;
            }

            $joined[] = $segment;
        }

        return $joined;
    }

    /**
     * Unchanged words: those next to a change, and "…" for what is between.
     *
     * @param  list<string>  $tokens
     */
    private static function context(array $tokens, bool $first, bool $last, bool $whole): string
    {
        $count = count($tokens);
        $gap = '<span class="epesi-history-gap">…</span>';

        // Cut only where it hides more than a couple of words.
        $keep = match (true) {
            $whole => $tokens,
            $first && $last => $tokens,
            $first => $count > self::CONTEXT + 2 ? [$gap, ...array_slice($tokens, -self::CONTEXT)] : $tokens,
            $last => $count > self::CONTEXT + 2 ? [...array_slice($tokens, 0, self::CONTEXT), $gap] : $tokens,
            default => $count > 2 * self::CONTEXT + 2
                ? [...array_slice($tokens, 0, self::CONTEXT), $gap, ...array_slice($tokens, -self::CONTEXT)]
                : $tokens,
        };

        return implode(' ', array_map(fn (string $token): string => $token === $gap ? $gap : e($token), $keep));
    }

    /**
     * A removed or added run without the line breaks at its ends, and how
     * many there were on each side (0, 1, or 2 for a paragraph gap — normalize()
     * never lets more than one blank line through). A run of breaks alone (a
     * paragraph split or joined) is kept whole: the break is all that changed.
     *
     * @param  list<string>  $tokens
     * @return array{0: list<string>, 1: int, 2: int}
     */
    private static function trimBreaks(array $tokens): array
    {
        if (array_diff($tokens, [self::BREAK]) === []) {
            return [$tokens, 0, 0];
        }

        $before = 0;
        while (($tokens[$before] ?? null) === self::BREAK) {
            $before++;
        }

        $after = 0;
        while (($tokens[count($tokens) - 1 - $after] ?? null) === self::BREAK) {
            $after++;
        }

        return [array_slice($tokens, $before, count($tokens) - $before - $after), $before, $after];
    }

    /**
     * Removed or added words. A line break in them is a "¶" rather than a
     * break: the break is what changed, and it is gone from one of the texts.
     *
     * @param  list<string>  $tokens
     */
    private static function run(array $tokens, bool $whole): string
    {
        $cut = ! $whole && count($tokens) > self::RUN + 2;
        $words = array_map(
            fn (string $token): string => $token === self::BREAK ? '¶' : e($token),
            $cut ? array_slice($tokens, 0, self::RUN) : $tokens,
        );

        return implode(' ', $words).($cut ? ' <span class="epesi-history-gap">…</span>' : '');
    }

    // ------------------------------------------------------ Rich formatting --

    /** Inline tags richTokenize() keeps, and the tag each re-wraps a word in. */
    private const RICH_TAGS = [
        'strong' => 'strong', 'b' => 'strong',
        'em' => 'em', 'i' => 'em',
        'code' => 'code',
        's' => 's', 'strike' => 's', 'del' => 's',
        'mark' => 'mark', 'sup' => 'sup', 'sub' => 'sub',
    ];

    /** Block elements a diff treats as a line break, same as plainText(). */
    private const BLOCKS = ['p', 'div', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'tr'];

    /**
     * The Show modal's version of render(): the words that changed still get
     * <ins>/<del>, but the words around them keep their original formatting
     * (bold, italic, code, links) instead of being flattened to plain text
     * first, as render() does for the table's condensed line. Worth the
     * extra HTML walk only for the one place that shows a whole rich-text
     * field; null on no change, same as render().
     */
    public static function renderRich(string $oldHtml, string $newHtml): ?string
    {
        [$oldPlain, $oldRich] = self::richTokenize($oldHtml);
        [$newPlain, $newRich] = self::richTokenize($newHtml);

        $diff = (new Differ(new UnifiedDiffOutputBuilder))->diffToArray($oldPlain, $newPlain);

        if (! in_array(Differ::REMOVED, array_column($diff, 1), true) && ! in_array(Differ::ADDED, array_column($diff, 1), true)) {
            return null;
        }

        $html = '';
        $same = [];
        $removed = [];
        $added = [];
        $oldIndex = 0;
        $newIndex = 0;

        // A space belongs between two pieces only where the next one's own
        // first word actually had one in the source — richDisplay() says so.
        $append = function (string $piece, bool $leadingSpace) use (&$html): void {
            if ($piece === '') {
                return;
            }

            $html .= ($html !== '' && $leadingSpace ? ' ' : '').$piece;
        };

        $flushSame = function () use (&$same, $append): void {
            if ($same !== []) {
                $append(...self::richDisplay($same));
                $same = [];
            }
        };

        $flushChange = function () use (&$removed, &$added, $append): void {
            if ($removed !== []) {
                [$piece, $space] = self::richDisplay($removed);
                $append('<del class="epesi-history-old">'.$piece.'</del>', $space);
                $removed = [];
            }

            if ($added !== []) {
                [$piece, $space] = self::richDisplay($added);
                $append('<ins class="epesi-history-new">'.$piece.'</ins>', $space);
                $added = [];
            }
        };

        foreach ($diff as [, $type]) {
            if ($type === Differ::OLD) {
                $flushChange();
                $same[] = $newRich[$newIndex];
                $oldIndex++;
                $newIndex++;

                continue;
            }

            $flushSame();

            if ($type === Differ::REMOVED) {
                $removed[] = $oldRich[$oldIndex];
                $oldIndex++;
            } else {
                $added[] = $newRich[$newIndex];
                $newIndex++;
            }
        }

        $flushSame();
        $flushChange();

        return $html;
    }

    /**
     * Rich text as parallel plain and formatted tokens — the same words and
     * line breaks tokens() would produce from plainText(), each paired with
     * the tags it was written in, so a diff can compare the plain words but
     * still show the unchanged ones as they were: not reduced to text first.
     *
     * @return array{0: list<string>, 1: list<string|array{tags: list<array{0: string, 1: ?string}>, word: string, space: bool}>}
     */
    public static function richTokenize(string $html): array
    {
        if (trim($html) === '') {
            return [[], []];
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();

        $root = $dom->getElementsByTagName('div')->item(0);

        if ($root === null) {
            return [[], []];
        }

        $plain = [];
        $rich = [];

        // One break: an ordinary line break (Shift+Enter), wherever it falls.
        $lineBreak = function () use (&$plain, &$rich): void {
            $count = count($plain);

            // Capped at two in a row: a third adds nothing more to see.
            if ($count >= 2 && $plain[$count - 1] === self::BREAK && $plain[$count - 2] === self::BREAK) {
                return;
            }

            $plain[] = self::BREAK;
            $rich[] = self::BREAK;
        };

        // Two: a paragraph boundary reads as a gap here too, matching the
        // real spacing sanitizeHtml()/prose() gives the same <p> on the View
        // page and the Show modal's "As created" side — not just wherever
        // the note happened to already have a blank line.
        $paragraphBreak = function () use ($lineBreak): void {
            $lineBreak();
            $lineBreak();
        };

        // Whether the next word had real whitespace before it in the source
        // — "<code>span</code>s" is one written word, not "span" and "s" a
        // space apart, and a diff's own re-joining must not add one.
        $pendingSpace = false;

        $word = function (string $word, array $tags) use (&$plain, &$rich, &$pendingSpace): void {
            $plain[] = $word;
            $rich[] = ['tags' => $tags, 'word' => $word, 'space' => $pendingSpace];
        };

        $walk = function (DOMNode $node, array $tags) use (&$walk, $word, $lineBreak, $paragraphBreak, &$pendingSpace): void {
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMText) {
                    $raw = $child->textContent;

                    if ($raw === '') {
                        continue;
                    }

                    if (preg_match('/^\s/u', $raw) === 1) {
                        $pendingSpace = true;
                    }

                    $pieces = preg_split('/\s+/u', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];

                    foreach ($pieces as $index => $piece) {
                        if ($index > 0) {
                            $pendingSpace = true;
                        }

                        $word($piece, $tags);
                    }

                    if ($pieces !== []) {
                        $pendingSpace = preg_match('/\s$/u', $raw) === 1;
                    }

                    continue;
                }

                if (! $child instanceof DOMElement) {
                    continue;
                }

                $name = strtolower($child->tagName);

                if ($name === 'br') {
                    $lineBreak();

                    continue;
                }

                if ($name === 'img') {
                    $word((string) __('[image]'), $tags);
                    $pendingSpace = false;

                    continue;
                }

                $childTags = $tags;

                if ($name === 'a' && $child->getAttribute('href') !== '') {
                    $childTags = [...$tags, ['a', $child->getAttribute('href')]];
                } elseif (isset(self::RICH_TAGS[$name])) {
                    $childTags = [...$tags, [self::RICH_TAGS[$name], null]];
                }

                // $walk() leaves $pendingSpace as its own last text node's
                // trailing whitespace said (false for "<code>span</code>s";
                // true when a mark's trailing space is inside it, as an
                // editor sometimes writes "<strong>Edit  </strong>page").
                $walk($child, $childTags);

                if (in_array($name, self::BLOCKS, true)) {
                    $paragraphBreak();
                }
            }
        };

        $walk($root, []);

        // A leading or trailing break is the document's own start or end, not
        // a paragraph break: normalize() drops those too.
        while ($plain !== [] && $plain[0] === self::BREAK) {
            array_shift($plain);
            array_shift($rich);
        }

        while ($plain !== [] && end($plain) === self::BREAK) {
            array_pop($plain);
            array_pop($rich);
        }

        return [$plain, $rich];
    }

    /**
     * A run of richTokenize() tokens as HTML: consecutive words with the same
     * formatting share one wrapping tag rather than one each — a run of bold
     * or code text is one span, not a chain of tiny ones — and a break
     * prints a literal <br>, wherever it falls in the run. Each word's own
     * 'space' decides whether it gets a space before it, so a word right
     * against a closing tag in the source ("<code>span</code>s") reads that
     * way here too; the caller gets the first one back, to decide the same
     * for whatever comes before this whole run.
     *
     * @param  list<string|array{tags: list<array{0: string, 1: ?string}>, word: string, space: bool}>  $tokens
     * @return array{0: string, 1: bool}
     */
    private static function richDisplay(array $tokens): array
    {
        $html = '';
        $run = [];
        $runTags = null;
        $leadingSpace = true;

        $flush = function () use (&$html, &$run, &$runTags): void {
            if ($run === []) {
                return;
            }

            $inner = '';

            foreach ($run as $i => $entry) {
                $inner .= ($i > 0 && $entry['space'] ? ' ' : '').$entry['word'];
            }

            $html .= ($html !== '' && $run[0]['space'] ? ' ' : '').self::wrapTags($runTags ?? [], $inner);
            $run = [];
            $runTags = null;
        };

        foreach ($tokens as $index => $token) {
            if ($index === 0) {
                $leadingSpace = $token === self::BREAK ? true : $token['space'];
            }

            if ($token === self::BREAK) {
                $flush();
                $html .= ($html !== '' ? ' ' : '').'<br>';

                continue;
            }

            if ($runTags !== null && self::tagSignature($token['tags']) !== self::tagSignature($runTags)) {
                $flush();
            }

            $runTags = $token['tags'];
            $run[] = $token;
        }

        $flush();

        return [$html, $leadingSpace];
    }

    /**
     * A word or run of words, HTML-escaped, wrapped in the given tags —
     * innermost first, so the nesting matches the source.
     *
     * @param  list<array{0: string, 1: ?string}>  $tags
     */
    private static function wrapTags(array $tags, string $text): string
    {
        $html = e($text);

        foreach (array_reverse($tags) as [$tag, $attribute]) {
            $html = $tag === 'a'
                ? '<a href="'.e((string) $attribute).'">'.$html.'</a>'
                : "<{$tag}>{$html}</{$tag}>";
        }

        return $html;
    }

    /** @param  list<array{0: string, 1: ?string}>  $tags */
    private static function tagSignature(array $tags): string
    {
        return implode('|', array_map(fn (array $tag): string => $tag[0].($tag[1] !== null ? ':'.$tag[1] : ''), $tags));
    }
}
