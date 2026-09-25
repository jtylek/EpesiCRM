<?php

namespace Epesi\Modules\Shoutbox\Filament\Concerns;

use App\Models\User;
use Epesi\Modules\Shoutbox\Models\Message;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Posting a message — to everyone, or to one colleague (Epesi's "To:"
 * autoselect). Backs the epesi-shoutbox::compose form.
 */
trait ComposesMessages
{
    public string $message = '';

    public ?int $to = null;

    public function send(): void
    {
        $this->validate([
            'message' => ['required', 'string', 'max:2000'],
            'to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('active', true)],
        ]);

        Message::create([
            'user_id' => Auth::id(),
            'to_user_id' => $this->to,
            'message' => trim($this->message),
        ]);

        $this->message = '';
    }

    /**
     * Falls back to everyone when the author is you, or is a deactivated
     * user no longer offered as a recipient.
     */
    public function replyTo(int $userId): void
    {
        $this->to = array_key_exists($userId, $this->getRecipients()) ? $userId : null;
    }

    /**
     * Active colleagues only — a deactivated user can't sign in to read a
     * private message.
     *
     * @return array<int, string>
     */
    public function getRecipients(): array
    {
        return User::query()
            ->whereKeyNot(Auth::id())
            ->where('active', true)
            ->with('contact')
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->displayName()])
            ->sort()
            ->all();
    }
}
