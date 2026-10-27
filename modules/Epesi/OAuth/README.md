# OAuth

One shared OAuth2 layer for epesi. It keeps the apps an installation registered with OAuth
providers, connects records to provider accounts, stores and renews their tokens, and gives
modules a valid access token when they need one. Mail uses it now, for Microsoft 365 and Gmail
over IMAP and SMTP (XOAUTH2). Premium integrations such as Allegro will add their own providers
to it.

It is a **core** module (`epesi/oauth`, `"core": true`): Mail, also core, requires it, and its
namespace is in `config('modules.core_namespaces')`, so Mail's classes load even before
`epesi:update` has registered the module on an updated installation.

Background, provider requirements and what is still to come:
[AI-shared/OAuth2-plan.md](../../../AI-shared/OAuth2-plan.md).

## For administrators

### Administration → OAuth Apps

Every installation registers **its own app** with each provider, because the redirect URI
contains the installation's address. The page lists these apps, one record per provider and
environment (production, or a sandbox for a provider that has one): provider, environment,
client ID, whether a secret is stored, tenant, whether the app is fully configured, and whether
its provider is still available (an app stays listed when the module that adds its provider is
disabled, so enabling it again needs no new setup).

- **New OAuth app**: pick the provider, then the environment. Only combinations that have no app
  yet are offered; with one left, it is picked for you. Then enter the **Client ID** and
  **Client secret** (both required), for Microsoft the **Tenant ID**, and any setting the
  provider adds for its apps (Allegro: the **User-Agent**, filled in with its default). The form shows the
  provider's setup notes, where to register the app, and the **Redirect URI** to register,
  exactly as shown (copy button).
- **View** a row to see the same, the number of connected accounts that use the app, and
  whether a secret is stored. **Edit** has its own Save and Cancel. The provider and environment
  can't be changed, since connected accounts remember both. The secret is stored encrypted and
  never shown again; leave the field empty to keep the stored one.
- **Test app** (View and Edit, only for a provider that can test an app without a sign-in:
  Allegro, not Microsoft or Google) asks the provider whether it accepts the app as saved, for
  Allegro with an app-only token and one read call. A notification lists each check, passed or
  failed with the provider's own message, and what the test doesn't prove: the redirect URI and
  the permissions (scopes) are checked only when someone connects an account. On Edit it asks
  first, since unsaved changes on the form are not tested. It stores nothing, and without a
  stored client ID and secret it says so without calling the provider.
- **Delete** asks first and says how many connected accounts use the app. Their tokens are kept:
  once an access token expires, that account stops working until the app is entered again.

Only super admins can open the page. Saving, deleting and Test app do nothing in demo mode.

### Microsoft 365 (Entra ID)

1. In Microsoft Entra ID → **App registrations → New registration**, add a **Web** platform
   with the redirect URI from the page.
2. **Certificates & secrets**: create a client secret and copy its *value*.
3. **API permissions** (delegated): `IMAP.AccessAsUser.All` and `SMTP.Send` (Office 365
   Exchange Online), plus `offline_access`, `openid` and `email`.
4. Enter the client ID and secret. Leave **Tenant ID** empty for an app open to any account
   (`common`); enter the directory's tenant ID or domain for a single-tenant app.
5. The Microsoft 365 admin must leave IMAP and SMTP AUTH enabled for the mailboxes.

### Google (Gmail, Google Workspace)

1. In the Google Cloud console, configure the **OAuth consent screen**. On Google Workspace choose
   the **Internal** user type, which needs no verification. Add the scope
   `https://mail.google.com/`.
2. Create an **OAuth client ID** of the type *Web application* with the redirect URI from the page.
3. Enter the client ID and secret.

`https://mail.google.com/` is a restricted scope. An *External* app needs Google's verification,
and while it is in *Testing*, its connections expire after 7 days. For a personal Gmail account
an app password (password sign-in) is the practical choice.

### Connecting a mail account

In **Settings → Mail accounts**, set **Sign in with** to *Microsoft 365 (OAuth2)* or *Google
(OAuth2)*. The server fields fill in, and the password fields disappear. Save, then click
**Connect account**: the provider's sign-in page opens, and afterwards the form shows *Connected
as …*. **Connect again** replaces the connection, **Disconnect** forgets it (and revokes it at
Google).

When a provider stops accepting a connection (revoked, password changed, unused for too long),
the account shows *Connect the account again*, and its user gets a bell notification.

The Roundcube **Mailbox** page logs in to an OAuth account with XOAUTH2, too. Epesi keeps the
refresh token, and Roundcube asks Epesi for each new access token (see
[AI-shared/Epesi-Laravel-Roundcube.md](../../../AI-shared/Epesi-Laravel-Roundcube.md)). While an
account isn't connected, or must be connected again, the page says so instead of opening the
mail client.

### Cron

`oauth:refresh` runs every 15 minutes (cron must be set up, see
[AI-shared/cron.md](../../../AI-shared/cron.md)). It renews tokens that expire within the next
20 minutes, so a refused connection is found early, and keeps refresh tokens in use.

## For developers

### Moving parts

| Part | Class |
| --- | --- |
| Provider contract | `Providers\OAuthProvider` (abstract) |
| Shipped providers | `Providers\MicrosoftProvider` (`microsoft`), `Providers\GoogleProvider` (`google`) |
| Registry | `OAuthProviders` |
| App credentials | `Models\OAuthClient`, table `epesi_oauth_clients`, one row per provider and environment |
| Tokens | `Models\OAuthToken`, table `epesi_oauth_tokens`, morph alias `oauth_token` |
| Owner contract | `Contracts\OAuthOwner` + trait `Concerns\HasOAuthTokens`; optional `Contracts\OAuthRecipients` |
| Connect flow | `Services\OAuthFlow`, callback `Http\Controllers\CallbackController` |
| Token service | `Services\OAuthTokens` |
| Buttons | `Filament\Actions\ConnectOAuthAction::connect()` / `::disconnect()` |
| Events | `Events\TokenConnected`, `Events\TokenRefreshed`, `Events\TokenNeedsReconnect` |
| Cron | `Console\RefreshTokensCommand` (`oauth:refresh`) |
| Administration → OAuth Apps | `Filament\Administration\Resources\OAuthApps\OAuthAppResource` (list, create, view, edit) |
| Test app | `Filament\Administration\Resources\OAuthApps\Actions\TestAppAction`, result type `Providers\AppCheck` |

All classes are under `Epesi\Modules\OAuth\`.

### Adding a provider

Subclass `OAuthProvider` and register it from your module's service provider `boot()`:

```php
OAuthProviders::register(AllegroProvider::class);
```

Required: `key()` (stable, stored with every row), `label()`, `authorizeUrl(OAuthClient)`,
`tokenUrl(OAuthClient)`, `scopes()` (defaults when the connecting module names none). Optional,
with their defaults:

- `environments()`: `['production' => 'Production']`. Add `'sandbox' => 'Sandbox'` for a
  provider with a test environment. Each environment is its own app under OAuth Apps, with its
  own client ID and secret; build the URLs from `$client->environment`.
- `usesBasicAuth()`: `false` sends the client ID and secret in the form body; `true` sends them
  as HTTP Basic authentication (Allegro).
- `callbackPath()`: `null` uses the shared `oauth/callback`. A path such as
  `administration/allegro/callback` gets its own route, served by the same handler, for an app
  already registered with that URI (providers like Allegro require an exact match). The other
  way is to change the URI on the provider's portal to the shared one. Either way OAuth Apps
  shows the exact URI to register.
- `appFields()`: `[]`. Settings of each app beyond its client ID and secret, as a list of
  `Providers\AppField` (key, label, help, default, required, max length; labels and help are
  English keys). OAuth Apps shows them on the app's create, view and edit pages, stores them in
  `epesi_oauth_clients.settings`, and a new app starts with the defaults. Read one with
  `$client->setting('key')`: the stored value, else the field's default. Use it for what is
  registered per app (Allegro's `User-Agent`), so the app's record is the only screen for it.
- `headers(?OAuthClient $client)`: extra headers for every token-server request (code exchange,
  refresh, app token, revocation) and for `OAuthTokens::http()`. Called for every request with
  the app of the environment in use (null when that environment has no app), so it can read the
  app's settings: Allegro sends `['User-Agent' => $client?->setting('user_agent') ?? default]`.
- `supportsAppTest()`: `false`. `true` puts **Test app** on the app's View and Edit pages
  (`Filament\Administration\Resources\OAuthApps\Actions\TestAppAction`), which calls
  `testApp(OAuthClient $app)` with the stored app (only when it has a client ID and secret) and
  shows the `Providers\AppCheck` list it returns (label, passed, message; translated text that
  never carries a secret or a token). The test must store nothing and change nothing at the
  provider: ask for an app token with `OAuthTokens::requestAppToken()`, not the cached
  `appToken()`, and send requests with `http($provider, $app)`. An `OAuthException` it throws is
  shown as the failure. `appTestLimits()` is the line shown with every result about what the
  test doesn't prove (default: the redirect URI and the scopes).
- `authorizeParameters()`, `scopeSeparator()`, `revokeUrl()`, `usesTenant()`, `consoleUrl()`,
  `setupHelp()`.

### Owners

Any Eloquent model with a registered morph alias can own tokens: implement `OAuthOwner` and use
`HasOAuthTokens`. One owner holds one token per provider, so two seller accounts are two owner
records with two tokens from the same app. Deleting the owner deletes its tokens (the trait's
`deleting` hook; a soft delete keeps them).

- `mayConnectOAuth(User $user)`: the owner's own rule for who may connect, reconnect and
  disconnect it. A mail account allows only its user, super admins included; a business record
  can allow super admins or ask its policy. It is not a policy ability, because Shield's
  `Gate::before` lets super admins past every policy.
- `oauthLabel()`, `oauthLoginHint()` (sent as `login_hint`), `oauthUrl()` (linked from
  notifications).
- Optional `OAuthRecipients::oauthRecipients()`: who gets the bell notification when the token
  needs reconnecting. Without it, every super admin does.

### Connecting

`ConnectOAuthAction::connect($provider, $returnUrl, $scopes, $environment)` puts a Demo-guarded
Connect button on the owner's page. Each argument is a closure that receives the record. It
calls `OAuthFlow::start()`, which:

- checks the app is set up and `mayConnectOAuth()`;
- keeps the flow in the session under a random `state` (owner, user, PKCE verifier, scopes,
  environment, return URL);
- redirects to the provider with an S256 `code_challenge`.

The callback accepts only a state started in the same session, once, within 15 minutes, by the
same user, who must still pass `mayConnectOAuth()`. It exchanges the code with the verifier and
stores the tokens (`TokenConnected`). It is off in demo mode, and it redirects only to addresses
on the installation. Connecting an owner again with more scopes updates the same token row.

### Using a token

`OAuthTokens` (a singleton; needs no session or request, so jobs and cron use it too):

- `accessToken($owner, ?$provider): string` returns a valid token, renewed first when it expires
  within 5 minutes.
- `freshToken($owner, ?$provider, ?$rejected): string` renews regardless of the expiry, for an
  API that answered 401. Pass the refused token: if another process renewed it meanwhile, its new
  token comes back without a second refresh.
- `invalidate($owner, ?$provider)` marks the token expired.
- `environment($owner, ?$provider): ?string` returns the environment the token came from, to pick
  the API host by.
- `http($provider, $app = null)` returns a request with the provider's headers for `$app`: an
  `OAuthClient`, or an environment name (`'sandbox'`), null for production. Pass the token's
  environment (`environment($owner, $provider)`) so the headers come from the right app.
  `authorizedHttp($owner, $provider)` does that and adds the bearer token.
- `appToken($provider, $environment, ?$scopes): string` returns an app-only token (client
  credentials grant), cached until shortly before it expires.
  `requestAppToken(OAuthProvider, OAuthClient $app, ?$scopes)` asks for one every time and keeps
  nothing (`['access_token' => …, 'expires_in' => …]`), for an app test.
- `disconnect($owner, $provider)` revokes the token where the provider can, then deletes it.

Failures throw `Exceptions\OAuthException`, whose message can be shown as it is: it never
contains a token or a secret. A refusal by the provider also carries its error code
(`$providerError`, e.g. `invalid_client`) and HTTP status (`$providerStatus`).

**Locked refresh.** Microsoft and Allegro rotate the refresh token on every refresh, so
refreshing runs under a cache lock per token. A process that waited for the lock reads the row
again and uses the token the other process stored. The lock is in the default cache store, which
every process on an installation shares; `OAUTH_LOCK_STORE` names another store. The per-process
`array` store is never used outside tests. A refresh answered `invalid_grant` marks the token
`needs_reconnect`, fires `TokenNeedsReconnect` once, and notifies the recipients. Other failures
leave it active with `last_error` set.

### Tables

- `epesi_oauth_clients`: `provider`, `environment`, `client_id`, `client_secret` (encrypted),
  `tenant`, `settings` (JSON, the provider's `appFields()`). Unique on (`provider`, `environment`).
- `epesi_oauth_tokens`: `provider`, `environment`, `owner_type`/`owner_id`, `access_token` and
  `refresh_token` (encrypted, `App\Casts\Encrypted`), `expires_at`, `scopes` (granted, else
  requested), `status` (`active` / `needs_reconnect`), `last_error`, `account` (who signed in, from
  the ID token), `connected_by`, `refreshed_at`. Unique on (`owner_type`, `owner_id`, `provider`).

### Tests

`tests/Feature/Modules/OAuthTest.php` (layer) and `tests/Feature/Modules/MailOAuthTest.php`
(Mail). `tests/Fakes/FakeOAuthProvider.php` is an Allegro-shaped provider (sandbox, Basic
auth, its own callback path, a User-Agent app field read at run time). `tests/Fakes/FakeChannel.php` is a business
record that owns tokens.
