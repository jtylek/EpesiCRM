# Optimization plan (Opus session, 2026-08-31)

> **Status:** PLAN, partly implemented - Tier 0, Tier 1 and Tier 2 are done (see the implementation logs in sections 8, 9 and 10). Tier 3 started in section 11: A2.3 shipped, 3.1 recommended for striking on measurement, 3.2/3.4 still open.

A performance + developer-experience plan for this Epesi checkout, written from
measurements taken on this machine rather than from reading code alone. Every number
below is reproducible — see "How these numbers were taken" at the end.

**Scope note:** sections 1-7 are the plan as originally written (2026-08-31), left
unedited so the reasoning and the pre-change measurements stay readable. **Tier 0 and
Tier 1 were implemented the same day — see the implementation log in §8 for what shipped,
what the plan got wrong, and what is still open. §9 covers the second pass: item 2.1
(the function-level profile), which found that every suspect §A4 named was wrong.**
`performance-profiling.md` remains the authoritative record of the performance work
itself.

**Design constraint applied throughout:** every proposal was checked against
`design-philosophy.md` — a module developer must keep writing plain PHP and get a
working screen for free. Items that would push HTML/CSS/JS work onto ordinary module
authors are listed in "Deliberately not proposed" rather than silently dropped.

---

## 1. Measured baseline

Live app, `MODULE_TIMES`/`SQL_TIMES` on, logged in as `jtylek`, Chrome via Playwright.
Flags restored to `0` afterwards.

| Screen | Render | SQL queries | SQL time | SQL as % of render |
|---|---|---|---|---|
| Login → Dashboard | 0.364 s | 157 | 0.131 s | 36% |
| Contacts: Browse (20 rows) | 0.283 s | 168 | 0.089 s | 31% |

Module-time breakdown for Contacts: Browse (nested; children roll up into parents):

```
0.2495s  /Base_Box|0
0.2110s    /Base_Box|0/CRM_Contacts|main_0
0.2103s      .../Utils_RecordBrowser|contact      <-- 74% of the whole render
0.0191s        .../Utils_RecordBrowser|contact/Utils_GenericBrowser
0.0066s  /Base_Box|0/Base_ActionBar|actionbar
0.0046s  /Base_Box|0/Base_Menu|menu
0.0040s  /Base_Box|0/CRM_Filters|filter
   ...   (all remaining chrome: search, login, statusbar, help, logo ~0.011s total)
```

Two conclusions that shape everything below:

1. **The page chrome is not the problem.** Menu, ActionBar, Search, Filters, StatusBar
   and Help together cost ~0.03 s. `Utils_RecordBrowser` alone costs 0.21 s.
2. **The database is not the main problem either.** Only ~31% of the Contacts render is
   SQL. Of `Utils_RecordBrowser`'s 0.2103 s, ~0.019 s is `Utils_GenericBrowser` (the code
   that actually emits the table). The remaining ~0.10 s of non-SQL time is
   RecordBrowser's own per-row/per-field PHP.

Bootstrap cost, measured from CLI (opcache off there, so treat as an upper bound —
under Apache with opcache the parse cost largely disappears but the *work* does not):

```
require include.php ........ ~35 ms   (80 files)
ModuleManager::load_modules() ~80 ms   (+71 files, 95 installed modules)
                              ------
total                        ~115 ms   151 files, ~1.78 MB of PHP parsed, 10 MB peak
```

Front-end, one login + two in-app navigations (resource timing, warm cache):

```
 3x  tot=1377ms  max=875ms   process.php
 8x  tot= 637ms  max=147ms   modules/Base/Notify/refresh.php
 2x  tot= 188ms  max=124ms   modules/Utils/RecordBrowser/indexer.php
 2x  tot= 163ms  max= 94ms   modules/Apps/Shoutbox/refresh.php
 2x  tot= 142ms  max= 84ms   modules/Utils/Messenger/refresh.php
 3x  tot= 138ms  max= 76ms   modules/Base/Theme/theme_css.php
 6x  tot=  96ms  max= 27ms   serve.php
~25 separate PNG/GIF requests for module theme icons
```

---

## 2. Runtime performance

### A1 — Four call sites produce 59% of the queries on Contacts: Browse

Grouping the SQL panel by caller (the technique `performance-profiling.md` already
documents) gives, for one 20-row Contacts: Browse render:

| Count | Time | Call site | What it is |
|---|---|---|---|
| 40x | 0.0196 s | `RecordBrowserCommon_0.php:2124` | `get_record_info()` — 2 queries × 20 rows |
| 20x | 0.0106 s | `WatchdogCommon_0.php:330` | `check_if_notified()` — residual `MAX(id)` per row |
| 20x | 0.0101 s | `RoundcubeCommon_0.php:50` | `get_mailto_link()` — **identical query, 20×** |
| 19x | 0.0099 s | `ContactsCommon_0.php:134` | `get_company()` — one company at a time |

**99 of 168 queries.** All four are the same shape `performance-profiling.md` already
names ("a per-row grid formatter calling a `FooCommon::get_thing($varies_per_row_id)`
helper"), so the fix discipline is established — these are simply sites the previous
passes didn't reach.

**A1.1 — `CRM_RoundcubeCommon::get_mailto_link()` (easiest win in the document).**
Line 50 runs
`Utils_RecordBrowserCommon::get_records_count('rc_accounts', ['epesi_user' => Acl::get_user()])`
with **no per-row variation at all** — same table, same user, same criteria, once per
email cell. `get_records_count()` has no cache (`RecordBrowserCommon_0.php:1672-1676`):
each call runs a full `build_query()` plus a `COUNT(*)`. The debug panel's own
duplicate-args underline flags all 20 as exact repeats.

Fix: a request-scoped `static` in a small `has_mail_account()` helper, used by both
`get_mailto_link()` and `attachment_getters()` (same query, line 59). One-line-ish
change, zero invalidation concerns within a request. **20 queries → 1.**

**A1.2 — `Utils_RecordBrowserCommon::get_record_info()` needs prefetch, not just a cache.**
The per-id cache added 2026-08-29 works, but every visible row is a distinct id, so the
grid still pays 2 queries per row (`created_on/by` from `_data_1`, `edited_on/by` from
`_edit_history`). `RecordBrowser_0.php`'s `add_info()` calls it once per row, and the row
id set is known before the loop starts.

Fix: add `prefetch_record_info($tab, array $ids)` that runs two grouped queries and
primes `self::$record_info_cache`, called once by the grid before rendering rows. The
existing per-id path stays as the self-healing fallback (same pattern
`Utils_CommonDataCommon::load_tree()` uses). **40 queries → 2.**

Cheap adjacent fix: line 2088's `_edit_history` query has `ORDER BY edited_on DESC` with
no `LIMIT 1`, so the DB sorts the full history to hand back one row.

**A1.3 — `Utils_WatchdogCommon`'s N+1 was only half fixed.** The 2026-08-28 fix
(`_user_last_seen()`) removed the per-row *subscription* query. But
`user_check_if_notified()` line 280 still runs
`SELECT MAX(id) FROM utils_watchdog_event WHERE internal_id=%d AND category_id=%d`
once per row, for every row the user is subscribed to.

Fix: the subscription map fetched by `_user_last_seen()` already gives the full set of
`internal_id`s for that `(user, category)`. One
`SELECT internal_id, MAX(id) ... WHERE category_id=%d AND internal_id IN (...) GROUP BY internal_id`
against that set replaces all of them. **20 queries → 1.** Worth updating
`performance-profiling.md`'s Watchdog entry to say the fix was partial — as written it
reads as complete.

**A1.4 — `CRM_ContactsCommon::get_company()`.** 19 distinct companies fetched one at a
time. Genuinely distinct ids, so memoization can't help — this needs the same prefetch
treatment as A1.2, seeded from the linked-company ids present in the already-loaded row
set. Lower priority than the first three (the ids aren't as cleanly available up front).

**Expected combined effect:** 168 → ~90 queries on Contacts: Browse, SQL time roughly
halved. Because SQL is only ~31% of the render, expect a ~10-15% wall-clock improvement
— real, but see A2 and A4 for the larger levers.

---

### A2 — Background pollers pay the full application bootstrap to do nothing

`modules/Base/Notify/refresh.php` was called **8 times for 637 ms** during a short
session. Its structure:

```php
define('READ_ONLY_SESSION', true);
require_once('../../../include.php');   // full bootstrap
ModuleManager::load_modules();          // all 95 modules, ~150 files
...
if (!Base_NotifyCommon::is_refresh_due($token)) exit();   // <-- most calls stop HERE
```

The rate limit (`refresh_rate = 30` seconds, `NotifyCommon_0.php:22`) is checked *after*
the entire application is loaded. The client polls on the same 30 s interval
(`Base_Notify.init(refresh_rate*1000)`), so the two are nominally aligned — but any
extra tab, a reload, or clock drift turns into a full bootstrap that returns an empty
body. `Utils/Messenger/refresh.php`, `Apps/Shoutbox/refresh.php` and
`Utils/RecordBrowser/indexer.php` have the same shape; together the four pollers cost
**~1.1 s** across the measured session, comparable to all three `process.php` renders.

Three fixes, increasing in ambition:

1. **Move the due-check before `load_modules()`.** `is_refresh_due()` needs a session
   token and one `base_notify` row — it needs `include.php`, not the module tree. Hoisting
   it above `ModuleManager::load_modules()` makes the common "nothing to report" path
   skip ~80 ms of module loading. Smallest change, largest ratio.
2. **Coalesce the pollers.** Four endpoints × one bootstrap each, all asking "anything new
   for me?", all on their own timers. One `poll.php` returning
   `{notify: …, messenger: …, shoutbox: …, indexer: …}` collapses four bootstraps into
   one. This is a real refactor (four modules' JS has to move to a shared dispatcher) but
   it is the single biggest structural saving on this list.
3. **Back off when the tab is hidden.** All four pollers run at full rate on a backgrounded
   tab. `document.visibilityState` gating is a few lines in each poller's JS.

`indexer.php` deserves a specific note: it already guards on a `DATA_DIR/Utils_RecordBrowser/last`
mtime *before* calling `load_modules()` — exactly the pattern (1) proposes. That guard is
the model to copy, not something to invent.

---

### A3 — Every entry point loads all 95 modules

`ModuleManager::load_modules()` registers all 95 installed modules and `require`s every
Common class flagged as containing code (~71 files, ~880 KB) on **every** request — a
grid render, an autocomplete callback, a notification poll, a CSS request that happens to
need the theme.

There is already a mechanism for this: `FORCE_CACHE_COMMON_FILES` concatenates every
Common file into one `temp/…/cache/common.php`, turning ~71 `require`s + `file_exists()`
calls into one. **It is off** — `include/config.php:30` defaults it to `0`,
`data/config.php:101` sets `0`, and `setup.php:612` writes `0` into every new install.
So no install ships with it on.

Two things to do here, and they are different in kind:

- **Short term — find out why it's off.** The flag exists, `console.php cache:rebuild`
  exists to regenerate the file, and `load_modules()` has careful handling for the
  "commons already individually loaded" case. Something made it not the default. Before
  proposing it as a win, turn it on locally, run `cache:rebuild`, and exercise the app;
  if it holds up, the question is whether `setup.php` should default it to `1`. Note the
  developer-experience cost: with it on, editing any `*Common_0.php` requires a
  `cache:rebuild` before the change takes effect, which is a real papercut and may well
  be the original reason for the default. A sensible landing point is **on in production
  installs, off in development** — which needs it separated from the single global flag
  it is today.
- **Longer term — load Commons lazily.** `include/autoloader.php` already resolves
  `Foo_BarCommon` on demand via `ModuleManager::include_common()`. The eager
  `commons_with_code` loop in `load_modules()` exists because some Common classes register
  hooks (menu entries, `on_init` callbacks, notification providers) as a side effect of
  being loaded, so they must run even if nobody names the class. The fix shape is to make
  those registrations *declarative* — a cached manifest of "module X contributes menu
  entries / notifications / on_init" built at install time — so the class itself only
  loads when actually used. This is the highest-value structural change in this document
  and also the riskiest; it deserves its own branch and its own design note.

---

### A4 — `Utils_RecordBrowser`'s non-SQL cost is the real bottleneck

0.2103 s for `Utils_RecordBrowser|contact`, of which ~0.089 s is SQL (whole page) and
0.019 s is `Utils_GenericBrowser`. Roughly **0.10 s of pure PHP per grid render** that is
neither database nor table markup.

`RecordBrowserCommon_0.php` is 3,945 lines and `RecordBrowser_0.php` is 3,179 — the two
largest hand-written files in the codebase. Per-row work runs through `get_val()`,
per-field display callbacks, `create_default_linked_label()`, access checks
(`Access.php::getRuleCrits()` showed 6 queries + per-row evaluation), tooltip building,
and favourite/watchdog icon assembly.

This has not been profiled at the function level yet, and it should be before anyone
optimizes it. **Recommended next step:** one Xdebug or Excimer profile of a single
Contacts: Browse `process.php` request, to turn "0.10 s somewhere in RecordBrowser" into
a ranked function list. Everything else in this section is guesswork until that exists.
This is the highest-information-per-hour action available and it is not currently
possible with the built-in profiler, which measures modules and queries but not PHP
functions.

Cheap things visible without a profile:

- `get_access($tab, 'view', $record)` is evaluated per row per call site;
  `getRuleCrits()` caches per `(tab, user, action)` but the per-record predicate
  evaluation is not memoized across the several places each row asks.
- `Base_ThemeCommon::get_template_file()` is called per row for every icon
  (`star_fav.png`, `watch_big.png`, edit/view/delete) — a resolver lookup per row per
  icon that could be hoisted out of the loop.

---

### A5 — Front end: ~25 icon requests, four aggregator endpoints, per-navigation CSS

**Icons.** One Contacts: Browse page pulls ~25 individual PNG/GIF files — `edit.png`,
`info.png`, `view.png`, `delete.png`, `plus_gray.png`, `minus_gray.png`, `collapse.gif`,
`expand.gif`, `star_fav.png`, `watching_small.png`, `watch_big.png`, four module
`icon-small.png`s, and more. There are 406 such files under `modules/*/theme*/`.

The codebase already solved this once: `bootstrap_icon()`/`Base_BootstrapIcons` replaced
`adminlte_icon()` in 2026-08-14 (see `adminlte-theme.md`) and the Bootstrap Icons webfont
is already loaded on every page. GenericBrowser's row-action icons, RecordBrowser's
favourite star, and Watchdog's subscription icons are simply sites that conversion never
reached. Converting them removes ~20 requests per page with **no new dependency and no
new concept** — the convention already exists and is documented.

**Aggregators.** Static assets are served through four different PHP endpoints:
`serve.php`, `modules/Base/Theme/theme_css.php`,
`libs/bootstrap-icons-1.13.1/__css.php`, and `modules/Base/Theme/asset.php`. Three of the
four run PHP (and `theme_css.php` a DB query for the active theme) on requests that are
then cached for a year. Consolidating on one endpoint would not speed up a warm cache but
would remove a class of "which loader handles this?" confusion that costs developer time.

**Per-navigation CSS/JS.** Because CSS is loaded per rendering module, each navigation
adds a *new* `theme_css.php` bundle URL (3 distinct ones across 2 navigations, 138 ms
total) and a new `serve.php` JS bundle (6 across the session). Each is render-blocking
on first visit to a screen. This is a direct consequence of the per-module CSS design
described in `CLAUDE.md`, and it is a genuine trade-off, not a bug — but a small
"common screens" prelude bundle (GenericBrowser + RecordBrowser + QuickForm CSS, which
almost every screen needs) shipped with the initial page would remove the blocking fetch
from the most common navigations.

One cosmetic defect worth fixing while in there: the cache-buster is appended as
`&amp;1788162412` (HTML-escaped) in `theme_css.php` URLs, so the parameter name is
literally `amp;1788162412`. It works by accident.

---

### A6 — Cache and session layer: already good here, fragile elsewhere

**Correcting a mid-session assumption:** APCu is not loaded on this machine, but that
turns out not to matter. `MEMCACHE_SESSION_SERVER` is set to `127.0.0.1:11211`, the
`memcached` extension *is* loaded, a server *is* listening, and the live driver is
`Phpfastcache\Drivers\Memcached\Driver`. Measured: 0.127 ms per `Cache::set()`,
0.002 ms per `Cache::get()`. **The object cache is not a bottleneck on this install and
installing APCu here would buy nothing measurable.**

The real issues are structural:

1. **`Cache::` is gated on a session constant.** `include/cache.php:21` only considers
   memcached `if (MEMCACHE_SESSION_SERVER)`. Turn off memcached *sessions* while leaving
   memcached running and the object cache silently drops to the Files driver — file
   locking and serialization on every `Cache::set()`. Conversely, an install that gains
   memcached after setup never picks it up without hand-editing `data/config.php`.
   Fix: a separate `CACHE_TYPE`/`CACHE_SERVER` pair, defaulting to the session values for
   backward compatibility.
2. **The fallback chain is theoretical.** `['Apcu', 'Zendshm', 'Files']` — on a typical
   XAMPP or shared host none of the first two are present, so the effective fallback for
   any install without memcached is Files. Since phpfastcache 9.2 is already vendored with
   Redis, Predis and Sqlite drivers available, adding Redis to the chain (and Sqlite ahead
   of Files) is configuration, not new dependencies. *This* is where APCu would earn its
   place — as a fallback for installs that have it, not as an upgrade for this box.
3. **Roundcube tests for the wrong class.**
   `modules/Libs/RoundCube/RC/config/config.inc.php:123,176-177` gate on
   `class_exists('Memcache')`. Only `Memcached` is loaded here, so Roundcube's IMAP cache
   and session storage silently fall back to the database **even though memcached is
   available and the rest of the app is using it**. `include/session.php:360-364` gets
   this right (checks `memcached` then `memcache`); the Roundcube config does not. Small,
   self-contained, and a genuine latent slowdown for anyone using webmail.

**Session locking.** `process.php` does not define `READ_ONLY_SESSION`, so every render
takes an exclusive session lock for the duration of the request. The pollers correctly set
it. With one user this is invisible; with a user who has several tabs open it serializes
their requests behind each other. Worth measuring before acting — but worth knowing when
someone reports "the app is slow when I have lots of tabs open."

---

## 3. Developer friendliness

### B1 — The CI this repo documents does not exist

`CLAUDE.md` says: *"Lint (what CI's `lint` job runs…)"*. `phpstan.neon`'s comments refer
to *"CI's phpstan job"* and *"the phpstan CI job's artifact"*. `AI-shared/dependency-upgrades.md`
and the Rector section describe CI behaviour in the present tense.

```
$ find .github -type f
.github/PULL_REQUEST_TEMPLATE/001_phpstan-stubs.md
```

**There are no workflows.** No `lint` job, no `phpstan` job, no Rector check. Every claim
about "CI only fails on *new* findings" describes an intention, not a running system.

This is the single most consequential DX gap in the repo, because it invalidates a
safety assumption that both humans and AI sessions have been relying on. The three
commands are already specified and already run clean locally; the missing piece is
~40 lines of YAML:

```yaml
# .github/workflows/ci.yml  — sketch
jobs:
  lint:     # php -l over include/ modules/, minus vendor/RoundCube/dev-tool excludes
  phpstan:  # vendor/bin/phpstan analyse -c phpstan.neon
  rector:   # vendor/bin/rector process --dry-run --config rector-php82.php  (advisory)
```

Do this first. It is hours of work and it makes every other item on this list safer.

### B2 — No tests, and the skeleton that was supposed to become tests is gone

`CLAUDE.md` says *"`codeception.yml` and `tests/` are an empty skeleton"*. Both were
removed. `AI-shared/README.md` knows this; `CLAUDE.md` does not.

`PROPOSAL_functional_tests.md` remains undecided. Given the app's AJAX-push architecture
(no deep-linkable URLs, verification means click-through), a full Codeception suite is a
large investment. A much cheaper first step that would pay for itself immediately:

- **A smoke script**, run in CI, that bootstraps `include.php` headlessly, calls
  `ModuleManager::load_modules()`, and asserts zero warnings/notices — this alone would
  have caught several of the bugs logged in `bug-patterns.md`.
- **A query-count regression guard.** The `SQL_TIMES` infrastructure already counts
  queries per render. A test that renders Contacts: Browse and fails if the count exceeds
  a recorded ceiling would make every N+1 in section A1 *stay* fixed. This is unusually
  well-suited to this codebase because the instrumentation already exists.

### B3 — Profiling requires editing a config file and remembering to undo it

Turning on the profiler means hand-editing two lines in `data/config.php` and manually
reverting them (as this session did). Forgetting leaves per-request overhead on
permanently.

Fix: allow both flags to be enabled per-session for an admin — e.g. a
`?epesi_debug=1` request parameter gated on `Base_AclCommon::i_am_admin()`, stored in the
session, with a visible "profiling on" indicator and a one-click off in the existing debug
bar. The debug bar redesigned on 2026-08-28 is the natural home for the toggle.

### B4 — `CLAUDE.md` has drifted from the code

Three concrete errors found while working, all in the "Commands" section that a new
session is told to trust:

| `CLAUDE.md` says | Reality |
|---|---|
| `console.php dev:create:module` | `dev:module:create` |
| `console.php dev:create:patch` | `dev:module:patch` |
| "`codeception.yml` and `tests/` are an empty skeleton" | Neither exists |
| "what CI's `lint` job runs" | No CI exists |

A doc that is wrong about its own commands is worse than one that omits them — a session
that tries `dev:create:module`, gets an error, and works around it has been actively
slowed down. See section 4 for how to keep this from recurring.

### B5 — PHPStan is at level 0 with a 133-entry baseline

Level 0 catches undefined functions/classes/methods and wrong argument counts, which is
genuinely the high-value tier for a codebase this age. But the stated plan ("Raise
gradually once a baseline exists") has not moved, and the blocker named in the config —
"no PSR-4 autoload for its own classes" — is solvable without touching the module loader:

PHPStan doesn't need runtime autoloading, only symbol resolution. A generated
`phpstan-classmap.php` (built by walking `modules/*/…/*_0.php` and `*Common_0.php` with
the same path convention `ModuleManager` uses, wired in via `scanFiles`) would let level 1
or 2 run without a flood of "unknown class". That is a small script, and it unlocks a
meaningful tier of real bug detection — level 1 catches undefined variables, which is
exactly the failure mode that `REPORT_ALL_ERRORS` mode turns into a blank module.

### B6 — The upgrade gap has discipline but no enforcement

`CLAUDE.md` correctly names the single most common regression: a fix to an `*Install.php`
default or seed data that never reaches existing installs because no patch was written.
The discipline is documented in `MIGRATION_NOTES.md`; the enforcement is entirely human
memory (and, per `MEMORY.md`, a saved correction telling sessions *not* to apply it
reflexively — so the rule is genuinely a judgment call, not a checklist).

A CI check that flags a diff touching `*Install.php` with no accompanying
`patches/*.php` file — as a **warning with an explicit "cosmetic/pre-release, no patch
needed" opt-out label** — would surface the decision at review time without forcing the
wrong answer. Pairs naturally with B1.

---

## 4. Critique of the AI memory (`AI-shared/` + `MEMORY.md`)

Asked for directly. The short version: **the content is unusually good and the
navigation is broken.** These notes are far better than typical AI-generated project
docs — dated, specific, honest about what was and wasn't verified, and repeatedly
self-correcting ("Root cause, corrected", "an earlier pass … suspected X … turned out
wrong when actually traced"). That habit is worth protecting. The problems are all
structural.

### Problem 1: two competing index files, one silently stale

`README.md` (18 KB) and `INDEX.md` (8 KB) are both indexes of the same folder.
`INDEX.md` says *"Last reorganized: 2026-08-05"* and is missing at least eleven files
added since — `performance-profiling.md`, `password-hashing.md`, `demo-mode.md`,
`demo-data.md`, `Simple-setup-ESS.md`, `branding-epesi-casing.md`,
`recordbrowser-live-schema-changes.md`, `mail-account-encryption-and-gmail-oauth.md`,
`how-to-write-HELP.md`, `import-wizard-plan.md`, `Epesi-Google-Calendar-sync.md`.

A session that opens `INDEX.md` first — a reasonable guess — gets a confidently-worded
index that omits the most operationally useful file in the directory. `CLAUDE.md` points
to `README.md`, so the two disagree about which is canonical.

**Fix: delete `INDEX.md`.** Not merge — delete. `README.md` is the one `CLAUDE.md`
references and the one being maintained. Two indexes will drift again; one won't.

### Problem 2: `README.md` is an index that stopped being scannable

Entries have grown into paragraph-length summaries. The `performance-profiling.md` entry
is ~30 lines and reproduces much of the file's own content — including implementation
detail (`get_id`/`get_value`/`get_array`/`get_nodes`) that only makes sense after reading
the file. An index that must itself be read in full is no longer an index.

**Fix: two-tier structure.** One line per file (name + when you'd reach for it), with the
detail moved into the files themselves:

```markdown
- [performance-profiling.md](performance-profiling.md) — how to profile a slow page
  (MODULE_TIMES/SQL_TIMES), and the N+1 fixes already applied. Read before optimizing
  anything.
- [bug-patterns.md](bug-patterns.md) — already-fixed bugs whose root-cause shape is
  likely to recur. Read when a bug feels familiar.
```

Target: `README.md` under 100 lines. Everything currently in a long entry belongs in the
file's own header, where it will be maintained alongside the content it describes.

### Problem 3: two files have outgrown the format

`bug-patterns.md` is 152 KB and `MIGRATION_NOTES.md` is 243 KB — together 40% of the
folder. Neither has a table of contents. `bug-patterns.md` in particular is a flat
append-only log, so finding "has this shape been seen before?" means grepping and hoping
you guess the vocabulary the original author used.

`bug-patterns.md` is also the file whose *purpose* most needs random access — its whole
value proposition is recognition. **Fix:** add a top-of-file index of one-line pattern
names grouped by kind (JS/DOM, RecordBrowser, theming/CSS, session/caching, dates),
each linking to its section. Do this for `bug-patterns.md` first; `MIGRATION_NOTES.md` is
consulted more linearly and is less urgent.

### Problem 4: no distinction between "verified", "planned", and "stale"

The folder mixes, with no consistent marker:

- **Verified fact** — `password-hashing.md` (confirmed live the same day)
- **Approved but unimplemented plan** — `menu-search-plan.md`,
  `Epesi-Google-Calendar-sync.md`, `import-wizard-plan.md`,
  `mail-account-encryption-and-gmail-oauth.md`, `release-packaging-plan.md`
- **Implemented but unverified** — `generic-browser-responsive-tables.md` ("not yet
  visually verified or merged")
- **Silently wrong** — `INDEX.md`

Five of ~35 files are plans for things that don't exist. `README.md` does say so in
prose, but only if you read the entry. A session that opens
`Epesi-Google-Calendar-sync.md` directly finds a detailed design with no status marker at
the top.

**Fix: a mandatory status line as the first line of every file.**

```markdown
> **Status:** verified 2026-08-29 · applies to: modules/Utils/RecordBrowser
> **Status:** PLAN — not implemented as of 2026-08-28
> **Status:** implemented, NOT visually verified — mobile-gb branch
```

Cheap to add, cheap to maintain, and it fixes the single most expensive failure mode
(acting on a plan as though it were a description of the code).

### Problem 5: `CLAUDE.md` drifts because nothing checks it

Section B4 lists four errors. The cause is structural: `CLAUDE.md` states verifiable
facts (command names, file existence) with no mechanism to verify them.

**Fix:** move verifiable claims out of prose. `console.php list` is the authoritative
command list — `CLAUDE.md` should say "run `console.php list`" and show *one* example,
not enumerate commands that then rot. For the rest, a CI job that greps `CLAUDE.md` for
`console.php <cmd>` patterns and checks each against `console.php list` is ~15 lines of
shell and would have caught two of the four.

### Problem 6: `MEMORY.md` (the private per-machine memory) is in good shape

Worth saying explicitly since it was in scope. Twelve entries, each one line, each with a
real hook. Two observations:

- `feedback_concurrent-sessions-shared-env.md` is load-bearing for anything this session
  did (editing `data/config.php` while another session might be running) and is correctly
  flagged.
- `reference_symfony-var-dumper-vendored.md` is arguably project knowledge rather than
  private memory — it's the kind of fact `AI-shared/` exists for. Minor.

The one gap: nothing in `MEMORY.md` or `AI-shared/` records that **there is no CI**,
despite four documents asserting there is. That belongs in `AI-shared/` (it's a shared,
machine-independent fact), and it is the highest-value single line either store is
currently missing.

### Suggested rewrite, in order

1. Delete `INDEX.md`. (5 min, removes a live source of wrong information)
2. Add the status line to all ~35 files. (1 hour)
3. Correct `CLAUDE.md`'s four errors, and replace the command enumeration with a pointer
   to `console.php list`. (20 min)
4. Add a note — `AI-shared/ci-status.md`, or a section in `README.md` — stating plainly
   that no CI exists and what the three local commands are. (10 min)
5. Compress `README.md` to one line per file, pushing detail into file headers. (2 hours)
6. Add a pattern index to the top of `bug-patterns.md`. (1 hour)
7. Update `performance-profiling.md`'s Watchdog section to record that the fix was
   partial (A1.3). (10 min)

---

## 5. Sequenced plan

Ordered by (confidence × payoff) ÷ risk. Items marked ⚑ change behaviour for existing
installs and need the upgrade-gap check from `CLAUDE.md`.

### Tier 0 — do these first, they make everything else safer

| # | Item | Effort | Payoff |
|---|---|---|---|
| 0.1 | Add `.github/workflows/ci.yml` (lint + phpstan + rector) — **B1** | hours | every later change gets a safety net |
| 0.2 | Fix `CLAUDE.md`'s four factual errors — **B4** | 20 min | stops actively misleading new sessions |
| 0.3 | Delete `INDEX.md`, add status lines — **§4** | ~1 hour | stops acting-on-a-plan-as-fact |

### Tier 1 — high confidence, low risk, measurable

| # | Item | Effort | Measured payoff |
|---|---|---|---|
| 1.1 | Memoize `CRM_RoundcubeCommon` mail-account check — **A1.1** | ~30 min | −20 queries/grid |
| 1.2 | `prefetch_record_info()` for grid rows — **A1.2** | half day | −38 queries/grid |
| 1.3 | Finish the Watchdog N+1 fix — **A1.3** | ~2 hours | −19 queries/grid |
| 1.4 | Hoist poller due-checks above `load_modules()` — **A2.1** | ~2 hours | ~80 ms × most polls |
| 1.5 | Fix Roundcube's `Memcache`/`Memcached` check — **A6.3** ⚑ | ~1 hour | webmail session + IMAP cache |
| 1.6 | Convert remaining grid PNG icons to `bootstrap_icon()` — **A5** | 1 day | −~20 requests/page |

Tier 1 total: roughly **168 → 90 queries** on Contacts: Browse, ~20 fewer HTTP requests
per page, and most notification polls stop loading 95 modules to return nothing.

### Tier 2 — worth doing, needs measurement or design first

| # | Item | Effort | Note |
|---|---|---|---|
| 2.1 | **Profile one `process.php` with Xdebug/Excimer** — **A4** | 1 day | prerequisite for all RecordBrowser work; highest information yield on this list |
| 2.2 | Separate `CACHE_TYPE` from `MEMCACHE_SESSION_SERVER`; add Redis/Sqlite to the chain — **A6.1/A6.2** ⚑ | 1 day | portability, not speed here |
| 2.3 | Admin-toggleable profiling — **B3** | 1 day | removes the edit-and-forget hazard |
| 2.4 | Query-count regression test — **B2** | 1-2 days | keeps Tier 1 fixed |
| 2.5 | Investigate `FORCE_CACHE_COMMON_FILES`; split prod/dev — **A3** | 2 days | find out why it's off before proposing it |
| 2.6 | PHPStan classmap → level 1 — **B5** | 2 days | unlocks undefined-variable detection |
| 2.7 | Compress `README.md`, index `bug-patterns.md` — **§4** | half day | |

### Tier 3 — structural, own branch, own design note

| # | Item | Note |
|---|---|---|
| 3.1 | Coalesce the four pollers into one endpoint — **A2.2** | biggest single structural saving; touches four modules' JS |
| 3.2 | Lazy Common loading via a declarative hook manifest — **A3** | largest win, largest risk; changes a core invariant |
| 3.3 | `Utils_RecordBrowser` per-row optimization — **A4** | blocked on 2.1 |
| 3.4 | Consolidate the four asset endpoints — **A5** | DX more than speed |

---

## 6. Deliberately not proposed

Checked against `design-philosophy.md` and rejected, recorded so they don't get
re-derived:

- **Replacing Smarty 2.** Vendored and patched in place on purpose
  (`MIGRATION_NOTES.md` §17). Templates are not a measured bottleneck —
  `Utils_GenericBrowser`, which does the actual table rendering, costs 0.019 s of a
  0.283 s render. No performance case exists.
- **Replacing the AJAX-push architecture with a REST/JSON API + SPA framework.** This is
  the framework's identity, and it is what lets a module author declare a PHP array and
  get a working screen. A JSON API would push view logic onto every module developer —
  precisely what `design-philosophy.md` says the framework exists to prevent.
- **Making module authors write per-screen CSS to reduce bundle count.** Same reason. The
  per-module CSS loading in A5 has a real cost, but the fix is a framework-side prelude
  bundle, not "each module ships its own optimized stylesheet."
- **Upgrading jQuery 1.11.3.** Already an explicit user decision to defer
  (`TODO.md`, 2026-08-06). Not a performance item.
- **Installing APCu on this machine.** Measured as unnecessary — memcached is already
  active and fast here (A6). APCu belongs in the *fallback chain* for installs without
  memcached, which is item 2.2.
- **Eliminating `location()`'s discard-and-re-render.** `performance-profiling.md`
  already establishes this is inherent to how `process()` propagates mid-render state
  changes, that the one profiled trigger is fixed, and that a general fix is a
  framework-level rewrite. Revisit only with a *new* profiled trigger.

---

## 7. How these numbers were taken

Reproducible; ~20 minutes end to end.

1. `data/config.php`: set `MODULE_TIMES` and `SQL_TIMES` to `1`.
   **Set them back to `0` when done** — they are real per-request overhead, and this
   machine runs concurrent sessions against the same tree
   (`MEMORY.md` → `feedback_concurrent-sessions-shared-env`).
2. Browser: use `http://127.0.0.1/newsetup/`, not `https://localhost/...` — the 443 vhost
   here has an invalid certificate (`environment-gotchas.md`), and `127.0.0.1` sidesteps
   the http→https auto-upgrade on `localhost`.
3. Per-screen totals and the caller grouping, from the browser console — note the
   `<br>`-separated module rows (not `<div>`), and `textContent` rather than `innerText`
   so closed `<details>` content is included:

```js
const secs = [...document.querySelectorAll('#debug_content details.epesi-debug-section')];
const sql  = secs.find(s => s.querySelector('summary').textContent.startsWith('SQL'));
const g = {};
[...sql.querySelectorAll(':scope > div')].forEach(r => {
  const t = r.textContent.replace(/\s+/g, ' ');
  const caller = String(t.split(', ').pop()).replace(/^File '.*newsetup./, '').slice(0, 60);
  const m = t.match(/(\d\.\d{4})/);
  (g[caller] ??= { n: 0, t: 0 }).n++;
  g[caller].t += m ? parseFloat(m[1]) : 0;
});
console.log(Object.entries(g).sort((a, b) => b[1].n - a[1].n).slice(0, 15)
  .map(([k, v]) => `${v.n}x ${v.t.toFixed(4)}s ${k}`).join('\n'));
```

4. Front-end timings: `performance.getEntriesByType('resource')`, grouped by URL path with
   the query string stripped (the cache-buster makes every bundle URL unique).
5. Bootstrap timings: a scratch CLI script that mirrors `include.php`'s require order with
   `microtime(true)` between each step, plus `ModuleManager::load_modules()`. Written to
   the session scratchpad, never the repo (`README.md`'s tool-usage convention). Note CLI
   has `opcache.enable_cli=0` here, so those are cold-parse upper bounds. **Run it twice
   —** the first run on this machine is dominated by filesystem/AV noise; the first pass
   reported 8.8 ms per `Cache::set()` and 1.5 ms per trivial query, both of which fell by
   ~70× on the second run. Don't draw conclusions from a single cold run.
6. Active cache driver: reflection on `Cache::$cache_object` after bootstrap. Worth
   checking rather than inferring from `phpinfo()` — the driver actually in use here was
   not the one the extension list suggested.

---

## 8. Implementation log (2026-08-31)

Tier 0 and Tier 1 implemented the same day the plan was written. Details of the
performance work live in `performance-profiling.md` (the authoritative record); this is
the checklist view.

### Done

| # | Item | Result |
|---|---|---|
| 0.1 | CI workflow | `.github/workflows/ci.yml` — lint (882 files), PHPStan, advisory Rector, plus a docs check. All verified green locally first. |
| 0.2 | `CLAUDE.md` factual errors | Fixed; command list replaced with a pointer to `console.php list` so it cannot rot again. |
| 0.3 | `INDEX.md` + status lines | `INDEX.md` deleted; `> **Status:**` line added to all 37 remaining files; `README.md` rewritten as a real index. |
| 1.1 | Roundcube per-row query | 20 → 1 |
| 1.2 | `prefetch_record_info()` | 40 → 2, verified byte-identical across 240 records |
| 1.3 | Watchdog residual N+1 | 20 → 1, verified identical across all 500 subscriptions; also added the invalidation the 2026-08-28 cache never had |
| 1.4 | Notify poller early-out | ~80ms → ~11-13ms when not due |
| 1.5 | Roundcube `Memcache`/`Memcached` | now selects `memcached`; was silently falling back to `db` |
| 2.7 | `README.md` compression | done as part of 0.3 |

**Measured:** Contacts: Browse **168 → 91 queries**, SQL 0.0885s → 0.0462s, render
0.283s → 0.245s. Other grids landed at 72-81 queries.

### Two things the plan got wrong

1. **PHPStan and Rector were never installed.** `CLAUDE.md` documented
   `vendor/bin/phpstan` and `vendor/bin/rector`, and `phpstan.neon` / `rector*.php` /
   `phpstan-baseline.neon` all exist — but neither package was in `composer.json` and
   neither binary was in `vendor/bin`. Both documented commands had simply never worked.
   (Two AI-shared notes — `mail-account-encryption-and-gmail-oauth.md` and
   `Epesi-Google-Calendar-sync.md` — had each independently recorded "phpstan not
   installed in this environment" and moved on; nobody wired it up.)

   **They are installed in `tools/`, not the root project, and that detail matters.**
   The first attempt added them as root `require-dev`. That is wrong here for two
   reasons found by trying it: `vendor/` is *committed* to this repo (3,248 tracked
   files, so a deployment needs no composer run), and the two packages are **69 MB /
   ~3,100 files** — they would have more than half again the committed tree and shipped
   in every release zip. Gitignoring them instead looked fine until `composer` put their
   bootstraps into `vendor/composer/autoload_files.php`, which `vendor/autoload.php`
   `require`s on **every request** — so a fresh clone would have fatalled on every page
   load. Regenerating with `--no-dev` fixed that but then broke Rector, which needs
   those dev autoload entries to resolve its scoped PHPStan classes.

   The resolution is `tools/composer.json`, an isolated project with its own
   `tools/vendor/` (gitignored). Root `composer.json`, `composer.lock` and `vendor/` are
   left **completely untouched**. Run `composer install -d tools`, then
   `tools/vendor/bin/phpstan` / `tools/vendor/bin/rector`. Worth knowing before anyone
   "tidies up" by folding them back into the root composer.json.

   With the tools working: PHPStan reported 21 errors, 18 of them inside gitignored
   `modules/Premium/` — which CI can never see — so `modules/Premium/*` and
   `modules/Custom/*` went into `excludePaths` (and both Rector configs), making a local
   run reproduce CI exactly. That was the mismatch `phpstan.neon`'s own comment already
   complained about. Baseline regenerated: 211 findings absorbed, **0 new**. Rector
   applies **zero** actual rules — all 10 files it would touch are whitespace-only
   re-prints, which is why its job is advisory.

2. **Item 1.6's premise was wrong.** The grids' action icons are *not* PNGs awaiting
   conversion — under adminltedark they are already Bootstrap Icons glyphs, drawn by CSS
   `::before` and selected via `[src*=...]` on an `<img>` that is then `display:none`.
   The real defect is bigger: **240 hidden `<img>` elements per grid page, every one
   actually downloaded** (confirmed live: `complete: true, naturalWidth: 14`), none ever
   shown. Removing the `<img>` breaks every one of those CSS selectors, so it is a
   coordinated PHP + CSS change to a block whose own comments record two prior attempts
   that were gotten wrong and had to be fixed after a user report. **Since done** —
   `10b3f45da` + `ab9b408d3`, 240 hidden `<img>` → 0 on both grid and record view. It
   broke exactly the way the warning predicted first (`isCoreAction()` read the removed
   `<img>`'s `src` and every row action fell through to the kebab menu); see
   `performance-profiling.md`'s "grep for absence assertions" entry.

## 9. Second implementation pass (2026-08-31, later)

### 2.1 done — and it was one line

The plan asked for "one Xdebug or Excimer profile … to turn *0.10s somewhere in
RecordBrowser* into a ranked function list", and called it the highest-information item
on the list. Done, with two corrections to the plan's assumptions:

- **There is no profiler extension on this machine** — no Xdebug, no Excimer, no
  tideways, and none shipped in `php/ext/`. The plan assumed one was available. Two
  pure-PHP substitutes also don't apply here: `declare(ticks)` is compile-time per-file
  so it cannot sample across a call tree, and `pcntl` doesn't exist on Windows. What
  worked was temporary `microtime()` probes aggregated by label — coarse phases first,
  then drill. Two rounds. Full method in `performance-profiling.md`.
- **The answer is `get_html_record_info()`, at 50% of the row loop** — it built a fresh
  `HTMLPurifier` per row, and HTMLPurifier builds its definitions lazily on first
  `purify()`, so every row rebuilt the whole definition set. Memoizing it took the row
  loop **0.0753s → 0.0519s (−31%)**. The same shape existed at five sites; all five now
  memoize. Verified byte-identical output across all four configs including hostile
  inputs.

Worth recording that **every suspect A4 named was wrong.** Per-row `get_access()`,
`get_template_file()` per icon, tooltip building and `get_val()` together account for 6%
of the row loop; the per-row access checks A4 singled out cost 0.0009s per 20 rows.
Acting on that list without profiling would have been pure wasted work — which is the
argument for 2.1 having been ranked first, and the reason to keep it ranked first for
`Utils_GenericBrowser` if that ever comes up.

### Still open

- **1.2 (partial), the item §2 calls A1.4** — `CRM_ContactsCommon::get_company()`,
  19 queries/page. Needs the same prefetch as the rest of 1.2; the linked-company ids are
  less cleanly available before the loop. (This bullet was numbered "1.4" when first
  written, which is the poller early-out — a slip; 1.4 is done.) Note this is now a
  *query-count* item, not a wall-clock one: SQL is well under a third of the render and
  the row loop's remaining non-SQL cost has no single hotspot left.
- **2.2-2.6, Tier 3** — untouched, as scoped.
- Shoutbox and Messenger pollers still pay the full bootstrap; 1.4's early-out is the
  model to copy.
- **Open decision, not a task:** whether `Dev-Tutorial.md` should reach module
  developers. `AI-shared/` no longer ships in release zips (`4e74778d7`), which settled
  the accidental half of that question but not the deliberate half.

### Note for whoever picks this up

Tier 0/Tier 1 and the icon work are committed (`3a4744919`, `10b3f45da`, `ab9b408d3`).
The §9 purifier changes touch six files across `Utils_RecordBrowser`, `Utils_Tooltip`,
`Utils_SafeHtml`, `CRM_Calendar` and `CRM_PhoneCall`. No patch file is needed for any of
it — these are code fixes, no stored or seed data changes.

---

## 10. Third implementation pass (2026-08-31, later still): all of Tier 2

Tier 2 is done, plus the A1.4 prefetch left over from Tier 1 and the Messenger poller.
Tier 3 is untouched, deliberately — see "What is left" below.

### Shipped

| # | Item | Result |
|---|---|---|
| 1.2 rest (A1.4) | Linked-record prefetch | `Utils_RecordBrowserCommon::prefetch_records()`, called by the grid for every select/multiselect column pointing at a recordset. Not Contacts-specific: any module with a linked column gets it. 19 queries → 1 per linked table. |
| Messenger poller | Early-out above `load_modules()` | Same shape as 1.4's — one existence query instead of a 95-module bootstrap. |
| 2.2 | `CACHE_TYPE` / `CACHE_SERVER` | Cache driver no longer keyed off `MEMCACHE_SESSION_SERVER`. Redis/Predis/Sqlite added to the chain; a pinned driver with a missing extension degrades instead of fatalling. `auto` reproduces the old behaviour exactly. |
| 2.3 | Admin-toggleable profiling | `include/profiling.php`. A super-admin switches either debug panel on **for their own session** from Administration → PHP & SQL Errors. The config constants stay as the install-wide default. |
| 2.4 | Query-count regression guard | `php console.php dev:query:budget` — 5 scenarios, all flat. |
| 2.5 | `FORCE_CACHE_COMMON_FILES` | Closed as already-answered — see the corrections below. |
| 2.6 | PHPStan level 1 | Level raised, `console/` added to the analysed paths, baseline regenerated (217 entries / 413 findings). One real bug found and fixed on the way (`module:list` reported the *previous* row's state for any unrecognised state). |
| §4.6 | `bug-patterns.md` index | 51 patterns, grouped into 10 categories, every anchor verified to resolve. |

### Four things the plan got wrong (and a fifth, below, that was mine)

1. **Item 2.5's premise was already answered, two weeks before the plan was written.**
   2.5 asks to "find out why `FORCE_CACHE_COMMON_FILES` is off before proposing it".
   `MIGRATION_NOTES.md` §66 (2026-08-13) answers it in full: the generator was broken by
   the no-closing-tag convention, has since been fixed (`0ffdd53a6`, `9e66c598b`), and was
   verified working live with the flag on. What actually remains is a *decision* — whether
   to default it on in production — weighed against the developer hazard already recorded
   in `environment-gotchas.md`. Not an investigation. Nothing shipped for it here.

2. **§4's suggested-rewrite item 4 is moot.** It asks for a note "stating plainly that no
   CI exists". CI exists (item 0.1 built it) and `README.md` has a Continuous integration
   section. Struck.

3. **"Shoutbox and Messenger pollers still pay the full bootstrap; 1.4's early-out is the
   model to copy" — only half true.** Messenger can copy it and now does: it polls every
   180s and almost always has nothing to say, so an existence check answers it. Shoutbox
   **cannot**. Its response is never empty — it re-renders the last 20 messages on every
   poll, and the client does `jQuery(...).load()`, which would blank the board on an empty
   response. Making Shoutbox cheap means either caching the rendered HTML against a
   message-set fingerprint (invalidation has to cover deletes, the admin flag and the
   per-user `to_user_login_id` split) or a JS change so the client tolerates a "nothing
   new" reply. Both are Tier 3 in size, and the second is really part of 3.1. Left alone.

4. **A fixed query budget was the wrong shape for 2.4, and the first version of the
   command shipped it anyway.** Cold scenarios legitimately include one-off schema reads —
   RecordBrowser's `_field`/`_callback` lookups, Watchdog's category id — that have
   nothing to do with row count. Every scenario came in "over budget" on the first run,
   and all four overages were those. A budget loose enough to admit them is loose enough
   to hide a real per-row query on a small fixture. The command now measures **slope**:
   each scenario runs over 5 records and over 25, after a discarded warm-up, and the
   assertion is that the count does not grow. Verified by injecting the regression — with
   both prefetch functions stubbed out it reports `5 rows: 10 queries / 25 rows: 50
   queries` and exits non-zero.

### Verified in the running app

Logged in, both grids and the toggle exercised end to end (2026-08-31, Edge via Playwright):

- **Contacts: Browse is now 73 queries** — 168 originally, 91 after the previous pass. The
  SQL panel shows exactly one `SELECT * FROM company_data_1 WHERE id IN (?,?,…)` with 19
  bound placeholders and zero per-row `get_company()` calls. Company names render on every
  row; Tasks, Meetings and Companies grids all render normally. Zero console errors or
  warnings across the whole session.
- **The session toggle works in both directions** and is independent per flag — SQL on with
  modules off shows only the SQL panel; both on shows both; both off hides the debug bar
  entirely. `data/config.php` was never touched, which was the whole point.

### A fifth thing the plan got wrong — this one was mine

Turning `MODULE_TIMES` from a `define()` into a mutable `Profiling::$modules` broke the
paired timing sites. A constant cannot change between `if (FLAG) $time = microtime(true)`
and `if (FLAG) … - $time`, so the pairing was implicit and free; a variable can, and the
first thing anyone does with the new switch is turn it on *from a screen rendered inside
the outermost pair*. `Undefined variable $time`, `include/module.php:1125` — caught in the
error log during the browser check, not by any of the CLI verification, because nothing on
the CLI flips the flag mid-request.

It mattered more than a stray warning: under `REPORT_ALL_ERRORS` the first warning of a
request blanks that module's whole output, so on such an install ticking "show module
render times" would have blanked the screen containing the tick box.

Fixed by making `set_session_override()` write the session and nothing else — the new value
is picked up at the next request's bootstrap, before anything renders, and the form says so
— plus reading the flag once per pair into a local. Re-ran the exact sequence afterwards:
no new log entries. Full write-up in `bug-patterns.md`; the general rule is that converting
a constant to a runtime flag means grepping for uses that are *paired across a span of
work*, which was 3 of 47 sites here.

### One finding worth more than the item that produced it

`prefetch_records()` was written to be byte-identical to `get_record()`, and a
`serialize()`-level comparison across 952 records said it was not: integer columns came
back as `1` through the per-id path and `'1'` through the batch. The cause is not GetRow
vs GetAll — it is **bound vs unbound parameters**. ADOdb on mysqli runs a bound query as a
prepared statement, which returns native PHP ints for integer columns; an unbound query
returns every column as a string. `get_record()` binds (`WHERE id=%d`), so any batch
replacement that interpolates its `IN (...)` list silently changes the type of every
integer column it caches.

That is a trap for exactly the work this plan asks for — replacing per-row queries with
batched ones — so it is written up in `bug-patterns.md`. It had already been shipped once:
`prefetch_record_info()` (item 1.2, previous pass) interpolated its `IN` list, so it was
caching string `created_by` where `get_record_info()` cached int. Both bind now, and both
were re-verified byte-identical (952 and 468 records, both `htmlspecialchars` modes,
including ids that do not exist).

The lesson generalises past this codebase: **"verified identical" is only worth what the
comparison operator was.** The previous pass's identical claim was true under `==` and
false under `===`.

### What is left

- **Tier 3 (3.1–3.4)** — untouched by design. The plan scopes these as "structural, own
  branch, own design note", and 3.2 as "largest win, largest risk; changes a core
  invariant". Shipping them inside a Tier 2 sweep would be the exact mistake the tiering
  exists to prevent. 3.3 is additionally *answered rather than pending*: §9's profile found
  no single hotspot left in the row loop, so there is no target to optimise until a new
  profile finds one.
- **2.5 is now closed — removed, on measurement.** ~~The prod/dev default decision for
  `FORCE_CACHE_COMMON_FILES`.~~ Same day (2026-08-31): flipped on, broke the webmail
  within the hour, fixed, flipped back off once actually measured, then removed
  entirely — `ModuleManager`'s bundle branch, `create_common_cache()`, both constants,
  every call site. The flag was worth ~3.5 ms per request with opcache and nothing
  without it (roughly 1% of a page render), against a stale-`Common_0.php` trap with no
  warning and two ways for one compilation unit to fatal the whole app. Full numbers,
  what was removed, and what to do if this is proposed again: `deliberate-removals.md`'s
  "`FORCE_CACHE_COMMON_FILES` common-class bundle" entry.
  **The process lesson is the more valuable half: it shipped on a plausible argument
  from this plan's own §A3 framing, was never measured, and broke the webmail within
  the hour.** The value moved three times in one day (`8fa13be19` 1 → `d9283c47a` 0 →
  `5e3ed0378` 1 → `df9a0cf82` 0 → removed) before anyone asked what it was actually
  worth.
- **PHPStan level 2** — level 1 is in. Level 2 starts checking unknown methods on typed
  expressions, which the missing autoload makes noisy; it wants its own pass, not a config
  bump.
- **Whole-page query counts are still browser-only.** `dev:query:budget` guards the
  Common-layer invariants, not page totals — `Epesi::process()` needs browser session
  state to render a real screen. Section 7's procedure still applies, minus the
  `config.php` edit it used to require.
- **Decision closed (2026-08-31):** `Dev-Tutorial.md` reaches module developers through the
  git repo, and that is enough. Developers clone; the zipped distribution exists for
  SourceForge and the Softaculous autoinstaller, whose audience is end users installing the
  app, not people writing modules. `CreateDistCommand.php`'s `^AI-shared(/|$)` exclusion is
  therefore correct as-is and needs no counterpart. **Corollary worth remembering:** a
  released install has no `AI-shared/` on disk, so anything shipped to end users must not
  point at it — the generated `data/config.php` did, and was reworded here.

### Note for whoever picks this up

The §10 changes touch `include/` (`cache.php`, `config.php`, `database.php`, `epesi.php`,
`module.php`, new `profiling.php`), `Utils_RecordBrowser`, `Base_Error`,
`Utils_Messenger`, `console/`, `admin/modules/ConfigInfo.php`, `setup.php`, `phpstan.neon`
and the CI docs-check regex (it only matched `[a-z:]`, so a hyphenated command name would
have been silently truncated and then reported as missing).

**No patch file is needed.** Everything here is code, config *defaults* that reproduce the
previous behaviour when unset, or documentation — nothing changes stored or seed data. The
one thing an existing install may want after upgrading is unrelated and already recorded:
§66's stale `temp/data/cache/common.php`.

---

## 11. Tier 3, first pass (2026-08-31, branch `optimization`)

Measured before building, per §9's lesson. The headline finding is that **3.1 no longer
earns its risk**, and the reason is that Tier 1 already took its payoff.

### The numbers this pass rests on

Web SAPI (opcache on), steady state, this machine. CLI numbers are useless here — opcache
is off for CLI, which inflates every file-loading figure by 4-5x. Take these under Apache
or not at all.

| Phase | Cost |
|---|---|
| `include.php` bootstrap — what an early-out poll pays | ~6.5 ms |
| `load_modules()` | ~13.5 ms (bundle) / ~17 ms (per-module) |
| Full poll (bootstrap + `load_modules()`) | ~20 ms |
| `load_modules()` split: file loading | ~12 ms |
| `load_modules()` split: registration + bookkeeping | ~2.2 ms |

Two things follow that the plan did not know:

- **`load_modules()` is class-declaration *execution*, not parsing.** opcache caches
  compilation, not binding, so 95 files' worth of class declarations still cost ~12 ms
  every request. That is why `FORCE_CACHE_COMMON_FILES` — 71 `require`s down to 1 — buys
  only ~3 ms rather than the order of magnitude A3 implies. 3.2's target is real, but it
  is the *declaration* cost, and any design that still declares all 95 classes will not
  move it.
- **A2's remaining cost is nearly gone.** §8 measured these pollers at ~80 ms each because
  every poll ran the full bootstrap. With 1.4's and §10's early-outs in place the common
  path is ~6.5 ms.

### 3.1 (coalesce the four pollers): not worth doing, at these numbers

Coalescing removes *duplicate bootstraps*, not work. Notify polls at 30 s and Messenger at
180 s, so folding Messenger into Notify eliminates ~20 requests/hour/user × ~6.5 ms ≈
**0.14 s/hour/user**. That is the entire prize, against a refactor that moves four modules'
JS onto a shared dispatcher and has to reconcile four different response types (Notify
JSON, Messenger executable JS, Shoutbox HTML into a `.load()` target, indexer a
self-rescheduling `setTimeout`), three different intervals and two different auth shapes
(the indexer needs `CID` + a session token and elevates to SA).

Recommend striking 3.1 unless it is re-justified by a measurement, or unless it is wanted
for a non-performance reason (one dispatcher is a better place to put policy than four
copies — which is exactly what the item below ran into).

### What shipped instead: A2.3, which was never given a tier number

A2 listed three fixes. A2.1 became 1.4 and shipped; A2.2 became 3.1; **A2.3 — back off when
the tab is hidden — was never placed in any tier**, despite being the cheapest item in the
section. It is also, now, the largest remaining saving in it: a hidden tab keeps polling,
and browsers throttle background timers without stopping them. A CRM user with five tabs
open runs five sets of pollers; gating on `document.hidden` takes that to one.

Shipped for the three *status* pollers, each with a `visibilitychange` listener that fires
one refresh on the way back so a returning tab does not wait out its interval:

| Poller | Interval | Note |
|---|---|---|
| `Base_Notify` (`js/main.js`) | 30 s | A hidden tab has nobody to show a notification to. |
| `Utils_Messenger` | 180 s | A hidden tab cannot show the alarm's confirm dialog. |
| `Apps_Shoutbox` | 10-30 s | **The one that needed it most** — its response is never empty (it re-renders the last 20 messages), so it is the one poller with no server-side early-out available, and it has the fastest timer. This is also the answer to §10's "Shoutbox cannot copy 1.4's early-out": it cannot, but it can decline to ask. |

`Utils/RecordBrowser/indexer.php` is deliberately **not** gated. It is not a status poller —
it indexes 30 records per run and reschedules itself. Gating it would pause real work
whenever every tab happened to be hidden. The line drawn is: gate pollers that ask "is
there anything for me?", never work queues.

Verified: `php -l` on both PHP files, and the *generated* JavaScript for all three (both
Shoutbox `$big` variants) extracted and parsed with `node --check` — the concatenation is
where this shape of code usually breaks, not the PHP.

Not measurable from the CLI: the saving is per hidden tab, so it wants the §7 browser
procedure with several tabs open to quantify.

### Still open after this pass

- **3.2** — unchanged in value, better understood: the target is ~12 ms of class-declaration
  execution per request, and the bundle is not a substitute for it.
- **3.4** — untouched.
- **3.1** — recommend striking; see above.
- **3.3** — already answered in §10 (no hotspot left to aim at).
