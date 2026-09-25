<?php

namespace Epesi\Modules\Reminders\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * One alert on a record — a row of Epesi's `utils_messenger_message`.
 *
 * @property Carbon $remind_at
 * @property int|null $before_minutes
 * @property string|null $message
 * @property bool $send_email
 */
class Reminder extends Model
{
    protected $table = 'epesi_reminders';

    protected $fillable = [
        'remind_at',
        'before_minutes',
        'message',
        'send_email',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
            'before_minutes' => 'integer',
            'send_email' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Reminder $reminder): void {
            $reminder->created_by ??= Auth::id();
        });

        // Moved into the future (edited, or its record rescheduled): it is a
        // new alert, so everyone gets it again, even if the old time had
        // already fired and been turned off.
        static::updated(function (Reminder $reminder): void {
            if ($reminder->wasChanged('remind_at') && $reminder->remind_at->isFuture()) {
                $reminder->recipientRows()->update(['sent_at' => null, 'dismissed_at' => null]);
            }
        });
    }

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'epesi_reminder_recipients')
            ->using(ReminderRecipient::class)
            ->withPivot(['id', 'sent_at', 'dismissed_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<ReminderRecipient, $this>
     */
    public function recipientRows(): HasMany
    {
        return $this->hasMany(ReminderRecipient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isRelative(): bool
    {
        return $this->before_minutes !== null;
    }

    /**
     * Reminders whose recipient list includes $user and which they have not
     * turned off.
     */
    public function scopeActiveFor(Builder $query, User $user): Builder
    {
        return $query->whereHas('recipientRows', fn (Builder $q) => $q
            ->where('user_id', $user->getKey())
            ->whereNull('dismissed_at'));
    }

    /** "30 minutes before", "2 hours before", "1 day before", or "At a fixed time". */
    public function timingLabel(): string
    {
        return $this->isRelative() ? static::beforeLabel($this->before_minutes).' before' : 'At a fixed time';
    }

    public static function beforeLabel(int $minutes): string
    {
        [$amount, $unit] = static::splitMinutes($minutes);

        return $amount.' '.Str::plural(Str::singular($unit), $amount);
    }

    /**
     * The largest whole unit an offset is a multiple of, for filling the
     * "N minutes/hours/days" form back in.
     *
     * @return array{0: int, 1: 'minutes'|'hours'|'days'}
     */
    public static function splitMinutes(int $minutes): array
    {
        return match (true) {
            $minutes > 0 && $minutes % 1440 === 0 => [intdiv($minutes, 1440), 'days'],
            $minutes > 0 && $minutes % 60 === 0 => [intdiv($minutes, 60), 'hours'],
            default => [$minutes, 'minutes'],
        };
    }
}
