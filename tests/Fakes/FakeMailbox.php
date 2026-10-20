<?php

namespace Tests\Fakes;

use Epesi\Modules\Mail\Services\Imap\Mailbox;

/** An in-memory IMAP server: folder => [uid => raw message]. */
class FakeMailbox implements Mailbox
{
    public ?string $current = null;

    /**
     * @param  array<string, array<int, string>>  $messages
     * @param  array<string, int>  $validity
     */
    public function __construct(public array $messages = [], public array $validity = []) {}

    public function folders(): array
    {
        return array_keys($this->messages);
    }

    public function open(string $folder): int
    {
        $this->current = $folder;

        return $this->validity[$folder] ?? 1;
    }

    public function uidsWithMessageId(string $messageId): array
    {
        $uids = [];

        foreach ($this->messages[$this->current] ?? [] as $uid => $raw) {
            if (preg_match('/^Message-ID:\s*<?'.preg_quote($messageId, '/').'>?\s*$/mi', $raw)) {
                $uids[] = $uid;
            }
        }

        sort($uids);

        return $uids;
    }

    public function fetchRaw(int $uid): string
    {
        return $this->messages[$this->current][$uid];
    }

    public bool $moveFails = false;

    public function move(string $folder, array $uids, string $target): void
    {
        if ($this->moveFails) {
            throw new \RuntimeException('MOVE failed');
        }

        foreach ($uids as $uid) {
            $this->messages[$target][] = $this->messages[$folder][$uid];
            unset($this->messages[$folder][$uid]);
        }
    }

    /** @var array<string, array<int, string>> folder => appended raw messages */
    public array $appended = [];

    /** @var array<string, int> */
    public array $unseenCounts = [];

    public bool $failing = false;

    public function append(string $folder, string $raw): void
    {
        if ($this->failing) {
            throw new \RuntimeException('APPEND failed: no such folder');
        }

        $this->appended[$folder][] = $raw;
    }

    public function unseen(string $folder = 'INBOX'): int
    {
        if ($this->failing) {
            throw new \RuntimeException('connection refused');
        }

        return $this->unseenCounts[$folder] ?? 0;
    }

    public function close(): void {}
}
