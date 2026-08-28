# Performance profiling and N+1 query patterns

## How to profile a slow page in this app

Browser devtools' Network tab is misleading here: this app's own `serve.php`
(the minified JS/CSS bundle server) shows up as the "Initiator" for every
`process.php`/`ajax.php` XHR it fires, because that's where the client-side
`Epesi.load_js()`/callback-firing code physically lives — it does **not**
mean `serve.php` itself is slow. Check the actual row's own time; `serve.php`
requests are near-instant once cached (0 transferred, ~0ms) and are almost
never the real bottleneck. The real cost is in `process.php`/`ajax.php`
itself — the request that calls `Epesi::process()` and does the actual
module-tree render.

Don't guess which module/query is slow — this codebase has a built-in
profiler, just gated behind two commented-out constants in `data/config.php`:

```php
define('MODULE_TIMES',1);
define('SQL_TIMES',1);
```

Once both are on, every `process.php` response appends to a debug panel
(`#debug_content`, force-shown via JS — see "Debug/error console redesign"
below for its current UI) listing:
- a `Page renderered in Xs` total, always visible, followed by a collapsed
  "Modules load times (N, Xs total)" `<details>` section: every rendered
  module's own wall-clock time (`include/epesi.php`'s `MODULE_TIMES` block) —
  nested by module path, so a slow leaf module's cost rolls up through every
  ancestor's own total
- a collapsed "SQL queries (N, Xs total)" `<details>` section: every SQL
  query run (`include/database.php`'s `SQL_TIMES` instrumentation in
  `DB::Execute`/`GetOne`/`GetAssoc`/etc.), with args (rendered as an
  interactive collapsible tree via `symfony/var-dumper`'s `HtmlDumper` —
  see [[symfony-var-dumper-vendored]] — not flat `var_export()` text),
  timing, and the calling function/file/line

This splits "is it the database, or is it PHP?" immediately — e.g. on
Companies: Browse (see below), only 42% of total render time was SQL; the
rest was per-row PHP work in `Utils_RecordBrowser`/`Utils_GenericBrowser`.
From the browser console, the panel's totals can be pulled without dumping
the whole (often huge) debug blob — use `textContent`, not `innerText`:
the module-times/SQL-queries sections collapse via native `<details>`
(closed by default), and closed `<details>` content is excluded from
`innerText` (it's genuinely not rendered) but still present in
`textContent`:

```js
document.getElementById('debug').textContent
  .split('\n').find(l => l.startsWith('Page renderered'))
```

**Turn both flags back off when done** — they're real per-request overhead
(building the debug HTML, `json_encode`-ing query args) not meant to run in
normal operation.

**The debug panel can silently undercount a request** — see "Known but not
fixed: initial-load `process()` renders the whole module tree twice" below.
If a page's DevTools `process.php` time is much larger than what the debug
panel reports, don't assume the gap is network/Apache overhead; compare
DevTools' TTFB (`responseStart - requestStart` on the `process.php` resource
timing entry) against the debug panel's own `Page renderered in Xs` first.

To find *why* a specific query runs N times, group the debug panel's query
lines by their "Called by" function+file+line rather than reading them
one-by-one — that's what turns "234 queries" into "40 of them are
`Utils_WatchdogCommon::user_check_if_notified`, one per visible row."

## Debug/error console redesign (2026-08-28)

`#debug_content` used to render in normal document flow with no
positioning of its own — under the AdminLTE theme (the only real theme in
this app today, see `adminlte-theme.md`) that put it visually underneath
the fixed sidebar, and an actual runtime error (as opposed to just
`MODULE_TIMES`/`SQL_TIMES` output) additionally wiped the rest of the
response via `Epesi::discard()`, so the whole page went blank except one
undifferentiated `<pre>` wall of text. Redesigned into a slim bar pinned to
the bottom of the viewport (`position:fixed`, high `z-index`, collapsed to
just a one-line summary + error count by default; click to expand into a
scrollable panel, `✕` to dismiss) — see `theme/index.tpl` (bar markup +
toggle JS, wrapped in `{literal}`, see `bug-patterns.md`'s entry of this
same date for why that matters), `include/error.php` (each error now a
card: colored severity badge, headline message + file:line up front, full
stack trace collapsed behind "Stack trace (N)"), and `include/epesi.php`
(the `MODULE_TIMES`/`SQL_TIMES` restructuring described above).

**Gotcha for `modules/Base/Box` specifically**: its per-theme CSS files
don't cascade — `theme_adminltedark/default.css` is a full standalone
replacement for `theme/default.css`, not a delta (see that file's own
header comment). A style meant to apply regardless of theme (like this
bar) has to be duplicated into both files; adding it to only `theme/
default.css` silently never applies under the real running theme. Verified
by intentionally triggering a live error and screenshotting the actual
rendered page rather than trusting the CSS alone.

## Fixed: two N+1 patterns on RecordBrowser grids (2026-08-28)

Profiling **Companies: Browse** (a representative `Utils_RecordBrowser` grid,
~40 rows/page) found 234 queries taking 129.5ms of a 310ms total render, with
78% of the whole page (241ms) inside the `Utils_RecordBrowser|company`
module. Two call sites accounted for most of the query count:

1. **`Utils_WatchdogCommon::user_check_if_notified($user_id, $category, $id)`**
   — called once per row by `CRM_ContactsCommon::display_contacts_with_notification()`
   (a grid column formatter showing a "does this referenced contact have
   unread updates on this record" icon) with a *different* `$id` (the row's
   own record id) every time, so no plain per-tuple memoization could help:
   40 rows meant 40 separate `SELECT ... WHERE user_id=? AND internal_id=?
   AND category_id=?` queries even though most rows share only a handful of
   distinct referenced users.

2. **`Utils_CommonDataCommon::get_id()`/`get_value()`** — already had a
   `static $cache`, but it only memoized a path *after* resolving it, one DB
   round-trip per unresolved path segment. A grid with a per-row
   CommonData-backed field (category/status dropdowns etc.) meant every row's
   distinct value was a fresh cache miss: 42 `get_id` calls + 25 `get_value`
   calls in one page render.

**Fix shape** (both in the same commit): keyed the cache one level broader
than the thing that varies per row, and load it in one bulk query instead of
resolving it incrementally:

- Watchdog: cache by `(category_id, user_id)` → the user's whole subscription
  map (`internal_id => last_seen_event`) for that category, fetched once per
  distinct user instead of once per `(user, category, record)` triple. See
  `Utils_WatchdogCommon::_user_last_seen()` in
  `modules/Utils/Watchdog/WatchdogCommon_0.php`.
- CommonData: since `utils_commondata_tree` is small, slowly-changing
  reference data, load the *entire* table once per request
  (`Utils_CommonDataCommon::load_tree()` in
  `modules/Utils/CommonData/CommonDataCommon_0.php`) into shared class-level
  static indexes (`parent_id+akey -> id`, `id -> value`, and
  `parent_id+akey -> full row` for the listing methods below), and resolve
  every path/value from memory afterwards. The existing per-segment DB
  fallback is left in place (untouched) for anything created after the bulk
  snapshot — self-healing, so nothing had to change about
  `new_id()`/`set_value()`/etc. `get_id()`'s pre-existing `$clear_cache` param
  (used by `remove()`, which restructures the tree) now also resets the
  "loaded" flag, so a same-request write still forces a fresh reload on the
  next read.

**Result** (Companies: Browse, re-tested twice for stability): 234 → 157
queries, SQL time 129.5ms → ~63ms, total render 310ms → ~230ms.

**Follow-up (same day): `get_array()`/`get_nodes()` extended too.** These
weren't part of the profiled hot path above (each already had its own
effective `static $cache`, keyed by name/order/root+keys — no N+1 shape was
found for them on Companies: Browse), but were routed through the same
`load_tree()` bulk snapshot for consistency, since the infrastructure was
already there. The one real behavior change: `get_array()` used to let SQL do
`ORDER BY akey|position|value ASC`; now it `usort()`s the in-memory rows in
PHP instead, since there's no per-call query left to attach an `ORDER BY` to.
Verified live against real data (not just Companies: Browse) — the CommonData
admin browse screen (Administration → CommonData), both the root listing (8
items) and drilling into `Countries` (~250 items, position-ordered) — matched
pre-change output exactly, no warnings logged. Worth knowing if a future
`ORDER BY`-sensitive CommonData collation edge case ever comes up (accented
characters, locale-specific sort): the comparison is now plain PHP `<=>`
(byte-order for strings), not the DB's collation - a difference that hasn't
mattered for the plain-ASCII keys/labels this tree actually holds so far.

**How to apply**: this is the general fix shape for an N+1 found via the
profiler above in this codebase — a per-row grid formatter calling a
`FooCommon::get_thing($varies_per_row_id, ...)` helper. Look for a broader,
*shared* key across rows (a category id, a user id, a parent id — something
with far fewer distinct values than the row count) to batch on, and prefer
loading that whole slice in one query over trying to memoize the exact
per-row tuple, which never repeats. All the fixes here are deliberately
request-scoped only (a `static`/class-static cache, reset every request) —
not a cross-request cache (e.g. via `include/cache.php`'s `Cache::` class) —
specifically to avoid needing to find and instrument every mutation call
site with invalidation. A cross-request cache would help more (especially
for CommonData, which barely changes across requests at all) but wasn't
attempted here for that reason; revisit if request-scoped caching alone
stops being enough.

## Known but not fixed: initial-load `process()` renders the whole module tree twice (2026-08-29)

DevTools showed a plain post-login `/newsetup` load's `process.php` taking
613ms, while the debug panel's own `Page renderered in Xs` said ~0.14s for
the same request — a ~4x gap the panel itself gave no hint of. Traced by
temporarily adding response headers (`X-Process-Call-N-Time`,
`X-Before-Go-Time`/`X-After-Go-Time` around `include/epesi.php`'s
`process()`/`go()` calls, `X-Total-Server-Time` in `process.php` — added,
measured, reverted; none of this is in the codebase) to timestamp against
`$_SERVER['REQUEST_TIME_FLOAT']` at each stage. Confirmed only on a fresh
session/tab's first load (bare URL, no module targeted yet) — a normal
in-app click (sidebar → Contacts: Browse) does exactly one `process()` call,
no gap.

**What's happening**: `Epesi::process()` (`include/epesi.php:354`) renders
the *entire* module tree once via `self::go($root)` (~257ms on Dashboard) to
figure out what an empty/unresolved URL should even show. Partway through,
some module calls `location()` (`include/misc.php:52`) — here,
`Base_Box::push_main()` (`modules/Base/Box/Box_0.php:238`) resolving "no
main module yet" into the real default/last-visited screen. `location()` is
a generic side-effect accumulator used from 60+ call sites app-wide
(RecordBrowser filters, Wizard steps, history back/forward, Box push/pop,
etc.) — a module only decides to request a redirect *during its own render*,
so `process()` has no way to know one is coming without executing that first
pass. When it sees `location()` was called, it wipes `self::$content`
(discarding the first render entirely — none of its `MODULE_TIMES`/SQL
stats survive) and calls `self::process()` again for the resolved
destination (~141ms). **Only the second call's numbers ever reach the debug
panel** — `$debug`/`self::$content` are fresh per top-level call, and the
first, more expensive pass leaves no trace unless you go looking for it like
this.

**Not fixed**: `location()`'s "decide mid-render, discover after" design is
load-bearing for unrelated features across the whole app (see the call-site
list above) — resolving destinations without a render pass would be a
framework-level rewrite, not a scoped fix. Logged here so a future session
profiling initial-load latency sees the real cost (roughly 2x the visible
render) and doesn't have to re-derive this trace from scratch. Revisit only
if initial-load latency becomes a specific priority, and start from
`Base_Box::push_main()`/`location()` rather than re-profiling from zero.

**How to apply**: when a slow request is a bare/root URL load rather than an
in-app click, don't trust the debug panel's numbers as the whole story —
they reflect only the *last* `process()` call in that response. Compare
against DevTools' TTFB for the same request (see the profiling guide above)
to see if there's an unexplained gap, and if so, re-add temporary
`REQUEST_TIME_FLOAT`-relative response headers around `process()`/`go()`
(same technique as here) to confirm/quantify a repeat render before assuming
it's environment noise (this machine commonly runs several concurrent
Claude Code sessions — see [[concurrent-sessions-shared-env]] — which is a
real potential confound but wasn't the cause here: PHP-side timings summed
to the observed gap almost exactly).

## Known but not fixed: Simple Setup / EpesiStore hits an external server

`Base_Setup::simple_setup()`'s `add_store_products()` (see
`modules/Base/Setup/Setup_0.php`) calls
`Base_EpesiStoreCommon::get_modules_all_available()`, which round-trips to
the real `ess.epe.si` store server for the module catalog. On this dev
machine that one call accounted for essentially the entire ~600-700ms render
time of Administration: Modules Administration & Store (confirmed by profiling:
DB time was ~17ms of that, and the outer per-module loop over all 145
installed/available modules was ~3ms — the remaining ~580ms had no other
explanation). Not fixed, because that screen is rarely visited compared to
everyday grids like Companies/Contacts — logged here so a future session
doesn't have to re-derive the same trace if it comes up again (e.g. slow
Store screen complaints, or wanting to cache the store catalog response).
