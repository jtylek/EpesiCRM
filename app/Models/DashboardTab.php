<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
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
     * The name to show. A system tab under its default name is stored in
     * English and translated here, for whoever looks at it: stored
     * translated, it kept the language of whoever opened the dashboard first,
     * and on the demo every visitor shares the accounts. A name the user gave
     * the tab is shown as they typed it.
     */
    public function label(): string
    {
        return $this->key !== null && $this->key === config('dashboard.keys.'.$this->name)
            ? __($this->name)
            : $this->name;
    }

    /**
     * Legacy set_default_applets(), with config('dashboard.default') in place
     * of an administrator's default dashboard. An applet $available turns
     * down (its module off) is left out, and so is a tab left with none.
     *
     * @param  callable(class-string): bool  $available
     * @return Collection<int, self>
     */
    public static function createDefaults(int $userId, callable $available): Collection
    {
        $layout = collect(config('dashboard.default', []))
            ->map(fn (array $columns): array => array_map(
                fn (array $widgets): array => array_values(array_filter($widgets, $available)),
                $columns,
            ))
            ->filter(fn (array $columns): bool => array_merge(...$columns) !== []);

        // The dashboard needs a tab, even with nothing to put on it.
        if ($layout->isEmpty()) {
            $layout = collect(['Dashboard' => []]);
        }

        $tabs = new Collection;

        foreach ($layout as $name => $columns) {
            $key = config('dashboard.keys.'.$name);

            $tab = self::query()->create([
                'user_id' => $userId,
                // A system tab is translated by label(); any other is the user's own from now on.
                'name' => $key === null ? __($name) : $name,
                'key' => $key,
                'pos' => $tabs->count(),
            ]);

            foreach ($columns as $col => $widgets) {
                foreach ($widgets as $pos => $widget) {
                    $tab->applets()->create([
                        'user_id' => $userId,
                        'widget' => $widget,
                        'col' => $col,
                        'pos' => $pos,
                    ]);
                }
            }

            $tabs->push($tab);
        }

        return $tabs;
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
