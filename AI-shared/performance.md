# Profiling a slow page

## Don't guess from the Network tab

Devtools is misleading here. This app's own `serve.php` (the minified JS/CSS bundle server)
shows as the *Initiator* for every `process.php`/`ajax.php` XHR, because that is where the
client-side loader physically lives — it does not mean `serve.php` is slow. The real cost
is in `process.php`/`ajax.php`, the request that calls `Epesi::process()` and renders the
module tree.

## Use the built-in profiler

A super-admin turns either debug panel on **for their own session only**, from
Administration → *PHP & SQL Errors to mail*. The install-wide `MODULE_TIMES`/`SQL_TIMES`
constants in `data/config.php` still work and still set the default, but you do not have to
edit config, reload, and remember to undo it — which on a shared tree was never a private
act anyway.

**In code, read `Profiling::$sql` / `Profiling::$modules` (`include/profiling.php`), never
the constants** — a constant read skips the session override. `database.php` requires
`profiling.php` itself, because `index.php`, `init_js.php`, `theme_css.php`, `setup.php`
and Roundcube's `config.inc.php` all pull in the DB layer without going through
`include.php`.

The panel (`#debug_content`, a slim bar pinned to the bottom of the viewport) gives you:

- `Page renderered in Xs`, always visible;
- **Modules load times**, nested by module path — a slow leaf rolls up through every
  ancestor;
- **SQL queries**, each with arguments, timing and the calling function/file/line.

That split answers "database or PHP?" immediately. To find *why* a query runs N times,
**group the query lines by their "Called by" function+file+line** — that is what turns
"234 queries" into "40 of them are `user_check_if_notified`, one per visible row".

Reading the totals from the console needs `textContent`, not `innerText`: the sections are
native `<details>`, closed by default, and closed content is excluded from `innerText`.

```js
document.getElementById('debug').textContent
  .split('\n').find(l => l.startsWith('Page renderered'))
```

If devtools' `process.php` time is much larger than the panel's own total, compare TTFB
before blaming the network or Apache.

## Caching rules in this codebase

**Caches added for performance are request-scoped, by discipline.** That is deliberate: a
request-scoped cache does not require finding and instrumenting every mutation site.
Anything cross-request needs real invalidation and must say so where it is defined.

**`prefetch_*` methods are warm-ups, never required steps.**
`Utils_RecordBrowserCommon::prefetch_record_info()` and `Utils_WatchdogCommon::_last_event()`
prime a request-scoped cache with one grouped query, and both keep the per-id path as a
self-healing fallback. **Do not "simplify" a caller into assuming the prefetch ran** — a
grid that adds rows by another route must still render correctly, just with more queries.
`prefetch_record_info()` also leaves ids with no data row uncached on purpose, so
`get_record_info()`'s `trigger_error()` still fires for a genuinely missing record.

**Batching changes result types.** Bound and unbound ADOdb queries type integer columns
differently, so replacing a per-row query with a batched one silently turns ints into
strings (or back). Any `===`/`!==` downstream will break.

## The N+1 regression guard

```
php console.php dev:query:budget        # use the real PHP binary — see CLAUDE.md
php console.php dev:query:budget -v     # list every measured query
```

Every N+1 fix is one edit away from coming back, and the return is **invisible**: the page
is byte-identical, nothing errors, nothing logs. Only the query count moves.

So the check asserts **slope, not a fixed budget**. Each scenario runs over 5 records and
then over 25, after a discarded warm-up, and the count must not grow. Run it after touching
`Utils_RecordBrowser`, `Utils_Watchdog`, `CRM_Roundcube` or `CRM_Contacts`. Exit code is
non-zero on a regression, so it works as a pre-push check. It needs a populated database,
so it is local-only, not a CI job.

Two properties worth knowing before you extend it:

- **The warm-up uses the *small* set**, never the large one. A cache left warm can then only
  ever cover those 5 ids; the other 20 are still cold in the large pass, so a per-row query
  still shows up as slope.
- **A healthy scenario can legitimately report fewer queries than you expect** — `0`, or `1`
  where you predicted 2 — because a cache outside the reset list absorbed part of the work.
  Only the comparison between the two columns means anything. Never read a single column as
  a page cost.

**Confirm a new scenario can actually fail.** Stub out the prefetch it is meant to protect,
run the command, see it report `N+1`, then put it back. A scenario that passes on the day
you write it is not evidence.

It does **not** cover whole-page query counts: `Epesi::process()` renders from browser
session state, so page totals stay the manual measurement above.

## Standing notes

- **Poller `refresh.php` endpoints early-out before bootstrap.** `Base/Notify/refresh.php`
  and `Utils/Messenger/refresh.php` answer "nothing due" with one session+DB query instead
  of ~80 ms of `ModuleManager::load_modules()`. Both are deliberately **fail-open** — see
  [framework-internals.md](framework-internals.md) for the conditions that are load-bearing.
- **`Apps/Shoutbox/refresh.php` deliberately does not get that treatment.** Its response is
  never empty — the client writes it straight into the DOM, so an early-out returning
  nothing blanks the board.
- **The Setup / Store screen round-trips to an external store server** for the module
  catalog, which dominates its render time. Deliberately not optimized: it is rarely
  visited compared to everyday grids.
