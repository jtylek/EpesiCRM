<?php

namespace Tests\Fakes;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/** An SMTP transport that keeps what it was given instead of sending it. */
class RecordingTransport implements TransportInterface
{
    /** @var array<int, SentMessage> */
    public array $sent = [];

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        return $this->sent[] = new SentMessage($message, $envelope ?? Envelope::create($message));
    }

    public function __toString(): string
    {
        return 'recording://';
    }
}
