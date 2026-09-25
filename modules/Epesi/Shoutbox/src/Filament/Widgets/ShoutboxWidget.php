<?php

namespace Epesi\Modules\Shoutbox\Filament\Widgets;

use Epesi\Modules\Shoutbox\Filament\Concerns\ComposesMessages;
use Epesi\Modules\Shoutbox\Filament\Concerns\RedrawsWhenChanged;
use Epesi\Modules\Shoutbox\Models\Message;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The Shoutbox applet: the latest messages, newest first, and a box to post
 * one — to everyone, or to one colleague (Epesi's "To:" autoselect). Polls
 * for new messages, as Epesi's refresh.php did.
 */
class ShoutboxWidget extends Widget
{
    use ComposesMessages;
    use RedrawsWhenChanged;

    protected string $view = 'epesi-shoutbox::widget';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 5;

    public const SHOWN = 30;

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole(['super_admin', 'manager', 'employee']) ?? false;
    }

    /**
     * The shown messages and their "… ago" labels. A label moves on every
     * minute in a message's first hour, then hourly, daily and so on; the
     * ten minutes in which its author may delete it end on one of those
     * minutes, so the trash icon goes with that redraw.
     */
    protected function fingerprint(): string
    {
        return md5($this->shownMessages()
            ->get(['id', 'created_at'])
            ->map(fn (Message $shout): string => $shout->id.' '.$shout->created_at?->diffForHumans())
            ->implode(','));
    }

    /**
     * @return Builder<Message>
     */
    private function shownMessages(): Builder
    {
        return Message::query()
            ->visibleTo(Auth::user())
            ->where('deleted', false)
            ->latest('id')
            ->limit(self::SHOWN);
    }

    public function delete(int $id): void
    {
        $message = Message::query()->visibleTo(Auth::user())->findOrFail($id);

        abort_unless($message->canBeDeletedBy(Auth::user()), 403);

        $message->update(['deleted' => true]);
    }

    /**
     * @return Collection<int, Message>
     */
    public function getShouts(): Collection
    {
        return $this->shownMessages()
            ->with(['author.contact', 'recipient.contact'])
            ->get();
    }
}
