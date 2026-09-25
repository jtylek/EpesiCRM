<?php

namespace Epesi\Modules\Reminders\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * One recipient of a reminder and whether it has reached them — a row of
 * Epesi's `utils_messenger_users`.
 */
class ReminderRecipient extends Pivot
{
    protected $table = 'epesi_reminder_recipients';

    public $incrementing = true;

    protected $fillable = ['reminder_id', 'user_id', 'sent_at', 'dismissed_at'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
