<?php

namespace Epesi\Modules\OAuth\Providers;

use Epesi\Modules\OAuth\Models\OAuthClient;

/**
 * Microsoft Entra ID (Azure AD), v2.0 endpoints, for Microsoft 365 and
 * Outlook.com mailboxes over IMAP and SMTP (XOAUTH2).
 *
 * The tenant goes into the URLs: "common" (any work, school or personal
 * account) unless the administrator enters their directory's tenant ID or
 * domain, which a single-tenant app registration needs.
 */
class MicrosoftProvider extends OAuthProvider
{
    public const KEY = 'microsoft';

    public const DEFAULT_TENANT = 'common';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Microsoft 365';
    }

    public function authorizeUrl(OAuthClient $client): string
    {
        return $this->base($client).'/oauth2/v2.0/authorize';
    }

    public function tokenUrl(OAuthClient $client): string
    {
        return $this->base($client).'/oauth2/v2.0/token';
    }

    public function scopes(): array
    {
        return [
            'https://outlook.office.com/IMAP.AccessAsUser.All',
            'https://outlook.office.com/SMTP.Send',
            // A refresh token, so the connection outlives the one-hour access token.
            'offline_access',
            // An ID token naming who signed in, to show on the account.
            'openid',
            'email',
        ];
    }

    public function authorizeParameters(): array
    {
        return ['response_mode' => 'query', 'prompt' => 'select_account'];
    }

    public function usesTenant(): bool
    {
        return true;
    }

    public function consoleUrl(): ?string
    {
        return 'https://entra.microsoft.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade';
    }

    public function setupHelp(): ?string
    {
        return 'In Microsoft Entra ID, register an app (App registrations → New registration) with the redirect URI below as a "Web" platform. Under Certificates & secrets create a client secret, and under API permissions add the delegated permissions IMAP.AccessAsUser.All and SMTP.Send (APIs my organization uses → Office 365 Exchange Online), offline_access, openid and email. Leave the tenant empty for an app open to any account; enter your tenant ID for a single-tenant app.';
    }

    /**
     * The tenant is typed in by an administrator; anything but a GUID, a
     * domain or one of Microsoft's keywords falls back to "common" rather
     * than being put into a URL.
     */
    public static function tenant(OAuthClient $client): string
    {
        $tenant = trim((string) $client->tenant);

        return preg_match('/^[A-Za-z0-9][A-Za-z0-9.-]{0,252}$/', $tenant) ? $tenant : self::DEFAULT_TENANT;
    }

    protected function base(OAuthClient $client): string
    {
        return 'https://login.microsoftonline.com/'.rawurlencode(static::tenant($client));
    }
}
