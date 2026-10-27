<?php

namespace Epesi\Modules\OAuth\Exceptions;

use RuntimeException;

/**
 * Something the user or an administrator can act on: the message is shown as
 * it stands (translated), so it never carries a token or a secret.
 */
class OAuthException extends RuntimeException
{
    /** The provider's error code (e.g. "invalid_client"), when it refused a request. */
    public ?string $providerError = null;

    /** The HTTP status of the provider's refusal, 0 when it didn't answer one. */
    public int $providerStatus = 0;

    public static function unknownProvider(string $key): self
    {
        return new self(__('There is no OAuth provider ":provider".', ['provider' => $key]));
    }

    public static function notConfigured(string $label): self
    {
        return new self(__('The :provider app isn\'t set up yet. An administrator enters its client ID and secret under Administration → OAuth Apps.', ['provider' => $label]));
    }

    public static function notConnected(string $label): self
    {
        return new self(__('Not connected to :provider yet. Connect the account first.', ['provider' => $label]));
    }

    public static function needsReconnect(string $label): self
    {
        return new self(__(':provider no longer accepts this connection. Connect the account again.', ['provider' => $label]));
    }

    public static function busy(): self
    {
        return new self(__('The connection is being renewed by another process. Try again in a moment.'));
    }

    /** The provider's own error code and description, never its whole response. */
    public static function fromProvider(string $label, ?string $error, ?string $description, int $status): self
    {
        $detail = trim(($error ?? 'HTTP '.$status).($description ? ': '.$description : ''));

        $exception = new self(__(':provider refused the request (:detail).', ['provider' => $label, 'detail' => mb_strimwidth($detail, 0, 300, '…')]));
        $exception->providerError = $error;
        $exception->providerStatus = $status;

        return $exception;
    }
}
