# Google Calendar Sync

## On hold (2026-08-28)

Put on hold by the user after the very first live end-to-end test — the code path (OAuth
connect/callback, token refresh, event push) worked, but the Google Cloud Console side proved fiddly
enough in practice (consent-screen scope declaration silently narrowing the granted token, the
project-vs-scope-picker confusion, etc. — see "Setting up the Google side" below) that it wasn't worth
pushing through in one sitting. **Not abandoned** — the user intends to come back to it.

What's been done to reflect "on hold":
- `GoogleCalendarSyncInstall.php`'s `simple_setup()` is commented out, so the module no longer appears
  in **Modules Administration & Store** (the guided/simple view) — a fresh-install admin won't stumble
  into installing it and hitting the OAuth setup friction below. It's still fully reachable through
  **Advanced Setup** (which lists every module directly, regardless of `simple_setup()`) for a
  deliberate manual install.
- The module was manually uninstalled from this dev instance (tables/ACL permission removed via the
  normal uninstall path) after the test.
- **Do not install this module as part of a fresh/new Epesi install** until this note is removed.

**Evaluated an existing public alternative, decided not to build on it**: [tmlynar/iCalendarEPESI](https://github.com/tmlynar/iCalendarEPESI)
(2018, MIT) is a prior one-way Epesi→Google sync module. Read in full (4 files total) — not usable as a
foundation:
- Token storage is a single **unencrypted, shared-for-the-whole-installation** file (not per-user), and
  it's `unlink()`'d right after first use — no refresh-token handling at all, so it can't support
  unattended/cron sync the way this module needs; every run would require a fresh interactive Google
  login.
- Only ever inserts events (`events->get()` → insert-if-missing) — edits in Epesi never propagate, and
  deleted Epesi records are never removed from Google.
- Queries `crm_meeting_data_1`/`phonecall_data_1`/`task_data_1` directly instead of
  `Utils_RecordBrowserCommon::get_records()` — no `active=1` filter, so soft-deleted records would sync
  too, and it bypasses ACL entirely.
- Targets a stale schema (`crm_meeting.f_time` — this repo's actual `crm_meeting` splits that into
  separate `date`/`time` fields) and hardcodes `Europe/Warsaw` + a fixed `+02:00` UTC offset (wrong half
  the year even for Warsaw, since it isn't DST-adjusted).
- No recurrence handling, and depends on the same full `google/apiclient` SDK we deliberately avoided
  for this module (see "No vendored dependency" in the design doc) — without even vendoring or
  documenting it.
- The one idea worth knowing about: it gives each Google event a deterministic ID (`'meeting'.$id`)
  instead of a separate mapping table, using `events.get($id)` to detect "does this exist." Our
  `crm_googlecalendarsync_map` table does more (content-hash change detection, cleanup when a meeting
  leaves scope) so this wasn't adopted, but it's a legitimate lighter-weight alternative if the map
  table ever feels heavier than needed.

Everything below this note is unchanged setup/usage documentation, current as of when the module was
last actually working end-to-end (a real "Connected" status with `jtylek@gmail.com`, before the
`insufficient authentication scopes` sync failure that prompted the pause).

---

Pushes each Epesi user's meetings to their own Google Calendar. One-way only: Epesi is the source of
truth, changes made directly on the Google side are never pulled back or diffed against. Each user
connects their own Google account; syncing runs on a 15-minute cron interval, not push webhooks.

Design background and implementation notes: `AI-shared/Epesi-Google-Calendar-sync.md`.

## Setting up the Google side (one-time, per installation)

An administrator does this once for the whole Epesi installation. The [Google Cloud
Console](https://console.cloud.google.com/) home page doesn't make the next steps obvious, so go
directly to each of these three URLs, in order (any existing project, e.g. the default "My First
Project", is fine to use — no need to create a new one):

1. **Enable the Calendar API**: `https://console.cloud.google.com/apis/library/calendar-json.googleapis.com`
   → click **Enable**.
2. **Configure the OAuth consent screen** (required before Google will let you create a Client ID):
   `https://console.cloud.google.com/apis/credentials/consent` → pick **User Type** (see below), fill in
   the required fields (app name, support email, developer contact email), save. Then, under **Data
   Access** (the "Scopes" section), click **Add or Remove Scopes**, search for "Google Calendar API",
   and check the one for `.../auth/calendar.events` — **this step is easy to miss and syncing silently
   fails without it**: `calendar.events` is a sensitive scope, so Google drops it from the granted token
   if it isn't explicitly declared here, even though the app also requests it at authorize time. The
   symptom is a successful-looking "Connected" status with "Last error: Request had insufficient
   authentication scopes." If you hit that, add the scope here, then **Disconnect**/**Connect** again in
   Epesi (reconnecting is required — Google won't retroactively grant a scope to an existing token).
3. **Create the OAuth Client ID**: `https://console.cloud.google.com/apis/credentials` → **+ Create
   Credentials** → **OAuth client ID** → Application type **Web application** → any name → under
   **Authorized redirect URIs**, paste the exact URI Epesi's admin screen shows (see next step) →
   **Create**. Google then shows the Client ID and Client Secret.
4. In Epesi, open **Admin Panel → Google Calendar Sync**. It displays the exact **Authorized redirect
   URI** to register on the Client ID above — copy it verbatim into step 3. This value is deterministic
   (it doesn't change between visits to the screen), so it only needs registering once.
5. Paste the Client ID and Client Secret from step 3 into that same Epesi admin screen and save.

### Running on `localhost` / no public domain

This works fine — no public IP or domain is required. Google's rule is that a redirect URI must use
`https://`, *or* it may use plain `http://` if the host is `localhost`/`127.0.0.1`. An Epesi install
served at `https://localhost/...` already satisfies this unconditionally.

### OAuth consent screen

This is the part that *is* affected by not having a public domain:

- **On a Google Workspace domain you administer**: set the consent screen's User Type to **Internal**.
  No verification, no domain-ownership proof, works indefinitely with `localhost`. Only accounts inside
  that Workspace org can connect — not plain `@gmail.com` accounts.
- **Regular consumer Google accounts**: set User Type to **External**, leave Publishing status as
  **Testing**, and explicitly add each Epesi user's Google email under **Test users** (up to 100).
  Also works fine with `localhost`, no verification needed.

**Gotcha**: refresh tokens issued while an app is in **Testing** status expire after **7 days**,
regardless of use — every connected user would need to reconnect roughly weekly once that happens (the
status page under My Settings → Google Calendar Sync surfaces this as "Google authorization expired -
please reconnect" rather than failing silently). "Internal" apps don't have that limit. An External app
can be moved to "In production" without completing full verification — users just click through an
"unverified app" warning on first consent — which lifts the 7-day limit too, at the cost of that warning
screen.

## For each user

My Settings → **Google Calendar Sync** → Connect. No Client ID/Secret entry here; that's the
admin-only, installation-wide config above.

## Scope / limitations

- One-way sync (Epesi → Google) only.
- Recurring meetings are pushed as a single recurring Google event (translated to an RRULE), not
  expanded into individual occurrences.
- Only meetings within a rolling window (7 days in the past to 180 days in the future) are kept in
  sync; older/further-out meetings are left alone.
- Disconnecting removes the local connection and its sync bookkeeping, but does not delete
  already-pushed events from Google Calendar.
