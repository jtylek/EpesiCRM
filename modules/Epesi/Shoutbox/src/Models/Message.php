<?php

namespace Epesi\Modules\Shoutbox\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    /** How long its author may still delete a message — Epesi's 10 minutes. */
    public const DELETE_WINDOW_MINUTES = 10;

    protected $table = 'epesi_shoutbox_messages';

    protected $fillable = [
        'user_id',
        'to_user_id',
        'message',
        'deleted',
        'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'deleted' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * Messages to everyone, plus private ones $user sent or received.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereNull('to_user_id')
            ->orWhere('to_user_id', $user->id)
            ->orWhere('user_id', $user->id));
    }

    /**
     * The CSS background of the message's bubble, as $user sees it: mine are
     * light green to everyone and darker to one person; someone else's are
     * light grey to everyone and dark grey to me.
     */
    public function bubbleBackground(User $user): string
    {
        return match (true) {
            $this->user_id === $user->id && $this->to_user_id === null => 'color-mix(in srgb, var(--primary-500) 18%, transparent)',
            $this->user_id === $user->id => 'color-mix(in srgb, var(--primary-500) 38%, transparent)',
            $this->to_user_id === $user->id => 'color-mix(in srgb, var(--gray-500) 28%, transparent)',
            default => 'color-mix(in srgb, var(--gray-500) 9%, transparent)',
        };
    }

    /**
     * Only the author, and only within the first ten minutes.
     */
    public function canBeDeletedBy(User $user): bool
    {
        if ($this->deleted) {
            return false;
        }

        return $this->user_id === $user->id
            && $this->created_at?->gt(now()->subMinutes(self::DELETE_WINDOW_MINUTES));
    }
}
