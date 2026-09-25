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
