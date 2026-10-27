<?php

namespace Epesi\Modules\Mail\Services\Imap;

use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\OAuth\Exceptions\OAuthException;
use Epesi\Modules\OAuth\Services\OAuthTokens;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Connection\Protocols\ProtocolInterface;
use Webklex\PHPIMAP\IMAP;

/**
 * Mailbox over webklex/php-imap's own socket client — no ext-imap, which PHP
 * 8.4 no longer bundles. Talks to the protocol layer directly: EXAMINE keeps
 * the folder read-only (Epesi's `readonly` flag), and fetching `RFC822`
 * returns the untouched message for MailArchiver to parse.
 */
class WebklexMailbox implements Mailbox
{
    protected ?Client $client = null;

    public function __construct(protected MailAccount $account) {}

    public function folders(): array
    {
        return $this->client()->getFolders(false)
            ->map(fn ($folder): string => $folder->path)
            ->values()
            ->all();
    }

    public function open(string $folder): int
    {
        $status = $this->protocol()->examineFolder($this->wireName($folder))->validatedData();

        return (int) ($status['uidvalidity'] ?? 0);
    }

    public function uidsWithMessageId(string $messageId): array
    {
        $quoted = '"'.addcslashes($messageId, '"\\').'"';
        $uids = array_map('intval', $this->protocol()->search(['HEADER', 'Message-ID', $quoted])->array());
        sort($uids);

        return $uids;
    }

    public function fetchRaw(int $uid): string
    {
        $data = $this->protocol()->fetch(['RFC822'], [$uid], null, IMAP::ST_UID)->validatedData();

        return (string) (is_array($data) ? ($data[$uid] ?? reset($data)) : $data);
    }

    public function move(string $folder, array $uids, string $target): void
    {
        if ($uids === []) {
            return;
        }

        // SELECT, not EXAMINE: moving changes the folder. Without the MOVE
        // extension the library falls back to COPY, \Deleted and EXPUNGE.
        $this->protocol()->selectFolder($this->wireName($folder))->validatedData();
        $this->protocol()->moveManyMessages(array_map('intval', $uids), $this->wireName($target))->validatedData();
    }

    public function append(string $folder, string $raw): void
    {
        // IMAP wants CRLF line endings in the literal; a message built on
        // this side may carry bare LFs.
        $raw = (string) preg_replace("/\r?\n/", "\r\n", $raw);

        $this->protocol()->appendMessage($this->wireName($folder), $raw, ['\\Seen'])->validatedData();
    }

    public function unseen(string $folder = 'INBOX'): int
    {
        $status = $this->protocol()->folderStatus($this->wireName($folder), ['UNSEEN'])->validatedData();

        return (int) ($status['unseen'] ?? 0);
    }

    public function close(): void
    {
        $this->client?->disconnect();
        $this->client = null;
    }

    /**
     * A folder name as IMAP spells it (modified UTF-7). Names from Roundcube
     * or the server are that already, and ASCII; an account's archive folder
     * is typed in UTF-8. Only a name with non-ASCII characters is encoded, so
     * an already encoded one is never encoded twice.
     */
    protected function wireName(string $folder): string
    {
        return mb_check_encoding($folder, 'ASCII') ? $folder : mb_convert_encoding($folder, 'UTF7-IMAP', 'UTF-8');
    }

    protected function protocol(): ProtocolInterface
    {
        return $this->client()->getConnection();
    }

    protected function client(): Client
    {
        if ($this->client) {
            return $this->client;
        }

        $this->client = (new ClientManager)->make($this->clientConfig());

        return $this->client->connect();
    }

    /**
     * The webklex client's settings. An OAuth account logs in with
     * AUTHENTICATE XOAUTH2 and a fresh access token in place of the password.
     *
     * @return array<string, mixed>
     *
     * @throws OAuthException the account's connection is missing or refused
     */
    public function clientConfig(): array
    {
        $security = $this->account->imap_security;
        $oauth = $this->account->usesOAuth();

        return [
            'host' => $this->account->imap_host,
            'port' => $this->account->imapPort(),
            'protocol' => 'imap',
            'encryption' => in_array($security, ['ssl', 'tls'], true) ? $security : false,
            // Epesi connected with `novalidate-cert`; keep certificate checks
            // on unless the operator turns them off for self-signed servers.
            'validate_cert' => (bool) config('epesi-mail.imap_validate_cert', true),
            'username' => $this->account->imapLogin(),
            'password' => $oauth
                ? app(OAuthTokens::class)->accessToken($this->account, $this->account->oauthProvider())
                : (string) $this->account->imap_password,
            'authentication' => $oauth ? 'oauth' : null,
            // Gmail now and then answers a login late or drops it; 30 seconds failed too often.
            'timeout' => 60,
        ];
    }
}
