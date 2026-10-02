<?php

namespace Epesi\Modules\PriorityList\Filament\Widgets;

use App\Filament\Dashboard\Applet;
use App\Filament\Dashboard\IsApplet;
use App\Models\User;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\PriorityList\Models\Entry;
use Epesi\Modules\PriorityList\Models\PriorityListPreference;
use Epesi\Modules\PriorityList\PriorityList;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
class PriorityListWidget extends Widget implements Applet, HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use IsApplet;

    protected string $view = 'epesi-priority-list::widget';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->hasAnyRole(PriorityList::ROLES);
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
        return PriorityList::entries(Auth::user())
            ->with(['record' => fn (MorphTo $morphTo) => $morphTo->morphWith([
                Task::class => ['customers', 'customerCompanies'],
                Meeting::class => ['customers', 'customerCompanies'],
                PhoneCall::class => ['customer'],
            ])])
            ->get();
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

    public function requestComplete(int $entry): void
    {
        $record = $this->ownPriorityEntry($entry)->record;
        $user = Auth::user();

        abort_unless($user instanceof User && PriorityList::canComplete($user, $record), 403);

        if (PriorityListPreference::forUser($user)->suppress_completion_confirmation) {
            $this->completePriority($entry);

            return;
        }

        $this->mountAction('confirmCompletion', ['entry' => $entry]);
    }

    public function confirmCompletionAction(): Action
    {
        return Action::make('confirmCompletion')
            ->modal()
            ->modalHeading(fn (Action $action): string => __('Close :record?', [
                'record' => PriorityList::label($this->ownPriorityEntry((int) $action->getArguments()['entry'])->record),
            ]))
            ->modalDescription(__('Its status becomes Closed, and it leaves every priority list it is on.'))
            ->modalSubmitActionLabel(__('Close'))
            ->color('success')
            ->schema([
                Checkbox::make('suppress_completion_confirmation')
                    ->label('Do not show it again'),
            ])
            ->action(function (array $data, Action $action): void {
                $entryId = (int) ($action->getArguments()['entry'] ?? 0);
                $entry = $this->ownPriorityEntry($entryId);
                $user = Auth::user();

                abort_unless($user instanceof User && PriorityList::canComplete($user, $entry->record), 403);

                if ($data['suppress_completion_confirmation'] ?? false) {
                    PriorityListPreference::forUser($user)->update(['suppress_completion_confirmation' => true]);
                }

                $this->completePriority($entryId);
            });
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
