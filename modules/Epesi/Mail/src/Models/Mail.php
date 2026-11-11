<?php

namespace Epesi\Modules\Mail\Models;

use App\Models\User;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\Mail\Support\PlainBody;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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

        // Staff see every archived mail. A consultant sees only his own.
        // Being linked to a record he sees doesn't make someone else's
        // correspondence his.
        static::addGlobalScope('ownership', function (Builder $builder): void {
            $user = Auth::user();

            if ($user instanceof User && $user->isConsultant()) {
                $builder->mine($user);
            }
        });
    }

    /**
     * A user's own mail, for the consultant's limit and everyone's "My
     * records": what he archived or is the employee of, what he sent or
     * received, and what came through a mailbox he can sign in to.
     *
     * Sent or received is his contact's link: archiving links every contact
     * whose address is, as a whole, in From, To or Cc, so this needs no
     * matching on those text columns (ContactMatcher).
     */
    public function scopeMine(Builder $query, User $user): void
    {
        $contacts = Contact::query()->withoutGlobalScope('ownership')->where('user_id', $user->id)->select('id');

        $query->where(fn (Builder $query) => $query
            ->where($query->qualifyColumn('user_id'), $user->id)
            ->orWhereIn($query->qualifyColumn('employee_id'), $contacts)
            ->orWhereHas('links', fn (Builder $links) => $links
                ->where('linkable_type', (new Contact)->getMorphClass())
                ->whereIn('linkable_id', $contacts))
            ->orWhereIn($query->qualifyColumn('account_id'), MailAccount::idsOpenTo($user)));
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
     * Who archived it — what RecordBrowser's Record Info entries call "created by".
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
     * pointed at their stored attachment, then stripped down to plain
     * formatting (PlainBody) so every message reads the same way regardless
     * of what the sender's own CSS did — see PlainBody's own docblock.
     * Plain-text mail is wrapped so it keeps its line breaks; it carries no
     * sender styling to begin with, so it skips PlainBody.
     */
    public function displayHtml(): string
    {
        if (blank($this->body_html)) {
            return $this->plainTextHtml();
        }

        $byCid = $this->attachments
            ->filter(fn (MailAttachment $a): bool => filled($a->content_id))
            ->mapWithKeys(fn (MailAttachment $a): array => [trim((string) $a->content_id, '<>') => $a->url(inline: true)]);

        $html = (string) preg_replace_callback(
            '/cid:([^"\'\s>)]+)/i',
            fn (array $m): string => $byCid[$m[1]] ?? $m[0],
            (string) $this->body_html,
        );

        $sanitized = PlainBody::sanitize($html);

        return filled($sanitized) || blank($this->body_text)
            ? $sanitized
            : $this->plainTextHtml();
    }

    protected function plainTextHtml(): string
    {
        return '<pre style="white-space:pre-wrap;font:14px/1.5 system-ui,sans-serif">'.e((string) $this->body_text).'</pre>';
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
