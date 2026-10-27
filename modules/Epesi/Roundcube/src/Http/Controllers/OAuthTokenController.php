<?php

namespace Epesi\Modules\Roundcube\Http\Controllers;

use Epesi\Modules\OAuth\Exceptions\OAuthException;
use Epesi\Modules\Roundcube\Roundcube;
use Epesi\Modules\Roundcube\Services\OAuthGrants;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A new access token for a Roundcube session, asked by the epesi_sso plugin
 * from the server side (never the browser) with the grant its login ticket
 * carried (OAuthGrants). The secret comes in the POST body, so it stays out
 * of URLs and access logs, and the token goes back encrypted with the key
 * Epesi and Roundcube share, as the ticket's payload does.
 *
 * 403: the grant is no good any more (epesi_sso ends its session); 503: the
 * provider couldn't renew it this time (epesi_sso carries on, asks again).
 */
class OAuthTokenController
{
    public function __invoke(Request $request, OAuthGrants $grants): JsonResponse
    {
        $secret = $request->post('grant');

        if (! is_string($secret) || $secret === '' || strlen($secret) > 255) {
            return $this->answer(['error' => 'refused'], 403);
        }

        try {
            $token = $grants->redeem($secret);
        } catch (OAuthException) {
            return $this->answer(['error' => 'unavailable'], 503);
        }

        if ($token === null) {
            return $this->answer(['error' => 'refused'], 403);
        }

        $payload = (new Encrypter(Roundcube::key('sso'), 'aes-256-gcm'))
            ->encryptString(json_encode($token, JSON_THROW_ON_ERROR));

        return $this->answer(['payload' => $payload]);
    }

    /**
     * @param  array<string, string>  $data
     */
    protected function answer(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, ['Cache-Control' => 'no-store']);
    }
}
