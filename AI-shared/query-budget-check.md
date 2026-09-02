# `dev:query:budget` — the N+1 regression guard

> **Status:** REFERENCE - how the query-budget check works and how to add a scenario. Run it after touching RecordBrowser, Watchdog, Roundcube or Contacts.

```
php console.php dev:query:budget        # use the real PHP binary — see CLAUDE.md
php console.php dev:query:budget -v     # list every measured query
```

Exit code is non-zero when anything regressed, so it works as a pre-push check.
Source: `console/Develop/QueryBudgetCommand.php`, registered in `console.php`.

## What problem it exists for

The 2026-08-31 performance work removed four per-row query patterns from the grid
(`performance-profiling.md` has the fixes). Every one of them is one edit away from
returning, and the return is **invisible**: the rendered page is byte-identical, nothing
errors, nothing logs. Only the query count moves. "Just call `get_record()` here, it's
cached" is a reasonable-looking change that quietly reintroduces an N+1, because the cache
it relies on is only ever warm when something else primed it.

So the thing worth asserting is not "this page costs N queries" — it is **"the cost does
not grow with the number of rows."**

## How it works

For each scenario the command:

1. Asks the scenario for a **small** id set (5 records) and a **large** one (25). If either
   comes back `null` — module not installed, or the fixture has too few records — the
   scenario is skipped rather than failed.
2. Runs the scenario once and **throws the result away**. This warms every per-request
   static it touches.
3. Runs it again over 5 records, then over 25, recording the queries each caused.
4. Asserts `queries(25) <= queries(5)`.

Measurement is `Profiling::$sql` flipped on around the call and `DB::GetQueries()` sliced —
the runtime switch from `include/profiling.php`. Before that switch existed this command
would have had to ask you to edit `data/config.php` first and then remember to undo it.

Output is one line per scenario:

```
  FLAT  A1.4 get_company() over a grid page       5 rows: 1 queries   25 rows: 1 queries
  N+1   A1.2 get_record_info() over a grid page   5 rows: 10 queries  25 rows: 50 queries
```

A failing scenario prints every query it made, with the caller, so you can see which call
site came back. `-v` prints them for passing scenarios too.

## Why slope, and not a fixed budget

The first version asserted a fixed number per scenario and **every scenario failed its
first run** — for reasons that were not bugs. An honest cold count includes one-off
schema reads: RecordBrowser's `_field` and `_callback` lookups, Watchdog's category id.
Those are memoized per request and have nothing to do with row count.

A budget loose enough to admit them is loose enough to hide a real per-row query on a small
fixture. Warming first and comparing two sizes measures only the row-dependent part, which
is the part that matters. Keep that in mind before "simplifying" this back to a threshold.

## Why the warm-up uses the *small* set

Step 2 above warms with the 5-record set, never the 25-record one, and that is the property
that makes the whole thing robust. A cache left warm from the warm-up can only ever cover
those 5 ids; the other 20 are still cold in the large pass, so a per-row query still shows
up as slope.

That matters because `reset_record_caches()` clears
`Utils_RecordBrowserCommon::$record_cache` and `$record_info_cache` **and nothing else** —
schema-level caches stay warm on purpose (they are row-count-independent, so clearing them
would add the same constant to both passes and blur the comparison), and so do module-level
statics like `CRM_ContactsCommon::get_company()`'s own per-id cache.

This was checked rather than assumed. Dropping `record_cache` from the reset list *and*
stubbing out `prefetch_records()` still reports the regression:

```
N+1   A1.4 get_company() over a grid page    5 rows: 0 queries   25 rows: 20 queries
```

The small pass reads 0 — `get_company()`'s own static still holds those 5 — and the large
pass pays 20. Slope survives a partially-warm cache, which is exactly what you want from a
check other people will extend without reading this file.

It also explains an output that otherwise looks wrong: a healthy scenario can legitimately
report *fewer* queries than you expect (`0`, or `1` where you predicted 2) because a cache
outside the reset list absorbed part of the work. Only the comparison between the two
columns means anything. Do not read a single column as a page cost.

**The residual risk** is a cache keyed on something that is not per-row — per user, per
category, whole-tab — since one warm-up fills it for every id at any set size. Watchdog's
`(user, category)` cache is that shape, which is why its scenario reads `1 / 1`. A per-row
query hidden *behind* such a cache would not show slope; it also would not be an N+1 in the
first place, so this is a narrow gap rather than a hole.

**Either way, confirm a new scenario can fail.** Stub out the prefetch it is meant to
protect, run the command, see `N+1`, put it back. A scenario that passes on the day you
write it is not evidence; the only evidence is watching it fail. That is how the current
five were checked — with both prefetch functions disabled the command reports
`5 rows: 10 queries / 25 rows: 50 queries` for `get_record_info()` and exits non-zero.

## Adding a scenario

`scenarios()` returns `name => ['ids' => fn($n), 'run' => fn($ids)]`:

```php
'A1.4 get_company() over a grid page' => array(
    'ids' => fn($n) => $this->ids_from('company', $n),
    'run' => function ($ids) {
        Utils_RecordBrowserCommon::prefetch_records('company', $ids);
        foreach ($ids as $id) CRM_ContactsCommon::get_company($id);
    },
),
```

- **`ids`** takes a row count and returns ids, or `null` to skip. `ids_from($tab, $n)`
  handles the usual case and already returns `null` when the recordset does not exist or
  holds fewer than two records.
- **`run`** should mirror the *real* call sequence — the prefetch the framework does, then
  the per-row calls the display code makes. If you only write the prefetch, you are testing
  nothing; if you only write the loop, you are asserting the unfixed behaviour.
- Some helpers memoize by something other than record id, so a scenario can legitimately
  settle at a low constant in both columns: the Roundcube one reads `0 / 0` (its
  mail-account check is memoized per user and the warm-up already paid for it), Watchdog
  reads `1 / 1`. Both are correct flat results, and a per-row query keyed on the row id
  would still show slope through either.
- Anything needing a logged-in user should set one explicitly (`Acl::set_user(...)`); there
  is no session here.

## What it does not cover

**Whole-page query counts.** `Epesi::process()` renders from module-tree state that lives
in a browser session, so a faithful page render needs a browser. Page totals stay a manual
measurement — the procedure is in [performance-profiling.md](performance-profiling.md), and it no
longer needs a `config.php` edit now that a super-admin can switch the SQL panel on for their
own session.

**CI.** It needs a populated database, so this is a local pre-push check, not a CI job.

**Correctness.** This is a performance guard only. There is still no test suite — see
`CLAUDE.md`'s Tests note.
