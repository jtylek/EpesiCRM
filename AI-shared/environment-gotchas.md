# Environment / DB gotchas that looked like application bugs

(See `CLAUDE.md`'s "Environment quirks" and "Error handling" sections first —
some overlap exists deliberately; this file covers additional gotchas found
during work on this codebase that aren't yet folded into CLAUDE.md.)

## CLI scripts share the live DB — writes in a "test" script are real

Any ad-hoc PHP script that bootstraps `include.php` connects to the **same
database the user's browser session uses**. A verification script that writes
a setting "to leave things as found" (e.g.
`Variable::set('default_theme', 'default')`) silently reverts whatever the
user just configured through the UI — and the resulting symptom looks like an
application bug (e.g. "theme switching isn't persisting"), not a test-script
side effect.

**How to apply**: prefer overriding state in-process rather than writing it.
If a script must write, read the original value first and restore *that*
value via `register_shutdown_function` — never hardcode a "default."

**`php update.php` from the CLI is a real mutating operation, not a dry
check** — `EpesiUpdate::run()` always falls through to the full patch-apply/
cache-rebuild flow once past the version check, including turning on
maintenance mode. Running it to "quickly test" something can put a live site
into maintenance mode mid-run. To test update/check logic safely, hit it over
HTTP (the web path dies before reaching the mutating flow if already
up-to-date) or render the relevant templates in an isolated script with fake
data instead.

**Git consequence**: because ordinary browsing during a dev session writes to
the live DB/filesystem, `git status` routinely shows unrelated `data/` churn
(lang cache regen, `Utils_RecordBrowser/last`, attachment blobs, etc.) mixed in
with real code edits. When staging/committing, name specific file paths —
never `git add -A`/`git add .` — so this drift doesn't ride along.

## Apache: missing assets only show up in access.log, not error.log

A broken `<img>` or CSS `url()` leaves **no trace in error.log** (which only
records PHP/script failures) — check `access.log` (filter for ` 404 `) for
missing static assets. A clean error.log is not evidence that assets are
loading. Also: CLI render tests print the URLs an app *emits* but never
perform browser-style relative-URL resolution, so they can't catch a wrong
base path — resolve a changed asset URL the way a browser actually would
(collapsing `..` against the *stylesheet's* own URL, not the page's) before
declaring it fixed.

## MySQL `max_allowed_packet` default (1M) is too small for this app

`History::set()` persists a gzip+serialized blob of per-tab module-var state
into a `longblob` column on *every request*, inlined via `DB::qstr()` (not a
bound param) — a large pasted note can push that blob past the stock 1MB
limit. ADOdb's mysqli driver runs with `mysqli_report(MYSQLI_REPORT_OFF)`, so
the resulting query failure is **completely silent** — no warning, no log
entry — and cascades into unrelated-looking errors elsewhere (e.g.
`EpesiSession::write()`'s own DB write failing at shutdown, producing PHP's
"Failed to write session data" warning instead of anything packet-related).

This install's `C:\xampp82\mysql\bin\my.ini` has `max_allowed_packet=64M`
under `[mysqld]` (raised from the 1M stock default — restart the MySQL
service if this is ever reverted). Note there's a *second*, unrelated
`max_allowed_packet=16M` under `[mysqldump]` further down the same file —
don't confuse the two.

**How to apply**: if a DB-backed operation fails with nothing in
`php_errors.log` to explain it, suspect a silently-swallowed ADOdb failure
(packet size, lock timeout) before assuming the code path was never reached.

## Outbound SMTP port 25 is blocked from this machine

Confirmed 2026-08-04 while debugging Mail server settings' "Test" button
hanging on "Loading..." for minutes: a direct TCP connect to an external
host's port 25 times out (no RST, no refusal - packets just go nowhere),
while ports 587 and 465 on the same host connect instantly. This is a very
common network/hosting policy (blocking outbound 25 curbs spam relaying) and
likely applies to real deployments too, not just this dev box - port 25 is
the MTA-to-MTA delivery port, not the authenticated-submission port a mail
client/app should be using anyway.

**How to apply**: if SMTP-related code (mail testing, cron mail delivery)
appears to hang rather than fail, check the configured `mail_host` port
before assuming a code bug - a silent hang (vs. an immediate connection-
refused error) is the signature of a filtered/blocked port, not a
misconfigured or down server. Recommend port 587 (STARTTLS) or 465
(implicit TLS) for SMTP submission instead of 25.

## MariaDB `multi-master.info` corruption (root cause never found)

Hit once (2026-07-29): `C:\xampp82\mysql\data\multi-master.info` should just
contain `0` (no replication configured on this box) but was found containing
~14 lines of raw text copied verbatim from `mysql_error.log`. On startup,
mysqld tried to recreate a bogus replication-channel file per corrupted line;
one generated an over-length Windows path and mysqld aborted entirely,
refusing to start. Fixed by resetting the file to `0` and removing the
spawned junk `master-*.info`/`relay-log-*.info` files. Confirmed nothing in
Epesi issues `CHANGE MASTER`, and replication was never configured in
`my.ini`.

**The actual cause was never identified** — something on this machine
appeared to be appending `mysql_error.log` lines into this specific file over
several hours, well before the session that found it. If MySQL refuses to
start again with `Failed to initialize multi master structures` / `Aborting`,
check this file first (reset to `0`, don't just delete the derived
per-channel files — they regenerate from the manifest) — and if it recurs,
that recurring external cause is the thing actually worth hunting down.
