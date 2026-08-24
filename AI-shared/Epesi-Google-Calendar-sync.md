# Google Calendar Sync — approved design (2026-08-24)

Approved plan for a new installable module, `modules/CRM/GoogleCalendarSync/`, that pushes each user's
Epesi meetings to their own Google Calendar. Not yet implemented — this is the design to build from,
checked in ahead of the code so the shape survives a context reset / different machine picking up the
work. Update this file (or note "implemented, see commit X") once built; don't let it drift once it does.

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

### 1. Dependency vendoring

`modules/CRM/GoogleCalendarSync/composer.json` requires `google/apiclient` (official Google API PHP
client — handles OAuth2 exchange/refresh and Calendar API v3 shapes, far less custom code than hand-rolled
curl). Add `@composer -d="modules/CRM/GoogleCalendarSync" install` to root `composer.json`'s
`post-install-cmd`, next to the `CRM/Mail` line. Load via `require_once` at the top of
`GoogleCalendarSyncCommon_0.php`, matching `TCPDFCommon_0.php:20`.

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
- `modules/CRM/GoogleCalendarSync/composer.json` — `google/apiclient` dependency
- `composer.json` (root) — add the module's `post-install-cmd` line
- `modules/CRM/GoogleCalendarSync/theme/` + `theme_adminlte/` — `connect()` page template

## Verification (once built)

1. `php -l` every new PHP file (`/c/xampp82/php/php.exe` on Windows); `vendor/bin/phpstan analyse -c phpstan.neon` stays clean.
2. `composer install` at root — installs `google/apiclient` into the module's own `vendor/`.
3. `php console.php module:install CRM/GoogleCalendarSync` — confirm tables + ACL permission created.
4. Google Cloud Console: enable Calendar API, create a Web-application OAuth Client ID, set the
   authorized redirect URI to the module's `create_ajax_callback_url` output, paste Client ID/Secret
   into the admin config screen.
5. As a normal user: open My Settings, confirm the "Google Calendar Sync" tile, click in, complete the
   Google consent flow, confirm the account row appears with encrypted (non-plaintext) tokens and the
   tile shows connected status.
6. Create a one-off meeting and a recurring meeting with that user as a participant; run `cron.php` (or
   wait an interval); confirm both appear correctly in Google Calendar including recurrence. Edit the
   meeting — confirm the Google event updates, not duplicates. Soft-delete it — confirm the Google event
   is removed and the map row cleaned up.
7. Force `token_expires` into the past and re-run sync — confirm token refresh works without requiring
   reconnect.
8. Check `data/.../cron.log` for unexpected errors across a few sync ticks.
