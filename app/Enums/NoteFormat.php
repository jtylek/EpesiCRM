<?php

namespace App\Enums;

use App\Support\UiState;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;
use League\HTMLToMarkdown\HtmlConverter;

/**
 * What a note's body is written in. E-mails are always HTML; a note is either,
 * chosen in the editor (the default for a new one is the user's, User
 * Settings → Notes). The format is stored on the note, so each renders the way
 * it was written whatever the viewer prefers.
 */
enum NoteFormat: string implements HasLabel
{
    case Html = 'html';
    case Markdown = 'markdown';

    public function getLabel(): string
    {
        return match ($this) {
            self::Html => __('HTML'),
            self::Markdown => __('Markdown'),
        };
    }

    /** A form's state for the format: the case itself or its value. */
    public static function fromState(mixed $state): ?self
    {
        return $state instanceof self ? $state : self::tryFrom((string) $state);
    }

    /** The user's format for a new note. */
    public static function default(): self
    {
        return self::tryFrom((string) UiState::recall('notes.format', self::Html->value)) ?? self::Html;
    }

    public static function setDefault(self $format): void
    {
        UiState::remember('notes.format', $format->value);
    }

    /**
     * The body as HTML for display. Markdown is rendered with raw HTML and
     * unsafe links stripped: a `<script>` or `javascript:` link in someone's
     * note would otherwise run in this app's origin with the viewer's session.
     */
    public function toHtml(?string $body): string
    {
        $body = (string) $body;

        return match ($this) {
            self::Html => $body,
            self::Markdown => Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
        };
    }

    /** The body as plain text, for labels, excerpts and search. */
    public function toPlainText(?string $body): string
    {
        return trim(html_entity_decode(strip_tags($this->toHtml($body))));
    }

    /**
     * The body converted to $target, for switching the editor. Markdown →
     * HTML loses nothing; HTML → Markdown loses what Markdown has no syntax
     * for (colours, alignment, ...).
     */
    public function convert(?string $body, self $target): string
    {
        $body = (string) $body;

        if ($this === $target || trim($body) === '') {
            return $body;
        }

        return match ($target) {
            self::Html => $this->toHtml($body),
            self::Markdown => trim((new HtmlConverter(['strip_tags' => true, 'header_style' => 'atx']))->convert($body)),
        };
    }
}
