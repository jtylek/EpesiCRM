<?php

namespace Tests\Fakes;

/** Hand-written RFC 822 messages for mail tests. */
class Eml
{
    public static function make(
        string $from = 'Ann Buyer <ann@customer.test>',
        string $to = 'me@ourcompany.test',
        string $subject = 'Offer',
        string $messageId = 'msg-1@customer.test',
        ?string $inReplyTo = null,
        string $date = 'Tue, 22 Sep 2026 10:15:00 +0200',
        string $cc = '',
        string $html = '<p>Hello, <b>please</b> send the offer.</p><img src="cid:logo@x">',
    ): string {
        $headers = [
            "From: {$from}",
            "To: {$to}",
            $cc !== '' ? "Cc: {$cc}" : null,
            "Subject: {$subject}",
            "Date: {$date}",
            "Message-ID: <{$messageId}>",
            $inReplyTo ? "In-Reply-To: <{$inReplyTo}>" : null,
            $inReplyTo ? "References: <{$inReplyTo}>" : null,
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="MIXED"',
        ];

        $png = base64_encode("\x89PNG\r\n\x1a\nfake");
        $pdf = base64_encode('%PDF-1.4 fake offer');

        return implode("\r\n", array_filter($headers))."\r\n\r\n".implode("\r\n", [
            '--MIXED',
            'Content-Type: multipart/related; boundary="REL"',
            '',
            '--REL',
            'Content-Type: text/html; charset=UTF-8',
            '',
            $html,
            '--REL',
            'Content-Type: image/png; name="logo.png"',
            'Content-Transfer-Encoding: base64',
            'Content-ID: <logo@x>',
            'Content-Disposition: inline; filename="logo.png"',
            '',
            $png,
            '--REL--',
            '--MIXED',
            'Content-Type: application/pdf; name="offer.pdf"',
            'Content-Transfer-Encoding: base64',
            'Content-Disposition: attachment; filename="offer.pdf"',
            '',
            $pdf,
            '--MIXED--',
            '',
        ]);
    }
}
