<?php

namespace Epesi\Modules\PriorityList\Filament\Widgets;

use App\Filament\Dashboard\Applet;
use App\Filament\Dashboard\IsApplet;
use Epesi\Modules\PriorityList\Models\Entry;
use Epesi\Modules\PriorityList\PriorityList;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The signed-in user's priority list on the dashboard: drag a row by its grip
 * to reorder, tick off what is done, take off what can wait. Not a Filament
 * table: its rows can only be dragged in a reorder mode that hides the row
 * actions, and here both are always there.
 *
 * It has no "how many to show" setting: the list never holds more than
 * PriorityList::LIMIT, so all of it is shown.
 */
class PriorityListWidget extends Widget implements Applet
{
    use IsApplet;

    protected string $view = 'epesi-priority-list::widget';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole(PriorityList::ROLES) ?? false;
    }

    public static function getAppletCaption(): string
    {
        return __('Priority list');
    }

    public static function getAppletDescription(): ?string
    {
        return __('The tasks, meetings and phone calls you work on next, in order');
    }

    /**
     * @return Collection<int, Entry>
     */
    public function getPriorityEntries(): Collection
    {
        return PriorityList::entries(Auth::user())->with('record')->get();
    }

    /** wire:sort's handler: $position is the entry's index after the drop. */
    public function reorderPriority(int|string $entry, int $position): void
    {
        PriorityList::move(Auth::user(), (int) $entry, $position);
    }

    public function completePriority(int $entry): void
    {
        $record = $this->ownPriorityEntry($entry)->record;

        abort_unless(PriorityList::canComplete(Auth::user(), $record), 403);

        $label = PriorityList::label($record);
        PriorityList::complete($record);

        Notification::make()->title(__('Done: :record', ['record' => $label]))->success()->send();
    }

    public function removePriority(int $entry): void
    {
        $this->ownPriorityEntry($entry)->delete();
    }

    protected function ownPriorityEntry(int $id): Entry
    {
        return PriorityList::entries(Auth::user())->with('record')->find($id) ?? abort(404);
    }
}
