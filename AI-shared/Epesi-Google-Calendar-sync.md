# Google Calendar Sync — approved design (2026-08-24)

Approved plan for a new installable module, `modules/CRM/GoogleCalendarSync/`, that pushes each user's
Epesi meetings to their own Google Calendar. Not yet implemented — this is the design to build from,
checked in ahead of the code so the shape survives a context reset / different machine picking up the
work. Update this file (or note "implemented, see commit X") once built; don't let it drift once it does.

## On hold (2026-08-28)

Built, installed, and got as far as a real live "Connected" status (`jtylek@gmail.com`) via the actual
Google OAuth flow — then hit `insufficient authentication scopes` on the actual Calendar API calls,
traced to Google's Data Access/scope-declaration requirement (see `README.md`'s setup walkthrough for
the full explanation). Put on hold by the user at that point rather than pushing through in one sitting
— the Console-side setup (project selection, enabling the API, consent-screen scope declaration, the
scope-picker's enabled-APIs-only filter) turned out fiddlier in practice than the code itself.
**Not abandoned.** Concretely, as of this pause: `GoogleCalendarSyncInstall.php`'s `simple_setup()` is
commented out (so it no longer shows in Modules Administration & Store, only in Advanced Setup) and the
module has been manually uninstalled from this dev instance. See `README.md`'s own "On hold" note at
the top for the same status, kept in sync with this one. Full setup/troubleshooting instructions are in
that README, not repeated here.

## Implementation status (2026-08-28, code complete — not yet verified live)

All three PHP files are written and `php -l` clean:
- `modules/CRM/GoogleCalendarSync/GoogleCalendarSyncInstall.php` — both tables
  (`crm_googlecalendarsync_accounts`, `crm_googlecalendarsync_map`, with unique indexes) and
  `Base_AclCommon::add_permission(_M('Google Calendar Sync'), array('ACCESS:employee'))`.
- `modules/CRM/GoogleCalendarSync/GoogleCalendarSyncCommon_0.php` — tile registration
  (`user_settings()`, gated on the ACL permission above — it wasn't actually checked anywhere in an
  earlier draft, fixed), admin config (`admin_caption()`/`admin_access()`), cron driver
  (`cron()`/`cron_sync()`/`sync_account()`), RRULE translation, encrypt/decrypt, and the full OAuth +
  Calendar-API-v3 flow as hand-rolled curl calls (see "No vendored dependency" section below for why).
- `modules/CRM/GoogleCalendarSync/GoogleCalendarSync_0.php` — `connect()` (My Settings tile target:
  status card + Connect/Disconnect) and `admin()` (superadmin-only Client ID/Secret form). No template
  files — both render via inline `print()` branching on `Base_ThemeCommon::is_adminlte_family()`, the
  same pattern `Base_EssClient::admin()`/`no_ssl_settings()` already use for simple status/form screens;
  deliberately deviates from the design's `theme/`+`theme_adminlte/` file list below (also see the
  `theme_adminltedark` correction 2 items down — that pairing is stale either way).

**Not yet done — needs a human with real Google Cloud credentials, can't be verified further here:**
`console.php module:install CRM/GoogleCalendarSync` hasn't been run, and the live OAuth
consent→callback→token-refresh→event-insert round trip is entirely untested (no Google OAuth Client
ID/Secret available in this environment). Walk through this file's own "Verification" checklist below
end-to-end before considering this done.

**Corrections found during research, supersede the original design text above:**
- The design says `theme/` + `theme_adminlte/`. **Wrong as of this repo's current state**:
  `theme_adminlte/` (light) was deleted outright on 2026-08-04 — the only AdminLTE-family theme now
  is `theme_adminltedark/` (see `adminlte-theme.md`). Moot anyway given the inline-`print()` plan above,
  but if templates end up getting added later, it must be `theme_adminltedark/`, not `theme_adminlte/`.
- `Base_Admin`'s tile-click dispatch (`Admin_0.php::list_admin_modules()`) calls
  `$this->pack_module($module, null, 'admin')` — i.e. the admin config screen must be a **public
  instance method `admin($store=false)` on the `CRM_GoogleCalendarSync` Module class** (`_0.php`),
  not a Common static. Mirror `Base_EssClient::admin()`: gate with
  `if (!Base_AclCommon::i_am_sa()) return;` at the top (that boolean check is also exactly what
  `CRM_GoogleCalendarSyncCommon::admin_access()` should return — no `admin_access_levels()` needed,
  confirmed via `Base_EssClientCommon::admin_access()`).
- Confirmed via `Base_AdminCommon::get_access()` / `ModuleManager::check_access()` that `admin_access()`
  alone (boolean) is sufficient to gate the whole admin tile; don't overbuild with per-section levels.
- `CRM_FiltersCommon::get_my_profile()` returns a **contact id** (`CRM_ContactsCommon::get_my_record()`
  wraps `Acl::get_user()`), not a criterion array — for `sync_account()`, resolve each account's contact
  id via `CRM_ContactsCommon::get_contact_by_user_id($account['epesi_user_id'])['id']` (doesn't depend
  on the global logged-in user, unlike `get_my_record()`).
- Decided **not** to build a RecordBrowser crits date-range expression for the ±window (didn't want to
  guess at the crits DSL's range-operator syntax without verifying it) — instead fetch all active
  `crm_meeting` rows for the contact via `get_records('crm_meeting', array('employees' => $contact_id))`
  (multiselect containment crit, same shape as `crm_event_get()`'s `'employees'=>$me['id']` default-value
  usage) and filter the -7d/+180d window in PHP against `date`/`recurrence_end`. Cheap enough since
  recurring meetings are single master rows, not expanded occurrences.
- `get_records()` already applies `active=1` and per-viewer ACL filtering itself (`build_query()`) — no
  need to pass `active=1` explicitly, but note it filters by the **currently logged-in** Acl user's view
  access, which under cron (`Base_AclCommon::set_sa_user()`) is SA and therefore no real restriction —
  the `employees` crit is what actually scopes results to the right person, not ACL.
- DB column type codes confirmed from existing `DB::CreateTable()` calls: `I4`=int, `I8`=bigint,
  `C(n)`=varchar, `X`=text/long text, `T`=datetime (`DEFTIMESTAMP` for a default-now column), `I1` used
  elsewhere for a boolean-ish flag. Columns are nullable by default unless `NOTNULL` is added. Unique
  index: `DB::CreateIndex($name, $table, 'col1,col2', array('UNIQUE'=>1))`.
- `Variable::get($name, false)` returns `''` instead of throwing when unset (2nd arg is `$throw_error`) —
  use that (not a try/catch) when reading the admin-configured Client ID/Secret.

**Resolved: the redirect_uri stability question flagged in an earlier pass of this section.** Google
requires the `redirect_uri` sent when requesting authorization and the one sent when exchanging the
code to be the exact same string, registerable once in Google Cloud Console. Plain
`Module::create_ajax_callback_url()` (`include/module.php:758`) bakes in the *live* per-tab `CID`
(`include/session.php:490` — `HTTP_X_CLIENT_ID`, not obviously stable across renders/tabs), which
would have broken that. Fixed by **not** using it: `CRM_GoogleCalendarSyncCommon::oauth_redirect_uri()`
inlines the same key-generation formula (`md5(serialize($func).serialize($args))`,
`$_SESSION['ajax_callbacks'][$key] = ...`) but with a hardcoded `cid=0` instead of the live `CID` —
`ajax.php` only ever checks `is_numeric($_GET['cid'])`, it never compares it against anything, so `0` is
just as valid as whatever the real per-tab id would have been, and is byte-stable across every render/
tab/session. Both `connect()` (building the "Connect" link) and `admin()` (displaying the URI to paste
into Google Cloud Console) call this same helper, so they always agree.

**Remaining next steps, in order:** (1) `php -l` already clean on all three files — run
`vendor/bin/phpstan analyse -c phpstan.neon` too once phpstan is available in this environment (it
isn't currently installed here); (2) `console.php module:install CRM/GoogleCalendarSync`, confirm both
tables + the ACL permission get created; (3) get a real Google Cloud OAuth Client ID/Secret from the
user (enable the Calendar API, Web-application credential type) and work through the "Verification"
checklist below end-to-end, including the token-refresh-after-expiry case and the recurrence variants.

## Scope

- **One-way sync: Epesi → Google only.** Epesi is the source of truth; edits made directly on the Google
  side are never pulled back or diffed against.
- **Per-user OAuth.** Each user connects their own Google account (mirrors `CRM_Mail`'s `rc_accounts` —
  one external-account row per Epesi user).
- **Cron polling only**, no push webhooks — fits `cron.php`'s one-callback-per-request drip model, no
  public-facing endpoint needed.
- **UI**: a new "Google Calendar Sync" tile in **My Settings**, next to "E-mail Accounts"/"Calendar".
  The tile opens a status page with a Connect/Disconnect button — no manual Client ID/Secret entry by
  the end user; the OAuth app itself is configured once, admin-side.

## Why these choices (research findings)

- The real event data is **`crm_meeting`** (a `Utils_RecordBrowser` recordset, `modules/CRM/Meeting/MeetingInstall.php`),
  **not** `modules/CRM/Calendar/Event/` — that path is just a thin dispatcher. `CRM/Calendar` is an
  aggregator: modules register as event sources via `CRM_CalendarCommon::new_event_handler()` into table
  `crm_calendar_custom_events_handlers`. This module doesn't need to register as a handler — it only
  reads `crm_meeting` as a data source.
- Recurrence on `crm_meeting` is a **single master row**, not RRULE and not expanded rows:
  `recurrence_type` (`''`=none, `1..7`=every N days, `8`=custom weekdays via `recurrence_hash`,
  `9`=every 2 weeks, `10`=monthly, `11`=yearly) + `recurrence_end`. `Duration == -1` means
  timeless/all-day. No timezone field (naive date+time), no location field, no external-ID/etag field
  anywhere. (`CRM_MeetingCommon::crm_event_get()`, `modules/CRM/Meeting/MeetingCommon_0.php:609-767`, is
  the reference expansion logic if ever needed — but for one-way push this design instead **translates
  the encoding directly into a Google RRULE string** and pushes one recurring Google event per meeting,
  no occurrence-level expansion.)
- CRUD goes through generic RecordBrowser statics, not a bespoke API:
  `Utils_RecordBrowserCommon::get_records()/get_record()/new_record()/update_record()/delete_record()`.
  `delete_record()` defaults to soft-delete (`active=0`) — matters for detecting "this meeting was
  removed" during sync. `Employees` (multiselect of `crm_contact` ids) is the participant field; there's
  no per-record owner — visibility is ACL-driven off `Permission` (public/read-only/private) + `Employees`.
- No existing external-ID/sync-token field to piggyback on anywhere in the codebase → this module needs
  its own mapping table.
- Module scaffolding command is `dev:module:create` (CLAUDE.md's `dev:create:module` is stale). Install
  is `console.php module:install <path>`.
- Cron hook: a module's `*Common` class defines `cron()` returning `['method' => interval_minutes]`;
  `cron.php` runs **one due callback per invocation** (see `modules/Utils/Watchdog/WatchdogCommon_0.php`) —
  the sync driver must self-batch, not assume it can loop over every account in one call.
- `Variable::get()/set()` (`include/variables.php`) is the right place for installation-wide config
  (Google OAuth Client ID/Secret). There's **no encryption-at-rest precedent** anywhere in core Epesi
  (`CRM_Mail`'s `rc_accounts` passwords are plaintext, masked only on display) — this module adds a
  minimal encrypt/decrypt helper for OAuth tokens rather than copying that plaintext precedent.
- The **My Settings tile** mechanism: `CRM_MailCommon::user_settings()` (`modules/CRM/Mail/MailCommon_0.php:665-670`)
  returns `array(__('E-mail Accounts') => 'account_manager')` — a **string** value (method name) makes
  the tile a link that pushes the module and calls that method as a full page
  (`Base_BoxCommon::push_module`, see `modules/CRM/Mail/Mail_0.php:290` and `account_manager()` at
  line 308). This is the template for our tile, since the screen needs a real page (status + button),
  not inline QuickForm fields.
- Ajax callback pattern for the OAuth redirect endpoint: `Module::create_ajax_callback_url(array('Class',
  'method'), $args)` (see `modules/CRM/Calendar/CalendarCommon_0.php:128-189`) — must be a plain static
  array-callable (not a closure), signature `function(Request $request, $args): Response`. The URL is
  deterministic for a fixed `$func`/`$args` pair, so it's stable enough to register once as the OAuth
  redirect URI in Google Cloud Console.
- No Guzzle client at the app root (only PSR-7 message objects). The established pattern for a module
  needing a real external API client is its own nested `composer.json` + vendored `vendor/`, wired into
  root `composer.json`'s `post-install-cmd` — exactly like `modules/CRM/Mail/` and `modules/Libs/TCPDF/`
  already do.
- `Base_ThemeCommon::install_default_theme()`/`uninstall_default_theme()` are confirmed **dead no-ops**
  since 2026-07-31 (themes resolve straight from `modules/` now) — do not call them in `Install.php`; the
  `dev:module:create` scaffold already omits it. See `deliberate-removals.md` and `MIGRATION_NOTES.md` §70.

## Design

### 1. No vendored dependency — hand-rolled curl client instead (revised 2026-08-28)

**Originally** this section specified `modules/CRM/GoogleCalendarSync/composer.json` requiring
`google/apiclient` (official Google API PHP client). **Reversed after actually installing it**:
`google/apiclient` pulls in `google/apiclient-services` as a *mandatory* (not optional/suggested)
dependency — a single monolithic package bundling PHP client stubs for *every* Google API (Gmail,
Drive, YouTube, Sheets, Calendar, hundreds of others), because Google ships one combined services
package rather than a per-API split. There is no `composer require` path to pull in just the Calendar
slice of it. Measured directly in this repo: **over 400MB installed** (10,000+ files) — bigger than
this entire Epesi checkout including its own root `vendor/`, for what amounts to a handful of REST
calls. Flagged by the user mid-build (`git status` showing 10,000+ new files) and confirmed before
proceeding further.

**Decided instead**: no Composer dependency at all for this module. The Google Calendar API v3 is a
plain JSON/HTTPS REST API, and OAuth2 authorization-code exchange/refresh is just a couple of
`POST` requests — both are straightforward to hand-roll with PHP's built-in `curl` extension. This
also better matches this codebase's own conventions: root `composer.json` doesn't even carry a full
HTTP client (`guzzlehttp/psr7` there is message objects only, not a client), and CLAUDE.md's stated
ethos is "no build step, surgical changes" — vendoring an entire multi-API SDK for one API was a
mismatch with that. Trade-off accepted deliberately: ~100-150 lines of custom HTTP/OAuth/Calendar-REST
code to write and maintain ourselves, in exchange for zero new dependencies and no `vendor/` directory
for this module at all. `modules/CRM/GoogleCalendarSync/composer.json` was deleted (never committed);
root `composer.json`'s `post-install-cmd` was NOT touched (no `CRM/Mail`-style nested-composer line
needed). `GoogleCalendarSyncCommon_0.php` implements its own small `http_request()`/`api_request()`
JSON-over-curl helpers instead of `require_once`-ing a vendored `autoload.php` the way
`TCPDFCommon_0.php:20` does — there is no vendor autoload for this module.

Everywhere below that still says `Google_Client`/`Google_Service_Calendar`/`Google_Service_Calendar_Event`
etc. (SDK class names) is **stale, superseded by this section** — read those as "the equivalent
hand-rolled curl call" instead. Left in place rather than rewritten line-by-line so the *shape* of the
OAuth flow / sync logic described below still reads as a design reference; only the "how it talks to
Google" mechanism changed.

### 2. Schema (`GoogleCalendarSyncInstall.php`)

Two plain tables (internal sync bookkeeping, not user-browsable RecordBrowser data), created the same
way `CRM_CalendarInstall` creates `crm_calendar_custom_events_handlers`:

- **`crm_googlecalendarsync_accounts`** — one row per connected Epesi user: `id, epesi_user_id (int,
  unique), google_email (varchar), access_token_enc (text), refresh_token_enc (text), token_expires
  (datetime), calendar_id (varchar, default 'primary'), enabled (bool), last_synced_on (datetime null),
  last_error (text null), created_on, updated_on`.
- **`crm_googlecalendarsync_map`** — one row per (meeting, connected user) pushed to Google: `id,
  meeting_id (int), epesi_user_id (int), google_event_id (varchar), content_hash (varchar),
  last_synced_on (datetime)`, unique on `(meeting_id, epesi_user_id)`.

`install()` also: `Base_AclCommon::add_permission('GoogleCalendarSync', array('ACCESS:employee'))` for
the tile/connect page; restrict the admin config screen via `admin_access_levels()` (`ACCESS:sa`/manager
only, pattern in `modules/Base/EssClient/`).

### 3. Credentials & encryption

- Installation-wide Google OAuth **Client ID/Secret** → `Variable::set('CRM_GoogleCalendarSync_client_id', ...)`
  / `..._client_secret`, entered via an admin settings screen built with `Libs_QuickForm` (template:
  `modules/Base/EssClient/EssClient_0.php`'s `license_key_form()`), registered via `admin_caption()` →
  `['label' => __('Google Calendar Sync'), 'section' => __('Server Configuration')]`.
- Per-user **access/refresh tokens** → the `crm_googlecalendarsync_accounts` row, encrypted with a small
  `CRM_GoogleCalendarSyncCommon::encrypt()/decrypt()` pair (`openssl_encrypt`/`openssl_decrypt`,
  AES-256-GCM) keyed by a secret generated on install and stored in `data/` (gitignored, outside the DB —
  check first whether `include/config.php` already exposes a reusable app secret before minting a new
  one). New territory for this codebase (no existing encrypted-secret helper) — keep it minimal and
  contained to this module.

### 4. OAuth connect flow (My Settings tile)

- `CRM_GoogleCalendarSyncCommon::user_settings()` → `array(__('Google Calendar Sync') => 'connect')`.
- `CRM_GoogleCalendarSync::connect($pushed_on_top = false)` — the page opened from the tile. Shows
  current status (connected Google account email, last synced time, last error if any) and either a
  "Connect Google Calendar" button (not connected) or "Disconnect" button (connected). No Client
  ID/Secret fields on this page.
- "Connect" links to an authorize URL built via `google/apiclient`'s `Google_Client`
  (`setClientId/setClientSecret/setRedirectUri/setScopes(['https://www.googleapis.com/auth/calendar.events'])/setAccessType('offline')/setPrompt('consent')`
  — offline + consent prompt needed to reliably get a `refresh_token`), using the admin-configured
  Client ID/Secret from `Variable::get()`.
- Redirect URI = `Module::create_ajax_callback_url(array('CRM_GoogleCalendarSyncCommon', 'oauth_callback'), null)`,
  registered once in Google Cloud Console. `oauth_callback(Request $request, $args)` exchanges the
  `code` query param via `Google_Client::fetchAccessTokenWithAuthCode()`, upserts the account row
  (encrypted), redirects back to the connect page with a success/error flash.
- "Disconnect" revokes the token (`Google_Client::revokeToken()`) and deletes the account row (and its
  `crm_googlecalendarsync_map` rows — leave Google-side events in place rather than trying to delete
  them all synchronously; known v1 limitation).

### 5. Sync driver (`CRM_GoogleCalendarSyncCommon`)

- `cron()` → `['cron_sync' => 15]` (15-minute interval).
- `cron_sync()` — batch driver: select up to *N* (e.g. 20) `enabled=1` accounts ordered by oldest
  `last_synced_on`, call `sync_account($account)` for each. Bounds per-tick work so a single `cron.php`
  invocation can't run unboundedly long.
- `sync_account($account)`:
  1. Refresh the access token if `token_expires` has passed (`Google_Client::refreshToken()`); mark
     `last_error` and skip if refresh fails (revoked/expired refresh token → needs reconnect).
  2. Fetch this user's relevant meetings: `Utils_RecordBrowserCommon::get_records('crm_meeting', $crits)`
     restricted to `active=1` and `Employees` containing the user's own `crm_contact` id, bounded to a
     configurable window (e.g. -7 days to +180 days). Reuse the same `employees`/ACL-style criterion
     `CRM_FiltersCommon::get_my_profile()` already builds for "my calendar" scope
     (`modules/CRM/Calendar/CalendarCommon_0.php:174-181`) rather than re-deriving it.
  3. Per meeting: compute a content hash over the fields that matter (title, description, date, time,
     duration, recurrence_type/end/hash, permission); look up the `crm_googlecalendarsync_map` row for
     `(meeting_id, epesi_user_id)`:
     - no row → build a Google event body and `events.insert`; store the returned event id + hash.
     - row exists, hash changed → `events.patch`/update; update hash.
     - row exists, hash unchanged → skip (no API call).
  4. Map rows for this user whose meeting no longer appears in the active/participant set (deleted,
     un-assigned, or fell outside the window) → `events.delete` on Google, remove the map row. Treat
     `404`/`410` from Google as "already gone."
  5. Catch `Google_Service_Exception`: `401` → mark `last_error` (needs reconnect); `403`/`429` → back
     off, skip rest of this account for this tick; otherwise record error and continue.
  6. Update `last_synced_on`/`last_error` on the account row.
- `build_google_event($meeting)`: `summary` ← Title, `description` ← Description, `start`/`end` ←
  Date+Time+Duration (`date`/`dateTime` per the `Duration == -1` timeless/all-day sentinel),
  `recurrence` ← RRULE translated from `recurrence_type`/`recurrence_end`/`recurrence_hash`
  (`FREQ=DAILY;INTERVAL=n` / `FREQ=WEEKLY;BYDAY=...` for the hash bitmap / `FREQ=WEEKLY;INTERVAL=2` /
  `FREQ=MONTHLY` / `FREQ=YEARLY`, each with `UNTIL=` when `recurrence_end` is set).

### 6. ACL

No separate main-menu entry — access is entirely via the My Settings tile (per-user) and the Admin Panel
config screen (install-wide), both gated by `Base_AclCommon::check_permission('GoogleCalendarSync')` /
`admin_access_levels()` respectively. Same as `CRM_Mail` having no standalone "E-mail Accounts" menu item.

## Critical files (once built)

- `modules/CRM/GoogleCalendarSync/GoogleCalendarSyncInstall.php` — schema, ACL
- `modules/CRM/GoogleCalendarSync/GoogleCalendarSync_0.php` — `connect()` page: status + Connect/Disconnect
- `modules/CRM/GoogleCalendarSync/GoogleCalendarSyncCommon_0.php` — tile registration, cron driver,
  OAuth, sync logic, encrypt/decrypt, RRULE translation, admin registration
- No `composer.json`/`vendor/` for this module — see "No vendored dependency" above. No template
  files either — `connect()`/`admin()` render inline, see the Implementation status note above.

## Verification (once built)

1. `php -l` every new PHP file (`/c/xampp82/php/php.exe` on Windows) — done, clean.
   `vendor/bin/phpstan analyse -c phpstan.neon` still needs to run once phpstan is available (not
   installed in this environment as of 2026-08-28).
2. `php console.php module:install CRM/GoogleCalendarSync` — confirm tables + ACL permission created.
   Not yet run.
3. Google Cloud Console: enable Calendar API, create a Web-application OAuth Client ID, set the
   authorized redirect URI to the value `CRM_GoogleCalendarSyncCommon::oauth_redirect_uri()` displays
   on the admin config screen, paste Client ID/Secret into that screen.
4. As a normal user: open My Settings, confirm the "Google Calendar Sync" tile, click in, complete the
   Google consent flow, confirm the account row appears with encrypted (non-plaintext) tokens and the
   tile shows connected status.
5. Create a one-off meeting and a recurring meeting with that user as a participant; run `cron.php` (or
   wait an interval); confirm both appear correctly in Google Calendar including recurrence. Edit the
   meeting — confirm the Google event updates, not duplicates. Soft-delete it — confirm the Google event
   is removed and the map row cleaned up.
6. Force `token_expires` into the past and re-run sync — confirm token refresh works without requiring
   reconnect.
7. Check `data/.../cron.log` for unexpected errors across a few sync ticks.
