<?php

namespace Epesi\Modules\Mail\Services;

use App\Models\User;
use App\Services\FileStorage;
use Carbon\CarbonImmutable;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Models\MailThread;
use Epesi\Modules\Watchdog\Watchdog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use ZBateson\MailMimeParser\Header\AddressHeader;
use ZBateson\MailMimeParser\Header\DateHeader;
use ZBateson\MailMimeParser\Header\Part\AddressPart;
use ZBateson\MailMimeParser\IMessage;
use ZBateson\MailMimeParser\Message;

/**
 * Turns a raw RFC 822 message into an archived Mail — the port of the
 * Roundcube `epesi_archive` plugin plus CRM_MailCommon::archive_message() and
 * create_thread(). Every way a message reaches the archive (IMAP fetch, an
 * uploaded .eml, a message sent from the CRM) comes through here, so matching,
 * de-duplication and threading behave the same for all of them.
 */
class MailArchiver
{
    public function __construct(protected ContactMatcher $matcher) {}

    /**
     * @param  array<int, Model>  $links  records to link besides the matched contacts/companies
     * @param  bool  $onlyIfMatched  archive nothing when no address matches a contact or company
     *                               (auto-archive of a whole folder)
     * @return Mail|null the archived message (the existing one for a duplicate), or null when skipped
     */
    public function archive(
        string $raw,
        ?User $user = null,
        ?MailAccount $account = null,
        array $links = [],
        bool $onlyIfMatched = false,
        ?string $direction = null,
    ): ?Mail {
        $message = Message::from($raw, false);

        $messageId = $this->stripAngles($message->getHeaderValue('Message-ID'));
        $from = $this->addresses($message, 'From');
        $to = $this->addresses($message, 'To');
        $cc = $this->addresses($message, 'Cc');
        $subject = Str::limit((string) $message->getSubject(), 500, '');
        $date = $this->date($message);

        // One archiving of a message at a time, across users and processes:
        // the scheduled fetch and the Mailbox's Archive button, or two people
        // filing the same message, must not both find it missing and store it.
        $key = $messageId ?? implode('|', [$this->format($from), $this->format($to), $subject, $date?->toIso8601String()]);

        return Cache::lock('epesi-mail-archive:'.sha1($key), 60)->block(30, function () use ($message, $messageId, $from, $to, $cc, $subject, $date, $user, $account, $links, $onlyIfMatched, $direction): ?Mail {
            if ($existing = $this->existing($messageId, $from, $to, $subject, $date)) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                foreach ($links as $record) {
                    $existing->linkTo($record);
                }

                return $existing;
            }

            return $this->store($message, $messageId, $from, $to, $cc, $subject, $date, $user, $account, $links, $onlyIfMatched, $direction);
        });
    }

    /**
     * The archived copy of a message, even a deleted one: the archive is
     * shared, so a message is kept once however many people file it. Found by
     * Message-ID, or, for the rare message without one, by sender, recipients,
     * subject and date.
     *
     * @param  array<string, string>  $from
     * @param  array<string, string>  $to
     */
    protected function existing(?string $messageId, array $from, array $to, string $subject, ?CarbonImmutable $date): ?Mail
    {
        $query = Mail::withTrashed();

        if ($messageId !== null) {
            return $query->where('message_id', $messageId)->first();
        }

        return $query->whereNull('message_id')
            ->where('from', $this->format($from))
            ->where('to', $this->format($to))
            ->where('subject', $subject)
            ->where('date', $date)
            ->first();
    }

    /**
     * @param  array<string, string>  $from
     * @param  array<string, string>  $to
     * @param  array<string, string>  $cc
     * @param  array<int, Model>  $links
     */
    protected function store(
        IMessage $message,
        ?string $messageId,
        array $from,
        array $to,
        array $cc,
        string $subject,
        ?CarbonImmutable $date,
        ?User $user,
        ?MailAccount $account,
        array $links,
        bool $onlyIfMatched,
        ?string $direction,
    ): ?Mail {
        $employee = $user?->contact;
        $ownAddresses = array_filter([
            mb_strtolower((string) $account?->email),
            mb_strtolower((string) $employee?->email),
            ...($employee ? $employee->mailAddresses()->pluck('email')->all() : []),
        ]);

        // Your own contact, matched by your addresses or not, is always
        // linked: whoever archives a message is the first thing the archive
        // has to tell, above all for a shared mailbox (support@...), whose
        // addresses say nothing about who filed it. It doesn't make a message
        // "involve a known contact" for auto-archive, though: your address is
        // on every message in your mailbox.
        $matched = $this->matcher->match(array_keys([...$from, ...$to, ...$cc]));
        $others = $matched->reject(fn (Model $m): bool => $employee !== null && $m instanceof Contact && $m->is($employee));

        if ($employee !== null) {
            $matched->prepend($employee);
        }

        if ($onlyIfMatched && $others->isEmpty() && $links === []) {
            return null;
        }

        $direction ??= $from !== [] && array_intersect(array_keys($from), $ownAddresses) !== []
            ? Mail::OUTGOING
            : Mail::INCOMING;

        return DB::transaction(function () use ($message, $messageId, $from, $to, $cc, $subject, $date, $user, $account, $employee, $matched, $links, $direction): Mail {
            $mail = Mail::create([
                'message_id' => $messageId,
                'in_reply_to' => $this->stripAngles($message->getHeaderValue('In-Reply-To')),
                'references' => $message->getHeaderValue('References'),
                'subject' => $subject,
                'from' => $this->format($from),
                'to' => $this->format($to),
                'cc' => $this->format($cc) ?: null,
                'date' => $date,
                'body_html' => $message->getHtmlContent(),
                'body_text' => $message->getTextContent(),
                'headers' => $this->headerBlock($message),
                'direction' => $direction,
                'employee_id' => $employee?->getKey(),
                'user_id' => $user?->getKey(),
                'account_id' => $account?->getKey(),
            ]);

            foreach ([...$matched->all(), ...$links] as $record) {
                $mail->linkTo($record);
            }

            $this->storeAttachments($mail, $message);
            $this->thread($mail);
            $this->subscribeWatchers($mail);

            return $mail;
        });
    }

    /**
     * CRM_MailCommon::create_thread(): join the thread of a message this one
     * answers (In-Reply-To/References), or of a message that already answers
     * this one (they can arrive out of order), else start a new thread.
     */
    public function thread(Mail $mail): MailThread
    {
        $ancestors = array_filter([
            $mail->in_reply_to,
            ...$this->messageIds((string) $mail->references),
        ]);

        $threadId = $ancestors === [] ? null : Mail::query()
            ->whereIn('message_id', $ancestors)
            ->whereNotNull('thread_id')
            ->value('thread_id');

        if ($threadId === null && filled($mail->message_id)) {
            $threadId = Mail::query()
                ->whereKeyNot($mail->getKey())
                ->whereNotNull('thread_id')
                ->where(fn ($q) => $q->where('in_reply_to', $mail->message_id)
                    ->orWhere('references', 'like', '%'.$mail->message_id.'%'))
                ->value('thread_id');
        }

        $thread = $threadId ? MailThread::query()->find($threadId) : MailThread::create(['subject' => $mail->subject]);

        $mail->forceFill(['thread_id' => $thread->getKey()])->saveQuietly();
        $thread->refreshSummary();

        return $thread;
    }

    protected function storeAttachments(Mail $mail, IMessage $message): void
    {
        $html = (string) $mail->body_html;

        foreach ($message->getAllAttachmentParts() as $index => $part) {
            $content = $part->getBinaryContentStream()?->getContents() ?? '';
            $name = $this->safeName($part->getFilename() ?: 'attachment-'.($index + 1));
            $file = app(FileStorage::class)->put($content, $name);

            $cid = $part->getContentId();

            $mail->attachments()->create([
                'name' => $name,
                'mime_type' => Str::limit($part->getContentType('application/octet-stream'), 120, ''),
                'size' => strlen($content),
                'content_id' => $cid,
                // Inline only when the body actually shows it; an image with a
                // Content-ID nobody references is still something to download.
                'inline' => filled($cid) && str_contains($html, 'cid:'.trim((string) $cid, '<>')),
                'stored_file_id' => $file->getKey(),
            ]);
        }
    }

    /**
     * CRM_MailCommon::subscribe_users_to_record(): whoever watches a contact
     * or company this message is linked to also watches the message.
     */
    protected function subscribeWatchers(Mail $mail): void
    {
        if (! class_exists(Watchdog::class)) {
            return;
        }

        foreach ($mail->linkedRecords() as $record) {
            foreach (Watchdog::subscribersOf($record) as $watcher) {
                Watchdog::subscribe($watcher, $mail);
            }
        }
    }

    /**
     * @return array<string, string> lower-cased address => display name
     */
    protected function addresses(IMessage $message, string $header): array
    {
        $result = [];
        $h = $message->getHeader($header);

        if (! $h instanceof AddressHeader) {
            return $result;
        }

        foreach ($h->getAddresses() as $address) {
            /** @var AddressPart $address */
            $email = mb_strtolower(trim($address->getEmail()));

            if ($email !== '') {
                $result[$email] = trim((string) $address->getName());
            }
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $addresses
     */
    protected function format(array $addresses): string
    {
        return collect($addresses)
            ->map(fn (string $name, string $email): string => $name !== '' ? "{$name} <{$email}>" : $email)
            ->implode(', ');
    }

    protected function date(IMessage $message): CarbonImmutable
    {
        $header = $message->getHeader('Date');

        try {
            if ($header instanceof DateHeader && $header->getDateTimeImmutable()) {
                // Stored in the app's timezone: the datetime cast writes the
                // wall-clock time as-is, so "10:15 +0200" would otherwise
                // come back as 10:15 UTC.
                return CarbonImmutable::instance($header->getDateTimeImmutable())->setTimezone(config('app.timezone'));
            }
        } catch (Throwable) {
            // An unparseable Date header is common enough in the wild; fall
            // back to the time it was archived, as Epesi did.
        }

        return CarbonImmutable::now();
    }

    protected function headerBlock(IMessage $message): string
    {
        return collect($message->getAllHeaders())
            ->reject(fn ($h): bool => in_array(strtolower($h->getName()), ['from', 'to', 'cc', 'bcc'], true))
            ->map(fn ($h): string => $h->getName().': '.($h->getValue() ?? $h->getRawValue()))
            ->implode("\n");
    }

    protected function stripAngles(?string $id): ?string
    {
        $id = trim((string) $id);

        return $id === '' ? null : Str::limit(trim($id, '<> '), 500, '');
    }

    /**
     * @return array<int, string>
     */
    protected function messageIds(string $header): array
    {
        preg_match_all('/<([^>]+)>/', $header, $m);

        return $m[1];
    }

    protected function safeName(string $name): string
    {
        $name = trim((string) preg_replace('/[^\pL\pN._\- ]+/u', '_', basename($name)), '. ');

        return Str::limit($name !== '' ? $name : 'attachment', 150, '');
    }
}
