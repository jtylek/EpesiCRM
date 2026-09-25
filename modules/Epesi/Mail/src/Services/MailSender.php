<?php

namespace Epesi\Modules\Mail\Services;

use App\Models\User;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\Imap\MailboxFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Sends a message through the user's own SMTP account and, when asked,
 * archives the sent copy linked to its recipients — Epesi's compose window
 * with "Archive on sending".
 */
class MailSender
{
    /** @var array<int, string> problems after the message went out, e.g. the Sent copy failed */
    protected array $warnings = [];

    public function __construct(
        protected SmtpTransportFactory $transports,
        protected MailArchiver $archiver,
        protected MailboxFactory $mailboxes,
    ) {}

    /**
     * What went wrong after the last send() had already delivered the
     * message — nothing here means the message was not sent.
     *
     * @return array<int, string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @param  array<int, string>  $to
     * @param  array<int, string>  $cc
     * @param  array<int, string>  $bcc
     * @param  array<int, array{path: string, name: string, disk?: string}>  $files
     * @param  array<int, Model>  $links  records the archived copy is linked to
     * @return Mail|null the archived copy, if archived
     */
    public function send(
        MailAccount $account,
        User $user,
        array $to,
        string $subject,
        string $html,
        array $cc = [],
        array $bcc = [],
        array $files = [],
        ?Mail $inReplyTo = null,
        array $links = [],
        ?bool $archive = null,
    ): ?Mail {
        if (! $account->canSend()) {
            throw new InvalidArgumentException('This mail account has no SMTP server configured.');
        }

        if ($to === []) {
            throw new InvalidArgumentException('A message needs at least one recipient.');
        }

        $email = (new Email)
            ->from(new Address($account->email, (string) ($account->from_name ?: $user->displayName())))
            ->to(...$to)
            ->subject($subject)
            ->html($this->withSignature($html, $account))
            ->text(trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html)))));

        if ($cc !== []) {
            $email->cc(...$cc);
        }

        if ($bcc !== []) {
            $email->bcc(...$bcc);
        }

        if ($inReplyTo?->message_id) {
            $id = '<'.$inReplyTo->message_id.'>';
            $email->getHeaders()->addIdHeader('In-Reply-To', $inReplyTo->message_id);
            $email->getHeaders()->addTextHeader('References', trim($inReplyTo->references.' '.$id));
        }

        foreach ($files as $file) {
            $email->attach(Storage::disk($file['disk'] ?? 'local')->get($file['path']), $file['name']);
        }

        $this->warnings = [];

        $sent = $this->transports->forAccount($account)->send($email);

        // What actually went out: the transport's final message, Message-ID
        // included.
        $raw = $sent?->toString() ?? $email->toString();

        // The user's own Sent folder keeps the Bcc list, as every mail
        // client's copy does. Symfony strips Bcc from the message it sends,
        // so it is put back for this copy only; the CRM archive below never
        // gets it.
        $this->saveToSent($account, $bcc === [] ? $raw : 'Bcc: '.implode(', ', $bcc)."\r\n".$raw);

        if (! ($archive ?? $account->archive_on_sending)) {
            return null;
        }

        $raw = (string) preg_replace('/^Bcc:.*(\r?\n[ \t].*)*\r?\n/mi', '', $raw);

        if ($inReplyTo) {
            $links = [...$links, ...$inReplyTo->linkedRecords()->all()];
        }

        return $this->archiver->archive($raw, $user, $account, $links, direction: Mail::OUTGOING);
    }

    /**
     * SMTP doesn't file anything in Sent — every mail client appends its own
     * copy over IMAP, and so does this. The message is already delivered at
     * this point, so a failure here is a warning, never an error.
     */
    protected function saveToSent(MailAccount $account, string $raw): void
    {
        if (! $account->savesToSent()) {
            return;
        }

        try {
            $mailbox = $this->mailboxes->make($account);

            try {
                $mailbox->append((string) $account->sent_folder, $raw);
            } finally {
                $mailbox->close();
            }
        } catch (Throwable $e) {
            $this->warnings[] = "The message was sent, but no copy was saved in \"{$account->sent_folder}\": {$e->getMessage()}";
        }
    }

    protected function withSignature(string $html, MailAccount $account): string
    {
        $parts = array_filter([$html, $account->signature, config('epesi-mail.global_signature')], 'filled');

        return implode('<br><br>', $parts);
    }
}
