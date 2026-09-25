<?php

namespace Epesi\Modules\Mail\Services;

use Epesi\Modules\Mail\Models\MailAccount;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * A Symfony SMTP transport for one account. Bound in the container so tests
 * can swap in a transport that records instead of sending.
 */
class SmtpTransportFactory
{
    public function forAccount(MailAccount $account): TransportInterface
    {
        $security = $account->smtp_security;
        $port = $account->smtp_port ?: match ($security) {
            'ssl' => 465,
            'tls' => 587,
            default => 25,
        };

        // ssl: implicit TLS from the first byte. tls: STARTTLS, which
        // EsmtpTransport negotiates on its own when the server offers it.
        $transport = new EsmtpTransport((string) $account->smtp_host, $port, $security === 'ssl');

        if ($security === 'none') {
            $transport->setAutoTls(false);
        }

        if ($account->smtp_auth) {
            $transport->setUsername($account->smtpLogin());
            $transport->setPassword($account->smtpPassword());
        }

        return $transport;
    }
}
