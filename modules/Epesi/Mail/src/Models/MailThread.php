<?php

namespace Epesi\Modules\Mail\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A conversation — an `rc_mail_threads` record, kept up to date by
 * MailArchiver as messages join it.
 */
class MailThread extends Model
{
    protected $table = 'epesi_mail_threads';

    protected $fillable = [
        'subject',
        'first_date',
        'last_date',
        'message_count',
    ];

    protected function casts(): array
    {
        return [
            'first_date' => 'datetime',
            'last_date' => 'datetime',
            'message_count' => 'integer',
        ];
    }

    /**
     * @return HasMany<Mail, $this>
     */
    public function mails(): HasMany
    {
        return $this->hasMany(Mail::class, 'thread_id');
    }

    /**
     * CRM_MailCommon::create_thread()'s bookkeeping, recomputed rather than
     * patched so a deleted message can never leave the counts wrong.
     */
    public function refreshSummary(): void
    {
        $mails = $this->mails()->orderBy('date')->get(['subject', 'date', 'references', 'in_reply_to']);

        if ($mails->isEmpty()) {
            $this->delete();

            return;
        }

        // The thread is named after its opening message (the one that
        // references nothing), else the shortest subject — "Re: Re: Offer"
        // loses to "Offer".
        $opening = $mails->first(fn (Mail $m): bool => blank($m->references) && blank($m->in_reply_to));
        $subject = $opening?->subject ?? $mails->sortBy(fn (Mail $m): int => mb_strlen((string) $m->subject))->first()->subject;

        $this->update([
            'subject' => $subject,
            'first_date' => $mails->min('date'),
            'last_date' => $mails->max('date'),
            'message_count' => $mails->count(),
        ]);
    }
}
