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

## Hardcoded `EPESI_URL` sends post-action redirects off `localhost` to the real production domain

`data/config.php`'s `EPESI_URL` constant is an absolute base URL, not derived
from the request. On a dev copy of a site whose config still has this set to
the real public domain (e.g. `http://bim.epe.si`, which has real public DNS
pointing at an unrelated production server), any code path that redirects
using this constant — `update.php`'s `up=end` step, for one — sends the
browser away from `localhost` entirely. The browser lands on the *actual*
production server, which correctly has no idea what this dev session is and
returns its own 403. Apache's local `access.log`/`error.log` show nothing for
the redirect target at all (because the request never reached this machine),
which is the tell: a "timeout" or "403" with **zero corresponding local log
entry**, even re-checked live, minutes later.

**How to apply**: after cloning/restoring a site's `data/` locally, check
`EPESI_URL` before assuming any 403/redirect weirdness is a code bug — if it
doesn't match the URL actually typed into the browser (`http://localhost/
<folder>` for path-based local access), fix it there first. [[log-monitoring]]
applies here too: check access.log for the *absence* of an expected
follow-up request, not just for errors in it.

## Stale `data/cache/` after swapping the codebase wholesale (not via incremental patches)

Epesi's compiled-template/module cache (`data/cache/<INSTALLATION_ID>/`) and
minified-asset cache (`data/cache/minify/`) are built against whatever code
was on disk when they were generated. The normal `update.php` patch flow
accounts for this, but a bulk code swap done outside that flow (e.g. `git
checkout` onto a different branch, or overlaying a new release directly) can
leave old compiled templates referencing classes/methods/theme files the new
code no longer has — a source of confusing bugs that look like the new code
is broken when it's actually stale cache.

**How to apply**: after any out-of-band code swap (not done through the
update wizard), clear `data/cache/` before testing — it's fully regenerable,
not user data. Confirmed safe 2026-08-13: 76MB / 2,132 files cleared
(`<INSTALLATION_ID>/` + `minify/`) with no ill effect, immediately after
pulling `jtylek/epesi`'s `jasiek` branch on top of a PHP 8.2 migration.

## Clear `data/logs/php_errors.log` and `data/logs/cron.log` before starting a migration test pass

Both accumulate across unrelated prior sessions (`cron.log` in particular can
sit at several MB from routine cron runs going back months). Starting a PHP
8.2 migration test pass with both cleared first makes it obvious which
errors/warnings are freshly caused by the migration vs. pre-existing noise —
especially with `REPORT_ALL_ERRORS` turned on in `data/config.php`, which
makes `php_errors.log` far noisier (E_NOTICE/E_DEPRECATED included, not just
fatals).

**How to apply**: before a migration/compat test session, truncate both
files. Neither is needed for anything else — `cron.log` is just a running
append-log of past cron job output, and `php_errors.log` is fully
regenerated by the next request that errors.

## This machine's Apache SSL vhost is misconfigured (port mismatch) — `https://localhost` fails TLS entirely

`C:\xampp82\apache\conf\extra\httpd-ssl.conf` has `Listen 443` at the top
level, but its only `<VirtualHost>` block is bound to `_default_:445`, not
`:443`. Nothing is actually listening for TLS on 443 as a result — a raw
handshake attempt (`curl -v https://localhost/`) fails immediately with
`SEC_E_INVALID_TOKEN` and the connection closes before any HTTP response is
produced.

This matters because most browsers now silently upgrade `http://` navigation
to `https://` (Chrome/Firefox "always use secure connections" / HTTPS-Only
Mode, or a stale HSTS entry for `localhost` from unrelated prior testing).
When that happens here, the browser lands on the broken port-443 endpoint and
renders its own fallback page — which can look like a generic "403 Forbidden"
with **no corresponding entry in Apache's access.log or error.log at all**,
same tell as the `EPESI_URL` gotcha above. Don't waste time looking for a
server-side cause of a 403 that has zero log footprint.

**How to apply**: if a local `bim.epe.si` test session shows an unexplained
403/connection failure and the address bar has silently become `https://`,
suspect the browser's HTTP→HTTPS auto-upgrade before the app. Fix by clearing
that origin's HSTS/HTTPS-only exception in the browser (Chrome:
`chrome://net-internals/#hsts` → delete domain security policy for
`localhost`; Firefox: HTTPS-Only Mode exceptions) rather than chasing it
server-side — the port-445/443 mismatch is a separate, real misconfiguration
but fixing it (editing `httpd-ssl.conf`, restarting Apache) affects every
site under this XAMPP instance, not just this one, so treat that as a
deliberate separate decision, not a quick fix.
