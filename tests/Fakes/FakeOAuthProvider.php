<?php

namespace Tests\Fakes;

use Epesi\Modules\OAuth\Models\OAuthClient;
use Epesi\Modules\OAuth\Providers\AppCheck;
use Epesi\Modules\OAuth\Providers\AppField;
use Epesi\Modules\OAuth\Providers\OAuthProvider;

/**
 * A provider as a module would add one, shaped like Allegro: a production
 * and a sandbox environment with their own apps and hosts, HTTP Basic client
 * authentication, its own callback path (an app registered before the shared
 * callback existed) and a User-Agent per app, an app setting (appFields())
 * with a default, read at run time. A test gives it an app test by setting
 * $appTest (the checks to report, or an exception to throw).
 */
class FakeOAuthProvider extends OAuthProvider
{
    public const KEY = 'fake';

    /** The User-Agent app setting's default, changeable by a test. */
    public static string $userAgent = 'epesi-test/1.0 (+https://epe.si)';

    /** @var list<AppCheck>|\Throwable|null null: no app test */
    public static array|\Throwable|null $appTest = null;

    /** @var list<OAuthClient> the apps testApp() was given */
    public static array $testedApps = [];

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Fake Market';
    }

    public function authorizeUrl(OAuthClient $client): string
    {
        return 'https://'.$this->host($client).'/auth/authorize';
    }

    public function tokenUrl(OAuthClient $client): string
    {
        return 'https://'.$this->host($client).'/auth/token';
    }

    public function scopes(): array
    {
        return ['orders:read'];
    }

    public function environments(): array
    {
        return ['production' => 'Production', 'sandbox' => 'Sandbox'];
    }

    public function usesBasicAuth(): bool
    {
        return true;
    }

    public function callbackPath(): ?string
    {
        return 'administration/fake/callback';
    }

    public function headers(?OAuthClient $client = null): array
    {
        return ['User-Agent' => $client?->setting('user_agent') ?? static::$userAgent];
    }

    public function appFields(): array
    {
        return [new AppField('user_agent', 'User-Agent', 'Sent with every request.', static::$userAgent, required: true)];
    }

    public function supportsAppTest(): bool
    {
        return static::$appTest !== null;
    }

    public function testApp(OAuthClient $app): array
    {
        static::$testedApps[] = $app;

        if (static::$appTest instanceof \Throwable) {
            throw static::$appTest;
        }

        return static::$appTest ?? [];
    }

    protected function host(OAuthClient $client): string
    {
        return $client->environment === 'sandbox' ? 'sandbox.fake.test' : 'fake.test';
    }
}
