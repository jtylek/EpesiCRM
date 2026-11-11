<?php

namespace Epesi\Modules\Mail\LegacyImport;

use App\Models\User;
use App\Services\FileStorage;
use App\Services\LegacyImport\CountedTables;
use App\Services\LegacyImport\Importer;
use App\Services\LegacyImport\ImportSummary;
use App\Services\LegacyImport\LegacyFileTransfer;
use App\Services\LegacyImport\LegacyIdMap;
use App\Services\LegacyImport\LegacyPendingLinks;
use App\Services\LegacyImport\LegacyRecordRefs;
use App\Services\LegacyImport\LegacyValue;
use App\Services\LegacyImport\TimeBudget;
use App\Services\LegacyImport\TimeBudgetReached;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Models\MailLink;
use Epesi\Modules\Mail\Models\MailThread;
use Epesi\Modules\Mail\Services\MailArchiver;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `import:legacy mail`: CRM/Mail's data from the legacy Epesi database —
 *
 *   rc_accounts → MailAccount (passwords decrypted with the legacy install's
 *                 key file, re-encrypted with this app's key)
 *   rc_mails    → Mail, with its Contacts/Related links, its thread and its
 *                 attachments (read from the legacy data directory)
 *
 * rc_multiple_emails (extra addresses) is imported by ContactsImporter/
 * CompaniesImporter instead, as further items of the contact's/company's own
 * E-mail addresses collection — see Importer::legacyExtraEmails().
 *
 * Idempotent like the core importers: mails upsert by `legacy_id`, accounts by
 * owner + address, and a mail's links and attachments are rebuilt on every
 * run. Must run after users, companies, contacts, tasks, meetings and phone
 * calls, which it links to.
 *
 * Attachment files go through a LegacyFileTransfer: `import:legacy` copies them, the
 * Migration Wizard binds one that MOVES them, so an in-place migration never needs
 * twice the disk space. Both layouts are read: a stock 1.9.1 file at
 * `CRM_Mail/attachments/<mail id>/<mime id>`, and (after the legacy 2026-06-29 patch)
 * the Utils_FileStorage file named by `file_id`, falling back to the per-mail path
 * like the legacy get.php. Each file keeps its mail's own date (`f_date`) as its
 * creation date.
 *
 * Mail edit history isn't replayed: the only editable part of an archived
 * message was its links.
 */
class MailImporter implements CountedTables
{
    /**
     * Every `rc_mails_data_1` row becomes one Mail (an inactive one is soft-deleted). The other tables have no
     * `legacy_id` to count by, so each is compared row by row: see the count methods below.
     */
    public function countedTables(): array
    {
        return [
            [
                'table' => 'rc_mails_data_1', 'model' => Mail::class, 'skip' => null,
                'sides' => ['rc_mails_attachments_download'],
                'pending' => fn (Connection $legacy): array => $this->pendingLinks($legacy),
            ],
            ['table' => 'rc_mails_attachments', 'label' => 'Mail attachments', 'skip_label' => 'file not in the legacy data folder', 'count' => fn (Connection $legacy, array $absent): array => $this->countAttachments($legacy, $absent)],
            ['table' => 'rc_mail_threads_data_1', 'label' => 'Mail threads', 'skip_label' => 'without a live message', 'count' => fn (Connection $legacy): array => $this->countThreads($legacy)],
            ['table' => 'rc_accounts_data_1', 'label' => 'Mail accounts', 'skip_label' => 'inactive', 'count' => fn (Connection $legacy): array => $this->countAccounts($legacy)],
            ['table' => 'rc_multiple_emails_data_1', 'label' => 'Extra e-mail addresses', 'skip_label' => 'inactive or without an owner', 'count' => fn (Connection $legacy): array => $this->countExtraEmails($legacy)],
            Importer::relatedSetting('rc_related_data_1'),
        ];
    }

    /**
     * A mail's Related link to a recordset nothing imports is not carried over (importLinks()).
     *
     * @return list<string>
     */
    private function pendingLinks(Connection $legacy): array
    {
        $links = [];
        foreach ($legacy->table('rc_mails_data_1')->whereNotNull('f_related')->where('f_related', '<>', '')->pluck('f_related') as $raw) {
            foreach (LegacyValue::multi($raw) as $token) {
                $parsed = $this->parseToken($token);
                if ($parsed !== null) {
                    $links[$parsed[0]] = ($links[$parsed[0]] ?? 0) + 1;
                }
            }
        }

        return LegacyPendingLinks::describe($legacy, LegacyRecordRefs::fromImporters(), $links);
    }

    /**
     * A row is accounted for by the attachment of its mail with its part number, or by the file ledger saying the
     * legacy installation never had that file. Anything else has no explanation and keeps the table.
     *
     * @param  array<string, true>  $absent
     * @return array{source: int, imported: int, skipped: int, missing_ids: int}
     */
    private function countAttachments(Connection $legacy, array $absent): array
    {
        $here = [];
        foreach (DB::table('epesi_mail_attachments as a')->join('epesi_mails as m', 'm.id', '=', 'a.mail_id')->whereNotNull('m.legacy_id')->get(['m.legacy_id', 'a.content_id']) as $row) {
            $here[$row->legacy_id.'/'.$row->content_id] = true;
        }
        $counts = ['source' => 0, 'imported' => 0, 'skipped' => 0, 'missing_ids' => 0];
        foreach ($legacy->table('rc_mails_attachments')->get() as $row) {
            $counts['source']++;
            if (isset($here[$row->mail_id.'/'.$row->mime_id])) {
                $counts['imported']++;
            } elseif (isset($absent[$this->attachmentCandidates((int) $row->mail_id, $row)[0] ?? ''])) {
                $counts['skipped']++;
            } else {
                $counts['missing_ids']++;
            }
        }

        return $counts;
    }

    /**
     * Threads are rebuilt from the messages: a legacy thread is accounted for when each of its live messages is in a
     * thread here. One with no live message left has nothing to show (a thread holds only what its messages give it).
     *
     * @return array{source: int, imported: int, skipped: int, missing_ids: int}
     */
    private function countThreads(Connection $legacy): array
    {
        $threadOf = Mail::withTrashed()->withoutGlobalScopes()->whereNotNull('legacy_id')->pluck('thread_id', 'legacy_id')->all();
        $live = [];
        $lost = [];
        foreach ($legacy->table('rc_mails_data_1')->where('f_thread', '>', 0)->where('active', 1)->get(['id', 'f_thread']) as $row) {
            $live[$row->f_thread] = true;
            if (($threadOf[$row->id] ?? null) === null) {
                $lost[$row->f_thread] = true;
            }
        }
        $counts = ['source' => 0, 'imported' => 0, 'skipped' => 0, 'missing_ids' => 0];
        foreach ($legacy->table('rc_mail_threads_data_1')->pluck('id') as $id) {
            $counts['source']++;
            $key = isset($lost[$id]) ? 'missing_ids' : (isset($live[$id]) ? 'imported' : 'skipped');
            $counts[$key]++;
        }

        return $counts;
    }

    /**
     * An active row is accounted for by the account of its user and address (two rows with the same pair become one,
     * importAccounts()), and only when its stored passwords got there too. Inactive rows are not imported.
     *
     * @return array{source: int, imported: int, skipped: int, missing_ids: int}
     */
    private function countAccounts(Connection $legacy): array
    {
        $users = LegacyIdMap::for(User::class);
        $accounts = MailAccount::query()->withoutGlobalScopes()->get()->keyBy(static fn (MailAccount $account): string => $account->user_id.'|'.mb_strtolower((string) $account->email));
        $counts = ['source' => 0, 'imported' => 0, 'skipped' => 0, 'missing_ids' => 0];
        foreach ($legacy->table('rc_accounts_data_1')->get() as $row) {
            $counts['source']++;
            if ((int) ($row->active ?? 1) === 0) {
                $counts['skipped']++;

                continue;
            }
            $account = $accounts[$users->get((int) $row->f_epesi_user).'|'.mb_strtolower((string) $row->f_email)] ?? null;
            $passwordLost = $account !== null && ((filled($row->f_password ?? null) && blank($account->imap_password)) || (filled($row->f_smtp_password ?? null) && blank($account->smtp_password)));
            $counts[$account === null || $passwordLost ? 'missing_ids' : 'imported']++;
        }

        return $counts;
    }

    /**
     * Contacts and companies absorb their active extra addresses (Importer::legacyExtraEmails()), and an address
     * belongs to one record only, so it is looked for on any record. A row with no owner has nobody to go to.
     *
     * @return array{source: int, imported: int, skipped: int, missing_ids: int}
     */
    private function countExtraEmails(Connection $legacy): array
    {
        $here = [];
        foreach (DB::table('epesi_recordbrowser_email_addresses')->pluck('value') as $value) {
            $here[mb_strtolower(trim((string) $value))] = true;
        }
        $counts = ['source' => 0, 'imported' => 0, 'skipped' => 0, 'missing_ids' => 0];
        foreach ($legacy->table('rc_multiple_emails_data_1')->get() as $row) {
            $counts['source']++;
            if ((int) ($row->active ?? 1) === 0 || blank($row->f_record_type ?? null) || (int) ($row->f_record_id ?? 0) === 0) {
                $counts['skipped']++;
            } else {
                $counts[isset($here[mb_strtolower(trim((string) $row->f_email))]) ? 'imported' : 'missing_ids']++;
            }
        }

        return $counts;
    }

    /**
     * What a "Related" token's legacy recordset became here: whatever importer
     * brings it over, core or a module's.
     */
    protected LegacyRecordRefs $refs;

    protected ImportSummary $summary;

    /** @var array<class-string, LegacyIdMap> */
    protected array $maps = [];

    protected ?string $dataDir;

    public function __construct(protected MailArchiver $archiver, protected ?LegacyFileTransfer $transfer = null)
    {
        $this->transfer ??= app(LegacyFileTransfer::class);
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

        foreach ([User::class, Contact::class] as $class) {
            $this->maps[$class] = LegacyIdMap::for($class);
        }

        $this->refs = LegacyRecordRefs::fromImporters();

        $this->importAccounts();
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

    // --------------------------------------------------------------- Mails --

    protected function importMails(): void
    {
        $budget = app(TimeBudget::class);
        $checkpoint = $this->checkpoint();

        if ($checkpoint['stage'] === 'mails') {
            $count = 0;
            $last = $checkpoint['mail'];
            // For the wizard's status line only: the messages already in (an earlier slice) and all of them.
            $report = Importer::$onProgress;
            $total = $report === null ? 0 : $this->legacy()->table('rc_mails_data_1')->count();
            $before = $report === null ? 0 : $this->legacy()->table('rc_mails_data_1')->where('id', '<=', $checkpoint['mail'])->count();

            // In small id ranges, never one query over the whole table: the rows carry whole message bodies (hundreds of
            // MB on a real site), which one `select * ... order by id` sorts and loads into memory at once, so the
            // request ran out of memory before the first mail was imported.
            while (($rows = $this->legacy()->table('rc_mails_data_1')->where('id', '>', $last)->orderBy('id')->limit(50)->get())->isNotEmpty()) {
                foreach ($rows as $row) {
                    if ($report !== null && $count % 50 === 0) {
                        $report('mail', 'messages', $before + $count, $total);
                    }
                    $this->importMail($row);
                    $last = (int) $row->id;
                    $count++;

                    if ($budget->exceeded()) {
                        $this->saveCheckpoint(['stage' => 'mails', 'mail' => $last, 'offset' => 0]);

                        throw new TimeBudgetReached;
                    }
                    if ($count % 200 === 0) {
                        $this->saveCheckpoint(['stage' => 'mails', 'mail' => $last, 'offset' => 0]);
                    }
                }
            }
            $this->saveCheckpoint(['stage' => 'threads', 'mail' => $last, 'offset' => 0]);
            $checkpoint = ['stage' => 'threads', 'mail' => $last, 'offset' => 0];
        }

        // Threads are joined once every message is in, from the database, so a run that was cut into slices
        // joins exactly what one uninterrupted run would.
        $ids = Mail::withTrashed()->pluck('id', 'legacy_id')->all();
        $groups = [];
        $unthreaded = [];

        foreach ($this->legacy()->table('rc_mails_data_1')->orderBy('id')->get(['id', 'f_thread']) as $row) {
            $mailId = $ids[$row->id] ?? null;

            if ($mailId === null) {
                continue;
            }
            if ((int) ($row->f_thread ?? 0) > 0) {
                $groups[(int) $row->f_thread][] = $mailId;
            } else {
                $unthreaded[] = $mailId;
            }
        }
        $work = [...array_map(static fn (array $mailIds): array => ['join', $mailIds], array_values($groups)), ...array_map(static fn (int $mailId): array => ['thread', $mailId], $unthreaded)];

        for ($i = $checkpoint['offset']; $i < count($work); $i++) {
            if (Importer::$onProgress !== null && $i % 50 === 0) {
                (Importer::$onProgress)('mail', 'threads', $i, count($work));
            }
            [$kind, $what] = $work[$i];

            if ($kind === 'join') {
                $this->joinThread($what);
            } else {
                $mail = Mail::withTrashed()->find($what);
                $mail && $this->archiver->thread($mail->refresh());
            }

            if ($budget->exceeded() && $i + 1 < count($work)) {
                $this->saveCheckpoint(['stage' => 'threads', 'mail' => $checkpoint['mail'], 'offset' => $i + 1]);

                throw new TimeBudgetReached;
            }
        }
        $this->clearCheckpoint();
    }

    /**
     * Where a sliced run stands: the last whole mail, or how many thread jobs are done. Kept in a small file
     * (config epesi-mail.legacy_checkpoint) that only the Migration Wizard sets; `import:legacy` has none and
     * always starts from the top. A crash loses at most the last 200 mails, which are simply imported again
     * (every step upserts by legacy id and a moved file resolves from the file ledger).
     *
     * @return array{stage: string, mail: int, offset: int}
     */
    protected function checkpoint(): array
    {
        $path = config('epesi-mail.legacy_checkpoint');
        $value = filled($path) && is_file((string) $path) ? json_decode((string) file_get_contents((string) $path), true) : null;

        return is_array($value) && in_array($value['stage'] ?? null, ['mails', 'threads'], true)
            ? ['stage' => $value['stage'], 'mail' => (int) ($value['mail'] ?? 0), 'offset' => (int) ($value['offset'] ?? 0)]
            : ['stage' => 'mails', 'mail' => 0, 'offset' => 0];
    }

    /** @param  array{stage: string, mail: int, offset: int}  $checkpoint */
    protected function saveCheckpoint(array $checkpoint): void
    {
        $path = config('epesi-mail.legacy_checkpoint');

        if (filled($path)) {
            file_put_contents((string) $path, json_encode($checkpoint, JSON_THROW_ON_ERROR), LOCK_EX);
        }
    }

    protected function clearCheckpoint(): void
    {
        $path = config('epesi-mail.legacy_checkpoint');

        if (filled($path) && is_file((string) $path)) {
            @unlink((string) $path);
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

        // Epesi's epesi_archive saved Roundcube's placeholder ("mid:4", the
        // message's position in its folder) when it hadn't fetched the header.
        $messageId = trim((string) $row->f_message_id, '<> ');

        $mail->forceFill([
            'message_id' => $messageId !== '' && ! str_starts_with($messageId, 'mid:') ? $messageId : null,
            'in_reply_to' => $this->header($headers, 'in_reply_to') ? trim($this->header($headers, 'in_reply_to'), '<> ') : null,
            'references' => $row->f_references ?: null,
            'subject' => Str::limit((string) $row->f_subject, 500, ''),
            'from' => $row->f_from,
            'to' => $row->f_to,
            'cc' => $this->header($headers, 'cc'),
            'date' => $this->mailDate($row, $headers),
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

        $this->importLinks($mail, $row, $isNew);
        $this->importAttachments($mail, (int) $row->id, $this->mailDate($row, $headers));

        return $mail;
    }

    /**
     * The message's date. Legacy kept `0000-00-00 00:00:00` for a message whose Date header it could not read
     * (a truthy value that no database here accepts), so that is not a date: the `internaldate` header the mail
     * server stamped is used next, and the row's own creation time last.
     */
    protected function mailDate(object $row, string $headers): string
    {
        $date = (string) ($row->f_date ?? '');
        if ($date !== '' && ! str_starts_with($date, '0000') && strtotime($date) !== false) {
            return $date;
        }

        $internal = $this->header($headers, 'internaldate');
        $stamp = $internal === null ? false : strtotime($internal);
        if ($stamp !== false && $stamp > 0) {
            return date('Y-m-d H:i:s', $stamp);
        }

        return (string) $row->created_on;
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
            ...($employeeId ? Contact::withTrashed()->find($employeeId)?->collection('emails')->pluck('value')->all() ?? [] : []),
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
    /**
     * The mail's links: its employee, then every record of rc_mails' Contacts and Related. The ids come from the
     * importers' id maps (already in memory), not from loading each record, and the links go in with one insert
     * (the unique index skips a repeat). Only a mail imported before has old links to remove.
     */
    protected function importLinks(Mail $mail, object $row, bool $isNew = false): void
    {
        if (! $isNew) {
            $mail->links()->delete();
        }

        $targets = [];
        if ($mail->employee_id) {
            $targets[] = [$mail->employee()->getRelated()->getMorphClass(), (int) $mail->employee_id];
        }

        foreach ([...LegacyValue::multi($row->f_contacts ?? null), ...LegacyValue::multi($row->f_related ?? null)] as $token) {
            $target = $this->resolveTarget($token);

            if ($target === null) {
                $this->summary->warn("rc_mails#{$row->id}: linked record \"{$token}\" wasn't imported, link skipped");

                continue;
            }

            $targets[] = $target;
        }

        $now = now();
        $rows = [];
        foreach ($targets as [$type, $id]) {
            $rows[$type.':'.$id] = ['mail_id' => $mail->getKey(), 'linkable_type' => $type, 'linkable_id' => $id, 'created_at' => $now, 'updated_at' => $now];
        }
        if ($rows !== []) {
            MailLink::query()->insertOrIgnore(array_values($rows));
        }
    }

    /**
     * The morph type and id a legacy link token ("contact/5", older "P:5"/"C:5", or a bare contact id) became, or
     * null when that record was not imported.
     *
     * @return array{0: string, 1: int}|null
     */
    protected function resolveTarget(string $token): ?array
    {
        $parsed = $this->parseToken($token);
        if ($parsed === null) {
            return null;
        }
        [$tab, $legacyId] = $parsed;
        $class = $this->refs->modelFor($tab);
        $id = $this->refs->id($tab, $legacyId);

        return $class === null || $id === null ? null : [Relation::getMorphAlias($class), $id];
    }

    /** @return array{0: string, 1: int}|null the legacy tab and id a link token names */
    protected function parseToken(string $token): ?array
    {
        $token = trim($token);

        if (ctype_digit($token)) {
            return ['contact', (int) $token];
        }
        if (preg_match('#^([A-Za-z_0-9]+)[:/](\d+)$#', $token, $m)) {
            return [['P' => 'contact', 'C' => 'company'][$m[1]] ?? $m[1], (int) $m[2]];
        }

        return null;
    }

    protected function resolveToken(string $token): ?Model
    {
        $parsed = $this->parseToken($token);

        return $parsed === null ? null : $this->refs->record($parsed[0], $parsed[1]);
    }

    protected function importAttachments(Mail $mail, int $legacyMailId, ?string $date): void
    {
        // A previous run's rows go only once this run's are in: their files
        // are then found already in the file storage, instead of being
        // deleted from it and copied in again.
        $moving = $this->transfer->moves();
        $previous = $mail->attachments()->with('storedFile.content')->get();
        $reuse = $previous->map->storedFile->filter()->values();
        $kept = [];

        $rows = $this->dataDir !== null && $this->legacy()->getSchemaBuilder()->hasTable('rc_mails_attachments')
            ? $this->legacy()->table('rc_mails_attachments')->where('mail_id', $legacyMailId)->get()
            : collect();

        foreach ($rows as $row) {
            $name = Str::limit(trim((string) preg_replace('/[^\pL\pN._\- ]+/u', '_', basename((string) $row->name)), '. ') ?: 'attachment', 150, '');
            $file = $this->transfer->store($this->dataDir, $this->attachmentCandidates($legacyMailId, $row), $name, $reuse, 'mail', $legacyMailId, $date);

            if ($file === null) {
                $this->summary->warn("rc_mails#{$legacyMailId}: attachment \"{$row->name}\" not found in the legacy data directory, skipped");

                continue;
            }

            $kept[] = $file->getKey();

            $mail->attachments()->create([
                'name' => $name,
                'mime_type' => Str::limit((string) ($row->type ?: 'application/octet-stream'), 120, ''),
                'size' => $file->size(),
                'content_id' => $row->mime_id,
                'inline' => (int) ($row->attachment ?? 1) === 0,
                'stored_file_id' => $file->getKey(),
            ]);
        }

        // A file the new rows kept is theirs now: only the old row goes. When the files are
        // moved, a stored file may be the only copy there is, so an old row that was not
        // re-kept stays.
        foreach ($previous as $old) {
            if (in_array($old->stored_file_id, $kept)) {
                $old->deleteQuietly();
            } elseif (! $moving) {
                $old->delete();
            }
        }
    }

    /**
     * Where the attachment's bytes are, paths relative to the legacy data/
     * directory, in order of preference: Utils_FileStorage (content-addressed
     * by sha512, the layout since the 2026-06-29 patch, and the same one
     * App\Services\FileStorage uses), then the older per-mail directory
     * `CRM_Mail/attachments/<mail id>/<mime id>` of an unpatched install (the
     * stock 1.9.1 layout).
     *
     * @return list<string>
     */
    protected function attachmentCandidates(int $legacyMailId, object $row): array
    {
        $candidates = [];

        if (! empty($row->file_id)) {
            $hash = $this->legacy()->table('utils_filestorage as s')
                ->join('utils_filestorage_files as f', 'f.id', '=', 's.file_id')
                ->where('s.id', $row->file_id)
                ->value('f.hash');

            if (is_string($hash) && preg_match('/^[0-9a-f]{16,128}$/D', $hash) === 1) {
                $candidates[] = 'Utils_FileStorage/'.FileStorage::pathFor($hash);
            }
        }

        // mime_id is a part number or an md5; anything else never becomes a path.
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/D', (string) $row->mime_id) === 1) {
            $candidates[] = 'CRM_Mail/attachments/'.$legacyMailId.'/'.$row->mime_id;
        }

        return $candidates;
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
