# CRM_Mail: password encryption at rest + Gmail OAuth account type — plan (2026-08-29)

> **Status:** PLAN - Phase 1 (password encryption at rest) NOT implemented; Gmail OAuth deferred. Gmail already works via an app-specific password.

Written in response to a request to plan (1) encrypting the plaintext IMAP/SMTP passwords `CRM_Mail`
(`modules/CRM/Mail/`) stores in `rc_accounts`, and (2) adding a second "Gmail (OAuth)" account type alongside
today's plain-IMAP account, so no password is stored for Gmail accounts at all. Two phases, phase 1 is
standalone and should ship first; phase 2 builds on it.

## Status (2026-08-29)

**Phase 1 — implemented**, per user decision to proceed now: `CRM_MailCommon::encrypt()`/`decrypt()`/
`get_encryption_key()` added; `submit_account()`/`encrypt_account_secret()` encrypt on write with the
marker-field merge-guard described below; `QFfield_password()`/`QFfield_smtp_password()` no longer
round-trip the stored value into the edit form (blank-on-edit, masked-on-view, required only when actually
needed); `get_unread_messages()`, `get_connection()`, and `modules/Libs/RoundCube/RC/config/config.inc.php`
(covers both autologon plugins transitively, since they read the same `$account` global) all decrypt at
point of use. Migration patch: `modules/CRM/Mail/patches/20260829_encrypt_account_passwords.php`. `php -l`
clean on every changed/new file; `vendor/bin/phpstan` still not installed in this environment [RESOLVED 2026-08-31: installed via `composer install -d tools`, run it as `tools/vendor/bin/phpstan`] (same gap
noted in `Epesi-Google-Calendar-sync.md`). Not yet verified against a real running instance — see Phase 1's
verification checklist below before considering this fully done.

**Phase 2 — explicitly deferred**, user decision (2026-08-29): *"We will be using Gmail setup where an
account has application specific password and this works with Epesi now. Phase 2 will be implemented later
when I have more time."* I.e. the user's own Gmail account is already working today as a plain-IMAP
`rc_accounts` row, authenticated via a Gmail **app-specific password** (not real OAuth) — which needs no new
code at all now that Phase 1 encrypts it at rest like any other account's password. Gmail OAuth as a
distinct account type (no stored password/app-password whatsoever, full `mail.google.com` scope
consent flow) remains exactly as designed below, to be picked up later. Nothing in this repo currently
blocks that pickup — the design doesn't depend on anything Phase 1 changed beyond what's already noted here.

## Current state (confirmed by reading the code, not assumed)

`rc_accounts` (`modules/CRM/Mail/MailInstall.php:58-80`) stores `Password` and `SMTP Password` as plain
`type=>'text'` columns — genuinely plaintext, not hashed or encrypted. `display_password()`
(`MailCommon_0.php:151`) masks them as `******` in the UI, but `QFfield_password()`/`QFfield_smtp_password()`
(`MailCommon_0.php:156,192`) pre-fill the **real stored plaintext** back into the edit form's `<input
type=password>` value on every edit — so today, opening an account for editing round-trips the plaintext
into page HTML, and the password field is unconditionally `required`, forcing re-entry (or re-exposure) on
every edit.

There are exactly two places in PHP that read this password to actually connect anywhere (confirmed by
grep — nothing else touches `f_password`/`f_smtp_password` or `$rec['password']`/`$rec['smtp_password']`):

1. `CRM_MailCommon::get_unread_messages()` (`MailCommon_0.php:571-622`) — the "Mail indicator" applet,
   calls PHP's `imap_open()` directly with `$rec['login']`/`$rec['password']`.
2. `modules/Libs/RoundCube/RC/config/config.inc.php:51,106-128` — the vendored full Roundcube webmail app
   (used for the actual inbox/compose UI, reached via `CRM_Roundcube`) does a **raw** `DB::GetRow('SELECT *
   FROM rc_accounts_data_1 ...')`, bypassing `Utils_RecordBrowserCommon::get_record()` entirely, and feeds
   `$account['f_password']`/`f_smtp_password'` straight into Roundcube's own IMAP/SMTP config keys. The two
   autologon plugins (`epesi_autologon.php`, `epesi_autorelogon.php`) also read `$account['f_password']`
   directly for the IMAP login handshake.

A third method, `CRM_MailCommon::get_connection()`/`get_folders()` (`MailCommon_0.php:732-766`, wraps the
vendored `Fetch\Server` / c-client `imap_open`), also reads `$rec['password']` — but grep across `modules/`
finds no caller of `get_folders()` anywhere else. It appears to be dead/unreachable code today. Left alone
in this plan (not fixed, not deliberately broken further) — flagged under Phase 2's known gaps below since
it has no OAuth story either.

**Pre-existing bug worth noting (not caused by this plan, but relevant to Phase 2):** `config.inc.php:120`
computes `$config['smtp_port'] = $account['f_smtp_security']=='ssl'?465:25` — STARTTLS (`security=='tls'`,
the modern default for port 587) falls through to port 25, which is wrong. Gmail's SMTP works on either
465 (implicit TLS) or 587 (STARTTLS); Phase 2's Gmail preset sidesteps this by using `security='ssl'`/port
465 for SMTP rather than `tls`/587, so this plan doesn't strictly need to fix it — but call it out to the
user since it likely already misconfigures port 25 for any existing STARTTLS-on-587 provider.

## Why these choices (research findings)

- **Real encryption-at-rest precedent already exists in this codebase**: `CRM_GoogleCalendarSyncCommon::
  encrypt()`/`decrypt()`/`get_encryption_key()` (`modules/CRM/GoogleCalendarSync/GoogleCalendarSyncCommon_0.php:488-516`,
  built 2026-08-24/28) — AES-256-GCM via `openssl_encrypt`, keyed by a random 32-byte file under
  `ModuleManager::get_data_dir(...)` (outside the DB, outside git). Its own docblock says explicitly: *"No
  existing encrypted-secret precedent anywhere in core Epesi (`CRM_Mail`'s `rc_accounts` passwords are
  plaintext, masked only on display) — this is new, minimal, and contained to this module."* This plan is
  the direct follow-through on that gap.
- **A second, independent design already planned the same pattern**: `modules/Premium/PasswordManager/PLAN.md`
  §5 (design-only, not yet built) copies the identical `encrypt()`/`decrypt()`/`get_encryption_key()` shape
  for a generic credential-vault module, and its §4/§6 worked through two gotchas directly relevant here:
  - **Leave the password field blank on edit**, with a "leave blank to keep current password" hint — never
    round-trip the decrypted value back into form HTML.
  - **The `update_record()` merge trap**: `Utils_RecordBrowserCommon::update_record()` (used by both the
    real edit form and the grid's inline single-field edit) merges the *existing* stored value into
    `$values` for any field the caller didn't touch, before the processing callback runs. So an edit that
    only touches, say, "Default Account" arrives at `submit_account()` with `$values['password']` silently
    populated from the **old ciphertext** — indistinguishable from "blank = no change" or "genuine new
    plaintext" by content alone. PasswordManager's planned fix: a QuickForm-only hidden marker field
    (`password_submitted`) present on any *real* form submission but never part of the stored record, so
    the processing callback only trusts the password field's emptiness when that marker is present.
    `CRM_Mail`'s `submit_account()` needs the identical fix for both `password` and `smtp_password`, or a
    routine unrelated edit will silently corrupt (double-encrypt) the stored password.
- **Three consumers now justify a shared helper.** GoogleCalendarSync (built), PasswordManager (planned),
  and this plan make three independent copies of the same ~20-line encrypt/decrypt pair. Recommend
  extracting a small shared static, parameterized by module name (so each module keeps its own isolated
  key file — a compromise of one module's key doesn't expose another's secrets, preserving the original
  "contained" reasoning): `Base_CryptCommon::encrypt($plain, $module_name)` /
  `decrypt($encoded, $module_name)` / private `get_encryption_key($module_name)`. GoogleCalendarSync's own
  three methods become one-line delegations. **This is a nice-to-have, not a blocker** — Phase 1 can ship
  with its own copy first and be refactored to share once this doc is agreed.
- **Roundcube's vendored IMAP/SMTP clients already support OAuth natively** — confirmed by reading the
  vendored code, not assumed: `rcube_imap_generic::authenticate()` (`modules/Libs/RoundCube/RC/program/lib/
  Roundcube/rcube_imap_generic.php:761-764,936-980`) implements both `XOAUTH2` and `OAUTHBEARER` SASL
  mechanisms when `$this->prefs['auth_type']` is set accordingly; the vendored PEAR `Net_SMTP`
  (`modules/Libs/RoundCube/RC/vendor/pear/net_smtp/Net/SMTP.php:235-236,1118-1160`) implements the same two
  methods for SMTP. **This means the existing Roundcube webmail experience (inbox browse, compose, send)
  used for both plain-IMAP and Gmail accounts today can keep working unmodified for Gmail-OAuth accounts**
  — only the credential-and-auth-type wiring in `config.inc.php` and the two autologon plugins needs to
  branch on account type. This significantly de-risks Phase 2 versus building a parallel Gmail-specific
  mail client. (Note: `rcmail_oauth.php`/`actions/login/oauth.php` in the same tree is a *different*
  feature — Roundcube's own "log into Roundcube via an external SSO/OIDC provider" flow, unrelated to
  authenticating *to the IMAP/SMTP backend* with a token. Not used by this plan.)
- **PHP's `imap_open()`/c-client (used by `get_unread_messages()`, and by the apparently-dead
  `get_connection()`) has no reliable, version-independent OAuth2 support** worth depending on here — unlike
  Roundcube's own pure-PHP IMAP client, c-client's OAUTHBEARER support is inconsistent across builds/OSes
  (this repo already runs on both Windows/XAMPP and Linux, per `CLAUDE.md`'s environment-quirks section).
  `CRM_GoogleCalendarSyncCommon`'s own precedent for talking to Google is a hand-rolled curl+JSON REST
  client, specifically to avoid a heavy/inconsistent SDK/library dependency (see its "No vendored
  dependency" section) — the same reasoning applies here: for the one native-PHP consumer that needs live
  Gmail data (the unread-mail applet), call the **Gmail REST API** (`gmail.googleapis.com`) directly via
  curl, rather than routing it through `imap_open`.
- **Field size**: `Password`/`SMTP Password` are `param=>'255'` (`VARCHAR(255)`). AES-256-GCM ciphertext is
  `base64(12-byte IV + 16-byte tag + ciphertext-same-length-as-plaintext)`; for any realistic password
  (well under ~150 chars) this comfortably fits under 255 after base64 inflation. No schema resize needed.

## Phase 1 — Encrypt `rc_accounts` passwords at rest

Ships independently of Phase 2 and fixes the plaintext-storage problem for every existing (and future
plain-IMAP) account.

### 1. Crypto helper

Add `CRM_MailCommon::encrypt($plain)` / `decrypt($encoded)`, copying the GoogleCalendarSync
implementation verbatim (AES-256-GCM, `openssl_encrypt`/`_decrypt`, random-32-byte key file under
`ModuleManager::get_data_dir('CRM_Mail')`, gitignored). If the `Base_CryptCommon` extraction above is done
first, these become one-line delegations instead.

### 2. Encrypt on write — fix `submit_account()`

`MailCommon_0.php:51-68` already runs as a registered processing callback for every add/edit
(`register_processing_callback('rc_accounts', ...)`), so this is the one correct choke point — no new
plumbing needed, just correct logic:

- `QFfield_password()`/`QFfield_smtp_password()`: stop calling `$form->setDefaults(array($field=>$default))`
  with the real stored value — leave the field blank on `edit`, with a hint label ("Leave blank to keep the
  current password"). Add a parallel hidden QuickForm element (e.g. `password_submitted`) that's always
  present on a real submission, so the processing callback can distinguish "field left blank on purpose" from
  "not part of this submission at all" (the `update_record()` merge case described above). Only require the
  password field (`addRule(...,'required')`) when `$mode == 'add'` — never on `edit`.
- `submit_account($param, $mode)`: for each of `password`/`smtp_password`, only touch the key in `$param`
  when the matching `*_submitted` marker is present *and* the value is non-empty — encrypt it in place. If
  the marker is present but the value is empty (edit, left blank on purpose), `unset($param['password'])`
  (and the smtp counterpart) so `update_record()`'s own field-diff skips the column, leaving the existing
  ciphertext untouched. On `mode=='add'`, both are always present/required, so this reduces to "always
  encrypt."

### 3. Decrypt at the point of use

Exactly two spots need a one-line wrap, per the "Current state" findings above:

- `get_unread_messages()` (`MailCommon_0.php:587`): wrap `$rec['password']` in `self::decrypt(...)` before
  passing to `imap_open()`. (The cache key on line 580 already hashes the raw field value — fine either way,
  ciphertext or plaintext both work as a cache-key input, but note it'll change once ciphertext replaces
  plaintext there, which just means one harmless one-time cache miss per account.)
- `config.inc.php:51`: right after the raw `DB::GetRow(...)` populates `$account`, decrypt
  `$account['f_password']` and `$account['f_smtp_password']` in place (`$account['f_password'] =
  CRM_MailCommon::decrypt($account['f_password']);` etc.) before anything else in the file reads them. This
  single edit covers both autologon plugins (they read the same `$account` global) and the
  `smtp_user`/`smtp_pass` config keys later in the same file.

`get_connection()`/`get_folders()` (apparently dead code) — decrypt there too for consistency/correctness,
even though nothing currently calls it; costs nothing and avoids leaving a silently-broken method behind.

### 4. Migrate existing data — new patch

Per `CLAUDE.md`'s upgrade-gap discipline: this changes stored data, so it needs a dated patch file (not just
an `Install.php` edit) to reach existing installs, e.g.
`modules/CRM/Mail/patches/20260829_encrypt_account_passwords.php`:

```php
$rows = DB::GetAll('SELECT id, f_password, f_smtp_password FROM rc_accounts_data_1');
foreach ((array) $rows as $r) {
    DB::Execute('UPDATE rc_accounts_data_1 SET f_password=%s, f_smtp_password=%s WHERE id=%d', array(
        $r['f_password'] !== '' ? CRM_MailCommon::encrypt($r['f_password']) : '',
        $r['f_smtp_password'] !== '' ? CRM_MailCommon::encrypt($r['f_smtp_password']) : '',
        $r['id'],
    ));
}
```

No schema change (same `text`/255 columns, just ciphertext going forward) — this patch is pure data
migration, safe to write directly rather than via `new_record_field()`.

## Phase 2 — "Gmail (OAuth)" as a second account type

Adds a second `Account Type` (IMAP / Gmail) at account-creation time. A Gmail account stores no password at
all — an OAuth access/refresh token pair instead, following the exact `CRM_GoogleCalendarSyncCommon` OAuth
pattern (admin registers a Google Cloud OAuth Client ID/Secret once; each user does a one-click Google
consent per account).

### 1. Schema — new fields on `rc_accounts`, additive patch

Same table, four new fields (`Utils_RecordBrowserCommon::new_record_field()`, non-destructive — see
`AI-shared/recordbrowser-live-schema-changes.md` for why this is the right primitive for an
already-populated recordset — plus mirrored entries in `MailInstall.php`'s own field array for fresh
installs):

- `Account Type` — `commondata` (new `CRM/Mail/AccountType` common-data set: `imap` default / `gmail_oauth`),
  `visible=>false` (drives form behavior via JS, not shown as a browse column).
- `OAuth Access Token` — `long text`, `visible=>false`.
- `OAuth Refresh Token` — `long text`, `visible=>false`.
- `OAuth Token Expires` — `timestamp`, `visible=>false`.

For a `gmail_oauth` row, the *existing* `Server`/`Security`/`SMTP Server`/`SMTP Security`/`SMTP Auth`/
`Login`/`SMTP Login` fields are populated automatically at connect time with Gmail's known-good constants
(`imap.gmail.com`/`ssl`, `smtp.gmail.com`/`ssl`/port 465 — deliberately `ssl` not `tls`, sidestepping the
pre-existing `smtp_port` STARTTLS bug noted above — `smtp_auth=1`, both logins = the connected Gmail
address). This is the key design choice that keeps every *existing* consumer (`config.inc.php`,
`get_unread_messages()`, the account grid, "Default Account" logic, etc.) working against `rc_accounts`
unmodified for connectivity metadata — only the *password* itself is fundamentally different for these rows.

### 2. Admin config — Google Cloud OAuth Client ID/Secret

Own `Variable::set('crm_mail_gmail_client_id', ...)`/`_client_secret` pair — **not** shared with
`crm_googlecalendarsync_client_id`, since it's a different OAuth app/scope/consent-screen configuration and
`CRM_GoogleCalendarSync` may not even be installed. New admin screen mirroring
`CRM_GoogleCalendarSync_0::admin()` (`admin_caption()` → `Server Configuration` section, `admin_access()` →
`Base_AclCommon::i_am_sa()`).

### 3. Connect UX — a type picker ahead of the generic RecordBrowser form

`Mail_0::account_manager()` (`Mail_0.php:308`) today just opens the stock generic
`Utils_RecordBrowser`/`rc_accounts` add/edit screen shown in the screenshot — there's no room inside that
generic field-array-driven form for "click a button, get redirected to Google, come back with tokens
already filled in." Recommended shape: a small new "Add E-mail Account" picker page (own method on
`Mail_0`, rendered inline like `CRM_GoogleCalendarSync_0::connect()` — no template file needed) offering two
choices:

- **"IMAP" →** proceeds to today's existing generic add form, unchanged, for `account_type=imap`.
- **"Gmail" →** a "Sign in with Google" link straight to the OAuth authorize URL (scope
  `https://mail.google.com/` — full IMAP/SMTP access; this is one of Google's *restricted* scopes, see Risks
  below). No form fields at all. On return, an `ajax.php` OAuth callback
  (`CRM_MailCommon::gmail_oauth_callback`, built exactly like
  `CRM_GoogleCalendarSyncCommon::oauth_callback()`/`save_account()` — same `oauth_redirect_uri()`
  byte-stable-URL trick) exchanges the code, fetches the connected address, and calls
  `Utils_RecordBrowserCommon::new_record('rc_accounts', [...])` directly server-side with the Gmail presets
  above plus the encrypted tokens — never through the QuickForm/password-field machinery at all.

(Alternative considered: fold the type choice into the existing single form as a toggle field, à la the
existing `smtp_auth` show/hide JS pattern. Rejected as the primary design — Gmail rows need zero of the
manual server/password fields, and an OAuth redirect-and-callback doesn't fit a single synchronous form
submit — but flag this as the fallback if a two-step flow turns out to clash with how `Utils_RecordBrowser`'s
own "Add" button is wired.)

### 4. Live token access — `get_imap_password()`/`get_smtp_password()`

New small helpers in `CRM_MailCommon`, used everywhere Phase 1 introduced a `decrypt()` call:

```php
public static function get_imap_password(&$rec) {
    if (($rec['account_type'] ?? 'imap') !== 'gmail_oauth') return self::decrypt($rec['password']);
    return self::gmail_access_token($rec); // refreshes via oauth2.googleapis.com if token_expires has passed, mirrors
                                            // CRM_GoogleCalendarSyncCommon::access_token_for_account()
}
```

Gmail uses the **same** access token for both IMAP and SMTP, so `get_smtp_password()` for a `gmail_oauth`
row just returns the same token. `$rec` passed by reference so a refreshed token's new `token_expires` is
visible to the caller without a second DB round-trip (same convention as
`access_token_for_account()`), while the refreshed token is always persisted back to the DB (encrypted)
regardless.

### 5. Wiring the two real consumers

- **`config.inc.php`**: after decrypting/resolving the password per account (Phase 1's edit), branch: if
  `account_type=='gmail_oauth'`, set `$config['imap_auth_type'] = $config['smtp_auth_type'] = 'XOAUTH2'` and
  use the live access token (via `get_imap_password()`/`get_smtp_password()`) as the "password" passed
  through everywhere `f_password`/`f_smtp_password` are read today — both autologon plugins' `authenticate()`
  hooks included (they just read the same resolved value, no plugin-code change needed if the resolution
  happens once in `config.inc.php` before the plugins run — confirm this is early enough at implementation
  time; if not, the two plugins need the same one-line branch).
- **`get_unread_messages()`**: for `gmail_oauth` rows, skip `imap_open()` entirely — call the Gmail REST API
  instead (`GET https://gmail.googleapis.com/gmail/v1/users/me/messages?q=is:unread`, `Authorization: Bearer
  <token>`), reusing `CRM_GoogleCalendarSyncCommon`'s hand-rolled `http_request()` shape (or the shared
  `Base_CryptCommon`-style extraction, if a `Base_GoogleOAuthCommon` helper ends up worth pulling out too —
  optional, not required for v1).

### 6. Known gap, stated rather than silently left

`get_connection()`/`get_folders()` (the `Fetch\Server`/c-client wrapper) has no OAuth path in this plan and,
per the research above, is not called anywhere else in `modules/` today — left as-is for `gmail_oauth`
accounts (they simply can't use it, same as today's dead-code status for everyone). If a future caller needs
it, that's new scope, not a silent regression from this plan.

### 7. Disconnect / revoke

Mirror `CRM_GoogleCalendarSyncCommon::disconnect()` — an "Disconnect Gmail" action (from the account's edit
view or a custom button) that best-effort revokes via `https://oauth2.googleapis.com/revoke` and deletes (or
converts back to a manual IMAP row, if the user wants to keep using the address with an app password) the
`rc_accounts` row.

## Risks / open questions (surface before building, not after)

- **Google Cloud Console setup friction is a known, already-hit cost in this exact codebase.**
  `AI-shared/Epesi-Google-Calendar-sync.md`'s own "On hold" note: that module got all the way to a live
  "Connected" OAuth status and then hit `insufficient authentication scopes` from Console-side consent-screen
  scope declaration, fiddly enough that the user paused rather than push through in one sitting. Gmail's
  `https://mail.google.com/` scope (full mailbox access) is *more* sensitive than Calendar's — expect the
  same or worse friction (Google's "restricted scope" tier, Testing-mode publish status with an explicit
  test-user allowlist, possibly a CASA security assessment if ever taken to public "In production" status).
  This is fine for a single self-hosted install's own small user list under Testing mode, but worth setting
  expectations up front rather than discovering it mid-build.
- **Every Epesi installation that wants Gmail OAuth needs its own Google Cloud OAuth client** (Client
  ID/Secret + registered redirect URI) — there's no shared/central one this ships with, same as
  `CRM_GoogleCalendarSync` today. Fine for a self-hosted app, but means the admin setup instructions need to
  be as thorough as `GoogleCalendarSync`'s own `README.md` walkthrough.
- **Roundcube OAuth wiring (`config.inc.php` + both autologon plugins) is based on reading the vendored
  IMAP/SMTP client code, not a live end-to-end test against real Gmail credentials** — high confidence
  (`XOAUTH2`/`OAUTHBEARER` are genuinely implemented in both `rcube_imap_generic` and the vendored
  `Net_SMTP`), but should be the first thing verified end-to-end once a real Google Cloud OAuth client is
  available, before considering Phase 2 done.

## Critical files (once built)

- `modules/CRM/Mail/MailCommon_0.php` — `encrypt()`/`decrypt()` (or delegate to a shared `Base_CryptCommon`),
  fixed `QFfield_password`/`QFfield_smtp_password`/`submit_account()`, `get_unread_messages()` decrypt,
  Phase 2's `get_imap_password()`/`get_smtp_password()`/OAuth flow/Gmail REST helpers.
- `modules/CRM/Mail/MailInstall.php` — new Phase 2 fields mirrored for fresh installs.
- `modules/CRM/Mail/Mail_0.php` — new account-type picker entry point (Phase 2).
- `modules/CRM/Mail/patches/20260829_encrypt_account_passwords.php` — Phase 1 data migration.
- `modules/CRM/Mail/patches/<date>_gmail_oauth_fields.php` — Phase 2 `new_record_field()` additions.
- `modules/Libs/RoundCube/RC/config/config.inc.php`, `.../plugins/epesi_autologon/epesi_autologon.php`,
  `.../plugins/epesi_autorelogon/epesi_autorelogon.php` — decrypt (Phase 1) / OAuth branch (Phase 2).
- Optional shared extraction: new `Base_CryptCommon` (or similar), with
  `CRM_GoogleCalendarSyncCommon`/`Premium_PasswordManagerCommon` (when built) updated to delegate to it.

## Verification checklist

**Phase 1:**
1. `php -l` every changed file. Existing account: confirm `rc_accounts_data_1.f_password` is ciphertext in
   the DB after the migration patch runs, and that the account still authenticates (mail indicator applet
   shows correct unread count; opening the account in the Roundcube webmail iframe still logs in).
2. Edit an *unrelated* field on an existing account (e.g. toggle "Default Account") without touching the
   password field — confirm the stored password still decrypts correctly afterward (this is the
   `update_record()`-merge regression test called out above).
3. Edit an account and actually change its password — confirm the new plaintext gets encrypted, old value is
   gone, and the account authenticates with the new password afterward.
4. View-source on the edit form — confirm no plaintext or ciphertext password value is ever emitted into the
   page HTML.
5. Add a brand-new account — password still required or the "Add" submission should reject.

**Phase 2:**
1. Google Cloud Console: create an OAuth Client ID (Web application), enable whatever's needed for
   `mail.google.com` scope, add the app's redirect URI, add the test Gmail account as a test user under
   Testing publish status.
2. From "Add E-mail Account", choose Gmail, complete Google's consent screen, confirm a new `rc_accounts`
   row appears with `account_type=gmail_oauth`, encrypted tokens, and the Gmail preset connectivity fields.
3. Open the account in the Roundcube webmail iframe — confirm it logs in via XOAUTH2 (check
   `data/CRM_Roundcube/log/` for the actual auth mechanism used) and both inbox browsing and sending a test
   email work.
4. Confirm the mail indicator applet shows the correct unread count for the Gmail account (exercising the
   Gmail REST API path, not `imap_open`).
5. Force `token_expires` into the past and repeat steps 3–4 — confirm token refresh happens transparently.
6. Disconnect the account — confirm the token is revoked (or best-effort attempted) and the row is removed.
