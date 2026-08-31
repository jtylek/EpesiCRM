# Environment / DB gotchas that looked like application bugs

> **Status:** REFERENCE - DB/server/tooling issues that looked like application bugs.

(See `CLAUDE.md`'s "Environment quirks" and "Error handling" sections first —
some overlap exists deliberately; this file covers additional gotchas found
during work on this codebase that aren't yet folded into CLAUDE.md.)

## `modules/Custom/` is only *partly* gitignored, unlike all of `modules/Premium/`

Don't assume `modules/Custom/` is gitignored wholesale the way `modules/Premium/`
is — verified 2026-08-14 via `git check-ignore`/`.gitignore` that only
`modules/Custom/Tutorial` (the shipped example module paired with
`AI-shared/Dev-Tutorial.md`) was tracked in the main repo; every other
`modules/Custom/<X>` is meant to be a separate nested git repo, same pattern as
each `modules/Premium/<X>`, but that carve-out wasn't actually encoded in
`.gitignore` until this same date — before then, a real Custom module dropped in
next to Tutorial would have been silently swept into the main repo's history
instead of staying its own repo.

**How to apply**: `.gitignore` now has `modules/Custom/*` +
`!modules/Custom/Tutorial` right under the `modules/Premium/` rule. If you're
about to `git add`/audit/sweep `modules/Custom/`, don't assume the `Grep` tool
(which respects `.gitignore`) sees everything under it the way it does for a
genuinely fully-tracked directory — a real Custom module living there needs the
same plain-Bash-grep treatment as `modules/Premium/` (see the
Premium/Custom-migration-scope memory note), even though `Tutorial` itself is
fine through the normal tools.

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

## Stale `temp/<DATA_DIR>/cache/` after swapping the codebase wholesale (not via incremental patches)

Epesi's compiled-module cache (`common.php`), the general-purpose `Cache::`
store (`<INSTALLATION_ID>/`, Phpfastcache Files driver — see
`include/cache.php`), the minified-asset cache (`minify/`) and the
asset-version scan cache (`asset_version.txt`) are all built against whatever
code was on disk when they were generated. As of 2026-08-29 all four live
under `temp/<DATA_DIR>/cache/` (e.g. `temp/data/cache/` on a default
install) instead of `data/cache/` — see `bug-patterns.md`'s "Runtime
cache/scratch-file call sites default to `DATA_DIR` instead of `TEMP_DIR`"
entry — but the staleness risk is the same as before the move. The normal
`update.php` patch flow accounts for this, but a bulk code swap done outside
that flow (e.g. `git checkout` onto a different branch, or overlaying a new
release directly) can leave old compiled templates referencing
classes/methods/theme files the new code no longer has — a source of
confusing bugs that look like the new code is broken when it's actually
stale cache.

**How to apply**: after any out-of-band code swap (not done through the
update wizard), clear `temp/<DATA_DIR>/cache/` before testing — all of it is
fully regenerable, not user data. Confirmed safe 2026-08-13: 76MB / 2,132
files cleared (`<INSTALLATION_ID>/` + `minify/`, both under `data/cache/` at
the time, pre-2026-08-29 move) with no ill effect, immediately after pulling
`jtylek/epesi`'s `jasiek` branch on top of a PHP 8.2 migration.

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

## `FORCE_CACHE_COMMON_FILES` bundles every module's `Common` class — edits to one silently don't take effect

`data/config.php`'s `FORCE_CACHE_COMMON_FILES` (see `ModuleManager::load_modules()`/
`create_common_cache()` in `include/module_manager.php`) concatenates every
installed module's `*Common_0.php` into one file, `data/cache/common.php`, and
loads *that* instead of the individual source files whenever it exists. The
check is `file_exists()`, not a timestamp/hash comparison — so once the bundle
exists, editing a `*Common_0.php` (e.g. a `menu()` or `bootstrap_icon()` method)
has **zero effect** until the bundle is explicitly rebuilt. There's no error,
no stale-cache warning — the app just keeps running the old method body,
which reads exactly like the edit itself was wrong or never saved. Confirmed
2026-08-13: a `Premium_TimesheetCommon::menu()`/`bootstrap_icon()` edit didn't
show up in the browser even via a correct local URL and a fresh incognito
session (ruling out session/browser caching) — a CLI probe through the real
`include.php` bootstrap showed the newly-added `bootstrap_icon()` method
didn't exist at all on the loaded class, confirming the bundle was serving a
pre-edit snapshot.

Rebuild it with `console.php cache:rebuild` (`Cache::clear()` +
`ModuleManager::create_common_cache()` — same action as the admin "Clear
Cache" tool, `admin/modules/ClearCache.php`, added 2026-08-13 specifically
for this).

**How to apply**: if an edit to any `*Common_0.php` file (menu registration,
`bootstrap_icon()`, any static method called via
`ModuleManager::call_common_methods()`) doesn't take effect after a real
page reload/incognito test, check `data/config.php` for
`FORCE_CACHE_COMMON_FILES` before suspecting the edit itself, a typo, or
browser/session caching — rebuild the cache (or delete
`data/cache/common.php`) and retest. **This dev machine keeps it disabled**
(`FORCE_CACHE_COMMON_FILES = 0` in `data/config.php`) precisely to avoid this
trap during active development — it's a real production perf feature (one
bundled file vs. one `require` per module per request), just not worth the
debugging cost locally. Don't re-enable it here without also remembering to
rebuild the cache after every `Common_0.php` edit.

**Re-hit 2026-08-25 despite the above:** `data/config.php` is gitignored, not tracked — a `setup.php`/
`FirstRun` run during unrelated testing earlier in the same day regenerated it fresh from the shipped
template, silently reverting this to `1` again. Hit exactly the failure mode described above: removed
`Base_EssClientCommon::menu()` (a "Register Epesi!" Support-menu entry), the user logged out and back in
(ruling out the session-level menu cache from `how-menu-works.md`) and the entry was still there — turned
out to be this, not that. Re-disabled it in `data/config.php`. **If `data/config.php` ever gets
regenerated** (fresh `setup.php` run, restored from a backup, etc.), re-check/re-flip this — it doesn't
survive that the way git-tracked settings would.

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

**How to apply**: if a local test session shows an unexplained
403/connection failure and the address bar has silently become `https://`,
suspect the browser's HTTP→HTTPS auto-upgrade before the app. Fix by clearing
that origin's HSTS/HTTPS-only exception in the browser (Chrome:
`chrome://net-internals/#hsts` → delete domain security policy for
`localhost`; Firefox: HTTPS-Only Mode exceptions) rather than chasing it
server-side — the port-445/443 mismatch is a separate, real misconfiguration
but fixing it (editing `httpd-ssl.conf`, restarting Apache) affects every
site under this XAMPP instance, not just this one, so treat that as a
deliberate separate decision, not a quick fix.

### Same root cause also breaks individual AJAX actions, with a real access.log footprint this time

Verified 2026-08-21 (`Utils_FileStorage`'s "Get link" button). Once a tab has
silently upgraded to `https://localhost/...`, full-page navigation and
same-scheme relative requests can keep working normally — only requests built
from the hardcoded-`http://` `EPESI_URL` (the `EPESI_URL` gotcha above) start
failing, because they're now cross-origin by *scheme* alone relative to the
https page. Any JS that calls such an absolute URL (e.g.
`modules/Utils/FileStorage/remote.js`'s `$.ajax(url, {method:'post'})` for
"Get link") trips a CORS preflight — this app has no OPTIONS/CORS handling
anywhere, so the preflight always 403s, the real POST never fires, and the
user just sees a generic AJAX failure (`error:` callback, e.g. "Failure
(error)"). Unlike the whole-page-navigation case above, this variant **does**
show up in access.log: an `OPTIONS ... 403` on the affected endpoint with an
empty `Referer` (`"-"` — Chrome's preflight doesn't send one) and no
POST/GET immediately following it. Comparing `Referer` scheme across
neighboring requests in the same session (`https://localhost/...` on
everything else vs. missing on the failing endpoint) confirms the upgrade
rather than an app/ACL bug.

**How to apply**: symptom is "one specific AJAX action fails while the rest
of the app works fine," not a whole-page failure — easy to mistake for a code
bug (undefined-array-key warnings, ACL checks, etc. are all worth ruling out
first, but if those check out) look for `OPTIONS .* 403` near the failure's
timestamp in access.log before assuming a server-side cause. Same fix as
above: clear the `localhost` HSTS entry and reload fresh over `http://`.

### Same root cause again, this time a JS exception instead of a network failure

Confirmed 2026-08-24 (`CRM_Roundcube`'s webmail screen,
[Roundcube_0.php:84-86](../modules/CRM/Roundcube/Roundcube_0.php#L84-L86)):
`navigator.registerProtocolHandler('mailto', $epesi_mail_url, ...)` is called
with a URL built from `get_epesi_url()` (i.e. the hardcoded `http://`
`EPESI_URL`). If the tab has silently upgraded to `https://localhost/...`,
the handler URL is cross-origin by scheme alone relative to the document, and
the browser throws `Uncaught SecurityError: Failed to execute
'registerProtocolHandler' on 'Navigator': Can only register custom handler in
the document's origin.` — a console JS error with no network request at all,
so it won't show up in access.log even as an absence (unlike the AJAX variant
above).

**How to apply**: an `Uncaught SecurityError` naming `registerProtocolHandler`
(or any other API that enforces same-origin against an absolute URL the app
builds from `EPESI_URL`) is the same scheme-mismatch root cause, not a code
bug — check the page's actual `https://` vs. the config's `http://` before
touching the calling code. Same fix: clear the `localhost` HSTS entry and
reload over `http://`.

## Browser-driven UI verification on this machine: no `chromium-cli`, Playwright's own Chromium was never downloaded, and the app has no deep-linkable URLs

No project skill exists yet for driving this app in a browser (checked
`.claude/skills/` — nothing there; if you build one, save it there for next time).
`chromium-cli` isn't installed either. A global npm package IS present,
`@playwright/mcp` (`npm ls -g`), and its own `node_modules/playwright` can be
`require()`'d directly from a throwaway Node script for a quick verification pass —
but Playwright's *own* bundled Chromium was never downloaded on this machine:
`chromium.launch()` fails immediately with `Executable doesn't exist at
...\ms-playwright\chromium_headless_shell-<rev>\...\chrome-headless-shell.exe`,
telling you to run `npx playwright install` (downloads ~100+ MB). Don't do that —
`chromium.launch({ headless: true, channel: 'msedge' })` drives this machine's
already-installed Edge instead, zero extra install (Chrome is also present at the
standard `Program Files\Google\Chrome\Application\` path, same idea with
`channel: 'chrome'`). Confirmed working end-to-end (login, click-through navigation,
screenshots, `page.on('console'/'pageerror')` error capture) 2026-08-13.

Login form fields (`Base_User_Login`, both the legacy and adminltedark templates):
`input[name="username"]`, `input[name="password"]`, submit via
`input[type="submit"], button[type="submit"]`.

Since this app is an old-style AJAX-push SPA with no real navigable per-screen URLs
(see `CLAUDE.md`'s Rendering section — `Module::create_href()` emits
`href="javascript:void(0)" onclick=...`, not a real `href`), verifying a specific
screen means logging in and clicking through the actual UI (sidebar → module), not
navigating straight to a deep-link URL. Watch for ambiguous text-based locators
matching the *sidebar* instead of the intended on-page element — e.g.
`page.getByText('Accounting')` while testing a Knowledge Base tree matched the
sidebar's unrelated top-level "Accounting" menu entry before the KB tree's own
"Accounting" category link with identical text; scope the locator to the specific
container instead of the whole page (e.g.
`page.locator('.epesi-kb-body').getByText('Accounting', { exact: true })`).

App URL for local browser testing: **don't hardcode a URL in this file.** This
machine runs multiple Epesi projects side by side, each in its own `htdocs`
subdirectory with its own `data/config.php` and its own database, so the
local test URL differs per checkout. Read that checkout's `data/config.php`
for its `EPESI_URL` (see the gotcha above — confirm it's actually set to the
localhost path-based URL and not left pointing at a real production domain)
and use that: path-based `http://localhost/<folder>`, not `https://` (this
file's own SSL-vhost entry above).

**Never write real login credentials into this file or any other git-tracked
doc** — it travels with `git clone`/`git pull` to every developer and computer, unlike
per-session/per-machine notes. Ask the user for credentials in-session instead, or use
whatever secrets mechanism the project already has, and only record the *shape* of the
login flow (field selectors, above) here.

## `modules/Premium/` checkouts can change under you mid-session from concurrent work elsewhere — verify before assuming corruption

Hit 2026-08-19: mid-session, `modules/Premium/ListManager/ListManagerInstall.php`
was found to differ from what the assistant had last written — on top of its
own one-line edit (deleting a leftover `set_quickjump()` call, see
`deliberate-removals.md`), the file was also missing
`Base_ThemeCommon::install_default_theme($this->get_type());` from `install()`
(while `uninstall()` still called the matching `uninstall_default_theme()`),
and both `@DB::CreateIndex(...)` calls had been repointed at
`premium_listmanager_element_data_1` instead of the recordset name used
everywhere else in the file, `premium_listmanager_element`. This looked like
corruption — an asymmetric install/uninstall pair, an index pointed at a
table name that appeared nowhere else. **It wasn't**: confirmed by the user
these were deliberate, already-tested fixes from another session working on
the same nested repo. The `_data_1` rename in particular is actually
*correcting* a real pre-existing bug, not introducing one:
`Utils_RecordBrowserCommon::install_new_recordset()` always physically
creates a recordset's data table as `<name>_data_1` (see
`RecordBrowserCommon_0.php:801`, indexed internally the same way at
`:809-810`) — the bare `premium_listmanager_element` table the original code
targeted never existed, so those two `@`-suppressed `CreateIndex` calls had
silently no-op'd since this module was written. Same shape likely applies
anywhere else in `modules/Premium/` that indexes a recordset by its bare
logical name instead of `<name>_data_1` — worth a grep
(`@DB::CreateIndex\('[a-z_]+','premium_`) if this comes up again.

The directory also went missing entirely once earlier in the same session
right after being cloned (re-cloned as a workaround) — whether that was the
same concurrent activity or something else was never pinned down.

Since `modules/Premium/` is entirely gitignored (see `CLAUDE.md`), there's
no git history to diff against for either kind of change — a normal
tracked-repo file would at least show up in `git status`/`git diff` with a
"someone else's commit" explanation available via `git log`. A gitignored
nested repo gives you none of that, so an unexplained diff is genuinely
ambiguous between corruption and legitimate concurrent work.

**How to apply**: if a file under `modules/Premium/` (or any other
gitignored, separately-repo'd tree) doesn't match what you last wrote,
**don't revert it and don't assume corruption** — flag the specific diff to
the user and ask, the way you would for any surprising change to code you
don't own outright. It may well be tested work from a parallel session on
the same nested repo that just hasn't been communicated yet.

## Rector and PHPStan are installed globally via Composer on this machine, not per-project

Done 2026-08-21 (Jasiek's Windows box) so both tools are shared across every local
PHP project instead of living in this repo's own `vendor/`:

```
composer global require rector/rector:^2 phpstan/phpstan:^2
```

This installs into `C:\Users\jasiek\AppData\Roaming\Composer\vendor\bin`, which was
already on `PATH` (`composer global config home` shows the home dir; its `vendor\bin`
is the standard global-bin location Composer adds during setup) — so `rector` and
`phpstan` now resolve directly from any project directory, no `vendor/bin/` prefix,
no per-project `composer.json` entry. Usage is otherwise unchanged: run from inside a
project and point at its own config, e.g. `phpstan analyse -c phpstan.neon`,
`rector process --dry-run --config rector-php82.php`.

This repo's own `composer.json` deliberately does **not** list either tool as a
dependency — CI installs both isolated into scratch dirs per run
(`.github/workflows/php-checks.yml`: `composer --working-dir=/tmp/rector require
rector/rector:^2`, same shape for `/tmp/phpstan`) specifically to dodge a php-parser
version conflict with Epesi's own deps. A global Composer install is the same shape
of fix — its own vendor tree, entirely outside any project — just persistent instead
of re-fetched every run. Versions were pinned to `^2` for both to match what CI uses.

**How to apply on another developer's computer**: this is per-machine state, not
something `git pull` brings along — run the same `composer global require` command
there too. If that machine's global Composer bin dir isn't already on `PATH`, add it
(`composer global config home` prints the path; the tools live in `<that
path>\vendor\bin` on Windows or `<that path>/vendor/bin` on Linux/macOS). Keep the
version constraint in sync with whatever `.github/workflows/php-checks.yml` pins if
that ever changes, so local runs and CI report the same findings.

While verifying this install against this repo's actual `phpstan.neon`, the run hit
a **real** issue unrelated to the global-vs-local install choice: see the "vendored
Kint debug library uses removed PHP 7 syntax" note below (`phpstan.neon`'s
`excludePaths` didn't cover it, so any invocation — global or CI's isolated one
— would abort with parse errors).

## Vendored Kint debug library breaks PHPStan with removed PHP 7 curly-brace syntax

Found 2026-08-21 while smoke-testing the global PHPStan install (see above) against
this repo's real `phpstan.neon`: the run aborted with "Result is incomplete because
of severe errors" instead of reporting ordinary findings.
`modules/Develop/MiscUtils/kint/parsers/custom/json.php` (Kint, a vendored
third-party var-dump/debug library, added via commit `762612ba2` "materialize
previously-uncommitted vendor dependencies") uses old curly-brace string-offset
syntax (`$variable{0}`), which PHP 8.0 removed outright — a genuine parse error
under `phpVersion: 80200`, not a false positive. Unlike an ordinary finding, a parse
error can't be suppressed via `phpstan-baseline.neon` and aborts the whole analysis
run rather than just flagging that one file. The file was already absent from
`phpstan-baseline.neon`, meaning a fresh CI run would hit the same abort (CI's
PHPStan job has no `|| true`, so this would redden the build, not just add noise).

Fixed at first by adding `modules/Develop/MiscUtils/kint/*` to `phpstan.neon`'s
`excludePaths`, alongside the existing RoundCube/Smarty/Tests entries — same
pattern, since Kint here is vendored code we don't own, not Epesi's own code.

**Follow-up (2026-08-21): the whole `Develop_MiscUtils` module was deleted**,
not just excluded. It was a bare dev-tooling module (no menu, no ACL, no admin
screen) whose only content was a global `p($x)` debug-dump helper and this
bundled Kint library — a manual `var_dump`-style aid for a developer to sprinkle
into code while debugging, superseded now that AI-assisted sessions read code
and logs directly instead. Confirmed zero references anywhere else in the
tracked codebase (`Kint::`, `p(`, `Develop_MiscUtils`/`requires()`) before
removing. Not installed in this instance's DB (`console.php module:uninstall
Develop_MiscUtils` reported "not installed"), so no uninstall step was needed —
just `git rm -r modules/Develop/MiscUtils`. The `excludePaths` entry above is
now gone too, since the directory it pointed at no longer exists.

**How to apply**: if PHPStan (local or CI) ever reports "Result is incomplete
because of severe errors" again, check whether the failing file is vendored/
third-party code that landed under `modules/` without a matching `excludePaths`
entry, before assuming a real regression in Epesi's own code. And if any old
code still calls `p()` or `Kint::`, that call is now dead — `Develop_MiscUtils`
no longer exists to define/load them.

## Claude Code's own Bash sandbox can't run `/opt/lampp/bin/php` — missing `libcrypt.so.1`, unrelated to the real host

Confirmed 2026-08-19: from inside a Claude Code session's Bash tool on Karina's Linux
machine, `/opt/lampp/bin/php -v` fails with
`error while loading shared libraries: libcrypt.so.1: cannot open shared object file`.
This is **not** a real problem with the app or the actual host — Apache was serving
requests fine at the same time (`access_log`/`error_log` both had fresh entries), and a
prior session's memory had already confirmed this exact binary running successfully
(8.2.12). The break is specific to the Bash tool's own sandbox container, which ships a
newer `libxcrypt` (`libcrypt.so.2` only, no `.so.1` compat symlink) than what this XAMPP
8.2.12 PHP build was linked against.

Not fixable from inside that sandbox: `/usr/lib` is a read-only filesystem there, no
`apt`/`dpkg`/package manager is available, and there's no `sudo`/root. A same-directory
symlink workaround (`libcrypt.so.1` → `libcrypt.so.2` + `LD_LIBRARY_PATH`) was tried and
fails harder, at load time, with `version 'GLIBC_2.2.5' not found` — confirming a real
ABI break between the two library versions, not just a missing filename alias.

**How to apply**: if a Claude Code session reports `/opt/lampp/bin/php` (or any command
that shells out to it, e.g. `console.php`, `php -l`) failing with a `libcrypt.so.1`
error, don't chase it as an app/environment bug — it's a gap in that session's own Bash
sandbox image. Run the command from a real terminal instead; ask Claude to just read
files/reason about version info (e.g. `/opt/lampp/RELEASENOTES`,
`data/config.php`) rather than trying to execute the binary. Worth reporting to
Anthropic as a sandbox image gap if it keeps recurring, rather than working around it
per-session.

## `choco install` needs an elevated shell on this Windows/XAMPP machine

Confirmed 2026-08-22: `choco install <pkg> -y` from Claude Code's own (non-admin)
PowerShell fails with `UnauthorizedAccessException` on `C:\ProgramData\chocolatey\lib\...`
— that directory tree is admin-owned, and Chocolatey has no per-user install mode that
avoids it. `choco --version` works fine (reading), only the actual install write fails.

**How to apply**: don't retry or try to work around this from inside a session — ask the
user to run the `choco install` command themselves from an elevated ("Run as
Administrator") PowerShell. Any tool chocolatey would install (e.g. `rsync`, for a
cleaner alternative to the `robocopy /E` merge-copy pattern in `MIGRATION_NOTES.md` §70)
hits the same wall.

## Minimum PHP version: 8.0 required, 8.2 recommended — single source of truth is `include/compatibility_check.php`

If asked "what PHP version does Epesi need," don't guess from `composer.json` — it
declares no explicit `"php"` platform constraint, so it won't tell you. The actual
enforced floor is `CompatibilityCheck::system_check()`
(`include/compatibility_check.php:30-41`), shared by both `check.php` (setup's
"Compatibility check" step) and the logged-in admin "PHP Environment & config.php"
screen (`admin/modules/ConfigInfo.php`). Floor is PHP 8.0 because Epesi's own code
uses constructor property promotion (8.0+-only syntax) — anything older fails to
parse before the check itself could even run. 8.2 is called out as "recommended"
because that's what this release (`20260701-rc1`) is actually developed and tested
against, per `MIGRATION_NOTES.md`.

**How to apply**: read `system_check()`'s `$desired_version` directly rather than
citing a remembered number — if a future patch raises the floor (e.g. to drop 8.0/8.1
deprecation shims), that's the one place it needs to change and the one place to
re-check.

## Windows/NTFS + `core.fileMode=true` makes `git status` show ~30 vendored scripts as modified with zero content diff

Hit 2026-08-24 on jasiek's Windows box (`c:\xampp82\htdocs\euroleader`): `git status` listed
~30 files as modified — `modules/Libs/RoundCube/RC/bin/*.sh`, a handful of vendored PEAR
files under `RC/vendor/`, and a couple of top-level `vendor/**/*.sh` build scripts — but
`git diff` on every one showed **zero content changes**, only `old mode 100755` / `new mode
100644`. Root cause: these files are checked into git with the Unix executable bit set, but
NTFS doesn't track that bit at all. With `core.fileMode` at its default `true`, git compares
the (nonexistent-on-Windows) working-tree mode against the recorded index mode on every
`status`/`diff`, and it always loses — so the flip reappears on every checkout, not just once.
Committing these "changes" would strip the executable bit from real, deployed shell
scripts/binaries — a genuine regression on Linux, not a fix.

**How to apply**: don't commit these — verify a same-shaped mystery diff is mode-only before
touching it (`git diff -- <file>` showing only `old mode`/`new mode` lines, no `+`/`-`
content) rather than assuming it's real work or blindly discarding it. The actual fix is
`git config core.fileMode false` — **local to this repo checkout**, confirmed working
2026-08-24 (immediately cleared all ~30 spurious entries from `git status`). This is a git
config change, which Claude Code must never make on its own initiative (or even on request —
see CLAUDE.md's git safety rules) — ask the user to run it themselves. Per-repo (`--local`,
the default) is enough; only reach for `--global` if the same machine has other repos hitting
the same issue.

## A transient file-write failure inside one patch aborted the *entire* update run

Hit 2026-08-20, right after a large `git checkout`/`git clean` pass over
several nested repos under `modules/`: `update.php` died with `PATCH APPLY
ERROR` from `modules/CRM/Mail/patches/20260629_mail_attachments_to_filestorage.php`
— `Utils_FileStorageCommon::write_content()`'s `file_put_contents()` returned
`false` for one attachment (`Storing data failed`), 162 rows into a loop that
had already written many others successfully. Replaying the exact same
`mkdir`/`file_put_contents` sequence against all 162 remaining rows minutes
later succeeded for every one, with no code change — strongly points at a
transient lock (Windows Defender real-time scanning, or the Search Indexer)
on the `data/Utils_FileStorage/` tree, likely provoked by the preceding bulk
git operations touching the same `data/` volume.

The actual damage wasn't the transient failure itself — it's that the
migration's `foreach` loop had no per-row error handling, so one exception
propagated out of the patch entirely. `PatchUtil::apply_new()`
(`include/patches.php`) — called from `update.php` with `die_on_error=true`
— then raised a fatal `E_USER_ERROR` and stopped the *whole patch queue*,
not just that one attachment. `die_on_error` is a flag on the runner that
iterates every patch; it has no visibility into what one patch's own loop is
doing, so it can't be scoped to "one file" — and even with
`die_on_error=false`, `apply_new()` still unconditionally stops at the first
non-`SUCCESS` patch (deliberate: later patches can assume earlier ones
succeeded). Resilience has to live *inside* the patch, same pattern already
used in `modules/Base/patches/20260814_utf8mb4_migration.php`: wrap
per-item work in try/catch, log with `error_log()` (never `trigger_error()`
— `Patch::error_handler()` converts that straight back into a fatal
`PatchException`, undoing the point of catching it), and let the patch's own
idempotency pick up any skipped item on the next run. Added a 3-attempt
retry with a 0.2s backoff around the `write_content()` call specifically,
since that directly targets the observed transient-lock failure mode.

**How to apply**: any patch that loops over many independent items (rows,
files, records) needs its own per-item try/catch + `error_log()`-on-failure
— don't rely on `update.php`'s `die_on_error` to contain the blast radius,
it operates at the whole-queue level, not per-item. If a patch dies mid-loop
with no obvious logic bug, try replaying the same operation standalone
before assuming the code is wrong — a transient environment lock (especially
soon after bulk filesystem operations on the same volume) can look
identical to a real failure but simply not reproduce on retry.

## Never hard-delete a `user_login` row directly — 60+ tables have an FK into it

Hit 2026-08-29 cleaning up a disposable test account created for
[password-hashing.md](password-hashing.md)'s live login-flow verification. A
one-off bootstrapped script (`SET_SESSION=false` + `require 'include.php'` +
`ModuleManager::load_modules()`, same pattern as the non-destructive
schema-change recipe in `recordbrowser-live-schema-changes.md`) deleted
`user_password`/`user_autologin` for the test user then ran
`DB::Execute('DELETE FROM user_login WHERE id=%d', ...)` — the script didn't
check `DB::Execute()`'s return value, printed "Deleted" regardless, and the
delete had actually failed with MySQL error 1451 (FK constraint violation)
against `base_dashboard_applets`, which had rows for that user because
logging in once through the real UI auto-populates the Dashboard's default
applets. This surfaced awkwardly: a *different* Claude session monitoring
`data/logs/php_errors.log` in the same checkout (see `feedback_concurrent-
sessions-shared-env` in personal memory — same git tree, multiple sessions)
found the 1451 error, couldn't find a matching query anywhere in app source
or a matching request in `access.log` at that timestamp, and reasonably
suspected a real app bug before asking.

It isn't one. `SELECT TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_NAME='user_login'` on this schema returns **60+
tables** — every module's `*_favorite`/`*_recent`/`*_edit_history` tables,
dashboard state, presence tracking (`tools_whoisonline_users`), notify
cache, filters, shoutbox, messenger, autologin, password-reset tokens, and
more. This is exactly why the app's own real user-removal path
(`Base_User_LoginCommon::invalidate_password()`, used by
`CRM_ContactsCommon::submit_contact()` on Contact delete) deliberately never
hard-deletes the `user_login` row — it only blanks the password hash and
flips `Base_UserCommon::change_active_state()`, so ACL/audit/ownership rows
everywhere else keep something to point at. A one-off script that tries to
actually `DELETE FROM user_login` is fighting the schema's own design.

**How to apply**: for a real account, don't delete `user_login` at all —
deactivate + `invalidate_password()`, matching the app's own pattern. For a
throwaway test account created purely for a verification script, either
follow the same deactivate-don't-delete approach, or if a full removal is
genuinely wanted, delete every table that FK's into that specific
`user_login.id` first (query `information_schema.KEY_COLUMN_USAGE` for the
current list — don't hardcode one, it grows as modules are added) and check
`DB::Execute()`'s return value instead of assuming success. Also: if you see
an FK violation in the error log with no matching app-source query or
access-log request, suspect a manual/script action from a concurrent
session before assuming a real app bug — ask, the way the other session did
here, rather than building a fix for something that isn't broken.
