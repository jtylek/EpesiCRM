# Environment and DB gotchas that looked like application bugs

> **Status:** REFERENCE - things that cost real debugging time and turned out not to be code
> bugs. Machine-specific entries (one developer's XAMPP paths, SSL vhost, blocked ports,
> browser tooling) live in `AI-private/archive/environment-gotchas.md` — this file keeps what
> applies to any Epesi checkout.

## The repo

**`modules/Premium/` is gitignored wholesale; `modules/Custom/` is not.** Only
`modules/Custom/Tutorial` (the example module paired with [Dev-Tutorial.md](Dev-Tutorial.md))
is tracked — every other `modules/Custom/<X>` is meant to be its own nested repo, and
`.gitignore` encodes that as `modules/Custom/*` + `!modules/Custom/Tutorial`. Consequence:
tools that respect `.gitignore` (Claude Code's Grep, PHPStan, Rector) **silently skip** a real
Custom module the same way they skip Premium. A sweep that must include either needs plain
`grep` via Bash.

**Ordinary browsing during a dev session writes to the live DB and filesystem**, so
`git status` routinely shows unrelated `data/` churn — lang cache regeneration,
`Utils_RecordBrowser/last`, attachment blobs — mixed in with real edits. **Name specific paths
when staging; never `git add -A`/`git add .`.**

## CLI scripts share the live DB — writes in a "test" script are real

Any ad-hoc script that bootstraps `include.php` connects to **the same database the browser
session uses**. A verification script that writes a setting "to leave things as found" (say,
`Variable::set('default_theme', 'default')`) silently reverts whatever was just configured
through the UI — and the symptom looks like an application bug, not a test-script side effect.

Prefer overriding state in-process. If a script must write, read the original value first and
restore *that* via `register_shutdown_function` — never hardcode a "default".

**`php update.php` from the CLI is a real mutating operation, not a dry check.**
`EpesiUpdate::run()` always falls through to the full patch-apply / cache-rebuild flow once
past the version check, including turning on maintenance mode. To test update logic safely,
hit it over HTTP (the web path dies before the mutating flow when already up to date) or
render the templates in an isolated script with fake data.

## Never hard-delete a `user_login` row — 60+ tables have an FK into it

`SELECT TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE
REFERENCED_TABLE_NAME='user_login'` returns 60+ tables on this schema: every module's
`*_favorite`/`*_recent`/`*_edit_history`, dashboard state, presence tracking, notify cache,
filters, autologin, password-reset tokens. That is why the app's own removal path
(`Base_User_LoginCommon::invalidate_password()`, called from `submit_contact()` on Contact
delete) **never** hard-deletes the row — it blanks the hash and flips
`Base_UserCommon::change_active_state()`, so ACL, audit and ownership rows everywhere else
keep something to point at.

Deactivate, don't delete. If a throwaway test account genuinely must go, delete every
FK-referencing table for that id first (query `information_schema` for the current list —
don't hardcode one, it grows with every module) **and check `DB::Execute()`'s return value**;
a failed delete returns false while a script that ignores it happily prints "Deleted".

## A silent DB failure looks like "the code never ran"

ADOdb's mysqli driver runs with `mysqli_report(MYSQLI_REPORT_OFF)`, so a failed query produces
**no warning and no log entry**, and cascades into unrelated-looking errors elsewhere.

The recurring cause is **`max_allowed_packet`**: `History::set()` persists a gzip+serialized
blob of per-tab module state into a `longblob` on *every request*, inlined via `DB::qstr()`
rather than a bound parameter, so one large pasted note pushes it past the stock 1 MB limit.
The failure then surfaces as `EpesiSession::write()` failing at shutdown — "Failed to write
session data" — with nothing about packet size anywhere. Raise `max_allowed_packet` under
`[mysqld]` (64M is plenty). Note the second, unrelated `max_allowed_packet` under
`[mysqldump]` in the same file.

**Rule:** if a DB-backed operation fails with nothing in `php_errors.log` to explain it,
suspect a silently swallowed ADOdb failure — packet size, lock timeout — before assuming the
code path was never reached.

## `EPESI_URL` is absolute, not derived from the request

`data/config.php`'s `EPESI_URL` is a hardcoded base URL. On a dev copy of a real site whose
config still names the public domain, any redirect built from it — `update.php`'s `up=end`
step, for one — sends the browser off localhost to the *actual* production server, which
returns its own 403.

The tell is a "timeout" or 403 with **zero corresponding entry in the local access log**,
because the request never reached this machine. After cloning or restoring a site's `data/`
locally, check `EPESI_URL` before assuming a code bug.

## Stale `temp/<DATA_DIR>/cache/` after an out-of-band code swap

The compiled-template cache, the general-purpose `Cache::` store, the minified-asset cache and
the asset-version scan cache all live under `temp/<DATA_DIR>/cache/` and are built against
whatever code was on disk. The normal `update.php` flow accounts for that; a bulk swap outside
it — `git checkout` onto another branch, overlaying a release — does not, and leaves compiled
templates referencing classes the new code no longer has. It looks exactly like the new code
being broken.

**After any out-of-band code swap, clear `temp/<DATA_DIR>/cache/` before testing.** All of it
is regenerable; none of it is user data.

## Missing assets appear only in access.log

A broken `<img>` or CSS `url()` leaves **no trace in error.log**, which records only
PHP/script failures — filter `access.log` for ` 404 `. A clean error.log is not evidence that
assets load. CLI render tests print the URLs an app *emits* but never do browser-style
relative-URL resolution, so they cannot catch a wrong base path; resolve a changed asset URL
the way a browser would (collapsing `..` against the *stylesheet's* own URL, not the page's).

## A patch that loops over many items needs its own per-item try/catch

`PatchUtil::apply_new()`'s `die_on_error` operates at the **whole-queue** level, not per item,
and even with it off `apply_new()` still stops at the first non-`SUCCESS` patch (deliberate —
later patches may assume earlier ones succeeded). So one transient failure 162 rows into a
migration loop aborts the entire update run.

Resilience has to live inside the patch: wrap per-item work in try/catch, log with
`error_log()` — **never `trigger_error()`**, which `Patch::error_handler()` converts straight
back into a fatal `PatchException` — and let the patch's own idempotency pick up skipped items
on the next run. `modules/Base/patches/20260814_utf8mb4_migration.php` is the pattern.

And if a patch dies mid-loop with no obvious logic bug, replay the operation standalone before
assuming the code is wrong. A transient filesystem lock (antivirus, a search indexer, right
after bulk git operations on the same volume) looks identical to a real failure and simply
does not reproduce.

## PHPStan: "Result is incomplete because of severe errors"

That means a **parse error**, not an ordinary finding — it cannot be suppressed via
`phpstan-baseline.neon` and it aborts the whole run rather than flagging one file. The cause
here was vendored third-party code that landed under `modules/` (using PHP 7 curly-brace
string offsets, removed in 8.0) without a matching `excludePaths` entry. Check for that before
assuming a real regression in Epesi's own code.

`phpstan.neon` and both `rector*.php` deliberately exclude `modules/Premium/` and
`modules/Custom/` — see [load-bearing-oddities.md](load-bearing-oddities.md).

## Windows: `core.fileMode` makes vendored scripts look modified

On NTFS with `core.fileMode=true`, `git status` shows ~30 vendored scripts as modified with
zero content diff. It is the executable bit, not a change.
