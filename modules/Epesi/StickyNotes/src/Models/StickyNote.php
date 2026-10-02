<?php

namespace Epesi\Modules\StickyNotes\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $user_id
 * @property string $title
 * @property ?string $body
 * @property string $color
 * @property bool $active
 * @property int $position
 * @property int $col
 */
class StickyNote extends Model
{
    /** color => the base of its very light background (see background()). */
    public const COLORS = [
        'yellow' => '#eab308',
        'green' => '#22c55e',
        'blue' => '#3b82f6',
        'red' => '#ef4444',
        'grey' => '#6b7280',
    ];

    protected $table = 'epesi_sticky_notes';

    protected $fillable = ['user_id', 'title', 'body', 'color', 'active', 'position', 'col'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** A tint of the color over the page, so it reads as very light in both themes. */
    public function background(): string
    {
        return 'color-mix(in srgb, '.(self::COLORS[$this->color] ?? self::COLORS['yellow']).' 14%, transparent)';
    }

    /** The body's Markdown as safe HTML: raw HTML in it is dropped, and so are unsafe links. */
    public function bodyHtml(): string
    {
        return (string) Str::markdown((string) $this->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
