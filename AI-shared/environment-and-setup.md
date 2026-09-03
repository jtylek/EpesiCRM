# Environment and dev setup

Getting a working development install, and the things that will confuse you once you have
one. Everything here cost real debugging time and turned out not to be an application bug.

---

# Part 1 — working in the checkout

## Gitignored module trees are invisible to your tools

`modules/Custom/*` is gitignored except `modules/Custom/Tutorial` (the example module
paired with [Dev-Tutorial.md](Dev-Tutorial.md)) — every other `modules/Custom/<X>` is meant
to be its own nested repo, and `.gitignore` encodes that as `modules/Custom/*` +
`!modules/Custom/Tutorial`. Other module trees are gitignored wholesale.

**Consequence: tools that respect `.gitignore` — Claude Code's Grep, PHPStan, Rector —
silently skip those directories.** Not "report nothing found"; they never look. A sweep
that must include them needs plain `grep` via Bash instead. Old-syntax bugs in such a tree
surface only at runtime, which is why they survive long after a codebase-wide fix.

## Ordinary browsing writes to the repo

Using the app during a dev session writes to the live DB *and* the filesystem, so
`git status` routinely shows unrelated `data/` churn — lang cache regeneration,
`Utils_RecordBrowser/last`, attachment blobs — mixed in with real edits.

**Name specific paths when staging. Never `git add -A` or `git add .`.**

## Windows: a nested repo without `.gitattributes` rewrites whole files on commit

The main repo pins `* text=auto eol=lf` in `.gitattributes`, so line endings normalize to
LF on commit no matter what a given developer's `core.autocrlf` says. **The nested repos
have no `.gitattributes` of their own and do not inherit the main repo's.** They fall back
to `core.autocrlf`, which is `true` on a typical Windows install.

If such a repo's blobs were committed with CRLF, every commit made here converts them to LF
— so a one-line comment change lands as a **whole-file diff**. This is real, not
theoretical: a one-line edit once reported 2375 insertions and 2375 deletions.

**Check before committing to a nested repo** — this compares what git would store against
what is already stored:

```bash
git -C <repo> rev-parse HEAD:<file>          # stored blob
git -C <repo> hash-object <file>             # what a commit would store
```

Different hashes with an unmodified file means the conversion is about to fire.

**The fix is per-repo local config, not a content change:**

```bash
git -C <repo> config core.autocrlf false
```

Verify it is safe first — `git -C <repo> -c core.autocrlf=false status --porcelain` must
come back **empty**. If it lists files, that repo stores LF while its working tree holds
CRLF, and turning the conversion off would make every file look modified; leave it alone
and use a one-off `git -c core.autocrlf=false commit` instead.

This config lives in `.git/config`, so it is **not** shared — every clone and every other
machine needs it again.

---

# Part 2 — the database and the live install

## CLI scripts share the live DB — writes in a "test" script are real

Any ad-hoc script that bootstraps `include.php` connects to **the same database the browser
session uses**. A verification script that writes a setting "to leave things as found" (say,
`Variable::set('default_theme', 'default')`) silently reverts whatever was just configured
through the UI — and the symptom looks like an application bug, not a test-script side
effect.

Prefer overriding state in-process. If a script must write, read the original value first
and restore *that* via `register_shutdown_function` — never hardcode a "default".

**`php update.php` from the CLI is a real mutating operation, not a dry check.**
`EpesiUpdate::run()` always falls through to the full patch-apply / cache-rebuild flow once
past the version check, including turning on maintenance mode. To test update logic safely,
hit it over HTTP (the web path dies before the mutating flow when already up to date) or
render the templates in an isolated script with fake data.

## A silent DB failure looks like "the code never ran"

ADOdb's mysqli driver runs with `mysqli_report(MYSQLI_REPORT_OFF)`, so a failed query
produces **no warning and no log entry**, and cascades into unrelated-looking errors
elsewhere.

The recurring cause is **`max_allowed_packet`**: `History::set()` persists a
gzip+serialized blob of per-tab module state into a `longblob` on *every request*, inlined
via `DB::qstr()` rather than a bound parameter, so one large pasted note pushes it past the
stock 1 MB limit. The failure then surfaces as `EpesiSession::write()` failing at shutdown
— "Failed to write session data" — with nothing about packet size anywhere. Raise
`max_allowed_packet` under `[mysqld]` (64M is plenty). Note the second, unrelated
`max_allowed_packet` under `[mysqldump]` in the same file.

**Rule:** if a DB-backed operation fails with nothing in `php_errors.log` to explain it,
suspect a silently swallowed ADOdb failure — packet size, lock timeout — before assuming
the code path was never reached.

## Never hard-delete a `user_login` row — 60+ tables have an FK into it

`SELECT TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE
REFERENCED_TABLE_NAME='user_login'` returns 60+ tables: every module's
`*_favorite`/`*_recent`/`*_edit_history`, dashboard state, presence tracking, notify cache,
filters, autologin, password-reset tokens.

That is why the app's own removal path (`Base_User_LoginCommon::invalidate_password()`,
called from `submit_contact()` on Contact delete) **never** hard-deletes the row — it
blanks the hash and flips `Base_UserCommon::change_active_state()`, so ACL, audit and
ownership rows everywhere else keep something to point at.

Deactivate, don't delete. If a throwaway test account genuinely must go, delete every
FK-referencing table for that id first (query `information_schema` for the current list —
don't hardcode one, it grows with every module) **and check `DB::Execute()`'s return
value**; a failed delete returns false while a script that ignores it happily prints
"Deleted".

## `EPESI_URL` is absolute, not derived from the request

`data/config.php`'s `EPESI_URL` is a hardcoded base URL. On a dev copy of a real site whose
config still names the public domain, any redirect built from it — `update.php`'s `up=end`
step, for one — sends the browser off localhost to the *actual* production server, which
returns its own 403.

The tell is a "timeout" or 403 with **zero corresponding entry in the local access log**,
because the request never reached this machine. After cloning or restoring a site's `data/`
locally, check `EPESI_URL` before assuming a code bug.

## Stale `temp/<DATA_DIR>/cache/` after an out-of-band code swap

The compiled-template cache, the general-purpose `Cache::` store, the minified-asset cache
and the asset-version scan cache all live under `temp/<DATA_DIR>/cache/` and are built
against whatever code was on disk. The normal `update.php` flow accounts for that; a bulk
swap outside it — `git checkout` onto another branch, overlaying a release — does not, and
leaves compiled templates referencing classes the new code no longer has. It looks exactly
like the new code being broken.

**After any out-of-band code swap, clear `temp/<DATA_DIR>/cache/` before testing.** All of
it is regenerable; none of it is user data.

## Missing assets appear only in access.log

A broken `<img>` or CSS `url()` leaves **no trace in error.log**, which records only
PHP/script failures — filter `access.log` for ` 404 `. A clean error.log is not evidence
that assets load. CLI render tests print the URLs an app *emits* but never do browser-style
relative-URL resolution, so they cannot catch a wrong base path; resolve a changed asset
URL the way a browser would, collapsing `..` against the *stylesheet's* own URL, not the
page's.

## A patch that loops over many items needs its own per-item try/catch

`PatchUtil::apply_new()`'s `die_on_error` operates at the **whole-queue** level, not per
item, and even with it off `apply_new()` still stops at the first non-`SUCCESS` patch
(deliberate — later patches may assume earlier ones succeeded). So one transient failure
162 rows into a migration loop aborts the entire update run.

Resilience has to live inside the patch: wrap per-item work in try/catch, log with
`error_log()` — **never `trigger_error()`**, which `Patch::error_handler()` converts
straight back into a fatal `PatchException` — and let the patch's own idempotency pick up
skipped items on the next run. `modules/Base/patches/20260814_utf8mb4_migration.php` is the
pattern to copy.

And if a patch dies mid-loop with no obvious logic bug, replay the operation standalone
before assuming the code is wrong. A transient filesystem lock (antivirus, a search indexer,
right after bulk git operations on the same volume) looks identical to a real failure and
simply does not reproduce.

---

# Part 3 — seeding a dev install with demo data

Five commands under `console/Demo/` (namespace `Epesi\Console\Demo`, registered in
`console.php`) use Faker to seed realistic-looking records:

```
demo:generate:contacts [--count=N] [--create-company] [--employees=N]
demo:generate:phonecalls [--count=N]
demo:generate:meetings   [--count=N]
demo:generate:tasks      [--count=N]
demo:generate:shoutbox   [--count=N]
```

There is no `demo:generate:companies` — companies are created only as a side effect of
`demo:generate:contacts --create-company`.

## The order to run them in

The generators never create employees, and they hard-fail with a clear message rather than
inventing one. `employees_crits()` restricts the real "Employees" picker to contacts
belonging to *your own* company, derived from your own contact's `company_name` — so:

1. **Create your own company and your own contact by hand**, or through normal first-run
   setup. Nothing can automate this: your company is derived from *your own contact's*
   `company_name` (the contact whose `login` field is your user id), so the record has to
   exist first.
2. **`demo:generate:contacts --employees=10`** fills the employee pool — contacts with
   `company_name` set to your own company, which is all `employees_crits()` actually
   requires.
3. **Then** run `demo:generate:contacts --create-company` (customers — any company,
   unrelated to yours) and `demo:generate:phonecalls` / `:meetings` / `:tasks`, which
   assign employees only from step 2, picking 1–2 at random per record.

`--employees=N` and `--count=N` are independent, so `--count=100 --employees=10` seeds both
pools in one run. `--employees` on its own generates *only* employees. Re-running later
picks up a larger pool automatically — the query runs fresh every time, nothing is cached.

## What the generated data looks like

- **Not reproducible.** `\Faker\Factory::create()` is called with no seed, so every run
  produces different records. Fine for eyeballing a dev install, not for anything asserting
  on specific values.
- **Times are constrained to business hours.** Faker's `dateTimeBetween()` gives a useful
  *date* but a time-of-day drawn uniformly across 24h, which produced demo calendars full
  of 03:47 meetings. The `BusinessHours` trait places times in a 09:00–20:00 window on
  15-minute boundaries; for meetings the duration is chosen first and the window holds the
  *whole* meeting, so a 3h meeting starts by 17:00.
- **Titles are capped at 30 characters** by the `ShortTitle` trait, trimmed on word
  boundaries, because `sentence(4)` routinely ran past 40 and wrapped in grid rows.
- **Dates spread across today ± 30 days**, deliberately, so demo lists have a mix of past,
  present and future records to browse, sort and filter.

Both traits use **static properties rather than constants**, because trait constants
require PHP 8.2 and this app supports 8.1+ — see `CLAUDE.md`.

All generators run as user id 1 (`Acl::set_user(1)`) so `new_record()`'s `created_by`
binding has a real value in a CLI context, which never has a session.

## Two rules if you extend a generator

- **Demo contacts must never create real logins.** The old `--create-user` flag was removed
  outright, not merely left unused: a demo contact getting a real login account is a
  security-relevant mistake, not a cosmetic one. `--create-company` is fine — it creates a
  company record, no login involved.
- **Read the recordset's processing callback before adding a field.** `new_record()`
  bypasses QuickForm but not the registered `submit_*()` callback, and checkbox fields need
  a plain `0`/`1`, never a PHP `bool`. See
  [recordbrowser-recipes.md](recordbrowser-recipes.md).
