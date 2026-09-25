<?php

namespace Epesi\Modules\Mail\Models;

use App\Models\User;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One archived message — an `rc_mails` record. Everything but its links is
 * what the message said, so nothing but the links is editable (Epesi's edit
 * access excluded every other field).
 */
class Mail extends Model
{
    use LogsActivity, SoftDeletes;

    public const INCOMING = 'in';

    public const OUTGOING = 'out';

    protected $table = 'epesi_mails';

    protected $fillable = [
        'thread_id',
        'message_id',
        'in_reply_to',
        'references',
        'subject',
        'from',
        'to',
        'cc',
        'date',
        'body_html',
        'body_text',
        'headers',
        'direction',
        'employee_id',
        'user_id',
        'account_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The database would cascade the attachment rows away without a model
        // event, leaving their files in the file storage for good.
        static::forceDeleting(fn (Mail $mail) => $mail->attachments()->get()->each->delete());
    }

    /**
     * @return BelongsTo<MailThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(MailThread::class, 'thread_id');
    }

    /**
     * @return HasMany<MailLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(MailLink::class);
    }

    /**
     * @return HasMany<MailAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MailAttachment::class);
    }

    /**
     * The CRM contact of the user whose mailbox this came from — rc_mails'
     * Employee field.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'employee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Who archived it — what RecordBrowser's Record Info tab calls "created by".
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<MailAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'account_id');
    }

    public function linkTo(Model $record): void
    {
        $this->links()->firstOrCreate([
            'linkable_type' => $record->getMorphClass(),
            'linkable_id' => $record->getKey(),
        ]);
    }

    /**
     * @return Collection<int, Model>
     */
    public function linkedRecords(): Collection
    {
        return $this->links()->with('linkable')->get()->pluck('linkable')->filter()->values();
    }

    /**
     * The HTML body ready for the sandboxed viewer: inline images (`cid:`)
     * pointed at their stored attachment. Plain-text mail is wrapped so it
     * keeps its line breaks.
     */
    public function displayHtml(): string
    {
        if (blank($this->body_html)) {
            return '<pre style="white-space:pre-wrap;font:14px/1.5 system-ui,sans-serif">'.e((string) $this->body_text).'</pre>';
        }

        $byCid = $this->attachments
            ->filter(fn (MailAttachment $a): bool => filled($a->content_id))
            ->mapWithKeys(fn (MailAttachment $a): array => [trim((string) $a->content_id, '<>') => $a->url(inline: true)]);

        return (string) preg_replace_callback(
            '/cid:([^"\'\s>)]+)/i',
            fn (array $m): string => $byCid[$m[1]] ?? $m[0],
            (string) $this->body_html,
        );
    }

    /**
     * Every address the message was from, to or copied to, lower-cased.
     *
     * @return array<int, string>
     */
    public function addresses(): array
    {
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', implode(',', [$this->from, $this->to, $this->cc]), $m);

        return array_values(array_unique(array_map('mb_strtolower', $m[0])));
    }

    public function snippet(int $length = 120): string
    {
        $text = filled($this->body_text) ? $this->body_text : strip_tags((string) $this->body_html);

        return Str::limit(trim((string) preg_replace('/\s+/', ' ', html_entity_decode((string) $text))), $length);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['subject'])
            ->logOnlyDirty()
            ->useLogName('mail');
    }
}
