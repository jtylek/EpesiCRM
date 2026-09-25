<?php

namespace Epesi\Modules\Mail\LegacyImport;

use App\Models\User;
use App\Services\FileStorage;
use App\Services\LegacyImport\ImportSummary;
use App\Services\LegacyImport\LegacyIdMap;
use App\Services\LegacyImport\LegacyValue;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Meetings\Models\Meeting;
use Epesi\Modules\CRM\PhoneCalls\Models\PhoneCall;
use Epesi\Modules\CRM\Tasks\Models\Task;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Models\MailAddress;
use Epesi\Modules\Mail\Models\MailThread;
use Epesi\Modules\Mail\Services\MailArchiver;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `import:legacy mail`: CRM/Mail's data from the legacy Epesi database —
 *
 *   rc_accounts        → MailAccount (passwords decrypted with the legacy
 *                        install's key file, re-encrypted with this app's key)
 *   rc_multiple_emails → MailAddress
 *   rc_mails           → Mail, with its Contacts/Related links, its thread and
 *                        its attachments (read from the legacy data directory)
 *
 * Idempotent like the core importers: mails upsert by `legacy_id`, accounts by
 * owner + address, extra addresses by address, and a mail's links and
 * attachments are rebuilt on every run. Must run after users, companies,
 * contacts, tasks, meetings and phone calls, which it links to.
 *
 * Mail edit history isn't replayed: the only editable part of an archived
 * message was its links.
 */
class MailImporter
{
    /** Legacy recordset name of a "Related" token => this app's model. */
    protected const RELATED = [
        'contact' => Contact::class,
        'company' => Company::class,
        'task' => Task::class,
        'crm_meeting' => Meeting::class,
        'phonecall' => PhoneCall::class,
    ];

    protected ImportSummary $summary;

    /** @var array<class-string, LegacyIdMap> */
    protected array $maps = [];

    protected ?string $dataDir;

    public function __construct(protected MailArchiver $archiver)
    {
        $dir = config('epesi-mail.legacy_data_dir');
        $this->dataDir = filled($dir) ? rtrim((string) $dir, '/\\') : null;
    }

    public function run(bool $withHistory = true): ImportSummary
    {
        $this->summary = new ImportSummary;

        if (! $this->legacy()->getSchemaBuilder()->hasTable('rc_mails_data_1')) {
            $this->summary->warn('the legacy database has no CRM/Mail tables (rc_mails_data_1), nothing to import');

            return $this->summary;
        }

        if ($this->dataDir === null) {
            $this->summary->warn('LEGACY_DATA_DIR is not set: attachments and encrypted account passwords are skipped (point it at the legacy install\'s data/ directory)');
        }

        foreach ([User::class, Contact::class, Company::class, Task::class, Meeting::class, PhoneCall::class] as $class) {
            $this->maps[$class] = LegacyIdMap::for($class);
        }

        $this->importAccounts();
        $this->importAddresses();
        $this->importMails();

        return $this->summary;
    }

    // ------------------------------------------------------------ Accounts --

    protected function importAccounts(): void
    {
        if (! $this->legacy()->getSchemaBuilder()->hasTable('rc_accounts_data_1')) {
            return;
        }

        foreach ($this->legacy()->table('rc_accounts_data_1')->where('active', 1)->orderBy('id')->cursor() as $row) {
            $userId = $this->maps[User::class]->get((int) $row->f_epesi_user);

            if ($userId === null) {
                $this->summary->warn("rc_accounts#{$row->id}: its user wasn't imported, skipped");

                continue;
            }

            [$imapHost, $imapPort] = $this->hostAndPort((string) $row->f_server);
            [$smtpHost, $smtpPort] = $this->hostAndPort((string) ($row->f_smtp_server ?? ''));
            $root = trim((string) ($row->f_imap_root ?? ''), '.');

            $account = MailAccount::query()->firstOrNew(['user_id' => $userId, 'email' => (string) $row->f_email]);
            $isNew = ! $account->exists;

            $account->fill([
                'name' => $row->f_account_name ?: $row->f_email,
                'imap_host' => $imapHost,
                'imap_port' => $imapPort,
                'imap_security' => $this->security($row->f_security ?? null),
                'imap_login' => $row->f_login,
                'smtp_host' => $smtpHost,
                'smtp_port' => $smtpPort,
                'smtp_security' => $this->security($row->f_smtp_security ?? null),
                'smtp_auth' => (bool) ($row->f_smtp_auth ?? false),
                'smtp_login' => $row->f_smtp_login ?: null,
                'is_default' => (bool) ($row->f_default_account ?? false),
                'archive_on_sending' => (bool) ($row->f_archive_on_sending ?? false),
                // Epesi's Roundcube plugin put its folder under the IMAP root
                // when the account had one ("INBOX.CRM Archive").
                'archive_folder' => ($root !== '' ? $root.'.' : '').'CRM Archive',
            ]);

            foreach (['imap_password' => 'f_password', 'smtp_password' => 'f_smtp_password'] as $column => $field) {
                $password = $this->password($row->{$field} ?? null, "rc_accounts#{$row->id}.{$field}");

                if ($password !== null) {
                    $account->{$column} = $password;
                }
            }

            $account->save();
            $isNew ? $this->summary->created++ : $this->summary->updated++;
        }
    }

    /**
     * @return array{0: string|null, 1: int|null}
     */
    protected function hostAndPort(string $server): array
    {
        $server = trim($server);

        if ($server === '') {
            return [null, null];
        }

        if (preg_match('/^(.+):(\d+)$/', $server, $m)) {
            return [$m[1], (int) $m[2]];
        }

        return [$server, null];
    }

    protected function security(?string $value): string
    {
        return in_array($value, ['ssl', 'tls'], true) ? $value : 'none';
    }

    /**
     * A stored account password: AES-256-GCM with the legacy install's
     * `data/CRM_Mail/encryption.key` (CRM_MailCommon::encrypt(), since the
     * 2026-08-29 patch), or plain text on an install from before it.
     */
    protected function password(?string $stored, string $what): ?string
    {
        if (blank($stored)) {
            return null;
        }

        $raw = base64_decode($stored, true);
        $looksEncrypted = $raw !== false && strlen($raw) >= 28;

        if (! $looksEncrypted) {
            return $stored;
        }

        $keyFile = $this->dataDir ? $this->dataDir.'/CRM_Mail/encryption.key' : null;

        if ($keyFile === null || ! is_file($keyFile)) {
            $this->summary->warn("{$what}: looks encrypted but the legacy key file isn't available, left empty");

            return null;
        }

        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', (string) file_get_contents($keyFile), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));

        if ($plain === false) {
            $this->summary->warn("{$what}: could not be decrypted with the legacy key, left empty");

            return null;
        }

        return $plain;
    }

    // ----------------------------------------------------------- Addresses --

    protected function importAddresses(): void
    {
        if (! $this->legacy()->getSchemaBuilder()->hasTable('rc_multiple_emails_data_1')) {
            return;
        }

        foreach ($this->legacy()->table('rc_multiple_emails_data_1')->where('active', 1)->orderBy('id')->cursor() as $row) {
            $class = ['contact' => Contact::class, 'company' => Company::class][$row->f_record_type] ?? null;
            $id = $class ? $this->maps[$class]->get((int) $row->f_record_id) : null;

            if ($id === null || blank($row->f_email)) {
                $this->summary->warn("rc_multiple_emails#{$row->id}: its {$row->f_record_type} wasn't imported, skipped");

                continue;
            }

            $address = MailAddress::query()->firstOrNew(['email' => mb_strtolower(trim($row->f_email))]);
            $isNew = ! $address->exists;
            $address->fill(['addressable_type' => (new $class)->getMorphClass(), 'addressable_id' => $id])->save();
            $isNew ? $this->summary->created++ : $this->summary->updated++;
        }
    }

    // --------------------------------------------------------------- Mails --

    protected function importMails(): void
    {
        /** @var array<int, array<int, int>> legacy thread id => new mail ids */
        $threads = [];
        $unthreaded = [];

        foreach ($this->legacy()->table('rc_mails_data_1')->orderBy('id')->cursor() as $row) {
            $mail = $this->importMail($row);

            if ((int) ($row->f_thread ?? 0) > 0) {
                $threads[(int) $row->f_thread][] = $mail->getKey();
            } else {
                $unthreaded[] = $mail;
            }
        }

        foreach ($threads as $mailIds) {
            $this->joinThread($mailIds);
        }

        foreach ($unthreaded as $mail) {
            $this->archiver->thread($mail->refresh());
        }
    }

    protected function importMail(object $row): Mail
    {
        $mail = Mail::withTrashed()->firstOrNew(['legacy_id' => $row->id]);
        $isNew = ! $mail->exists;

        $userId = $this->maps[User::class]->get((int) ($row->created_by ?? 0));
        $employeeId = $this->maps[Contact::class]->get((int) ($row->f_employee ?? 0) ?: null);
        $headers = (string) ($row->f_headers_data ?? '');
        $body = $this->localiseInlineImages((string) ($row->f_body ?? ''));

        $mail->forceFill([
            'message_id' => filled($row->f_message_id) ? trim((string) $row->f_message_id, '<> ') : null,
            'in_reply_to' => $this->header($headers, 'in_reply_to') ? trim($this->header($headers, 'in_reply_to'), '<> ') : null,
            'references' => $row->f_references ?: null,
            'subject' => Str::limit((string) $row->f_subject, 500, ''),
            'from' => $row->f_from,
            'to' => $row->f_to,
            'cc' => $this->header($headers, 'cc'),
            'date' => $row->f_date ?: $row->created_on,
            'body_html' => $body,
            'body_text' => trim(html_entity_decode(strip_tags((string) preg_replace('/<br\s*\/?>/i', "\n", $body)))),
            'headers' => $headers ?: null,
            'direction' => $this->direction((string) $row->f_from, $employeeId, $userId),
            'employee_id' => $employeeId,
            'user_id' => $userId,
            'legacy_id' => $row->id,
        ]);
        $mail->timestamps = false;
        $mail->created_at = $row->created_on;
        $mail->updated_at = $row->created_on;
        $mail->deleted_at = (int) ($row->active ?? 1) === 0 ? $row->created_on : null;
        $mail->save();

        $isNew ? $this->summary->created++ : $this->summary->updated++;

        $this->importLinks($mail, $row);
        $this->importAttachments($mail, (int) $row->id, $row->f_date ?: $row->created_on);

        return $mail;
    }

    /**
     * Epesi rewrote an inline image's `cid:` to its own download URL
     * (`get.php?mail_id=__MAIL_ID__&mime_id=<md5>`) when archiving. Turning
     * that back into `cid:<md5>` — and storing the attachment under that
     * Content-ID — lets Mail::displayHtml() point it at the imported file.
     */
    protected function localiseInlineImages(string $html): string
    {
        return (string) preg_replace(
            '/[^"\'\s>]*get\.php\?[^"\'\s>]*?mime_id=([0-9a-f]{32})[^"\'\s>]*/i',
            'cid:$1',
            $html,
        );
    }

    /**
     * rc_mails.headers_data is "name: value" lines, names as Roundcube's
     * header object spells them (cc, in_reply_to, ...).
     */
    protected function header(string $headers, string $name): ?string
    {
        return preg_match('/^'.preg_quote($name, '/').':[ \t]*(.+)$/mi', $headers, $m) ? trim($m[1]) : null;
    }

    protected function direction(string $from, ?int $employeeId, ?int $userId): string
    {
        $own = array_filter([
            $employeeId ? Contact::withTrashed()->find($employeeId)?->email : null,
            ...($userId ? MailAccount::query()->where('user_id', $userId)->pluck('email')->all() : []),
        ]);

        foreach ($own as $address) {
            if (stripos($from, (string) $address) !== false) {
                return Mail::OUTGOING;
            }
        }

        return Mail::INCOMING;
    }

    /**
     * rc_mails' Contacts ("contact/5", older "P:5"/"C:5", or a bare contact id)
     * and Related ("task/7", "crm_meeting/3", ...) become links, and so does
     * the Employee who archived it: Epesi listed a message on its employee's
     * contact too, as MailArchiver now links whoever archives.
     */
    protected function importLinks(Mail $mail, object $row): void
    {
        $mail->links()->delete();

        if ($mail->employee) {
            $mail->linkTo($mail->employee);
        }

        $tokens = [...LegacyValue::multi($row->f_contacts ?? null), ...LegacyValue::multi($row->f_related ?? null)];

        foreach ($tokens as $token) {
            $record = $this->resolveToken($token);

            if ($record === null) {
                $this->summary->warn("rc_mails#{$row->id}: linked record \"{$token}\" wasn't imported, link skipped");

                continue;
            }

            $mail->linkTo($record);
        }
    }

    protected function resolveToken(string $token): ?Model
    {
        $token = trim($token);

        if (ctype_digit($token)) {
            [$tab, $id] = ['contact', (int) $token];
        } elseif (preg_match('#^([A-Za-z_0-9]+)[:/](\d+)$#', $token, $m)) {
            $tab = ['P' => 'contact', 'C' => 'company'][$m[1]] ?? $m[1];
            $id = (int) $m[2];
        } else {
            return null;
        }

        $class = self::RELATED[$tab] ?? null;
        $newId = $class ? $this->maps[$class]->get($id) : null;

        return $newId ? $class::query()->withoutGlobalScopes()->find($newId) : null;
    }

    protected function importAttachments(Mail $mail, int $legacyMailId, ?string $date): void
    {
        // A previous run's rows go only once this run's are in: their files
        // are then found already in the file storage, instead of being
        // deleted from it and copied in again.
        $previous = $mail->attachments()->get();

        $rows = $this->dataDir !== null && $this->legacy()->getSchemaBuilder()->hasTable('rc_mails_attachments')
            ? $this->legacy()->table('rc_mails_attachments')->where('mail_id', $legacyMailId)->get()
            : collect();

        foreach ($rows as $row) {
            $source = $this->attachmentPath($legacyMailId, $row);

            if ($source === null) {
                $this->summary->warn("rc_mails#{$legacyMailId}: attachment \"{$row->name}\" not found in the legacy data directory, skipped");

                continue;
            }

            $name = Str::limit(trim((string) preg_replace('/[^\pL\pN._\- ]+/u', '_', basename((string) $row->name)), '. ') ?: 'attachment', 150, '');
            $file = app(FileStorage::class)->putFile($source, $name);

            $mail->attachments()->create([
                'name' => $name,
                'mime_type' => Str::limit((string) ($row->type ?: 'application/octet-stream'), 120, ''),
                'size' => $file->size(),
                'content_id' => $row->mime_id,
                'inline' => (int) ($row->attachment ?? 1) === 0,
                'stored_file_id' => $file->getKey(),
            ]);
        }

        $previous->each->delete();
    }

    /**
     * Where the attachment's bytes are: Utils_FileStorage (content-addressed
     * by sha512, the layout since the 2026-06-29 patch, and the same one
     * App\Services\FileStorage uses), falling back to the older per-mail
     * directory `CRM_Mail/attachments/<mail id>/<mime id>` of an unpatched install.
     */
    protected function attachmentPath(int $legacyMailId, object $row): ?string
    {
        if (! empty($row->file_id)) {
            $hash = $this->legacy()->table('utils_filestorage as s')
                ->join('utils_filestorage_files as f', 'f.id', '=', 's.file_id')
                ->where('s.id', $row->file_id)
                ->value('f.hash');

            if ($hash) {
                $path = $this->dataDir.'/Utils_FileStorage/'.FileStorage::pathFor($hash);

                if (is_file($path)) {
                    return $path;
                }
            }
        }

        $old = $this->dataDir.'/CRM_Mail/attachments/'.$legacyMailId.'/'.$row->mime_id;

        return is_file($old) ? $old : null;
    }

    /**
     * One legacy rc_mail_threads record → one thread here, reusing the one an
     * earlier run created so re-imports don't multiply threads.
     *
     * @param  array<int, int>  $mailIds
     */
    protected function joinThread(array $mailIds): void
    {
        $threadId = Mail::withTrashed()->whereKey($mailIds)->whereNotNull('thread_id')->value('thread_id');
        $thread = ($threadId ? MailThread::query()->find($threadId) : null) ?? MailThread::create();

        Mail::withTrashed()->whereKey($mailIds)->update(['thread_id' => $thread->getKey()]);
        $thread->refreshSummary();
    }

    protected function legacy(): Connection
    {
        return DB::connection('legacy');
    }
}
