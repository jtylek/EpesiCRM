<?php

namespace Epesi\Modules\Store\Services;

use App\Services\Modules\ModuleException;

/**
 * The store refused a call for a reason it named: registration_required,
 * url_mismatch (with registered_url) or revoked.
 */
class StoreApiException extends ModuleException
{
    public const REGISTRATION_REQUIRED = 'registration_required';

    public const URL_MISMATCH = 'url_mismatch';

    public const REVOKED = 'revoked';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public readonly string $error, string $message, public readonly array $payload = [])
    {
        parent::__construct($message);
    }
}
