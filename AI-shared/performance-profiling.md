# Performance profiling and N+1 query patterns

> **Status:** REFERENCE - how to profile a slow page, plus the N+1 fixes already applied. Read before optimizing anything.

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

  > **Correction (2026-08-31): this fix was only half of the N+1.**
  > `user_check_if_notified()` runs *two* per-row queries, and only the
  > subscription lookup was batched here. The other one —
  > `SELECT MAX(id) FROM utils_watchdog_event WHERE internal_id=? AND
  > category_id=?` at what is now `WatchdogCommon_0.php:284` — kept running once
  > per subscribed row, and was still 20 of the 168 queries on a 20-row
  > Contacts: Browse three months later. Fixed 2026-08-31 by
  > `_last_event()`, which batches it over the internal_id set the subscription
  > map already provides. **The lesson worth carrying:** when a profiler
  > attributes N queries to one function, check whether that function issues
  > more than one query per call before declaring the N+1 closed — grouping by
  > caller file:line hides a second query inside the same callee.
  >
  > The same pass also added the invalidation this cache never had:
  > `user_subscribe()`/`user_unsubscribe()`/`user_notified()`/
  > `user_purge_notifications()`/`unregister_category()` all mutate
  > `utils_watchdog_subscription` and none of them cleared the snapshot, so a
  > same-request subscribe-then-read returned stale data. See
  > `_clear_subscription_cache()`.
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

## Fixed: `Utils_RecordBrowserCommon::get_record()`/`get_record_info()` had no cache at all (2026-08-29)

Profiling the post-login **Dashboard** (three `Utils_RecordBrowser` grid widgets:
Tasks, PhoneCall, Calendar) found 348 queries / 155.5ms SQL time out of a
169ms total render — 92% of the render was SQL. Unlike the Watchdog/
CommonData case above, these two weren't a partial-cache-miss-per-row
problem — they had **zero caching of any kind**, every single call a fresh
`DB::GetRow()`:

- `get_record($tab, $id)` — 101 calls, ~40ms. Called every time a grid
  renders a linked-record label (`create_default_linked_label()`,
  `record_link_open_tag()`), so the same Contact/Company showing up as
  "linked to" on several Task/PhoneCall/Meeting rows was re-fetched from
  scratch each time.
- `get_record_info($tab, $id)` — 56 calls (28 calls × 2 queries each:
  `created_on/by` from `_data_1`, `edited_on/by` from `_edit_history`).
  Called **once per visible grid row** by `RecordBrowser_0.php:906`'s
  `add_info()` — the hover-info tooltip icon shown on every row.

Together: 157 of 348 queries (45%), ~77ms (50% of SQL time), from two
functions that already had a proven fix shape sitting two methods above
`get_record()` in the same file — `CRM_ContactsCommon::get_contact()`/
`get_company()` (`ContactsCommon_0.php:112-123`) have used a plain
`static $cache` keyed by id for years.

**Fix**: class-level `private static $record_cache` / `$record_info_cache`
in `Utils_RecordBrowserCommon`, keyed `"$tab|$id|$htmlspecialchars"` /
`"$tab|$id"` respectively (the `$htmlspecialchars` flag changes
`get_record()`'s output, so it's part of the key, not ignored). Request-
scoped only, same discipline as the CommonData fix above.

**Invalidation** (the part CommonData's fix didn't need, since it deals with
add-only tree data): `get_record()` had never been cached before, so unlike
the CommonData case, *every* mutator needed a real invalidation call, not
just an optional one — a same-request read-after-write (edit a record, then
redisplay it) was already correct and must stay correct. Added
`Utils_RecordBrowserCommon::clear_record_cache($tab, $id = null)` (clears
one record, or the whole tab when `$id` is omitted), called from:
- `update_record()` — after the field-`UPDATE` loop, before `CompleteTrans()`
- `set_active()` — after the `active` flag flip (covers both `delete_record()`'s
  soft-delete path and `restore_record()`)
- `delete_record()`'s `$perma` branch — after the `DELETE`
- `set_record_properties()` — after its `created_on`/`created_by` overwrite
- `format_autonumber_str_all_records()` — whole-tab clear, since it rewrites
  one field across every row in the table
- `Utils_AttachmentCommon::attach_note()`/`detach_note()` — these write
  `utils_attachment_data_1` directly via `DB::Execute()`, bypassing
  `update_record()` entirely, so needed their own explicit
  `Utils_RecordBrowserCommon::clear_record_cache('utils_attachment', $id)` call

**Not covered**: a handful of rare, admin-triggered bulk field-type-conversion
writes in `RecordBrowser_0.php` (converting a column's stored type, e.g.
select→text, across every record in a table) and one bulk write in
`CRM_MailCommon` (clearing other accounts' "default" flag) still bypass the
cache directly. Same judgment call as CommonData's fix: these are rare,
narrow, admin-only paths, not part of any hot request path, and a repo-wide
sweep for every raw `UPDATE ..._data_1` (including inside `modules/Premium/`,
which Grep can't even see - it's gitignored, see CLAUDE.md) wasn't worth
chasing for this pass. Revisit if a same-request stale-read bug is ever
actually observed from one of these.

**Verified live** (not just re-derived from source): edited a Task's title via
the UI (Edit → change Title → Save, which is a **single** `process.php`
request that calls `update_record()` then immediately re-renders the view
template) and confirmed both the new title and the now-populated "Edited by"
line (from `get_record_info()`, previously "This record was never edited")
appeared correctly on the very next render - proving the invalidation fires
before the redisplay, not just that the happy path is faster. Reverted the
test edit after.

**Result** (Dashboard, re-tested twice for stability): 348 → 223 queries,
SQL time 155.5ms → ~105ms.

## Fixed: `CRM_ContactsCommon::get_contact_by_user_id()` login→contact_id now cached cross-request (2026-08-29)

Follow-up to the fix above, from a user suggestion made *before* profiling had
actually pinpointed `get_record()`/`get_record_info()` as the bigger hotspot.
`get_contact_by_user_id($uid)` (`ContactsCommon_0.php:73-96`) — "which Contact
is this logged-in user" — already had a request-scoped `static $cache`, so it
wasn't the Dashboard's bottleneck (only ~2 queries there). But it's called
from 28+ files across the app, i.e. on effectively every request, and the
login→contact_id mapping it resolves changes about as rarely as data gets:
normally set once when a Contact is linked to a user account and untouched
for the record's lifetime. That combination (called everywhere, changes
almost never) is exactly what's worth a cross-request cache even though no
single page shows a big win from it.

**Fix**: the `login` id (not the full contact record — that stays
request-scoped only via `get_contact()`, which changes far more often:
company, phone, etc.) is now cached via `include/cache.php`'s `Cache::`
class under key `crm_contact_login_uid_{$uid}`, sentinel `0` for "no linked
contact" (real ids are never `0`), 1h TTL as a safety net rather than the
primary mechanism.

**Invalidation**: hooked into `CRM_ContactsCommon::submit_contact()` — the
`contact` table's one registered `record_processing()` callback (confirmed:
only one row in `recordbrowser_processing_methods` for `tab='contact'`) —
by adding handlers for the `'added'`/`'edited'`/`'deleted'`/`'restored'`
modes (previously unhandled; only `'add'`/`'edit'`/`'delete'` were, for the
Base_User-account side effects). `'edited'` needed the *old* login value to
invalidate both the old and new uid's entry (a contact's link could in
theory move from one user to another) — captured in `'edit'` (pre-write) via
`Utils_RecordBrowserCommon::get_record()` (free: already request-cached by
`update_record()`'s own earlier call for that id) into a private static
bridge array, consumed and cleared in `'edited'` (post-write). `'deleted'`/
`'restored'` also invalidate even though the `login` field value itself
doesn't change on a soft-delete/restore — `get_id()`'s own query filters
`active=1`, so what it resolves to for that uid still flips.

**Verified in isolation, not through the live browser session**: cross-
request behavior can't be exercised within one PHP process (`get_contact_by
_user_id()`'s pre-existing request-scoped `static $cache` masks the `Cache::`
layer entirely if you call it twice in the same script — tripped over this
first, cost a debugging detour). Test used a scratch contact + a synthetic
fake uid (`999999`, `SET_SESSION=false` bootstrap keeps CLI test scripts off
the live session per `AI-shared/environment-gotchas.md`, well above the
dev DB's real max user id of 3), with each step run as a **separate `php`
process** to genuinely simulate separate requests:
link → cache invalidated → next process resolves fresh and re-primes the
cache → a third process, after directly corrupting the DB row's `login`
value out from under it, still returned the *stale cached* contact
(proving it was actually reading `Cache::`, not silently re-querying) →
soft-delete → invalidated → resolves to null → restore → invalidated →
resolves correctly again. Scratch contact perma-deleted and the cache key
cleared at the end; confirmed gone from the DB after.

## Fixed: initial-load `process()` was rendering the whole module tree twice (2026-08-29)

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
the *entire* module tree once via `self::go($root)` to figure out what an
empty/unresolved URL should even show. Partway through, some module calls
`location()` (`include/misc.php:52`). `location()` is a generic side-effect
accumulator used from 60+ call sites app-wide (RecordBrowser filters, Wizard
steps, history back/forward, Box push/pop, etc.) — a module only decides to
request a redirect *during its own render*, so `process()` has no way to
know one is coming without executing that first pass. When it sees
`location()` was called — for *any* non-empty-looking call, even
`location(array())` with nothing in it — it wipes `self::$content`
(discarding the first render entirely — none of its `MODULE_TIMES`/SQL
stats survive) and calls `self::process()` again (`$loc!==false` at
`epesi.php:393`, and `array() !== false` in PHP). **Only the second call's
numbers ever reach the debug panel** — `$debug`/`self::$content` are fresh
per top-level call, and the first, more expensive pass leaves no trace
unless you go looking for it like this.

**Root cause, corrected**: an earlier pass at this investigation (see git
blame on this section) suspected `Base_Box::push_main()` (resolving "no main
module yet") as the trigger, reasoning from the "no main module yet" framing
alone rather than an actual captured trace. Instrumenting `location()`
itself (`static $traced` + `debug_backtrace()`, first call per request only,
logged to a scratch file — added, captured, reverted) showed the real
trigger on a fresh session's bare load is
`CRM_Filters::body()`'s one-time lazy default-profile init
(`modules/CRM/Filters/Filters_0.php:70`, guarded by
`!isset($_SESSION['client']['filter_'.Acl::get_user()]['desc'])` so it only
fires once per session): `CRM_FiltersCommon::set_profile('my')` sets the
session's filter value/desc and then unconditionally called `location(array())`
— an *empty* array, not an actual redirect target — purely to force the
whole tree to redraw. `push_main()` was never in the trace; `Base_HomePage`
only *packs* its target as a child module (see its own comment,
`modules/Base/HomePage/HomePage_0.php:32-45`) and never calls `push_main()`
itself.

That empty-array call was provably redundant: `CRM_FiltersCommon::get()`
(`FiltersCommon_0.php:41-47`, called by the Dashboard's Tasks/PhoneCall/
Meeting widgets and Calendar) has its own independent lazy fallback that sets
the *same* session `value` the moment anything reads the filter — and `main`
renders before `filter` in `Base/Box/default.ini`'s container order, so by
the time `CRM_Filters::body()` runs, every earlier container already saw the
correct filter value in this same pass regardless. The only thing the lazy
init's `location()` call was announcing was the `desc` string (a UI label
CRM_Filters' own container is the only reader of) — nothing upstream needed
the redraw.

**Fix**: `CRM_FiltersCommon::set_profile($prof, $notify = true)` — the
lazy-default call site now passes `$notify = false`, skipping `location()`
for that one case only. The two real call sites (`Filters_0.php:58`, a user
actually picking a different filter, and `ContactsCommon_0.php:1078`) are
untouched, still default `$notify = true` — a real filter *change* still
needs the full redraw so already-rendered containers pick up the new value.

**Verified live**: cleared cookies, logged in fresh (genuinely new PHP
session, no persisted filter `desc`), confirmed via the `location()`
backtrace instrumentation that it no longer fires on this path; a second
fresh-tab bare load (same session, already authenticated — the exact
scenario originally profiled) produced *no* `location()` call at all.
DevTools TTFB for that request dropped to ~377ms against the debug panel's
own ~315ms (previously a ~4x gap, now consistent with ordinary bootstrap
overhead) — no discarded first pass left. Confirmed the `CRM_Filters`
"Perspective: My records" label still renders correctly from the
non-notifying lazy path (reads `$_SESSION[...]['desc']` within the same
pass, set before its own render), and that the explicit filter-picker UI
still opens normally with no console errors, since the real-change path
(`Filters_0.php:58`) wasn't touched by this fix.

**Not exhaustively fixed**: this closes the one diagnosed, profiled trigger.
`location()`'s general "decide mid-render, discover after" design is still
shared by the other 59+ call sites (RecordBrowser filters, Wizard steps,
history back/forward, Box push/pop, etc.) and any of them calling
`location()` — even with an empty array, as this one did — still forces the
same discard-and-rerender by design; that's inherent to how `process()`
propagates a session-wide state change to containers that already rendered
before the change happened, not a bug in each individual site. Resolving
destinations without a render pass at all would be a framework-level
rewrite, not a scoped fix — not attempted. Revisit only if a *different*
`location()` call site is independently profiled as a real cost; start from
the `location()` backtrace-instrumentation technique above (fast — under an
hour end to end) rather than assuming the trigger without a captured trace.

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
to the observed gap almost exactly). Once a gap is confirmed, don't guess
the trigger from the framing of "what looks unresolved" — instrument
`location()` itself (first-call-only `debug_backtrace()` to a scratch file)
to get the actual caller; the first hypothesis here (`Base_Box::push_main()`)
turned out wrong when actually traced.

## Fixed: `Utils_RecordBrowserCommon::get_description_fields()`'s cache guard never actually cached (2026-08-29)

Found while digging into the Dashboard's 221-222 SQL queries flagged by the
double-render investigation above. Grouping the debug panel's SQL section by
caller (see "How to profile" above) showed 42 *identical* `GetAssoc` calls —
`SELECT tab, description_fields FROM recordbrowser_table_properties`, same
args every time — all attributed to `get_description_fields()`
(`RecordBrowserCommon_0.php:1352`), called once per row by
`create_default_linked_label()` rendering a linked-record label. The
debug panel's own duplicate-detection styling (an underline added per
repeat of identical `args`, see the `SQL_TIMES` block in `include/epesi.php`)
already flagged all 42 as exact repeats — worth remembering as a visual cue
next time.

**Root cause**: the function already had a `static $cache = null;` guard
meant to load the whole table once — but it only ever assigned into
`$cache[$t]` inside `if ($fields)`, i.e. only for tabs with a *non-empty*
`description_fields` value. This dev DB has 17 tabs and **zero** with that
column set (confirmed via a throwaway CLI script bootstrapping like
`console.php` — `SET_SESSION=false` + `require 'include.php'`, see
[[environment-gotchas]] for why that flag matters), so `$cache` never
received a single key and stayed `null` forever — the `if ($cache===null)`
guard was true on every call, defeating the cache entirely. Likely not
dev-data-specific: `description_fields` is a rarely-configured
customization, so any install that doesn't set it hits this on every
linked-record label render, forever.

**Fix**: one line — `$cache = array();` right before the loop, so the
sentinel reflects "have I loaded" instead of "did I find any data"
(`RecordBrowserCommon_0.php:1355`). Tabs with no configured
`description_fields` still correctly `return false` (via the existing
`isset($cache[$tab])` check below), just without re-querying to find that
out each time.

**Verified**: the throwaway script showed query count going 3→4→4 (second
call now free) instead of 3→4→5 (pre-fix, unconditionally +1 every call).
Live on the Dashboard: 222→180 SQL queries. Also loaded Contacts: Browse
(exercises `create_default_linked_label()` on a different tab) after the
fix — renders correctly, no errors.

**How to apply**: this exact shape — a `static $cache = null` sentinel that
doubles as both "am I loaded" and "the loaded data," populated only inside
a conditional — silently breaks the moment the condition can be false for
*every* row in the source data. Grep for other `static $cache = null` guards
with an `if (...)` gate inside their populate loop before assigning into
`$cache` (rather than assigning `$cache = $db_ret` unconditionally up
front) — same bug shape, would need the same one-line fix.

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

## Fixed: three more grid N+1s + the notification poller (2026-08-31)

Profiling **Contacts: Browse** (20 rows) found 168 queries / 0.0885s SQL of a
0.283s render — only 31% SQL, so ~69% was PHP. Grouping the SQL panel by caller
showed **99 of 168 queries came from four call sites**, all the per-row-formatter
shape this document already describes:

| Count | Call site | Nature |
|---|---|---|
| 40x | `RecordBrowserCommon_0.php:2124` (`get_record_info()`) | 2 queries × 20 rows |
| 20x | `WatchdogCommon_0.php:330` (`check_if_notified()`) | residual `MAX(id)`, see the correction above |
| 20x | `RoundcubeCommon_0.php:50` (`get_mailto_link()`) | **identical** query, 20× |
| 19x | `ContactsCommon_0.php:134` (`get_company()`) | one company at a time |

**Fixed (three of the four):**

1. **`CRM_RoundcubeCommon`** — `get_mailto_link()` ran
   `get_records_count('rc_accounts', ['epesi_user' => Acl::get_user()])` once per
   e-mail cell. That call takes **no per-row argument at all** — same table, same
   user, same criteria every time — and `get_records_count()` has no cache
   (`RecordBrowserCommon_0.php:1672`), so each one was a fresh `build_query()` +
   `COUNT(*)`. Now a `user_has_mail_account()` memo keyed by user id, shared with
   `attachment_getters()`/`file_field_getters()` which ran the same query.
   **20 → 1.** The debug panel's duplicate-args underline had been flagging all 20
   as exact repeats — worth reading as the strong signal it is.
2. **`Utils_RecordBrowserCommon::prefetch_record_info($tab, $ids)`** — new. The
   per-id cache from 2026-08-29 removed repeats but every grid row is a *distinct*
   id, so the page still paid 2 queries per row. `RecordBrowser_0.php`'s render loop
   now warms the whole page in two grouped queries first. Pure warm-up: uncovered
   ids still fall through to the per-id path, and rows with no `_data_1` row are
   deliberately left uncached so `get_record_info()`'s `trigger_error()` still fires.
   **40 → 2.**
3. **`Utils_WatchdogCommon::_last_event()`** — see the correction above. **20 → 1.**

**Not fixed:** `CRM_ContactsCommon::get_company()` (19x). Genuinely distinct ids, so
it needs the same prefetch treatment as (2), but the linked-company ids are not as
cleanly available before the loop. Left as the next one to take.

**Result** (Contacts: Browse): **168 → 91 queries**, SQL 0.0885s → 0.0462s, render
0.283s → 0.245s. Companies/Tasks/Phonecalls/Meetings all landed at 72-81 queries.
Note the render time moved much less than the query count — a direct consequence of
SQL being under a third of the total. **The remaining ~0.10s of non-SQL time inside
`Utils_RecordBrowser` is now clearly the main target, and it has never been
function-profiled.** Do that (Xdebug/Excimer on one `process.php`) before optimizing
further; `MODULE_TIMES`/`SQL_TIMES` cannot see inside it.

**Verification** (both scripted, not eyeballed):
- `prefetch_record_info()` vs. the per-id path across 240 records in 4 tables
  (contact/company/task/phonecall): byte-identical for all 240, 120 queries → 2 per
  table. Also cross-checked one record's rendered tooltip against the DB, and a
  record *with* edit history against a raw `ORDER BY edited_on DESC LIMIT 1`.
- `user_check_if_notified()` before/after across all 500 real subscriptions in 7
  categories: identical for all 500.

### Fixed: `Base/Notify/refresh.php` bootstrapped the whole app to say "nothing new"

The browser polls this endpoint every 30s (`Base_NotifyCommon::refresh_rate`) and the
server rejects anything earlier — but the rejection happened *after*
`ModuleManager::load_modules()`, i.e. after loading all ~95 modules / ~150 files.
Measured: **8 requests, 637ms**, the largest cost on the page after `process.php`.

Fixed with a pre-bootstrap early-out that reproduces just enough of
`get_session_token()` + `is_refresh_due()` to prove a poll is early, using the session
and one query. **Deliberately fail-open** — it exits only when it can positively show
the poll is too early; anything unexpected falls through to the original path.

Two subtleties that cost a round trip each and are easy to get wrong again:
- **one_cache mode.** `get_session_token()` finds the row by `single_cache_uid`, not
  by `md5(user_id.'__'.session_id)`. Probing only the derived token silently
  fail-opens for every session except the one that created the row — which is
  precisely the multi-session case one_cache exists for. The probe matches either.
- **`telegram=0` is mandatory, not cosmetic.** Telegram rows also carry
  `single_cache_uid` but run on `refresh_rate_telegram` (300s), so letting one match
  answers this poller with the wrong cycle's timestamp.

**Result:** ~80ms (max 147ms) → **~11-13ms** when not due; unchanged when it is.
Verified by forcing `last_refresh=0`, confirming the next poll took the full path
(84ms) and restored its own timestamp, then that subsequent polls went fast again.

`Utils/RecordBrowser/indexer.php` already used this shape (an mtime guard ahead of
`load_modules()`) — it is the model to copy for `Apps/Shoutbox/refresh.php` and
`Utils/Messenger/refresh.php`, which still pay the full bootstrap.

### Fixed: grid row-action icons emit Bootstrap Icons glyphs, not hidden <img> (2026-08-31)

The plan that drove this pass assumed the grids' row-action icons were still PNGs
that wanted converting to `bootstrap_icon()`. **That premise was wrong, and the
reality is worse.** Under adminltedark the icons are *already* Bootstrap Icons
glyphs — but they are drawn by CSS `::before` on the `<a>`, selected via
`[src*=...]` attribute matching on an `<img>` that is then `display:none`
(`GenericBrowser/theme_adminltedark/default.css`, the long commented block around
line 510).

So each grid page emits 240 `<img>` elements (12 distinct URLs x 20 rows), the
browser downloads every one of them (`display:none` does not prevent fetching -
confirmed live: `complete: true, naturalWidth: 14`), and not one is ever shown.
The *network* cost is smaller than that sounds - 12 files, ~3.9 KB, fetched once per
browser cache lifetime and 0 bytes on any later navigation (measured) - so the real
price was 240 surplus DOM nodes and 240 `:has()` evaluations per grid render, not
bandwidth.

**Fixed the same day.** `GenericBrowser_0.php`'s new `action_icon_tag()` emits
`<i class="bi bi-...">` directly for every action whose meaning it knows - the same
thing `Base_ActionBar`'s adminltedark template has always done (its `$icon_map`).
`RecordBrowserCommon::get_fav_button_tags()` and
`WatchdogCommon::get_change_subscription_icon_tags()` were converted at their own call
sites. **Result: 240 hidden `<img>` per grid page -> 0**, verified across Contacts,
Companies, Tasks, Phonecalls and Meetings.

**The key correction to the entry above**: this was never really "sprite vs. individual
PNGs". A sprite *does* exist - `Base/ActionBar/theme/icons.png`, 16 KB, driven by
`background-position` - and it is the legacy theme's original design. But adminltedark
does not use it: ActionBar was migrated to Bootstrap Icons glyphs and emits no `<img>`
at all. GenericBrowser simply never got that migration, and was worked around in CSS
instead. Re-spriting would have been a step backwards; finishing the migration was the
fix. The default/legacy theme now exists only for old modules and is slated for
retirement, so glyphs are the single icon mechanism going forward.

**Three things worth knowing before touching this again:**

- **The raster `<img>` fallback is load-bearing, not leftover.** `action_icon_tag()`
  falls back to the original `<img>` for any icon it cannot identify. `Premium/Import`
  ships its own folder/manual/copy/checkbox artwork through the same path branch and has
  22 of its own `[src*=...]` rules in a **gitignored, separate git repo** - it keeps
  working untouched. Note its `edit.png`/`view.png` deliberately do *not* borrow
  GenericBrowser's glyph: the stem lookup is gated on the path being GenericBrowser's
  own. Converting a module later means it declaring `bootstrap_icon()`, with nothing
  here to change.
- **`:is()` takes the specificity of its most specific argument.** The generic
  `:is(.epesi-gb .Utils_GenericBrowser, #epesi-gb-actions-menu) i.action_button` rule
  therefore carries the weight of an *id*, so a bare `.epesi-fav-on { color: ... }` lost
  to it and the star/eye rendered grey instead of gold/green. The state-colour rules need
  the same `:is(...)` prefix. Caught by reading `getComputedStyle()`, not by looking at
  the page.
- **The mobile actions menu clones these `<a>`s** into `#epesi-gb-actions-menu` outside
  the table. Putting the class on the element itself (rather than selecting a sibling
  `<img>` via `:has()`) means clones carry it for free - verified: 10 glyphs, 0 images in
  the open menu.

**The record view was finished the same day too.** Its tools row (`info`/`clipboard`/
`history`/`history_inactive`, the favourite star, the watchdog eye, and the New Meeting/
Task/Phonecall/Note shortcuts) emitted 7 more `<img>` per record view from a different
place - `RecordBrowser_0.php`'s `view_entry` block, plus `ContactsCommon`/`TasksCommon`/
`PhoneCallCommon`/`MeetingCommon`. All converted; **0 images on the grid *and* the record
view** across Contacts, Companies, Tasks, Phonecalls and Meetings.

Module-identity shortcuts go through the new `Base_BootstrapIcons::tag($module)`, which
returns null when a module declares no `bootstrap_icon()` so the caller keeps its `<img>` -
the same opt-in shape as the grid.

**This is where the conversion nearly shipped broken, and the trap generalises:**
`View_entry.css` identified the CRM-filter link by `a:not(:has(img))` - "the only link in
this row with no image". That was a sound hook *only while every other link had an `<img>`*.
Removing them made every icon match, so each one got `font-size:0` and a funnel glyph
stamped in front of it. Fixed by testing for no icon child of any kind
(`:not(:has(img)):not(:has(i))`), which is what it always meant.

**Before deleting markup that other code keys on, grep for everything that asserts its
*absence*, not just its presence - and in every language, not just CSS.** This is the
single most useful thing to carry out of this change, because the version of the rule
written here first was too narrow and the bigger instance got through it.

- **CSS**: `:not(:has(img))` and friends. Fails silently and ugly rather than not at all.
- **JS**: `querySelector('img')` / `getAttribute('src')` - the same assertion in another
  language, and the one that actually shipped broken. `Base_Box/theme_adminltedark/
  default.tpl`'s `isCoreAction()` classified every row action by reading the `<img>`'s
  `src` and matching filename regexes against it. With no `<img>`, `src` was `''`, every
  regex failed, and **every action on every grid in the app fell through to "extra" and
  hid behind the More-actions kebab**. Fixed in `8dec01fcc` by having
  `action_icon_tag()` mark core actions server-side with an `action_button_core` class -
  deliberately not re-derived from the `bi-*` name, since a module identity glyph can
  legitimately coincide with a core one.

**And the process lesson, which is the part worth internalising:** the kebab regression
was visible in a verification screenshot and got explained away as "the actions are
probably collapsed because of the viewport width" - a plausible rendering explanation
that was never checked. In the same pass, two Utils_Attachment actions ("Copy link" and
"Cut") collapsed onto one glyph because the `[-_]small$` branch over-matched
`copy_small.png`/`cut_small.png`; that was also looked at directly and filed as an
acceptable identity-icon change (narrowed to `/^icon[-_]small$/` in `8dec01fcc` - and
note it is *not* fixable via `resolve()`'s `$by_filename` map, which is keyed by basename
alone, while the same `copy_small.png` deliberately means `bi-copy` for CRM_Mail and
`bi-link` for Utils_Attachment).

A concurrent session hit the mirror image of this on the same day - narrowing a
light-override selector in a way that read as a pure narrowing, silently reverting
light-mode table icons, then over-correcting and painting the fav/watch state glyphs
black - and reached the same conclusion independently: **a plausible explanation for what
you are looking at terminates the check, which is exactly what makes it dangerous.**
Assert resolved values (`getComputedStyle`, element counts, DOM shape) in every theme
rather than reasoning about the selector or trusting the screenshot.

Sizing needed care too: the record-view tools row is deliberately `1.25rem` (matched to
the ActionBar directly above it), not the grid's `1rem`, and its state-colour rules need
the `.epesi-rv-tools` prefix or the row's own grey wins on a specificity tie.

## Fixed: a fresh `HTMLPurifier` per grid row - half the RecordBrowser row loop (2026-08-31)

This closes **item 2.1** of `optimization-plan-opus.md` - "one Xdebug or Excimer profile
of a single Contacts: Browse `process.php` request, to turn *0.10s somewhere in
RecordBrowser* into a ranked function list". The plan called it the highest-value
remaining item and said everything about RecordBrowser was guesswork until it existed.
It now exists, and the answer was a single line.

### First: there is no profiler extension on this machine, and two obvious workarounds don't apply

`C:\xampp82\php` has **no Xdebug, no Excimer, no tideways** - `php -m` lists `memcached`
as the only non-core extension of interest, and `php/ext/` ships no profiler DLL. Worth
knowing before planning any profiling work here; the plan assumed one was available.

Two pure-PHP substitutes look viable and are not:

- **`register_tick_function` + `declare(ticks=1)`** - `declare(ticks)` is *compile-time
  and per-file*. It only fires for statements in the file that declares it, so it cannot
  sample across a call tree spanning `RecordBrowser_0.php`, `RecordBrowserCommon_0.php`,
  `GenericBrowser`, and vendor code. Useless as a general sampler.
- **A `pcntl` interval timer** - `pcntl` does not exist on Windows.

**What does work, and is what produced the numbers below:** temporary manual probes
(`microtime(true)` pairs aggregated by label into a static, dumped to a scratch file at
the end of `show_data()`). Coarse phases first, then drill into whichever dominates.
Two rounds were enough to go from "0.10s somewhere" to one line. Instrument, measure,
**then** revert the instrumentation - the probes are not in the tree.

Practical notes if you redo this: define the probe class in a `*Common_0.php` (Commons
load eagerly, so it is guaranteed defined for every caller - putting it in the module
class file risks a fatal from any path that loads the Common without the module), and
drive the page from the browser rather than CLI, because `show_data()` depends on module
variables and the theme render path.

### The ranked list (Contacts: Browse, 20 rows, warm, averaged over 3 renders)

| Phase | Time | Share of row loop |
|---|---|---|
| **A. row loop (inclusive)** | **0.0753s** | 100% |
| - I. actions block | 0.0499s | 66% |
| - - **J. `add_info`/`get_html_record_info()`** | **0.0380s** | **50%** |
| - - K. `call_additional_actions_methods()` | 0.0088s | 12% |
| - - view/edit/delete hrefs + `get_access()` | 0.0031s | 4% |
| - F. column loop (7 cols x 20 rows) | 0.0207s | 27% |
| - B-E, H (record_processing, fav, watchdog, per-row access) | 0.0042s | 6% |
| L. `display_module()` (GenericBrowser) | 0.0146s | - |
| M. pre-loop (query + setup) | 0.0050s | - |

The 0.10s the plan was chasing is real and it is **one function**:
`get_html_record_info()`, at ~1.9ms per row. Everything the plan listed as a suspect -
per-row `get_access()`, `get_template_file()` per icon, tooltip building, `get_val()` -
is collectively 6% of the loop. Note how cheap the per-row access checks turned out to
be (0.0009s/20 rows): **A4's "cheap things visible without a profile" would have been
wasted work**, which is the whole argument for profiling before optimizing.

### Root cause, and a corrected hypothesis

`get_html_record_info()` ended with:

```php
$config = HTMLPurifier_Config::createDefault();
$purifier = new HTMLPurifier($config);
return $purifier->purify(implode('<br>', $lines));
```

A brand-new purifier per row, to sanitise a ~5-line string the function itself just
assembled from a record id, `get_user_label()` and `time2reg()`.

The obvious hypothesis - "constructing HTMLPurifier is expensive" - is **wrong**, and
sub-probes proved it before anything was changed:

| Sub-phase | Time (20 rows) |
|---|---|
| `$purifier->purify()` | 0.0382s (90% of J) |
| `HTMLPurifier_Config::createDefault()` | 0.0008s |
| `new HTMLPurifier($config)` | 0.0005s |
| `get_user_label()` + `time2reg()` lines | 0.0010s |

The constructor is nearly free. HTMLPurifier builds its `HTMLDefinition`/`CSSDefinition`
**lazily, on the first `purify()` call** - so a fresh instance per row rebuilt the entire
definition set 20x per page, and the cost landed in `purify()`, not in `new`. Same
defect, different line than expected. Had the fix been applied on the hypothesis alone
it would still have worked, but for a reason the comment would have got wrong.

### The fix

Memoize the purifier in a request-scoped `static` - the shape this file already uses
everywhere:

```php
static $purifier = null;
if ($purifier === null) $purifier = new HTMLPurifier(HTMLPurifier_Config::createDefault());
return $purifier->purify(implode('<br>', $lines));
```

**Measured: `get_html_record_info()` 0.0380s -> 0.0168s per page; the whole row loop
0.0753s -> 0.0519s (-31%).**

The same `new HTMLPurifier()`-per-call shape existed at **five** sites; all five now
memoize, each with its own static because the configs differ:

| Site | Config | Called |
|---|---|---|
| `Utils_RecordBrowserCommon::get_html_record_info()` | default | per grid row |
| `Utils_TooltipCommon::format_info_tooltip()` | default | per grid row |
| `CRM_Calendar::…` (`Calendar_0.php`) | `HTML.AllowedElements=span` | per event |
| `CRM_PhoneCallCommon::display_subject()` | `HTML.ForbiddenElements` | per record |
| `Utils_SafeHtml_HtmlPurifier::output()` | `URI.AllowedSchemes` + `data:` | per call |

**A latent ordering bug fell out of this.** `PhoneCallCommon_0.php` called
`$config->set('HTML.ForbiddenElements', …)` *after* `new HTMLPurifier($config)`. It works
today only because the config object is shared by reference and the `set()` still lands
before the first `purify()` - but memoizing without reordering would have silently
applied the restriction to nobody. The `set()` now precedes construction, so the order is
no longer load-bearing.

### Verification

A scratch script purified 8 payloads - including the exact shape `get_html_record_info()`
builds, plus `<script>`, `onerror=`, a `javascript:` scheme and a `data:` URI - through
each of the four configs, comparing fresh-per-call against one shared instance:
**8/8 byte-identical for all four configs**, 1.8x-12.5x faster. Confirmed live afterwards:
20 record-info tooltips render with correct content
(`<strong>Record ID:</strong> 88<br><strong>Created by:</strong> Tylek Janusz<br>27/08/2026 21:47`),
Calendar renders clean, no new entries in `data/logs/php_errors.log`.

Sanitisation behaviour is deliberately unchanged. `get_user_label()` can carry
user-supplied names, so the purify call is doing real work and was left in place - only
the engine rebuild was removed.

### What is left in that function

`purify()` still costs ~0.7ms/row (0.0143s/page) even shared. Removing that means
arguing the assembled string needs no sanitising at all, or purifying only the
`get_user_label()` fragment - a change to the sanitisation contract, not an
optimisation, and it needs its own decision. The row loop's next-largest item is now the
column loop at 0.0207s, spread across 7 columns x 20 rows with no single hotspot.

---

## Fixed: the last grid N+1 — linked columns — plus a guard so these stay fixed (2026-08-31)

Closes the item the plan calls A1.4, the one left over when the other three grid N+1s were
fixed earlier the same day.

### The N+1

`CRM_ContactsCommon::get_company()` memoizes per id, so the usual "add a cache" answer had
already been applied and did nothing: a 20-row Contacts grid links 19 *distinct*
companies, so every call was a first call. 19 queries per page, all
`Utils_RecordBrowserCommon::get_record('company', $id)` underneath.

### The fix, and why it is not in `CRM_Contacts`

`Utils_RecordBrowserCommon::prefetch_records($tab, $ids)` — the same warm-up shape as
`prefetch_record_info()`, one level out: one `SELECT ... WHERE id IN (...)` that primes
`self::$record_cache`, after which the per-row `get_record()` calls are cache hits. Pure
warm-up; anything it misses still resolves through the per-id path.

The call site is `Utils_RecordBrowser_0.php`'s grid loop, not Contacts. Before rendering
rows it walks `$query_cols`, picks out every `select`/`multiselect` column whose
`ref_table` is a real recordset, collects those ids off the already-loaded `$records`, and
prefetches one table at a time. So it is not a Contacts fix that happens to live in
RecordBrowser — **every module with a linked column gets it**, including ones not written
yet. Excluded on purpose: `commondata` fields (their `ref_table` is a CommonData array
name, and `Utils_CommonDataCommon` has its own tree cache) and multi-recordset references
like `contact,company` (stored as `tab/id` tokens, so there is nothing to batch per table).

**19 queries → 1 per linked table.**

### The bug this nearly shipped with

The batch query first interpolated its ids into the SQL instead of binding them. That
changes every integer column from `int` to `string`, because ADOdb runs a bound query as a
mysqli prepared statement and an unbound one through the text protocol. Full write-up in
`bug-patterns.md` — it is the trap for this entire class of fix, and `prefetch_record_info()`
had already shipped with it. Both bind now.

Verification was `serialize()`-level, not `==`: 952 records for `prefetch_records` and 468
for `prefetch_record_info`, across four recordsets, both `htmlspecialchars` modes,
including ids that do not exist (the `null`-caching branch). Zero mismatches. The same
comparison written with `==` reports zero mismatches *before* the fix too, which is exactly
how the first one got through.

## Fixed: profiling no longer requires editing `data/config.php` (2026-08-31)

`MODULE_TIMES`/`SQL_TIMES` were read as constants at every measurement point, so profiling
meant editing config, reloading, and remembering to undo it — install-wide, for every user,
outliving the session that wanted it. On a machine where more than one person or agent
session shares the tree, that is not a private act.

`include/profiling.php` holds `Profiling::$sql` / `Profiling::$modules`, initialised from
the constants (so an install that sets them keeps behaving exactly as before, CLI and cron
included) and overridable **per session** by a super-admin from Administration → *PHP & SQL
Errors to mail*. Both directions work: a session can also silence a panel that config.php
turned on globally.

Read the flags, never the constants — a constant read skips the override. `database.php`
requires `profiling.php` itself rather than leaving it to callers, because `index.php`,
`init_js.php`, `theme_css.php`, `setup.php` and Roundcube's `config.inc.php` all pull the
DB layer in without going through `include.php`.

The procedure in this file's "How to profile a slow page" section still applies; it just
no longer starts and ends with a config edit.

## The guard: `php console.php dev:query:budget`

Everything above is one edit away from coming back — "just call `get_record()` here, it's
cached" is a reasonable-looking change that reintroduces the N+1 silently, because the
output is identical and only the query count moves.

The command asserts **slope, not a fixed budget**: each scenario runs over 5 records and
over 25, after a discarded warm-up, and the query count must not grow. Five scenarios, one
per fix — `get_record_info()`, `get_company()`, `get_record()`, the Roundcube mailto check,
Watchdog's `check_if_notified()`. All flat, and confirmed to actually catch a regression by
stubbing out both prefetch functions.

It does **not** cover whole-page counts: `Epesi::process()` renders from browser session
state, so page totals stay the manual measurement described at the top of this file.

**Full write-up — how it works, why slope rather than a threshold, why the warm-up uses the
smaller set, and how to add a scenario — is in
[query-budget-check.md](query-budget-check.md).** Read that before adding one.

## Fixed: `Utils/Messenger/refresh.php` bootstrapped the whole app to say "nothing due"

Same fix as `Base/Notify/refresh.php` got earlier (see above), and the same fail-open rule:
one existence query using nothing but the session and DB, and anything that does not line
up falls through to the original path. Messenger polls every 180s — a sixth of Notify's
rate, so a sixth of the payoff, but the same ~80ms of pointless bootstrap per poll.

### Why `Apps/Shoutbox/refresh.php` does *not* get the same treatment

Its response is never empty. It re-renders the last 20 messages on every poll and the
client does `jQuery('#shoutbox_board').load(...)`, which writes the response straight into
the DOM — so an early-out returning nothing blanks the board. Making it cheap means either
caching the rendered HTML against a message-set fingerprint (invalidation would have to
cover deletes, the Shoutbox Admin flag, and the per-user `to_user_login_id` split) or a
client-side change so "nothing new" is a valid reply. Both are larger than they look, and
the second belongs with the poller-coalescing work. Left alone deliberately — this note
exists so the next person does not re-derive it.
