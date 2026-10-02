<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One tab of a user's dashboard: legacy's base_dashboard_tabs.
 */
class DashboardTab extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'name',
        'key',
        'pos',
    ];

    protected function casts(): array
    {
        return [
            'pos' => 'integer',
        ];
    }

    /** The key of the tab only the Notes applet lives on. */
    public const NOTES = 'notes';

    /** Main, Agenda and Notes: part of every dashboard, and never deleted. */
    public function isSystem(): bool
    {
        return $this->key !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<DashboardApplet, $this>
     */
    public function applets(): HasMany
    {
        return $this->hasMany(DashboardApplet::class);
    }

    /**
     * Legacy add_applet(): at the bottom of the column with the fewest
     * applets, the leftmost of those on a tie.
     *
     * @param  array<string, mixed>|null  $settings
     */
    public function addApplet(string $widget, int $columns, ?array $settings = null): DashboardApplet
    {
        $counts = $this->applets()
            ->selectRaw('col, count(*) as applets')
            ->groupBy('col')
            ->pluck('applets', 'col');

        $heights = array_map(fn (int $col): int => (int) ($counts[$col] ?? 0), range(0, $columns - 1));
        $col = array_search(min($heights), $heights, true);

        return $this->applets()->create([
            'user_id' => $this->user_id,
            'widget' => $widget,
            'col' => $col,
            'pos' => ($this->applets()->where('col', $col)->max('pos') ?? -1) + 1,
            'settings' => $settings,
        ]);
    }
}
