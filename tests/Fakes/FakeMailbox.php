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

    public function uidsAfter(int $uid): array
    {
        $uids = array_filter(array_keys($this->messages[$this->current] ?? []), fn (int $u): bool => $u > $uid);
        sort($uids);

        return array_values($uids);
    }

    public function fetchRaw(int $uid): string
    {
        return $this->messages[$this->current][$uid];
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
