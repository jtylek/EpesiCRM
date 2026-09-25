<?php

namespace Epesi\Modules\Mail\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One user's mailbox — an `rc_accounts` record. Accounts are strictly
 * personal: Epesi's view crit was `epesi_user = USER_ID` for everyone,
 * administrators included, and MailAccountPolicy keeps that.
 */
class MailAccount extends Model
{
    protected $table = 'epesi_mail_accounts';

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'from_name',
        'imap_host',
        'imap_port',
        'imap_security',
        'imap_login',
        'imap_password',
        'smtp_host',
        'smtp_port',
        'smtp_security',
        'smtp_auth',
        'smtp_login',
        'smtp_password',
        'is_default',
        'archive_on_sending',
        'save_to_sent',
        'sent_folder',
        'archive_folder',
        'auto_archive',
        'auto_archive_folders',
        'signature',
        'last_fetched_at',
        'last_error',
    ];

    protected $hidden = ['imap_password', 'smtp_password'];

    /** The table's defaults, so a freshly created account carries them too. */
    protected $attributes = [
        'imap_security' => 'ssl',
        'smtp_security' => 'tls',
        'smtp_auth' => true,
        'is_default' => false,
        'archive_on_sending' => true,
        'save_to_sent' => true,
        'sent_folder' => 'Sent',
        'archive_folder' => 'CRM Archive',
        'auto_archive' => false,
    ];

    protected function casts(): array
    {
        return [
            'imap_password' => 'encrypted',
            'smtp_password' => 'encrypted',
            'imap_port' => 'integer',
            'smtp_port' => 'integer',
            'smtp_auth' => 'boolean',
            'is_default' => 'boolean',
            'archive_on_sending' => 'boolean',
            'save_to_sent' => 'boolean',
            'auto_archive' => 'boolean',
            'auto_archive_folders' => 'array',
            'last_fetched_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // CRM_MailCommon::submit_account(): a user's first account is their
        // default, and marking another one default clears the old one.
        static::saving(function (MailAccount $account): void {
            $others = static::query()->where('user_id', $account->user_id)->whereKeyNot($account->getKey());

            if (! $others->exists()) {
                $account->is_default = true;
            } elseif ($account->is_default && $account->isDirty('is_default')) {
                $others->update(['is_default' => false]);
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<MailAccountFolder, $this>
     */
    public function folders(): HasMany
    {
        return $this->hasMany(MailAccountFolder::class, 'account_id');
    }

    public function canReceive(): bool
    {
        return filled($this->imap_host);
    }

    /**
     * Epesi required a login; left empty here it means the e-mail address,
     * which is what most servers expect.
     */
    public function imapLogin(): string
    {
        return (string) ($this->imap_login ?: $this->email);
    }

    /** Empty SMTP credentials mean the IMAP ones, as Epesi filled them in. */
    public function smtpLogin(): string
    {
        return (string) ($this->smtp_login ?: $this->imapLogin());
    }

    public function smtpPassword(): string
    {
        return (string) ($this->smtp_password ?: $this->imap_password);
    }

    public function canSend(): bool
    {
        return filled($this->smtp_host);
    }

    /** Whether a sent message should be copied into the IMAP Sent folder. */
    public function savesToSent(): bool
    {
        return $this->save_to_sent && filled($this->sent_folder) && $this->canReceive();
    }

    /**
     * Folders read by the fetcher: the archive folder, whose every message is
     * archived (Epesi's "Use EPESI Archive directories"), and — with
     * auto-archive on — the others, archived only when a message involves a
     * known contact or company.
     *
     * @return array<string, bool> folder => archive only when matched
     */
    public function foldersToFetch(): array
    {
        $folders = [];

        if (filled($this->archive_folder)) {
            $folders[$this->archive_folder] = false;
        }

        if ($this->auto_archive) {
            foreach ($this->auto_archive_folders ?: ['INBOX'] as $folder) {
                $folders[$folder] ??= true;
            }
        }

        return $folders;
    }

    public static function defaultFor(User $user): ?self
    {
        return static::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }
}
