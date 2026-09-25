<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Models\DashboardApplet;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Renderless;

/**
 * Filament's own Dashboard (subclassed so its header matches every other
 * page's, see HasPageIconBreadcrumb), laid out as Epesi's Base_Dashboard was:
 * applets in three columns that each user drags into their own arrangement
 * by the title bar, saved per user in dashboard_applets.
 */
class Dashboard extends BaseDashboard
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;

    public const COLUMNS = 3;

    protected string $view = 'filament.pages.dashboard';

    /**
     * The applets this user can see, column by column, in their saved order.
     * One they have never placed (a new user, or a module installed since
     * they last arranged theirs) goes to the bottom of the shortest column.
     *
     * @return array<int, array<class-string<Widget>, class-string<Widget>|WidgetConfiguration>>
     */
    public function getAppletColumns(): array
    {
        $widgets = collect($this->getWidgets())
            ->keyBy(fn (string|WidgetConfiguration $widget): string => $this->normalizeWidgetClass($widget))
            ->filter(fn (string|WidgetConfiguration $widget, string $class): bool => $class::canView());

        $columns = array_fill(0, self::COLUMNS, []);

        $placed = DashboardApplet::query()
            ->where('user_id', Auth::id())
            ->orderBy('pos')
            ->orderBy('id')
            ->get();

        foreach ($placed as $applet) {
            if ($widgets->has($applet->widget)) {
                $columns[min($applet->col, self::COLUMNS - 1)][$applet->widget] = $widgets->pull($applet->widget);
            }
        }

        foreach ($widgets as $class => $widget) {
            $counts = array_map('count', $columns);
            $columns[array_search(min($counts), $counts, true)][$class] = $widget;
        }

        return $columns;
    }

    /**
     * What Filament's own widget grid would mount the widget with.
     *
     * @return array<string, mixed>
     */
    public function getAppletProperties(string|WidgetConfiguration $widget): array
    {
        return [
            ...$this->getWidgetData(),
            ...($widget instanceof WidgetConfiguration
                ? [...$widget->widget::getDefaultProperties(), ...$widget->getProperties()]
                : $widget::getDefaultProperties()),
        ];
    }

    /**
     * wire:sort's handler, called once per drop: $position is the applet's
     * index in $column after the drop. The whole page is saved, not just the
     * one applet, so applets still in their default place stay where the
     * user saw them. Renderless: SortableJS has already moved the element.
     */
    #[Renderless]
    public function moveApplet(string $widget, int $position, string $column): void
    {
        $columns = array_map(array_keys(...), $this->getAppletColumns());

        if (! in_array($widget, array_merge(...$columns), true)) {
            return;
        }

        $columns = array_map(fn (array $classes): array => array_values(array_diff($classes, [$widget])), $columns);
        array_splice($columns[max(0, min((int) $column, self::COLUMNS - 1))], max(0, $position), 0, [$widget]);

        $rows = [];

        foreach ($columns as $col => $classes) {
            foreach ($classes as $pos => $class) {
                $rows[] = ['user_id' => Auth::id(), 'widget' => $class, 'col' => $col, 'pos' => $pos];
            }
        }

        DashboardApplet::query()->upsert($rows, ['user_id', 'widget'], ['col', 'pos']);
    }
}
