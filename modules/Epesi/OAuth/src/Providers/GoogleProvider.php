<?php

namespace Epesi\Modules\OAuth\Providers;

use Epesi\Modules\OAuth\Models\OAuthClient;

/**
 * Google, for Gmail and Google Workspace over IMAP and SMTP (XOAUTH2). The
 * https://mail.google.com/ scope is restricted: each installation uses its
 * own Google Cloud project, Internal on Workspace (AI-shared/OAuth2-plan.md).
 */
class GoogleProvider extends OAuthProvider
{
    public const KEY = 'google';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Google';
    }

    public function authorizeUrl(OAuthClient $client): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth';
    }

    public function tokenUrl(OAuthClient $client): string
    {
        return 'https://oauth2.googleapis.com/token';
    }

    public function revokeUrl(OAuthClient $client): ?string
    {
        return 'https://oauth2.googleapis.com/revoke';
    }

    public function scopes(): array
    {
        return ['https://mail.google.com/', 'openid', 'email'];
    }

    public function authorizeParameters(): array
    {
        // A refresh token only comes with offline access, and only on the
        // first consent unless consent is asked for again.
        return ['access_type' => 'offline', 'prompt' => 'consent'];
    }

    public function consoleUrl(): ?string
    {
        return 'https://console.cloud.google.com/apis/credentials';
    }

    public function setupHelp(): ?string
    {
        return 'In the Google Cloud console, configure the OAuth consent screen (on Google Workspace choose the Internal user type, which needs no verification) and add the scope https://mail.google.com/. Then create an OAuth client ID of the type "Web application" with the redirect URI below. While an External app is in Testing, its connections expire after 7 days.';
    }
}
