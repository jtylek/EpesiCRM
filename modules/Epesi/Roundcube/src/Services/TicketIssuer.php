<?php

namespace Epesi\Modules\Roundcube\Services;

use App\Models\User;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Roundcube\Roundcube;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One-time logins for the Mailbox page's Roundcube frame, redeemed by the
 * epesi_sso Roundcube plugin (roundcube-plugins/epesi_sso/epesi_sso_ticket.php
 * reads what this writes). Roundcube can't read Laravel's session, so the
 * account's settings travel in the ticket, encrypted with a key only the two
 * share.
 */
class TicketIssuer
{
    public const TABLE = 'epesi_roundcube_tickets';

    /** @return string the token for the frame URL */
    public function issue(MailAccount $account, User $user): string
    {
        DB::table(self::TABLE)->where('expires_at', '<', now()->getTimestamp())->delete();

        $token = Str::random(48);

        DB::table(self::TABLE)->insert([
            'token' => hash('sha256', $token),
            'user_id' => $user->getKey(),
            'account_id' => $account->getKey(),
            'payload' => (new Encrypter(Roundcube::key('sso'), 'aes-256-gcm'))
                ->encryptString(json_encode($this->login($account, $user), JSON_THROW_ON_ERROR)),
            'expires_at' => now()->addSeconds((int) config('epesi-roundcube.ticket_ttl'))->getTimestamp(),
        ]);

        return $token;
    }

    public function url(MailAccount $account, User $user): string
    {
        return Roundcube::url(['_task' => 'login', Roundcube::TICKET_PARAMETER => $this->issue($account, $user)]);
    }

    /**
     * What epesi_sso logs in with and keeps for the other epesi_* plugins.
     *
     * @return array<string, mixed>
     */
    public function login(MailAccount $account, User $user): array
    {
        return [
            'user_id' => $user->getKey(),
            'account_id' => $account->getKey(),
            'imap' => [
                'host' => $this->host($account->imap_security, $account->imap_host, $account->imap_port, 143, 993),
                'user' => $account->imapLogin(),
                'pass' => (string) $account->imap_password,
            ],
            // Ports as SmtpTransportFactory picks them.
            'smtp' => ! $account->canSend() ? null : [
                'host' => $this->host($account->smtp_security, $account->smtp_host, $account->smtp_port, $account->smtp_security === 'tls' ? 587 : 25, 465),
                'user' => $account->smtp_auth ? $account->smtpLogin() : '',
                'pass' => $account->smtp_auth ? $account->smtpPassword() : '',
            ],
            'email' => $account->email,
            'name' => $account->from_name ?: $user->displayName(),
            'signature' => (string) $account->signature,
            'global_signature' => (string) config('epesi-mail.global_signature'),
            'archive_folder' => (string) $account->archive_folder,
            'archive_on_sending' => (bool) $account->archive_on_sending,
            // For the CRM address book's visibility rule.
            'sees_all' => $user->hasAnyRole(['super_admin', 'manager']),
            'company_id' => $user->companyId(),
            // Roundcube speaks the user's Epesi language, and the epesi_*
            // plugins' own labels come translated from here, with the rest.
            // The archive wording is Epesi's own.
            'language' => app()->getLocale(),
            'labels' => [
                'archive' => __('Archive'),
                'archive_title' => __('Archive to the CRM'),
                'archive_sent_title' => __('Archive this message after sending'),
                'contact_not_found' => __('Matching contact or company not found. Click again to force archive without contact association.'),
                'already_archived' => __('Message already archived'),
                'invalid_folder' => __('Cannot move to archive from this folder'),
                'login_refused' => __('Your mail server refused the login. Check this account under Settings > Mail accounts.'),
            ],
        ];
    }

    /**
     * Roundcube's host notation: ssl:// is TLS from the first byte, tls://
     * is STARTTLS, no scheme is plain.
     */
    protected function host(?string $security, ?string $host, ?int $port, int $plainPort, int $sslPort): string
    {
        $port = $port ?: ($security === 'ssl' ? $sslPort : $plainPort);
        $scheme = in_array($security, ['ssl', 'tls'], true) ? $security.'://' : '';

        return $scheme.$host.':'.$port;
    }
}
