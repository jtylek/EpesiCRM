<?php

namespace Epesi\Modules\Mail\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One record a message belongs to — an entry of rc_mails' Contacts or
 * Related multiselect.
 */
class MailLink extends Model
{
    protected $table = 'epesi_mail_links';

    protected $fillable = [
        'mail_id',
        'linkable_type',
        'linkable_id',
    ];

    /**
     * @return BelongsTo<Mail, $this>
     */
    public function mail(): BelongsTo
    {
        return $this->belongsTo(Mail::class);
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
