<?php

namespace Epesi\Modules\Mail\Services\Imap;

use Epesi\Modules\Mail\Models\MailAccount;
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
        $status = $this->protocol()->examineFolder($folder)->validatedData();

        return (int) ($status['uidvalidity'] ?? 0);
    }

    public function uidsAfter(int $uid): array
    {
        $found = $this->protocol()->search(['UID', ($uid + 1).':*'])->array();

        // "n:*" always matches the highest UID even when it is below n.
        $uids = array_values(array_filter(array_map('intval', $found), fn (int $u): bool => $u > $uid));
        sort($uids);

        return $uids;
    }

    public function fetchRaw(int $uid): string
    {
        $data = $this->protocol()->fetch(['RFC822'], [$uid], null, IMAP::ST_UID)->validatedData();

        return (string) (is_array($data) ? ($data[$uid] ?? reset($data)) : $data);
    }

    public function append(string $folder, string $raw): void
    {
        // IMAP wants CRLF line endings in the literal; a message built on
        // this side may carry bare LFs.
        $raw = (string) preg_replace("/\r?\n/", "\r\n", $raw);

        $this->protocol()->appendMessage($folder, $raw, ['\\Seen'])->validatedData();
    }

    public function unseen(string $folder = 'INBOX'): int
    {
        $status = $this->protocol()->folderStatus($folder, ['UNSEEN'])->validatedData();

        return (int) ($status['unseen'] ?? 0);
    }

    public function close(): void
    {
        $this->client?->disconnect();
        $this->client = null;
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

        $security = $this->account->imap_security;

        $this->client = (new ClientManager)->make([
            'host' => $this->account->imap_host,
            'port' => $this->account->imap_port ?: ($security === 'ssl' ? 993 : 143),
            'protocol' => 'imap',
            'encryption' => in_array($security, ['ssl', 'tls'], true) ? $security : false,
            // Epesi connected with `novalidate-cert`; keep certificate checks
            // on unless the operator turns them off for self-signed servers.
            'validate_cert' => (bool) config('epesi-mail.imap_validate_cert', true),
            'username' => $this->account->imapLogin(),
            'password' => (string) $this->account->imap_password,
            'timeout' => 30,
        ]);

        return $this->client->connect();
    }
}
