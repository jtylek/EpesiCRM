# Speeding up epesi: caching and other findings

Measured on 2026-10-02 on the XAMPP development machine: Windows, Apache with mod_php 8.2 and
OPcache, MySQL, the demo data set. Everything in "What was done" is implemented. "Still open"
lists what isn't.

## Result

Server time per page through Apache, the median of five warm requests, signed in as an employee
(the Modules page as the super admin). The numbers vary by about ±10 % between runs.

| Page | Before | Now, an installation |
|---|---|---|
| Login | 240 ms | 100 ms |
| Dashboard | 320 ms | 180 ms |
| Calendar | 340 ms | 165 ms |
| Companies list | 495 ms | 290 ms |
| Contacts list | 485 ms | 305 ms |
| Tasks list | 470 ms | 290 ms |
| Administration → Modules | 380 ms | 180 ms |

Queries per list page, before and now:

| List | Before | Now |
|---|---|---|
| Companies | 46 | 25 |
| Contacts | 49 | 27 |
| Tasks | 42 | 29 |
| Phone calls | 35 | 27 |

"Now" is an installation with the caches built by cron. A development checkout gets the query
savings and the smaller fixes, but builds no caches (see below), so its pages stay about
80-100 ms slower.

What each cache contributes, measured one at a time:

| Cache | Saving per request |
|---|---|
| Blade Icons manifest (`icons:cache`) | 90-100 ms |
| Filament panel components (`filament:cache-components`) | 30-40 ms |
| Routes (`route:cache`) | 10-20 ms |
| Config, events | within noise |
| Compiled views (`view:cache`) | none: Blade compiles a view on first use anyway, so it isn't built |

Without the Blade Icons cache, Blade Icons lists the SVG files of every icon set on every
request.

## What was done

### 1. Cached routes and installations in a folder

With routes cached, `GET /epesi/` used to answer 405 Method Not Allowed in an installation in a
folder (XAMPP's `htdocs/epesi`, a shared host's `public_html/crm`). Laravel's
`CompiledRouteCollection::match()` strips the trailing slash before matching. `/epesi/` becomes
`/epesi`, which no longer starts with the script's folder `/epesi/`, so Symfony finds no base URL.

`App\Routing\CompiledRouteCollection` skips the trimming for the root path.
`App\Routing\Router` loads cached routes into it, and `bootstrap/app.php` binds that router.
Nothing resolves the router earlier, so `app('router')`, `app(Router::class)` and the HTTP
kernel share one instance. `OptimizeTest` checks that Laravel's own collection still fails, so
the fix can go once Laravel fixes it.

### 2. Caches built by cron

`App\Support\Optimize\FrameworkCaches` builds Laravel's config, event and route caches,
Filament's component cache and the Blade Icons manifest.

- **Switch:** `config('optimize.enabled')`, from `EPESI_OPTIMIZE`. By default it is on when
  `APP_ENV=production` and the folder is not a git checkout. A cached config outlives
  `phpunit.xml`, so tests in a checkout would use the MySQL database. Existing installations
  need no `.env` change.
- **Who builds:** only cron, through `epesi:optimize` every minute (`routes/console.php`). It
  builds only in a process that started with none of the caches. The process that installs a
  module or writes `.env` can't build them: its panels and module registry predate the change.
  Filament's and Blade Icons' commands exist on the command line only, and the cron URL runs
  in a web request, so `build()` calls their code directly.
- **Who forgets:** whatever changes what the caches hold calls `FrameworkCaches::forget()`:
  `ModuleInstaller::afterChange()`, `SystemUpdate::syncManifests()` and `MailConfig::write()`.
  The next cron run builds them again.
- **Changes nobody announced:** cron keeps a fingerprint of the files the caches depend on.
  That covers `app/`, `config/`, `routes/`, `modules/`, `.env`, `VERSION`, `bootstrap/app.php`,
  the module registry, the release id, Composer's `installed.json` and the memcached probe
  result. When the fingerprint changes, cron forgets the caches, and the run after that builds
  them. A walk over about 900 files takes about 0.3 s on Windows.
- **Per request:** `FrameworkCaches::guard()` in `bootstrap/app.php` runs before the config
  loads. It forgets the caches when `.env` is newer than the cached config, or when the
  unpacked release has a different build id than the caches. `epesi:package` writes a unique
  id into every zip as `bootstrap/release-id`, which is git-ignored. So the first page load
  after unpacking a release already runs on the new files. The guard costs two `stat()` calls
  and two small includes. Caches somebody built by hand (`php artisan optimize`, no state file)
  are left alone by the guard. Cron replaces them with its own.
- **State:** `bootstrap/cache/epesi-optimize.php` holds the fingerprint, the release id and the
  build time.
- **By hand:** `php artisan epesi:optimize` does what cron does. Run it twice after changing
  files when you can't wait. `--clear` removes the caches.

Verified end to end in a copy served by Apache. Cron built every cache and the pages loaded
from them, including the folder's home page. A new release id and an edited `.env` each dropped
the caches on the next request, and the next cron run built them again.

### 3. Memcached when available

`CACHE_STORE=auto` means memcached when it works, and the file store otherwise
(`App\Support\Optimize\CacheStore`). A fresh installation's `.env` gets `auto` from `FirstBoot`.

- **Cron decides, not the page.** Asking memcached is a network call, and on Windows a refused
  connection can take seconds. `epesi:optimize` probes it with the store's own settings: it
  stores a value, reads it back, and writes the answer to `storage/framework/epesi-memcached.json`.
  It probes every minute while memcached answers, and every ten minutes while it doesn't.
  `config/cache.php` reads that file. Without cron, `auto` stays on files.
- **When memcached stops answering,** the next probe writes "no". That changes the
  fingerprint, so the cached config, which names the store, is dropped. The next page uses
  files. `epesi:optimize` takes no `withoutOverlapping()` lock, since that lock lives in the
  cache store.
- **Clock-proof expiry.** Laravel sends memcached an expiry as a Unix time. The memcached 1.4.4
  Windows build on this machine reports a time in 1987, so every entry with a lifetime expired
  at once, while `forever()` worked. `App\Support\Optimize\RelativeExpiryMemcachedStore`
  replaces the `memcached` driver (`bootstrap/app.php`) and sends lifetimes of up to 30 days
  as seconds from now. Memcached reads such a value as relative on any server, whatever its
  clock.
- **One server, several epesi.** The default `cache.prefix` now includes a hash of the
  installation's folder, so two installations on one memcached don't read each other's entries.
- **Eviction-safe Common Data.** Memcached may evict a key while older ones survive. The Common
  Data cache version was a count from 1, so after the version key was evicted, it could read old
  entries back. It is now a time in milliseconds. Its keys are hashed, because a path may hold
  spaces, which memcached keys can't. Entries expire after a day instead of never, so retired
  versions don't pile up in the file store.
- **Speed:** on this single machine, memcached and the file store measured the same. The file
  cache on a local disk is already fast, and the caches above matter far more. Memcached pays
  off with several web servers, a slow or network disk, and less file locking under load.

An installation older than this has `CACHE_STORE=file` in `.env`. Change it to `auto` to use
memcached.

### 4. Compression and browser caching

In `public/.htaccess`:

- `mod_deflate` compresses HTML, CSS, JavaScript, JSON and SVG.
- `mod_headers` caches static files for a week. A file whose name carries a content hash gets
  a year with `immutable`: Vite's `build/assets/` and Filament's Inter fonts.

The rules use plain `FilesMatch` blocks without Apache 2.4 expressions, which some hosts'
servers refuse with an error on every page. Filament's own files carry `?v=<version>`, so a
week of caching is safe for them.

| Response | Plain | Gzipped |
|---|---|---|
| theme CSS | 639 KB | 66 KB |
| calendar JS | 283 KB | 84 KB |
| Filament `support.js` | 145 KB | 50 KB |
| Login page HTML | 83 KB | 13 KB |
| Companies list HTML | 530 KB | 41 KB |

### 5. Application code

- **E-mail links:** the Companies and Contacts lists ran one query per row.
  `EmailAddress::url()` reads its owner, which each item loaded again. The collection relation
  in `HasCollections::collection()` is now marked `chaperone('owner')`, so loading the items
  hands each one its owner. This also fixes the View page. Eloquent's recursion guards cover
  the owner-item-owner cycle in `toArray()` and in Livewire's serialization.
- **Relation, Relations and Customer columns:** they read each row's related records one row
  at a time, for example the Tasks list's Employees and the Phone calls list's Customer.
  `Field::preloadOnPage($column, $relationship)` loads the relationship for the whole page
  from the column's `state()` closure. `relationColumn()`, `customerColumn()` and
  `PhoneCallResource`'s own Customer column call it. A module's custom column that reads a
  relationship should call it too.
- **Database-update check:** `SystemUpdate::waiting()` runs on every signed-in page load. It
  used to read the `modules` table, every `module.json`, the `migrations` table's existence and
  rows, and every migration folder, which took 15-30 ms. It is now cached under a cheap
  fingerprint: the size of the two tables, the `module.json` times and the migration folders'
  times. Running a migration or syncing a manifest changes the fingerprint, so nothing needs to
  forget it.
- **Login tracking:** `TrackLoginAudit` writes `ended_at` at most once a minute per session,
  with no query at all in between. Every Livewire request passes through it, including the
  Shoutbox's 10-second polls.
- **Appearance:** the theme and the application name are kept per request with `once()`.
  Saving a theme, a user's theme choice or the name flushes them. That saves 4-5 queries per
  page.
- **Schema checks:** `ListRecords` remembers per process whether a table has `created_by`. That
  check was an `information_schema` query on every list request. `FieldOverrides::all()`
  queries its table directly and treats an error as "not created yet", instead of calling
  `Schema::hasTable()` first.

### 6. Production php.ini

The settings live in the server's `php.ini`, so epesi recommends them and shows how the server
compares, rather than setting them. OPcache's sizes are fixed when PHP starts: neither
`.htaccess`, `.user.ini` nor `ini_set()` can change them, and on shared hosting only the host
can.

- **`config/php-production.ini`** ships in the release zip. It holds the recommended lines with
  comments, ready to paste into `php.ini`, and says where that file is on Linux, XAMPP, cPanel
  and DirectAdmin. It is in `config/`, not at the top: an RC2 updater refuses a zip with a
  top-level file it doesn't know (`CorePackage::FILES`). Laravel loads only `*.php` from `config/`.
- **`App\Support\Optimize\PhpSettings`** holds the same values, how to compare each one, and why
  it matters. `OptimizeTest` fails when the file and the class disagree.
- **Administration → Server Check** has a "PHP settings" section (beside the requirements, web
  server, PHP and extension details): each setting with this server's
  value, the recommendation and the reason, read in the web server's PHP. It is collapsed when
  everything is met.
- **The setup wizard's server check** lists each setting below the recommendation as a warning,
  never a blocker. It does so only in the browser, since the command line's `php.ini` may
  differ.

| Setting | Recommended | Why |
|---|---|---|
| `opcache.enable` | on | Without it, every request compiles epesi's PHP files again |
| `opcache.memory_consumption` | 256 | 106 MB was in use here with two copies of epesi loaded |
| `opcache.interned_strings_buffer` | 32 | 12.8 of 16 MB was in use |
| `opcache.max_accelerated_files` | 20000 | epesi has about 15,000 PHP files, compiled views included |
| `opcache.validate_timestamps` | on | Cron rebuilds the caches from the command line, which can't reset the web server's OPcache. With timestamps off, the web server keeps the old cache files |
| `realpath_cache_size` / `realpath_cache_ttl` | 4096K / 600 | Fewer file-system lookups per request |
| `memory_limit` | 256M | Imports, long lists and setup |
| `zend.assertions` | -1 | No assertion code in compiled files |
| `display_errors` / `expose_php` | Off / Off | Errors go to the log, and responses don't name the PHP version |

On Windows with XAMPP, Apache's `mpm_winnt_module` block also needs `ThreadStackSize 8388608`
once OPcache is on, or every worker thread crashes. The file says so.

This XAMPP machine, measured against it: OPcache memory, interned strings and file count are
below the recommendation, as are `realpath_cache_ttl`, `zend.assertions` and `expose_php`.
The XAMPP `php.ini` was left unchanged: it serves every site in `htdocs`.

Log level: a production `.env` should set `LOG_LEVEL=warning`, since `debug` writes every debug
message. The bim.epe.si production `.env` already does. The server's `.env` is
edited by hand there and never pushed, so check it after a first deploy.

### 7. Record pages, global search and list sorting

A second pass looked at what a person still feels after the caches: a record's View page, the
search box, and lists with real data volumes. Each change below is implemented.

- **Field lists are built once per request.** A Company View page rebuilt every resource's field
  list about eleven times, once per addon tab through `IncomingLinks::for()`, and
  `resolvedFields()` re-ran `fields()`, the custom fields and the overrides on every call.
  `app/Support/RequestMemo.php` (a `scoped` binding, so a Livewire request or queue job starts
  empty) now keeps the answers for the rest of one request. `FieldOverrides` and
  `CustomFieldRegistry` forget them when an override or custom field is saved. Field output that
  depends on the signed-in user is keyed by user id.
- **Per-record state is read once.** "Is it a favorite, watched, in my priority list" was asked
  by several widgets per View page, and the Watchdog badge ran twice per page. Each is memoized
  per request and invalidated by its own write.
- **The topbar's search check no longer builds every field list.** `Filament::isGlobalSearchEnabled()`
  reached `getGloballySearchableAttributes()`, which resolved all fields of every recordset on
  every page.
- **Global search costs less per keystroke.** A search needs a minimum length (one letter
  searched nothing useful across 19 resource groups), each group returns fewer results, and the
  mail result query reads only the columns its result row needs. Mail's `LIKE '%term%'` still
  scans the table; only a FULLTEXT index would fix that (see below).
- **Every CRM list has an index on its default sort column.** Companies, contacts, tasks,
  meetings and phone calls opened sorted by a column nothing indexed, so each page, search and
  page change sorted the whole table. One migration adds `(deleted_at, <sort column>)` to each,
  with `first_name` after `last_name` for contacts. On 100,000 rows per table (200,000 contacts)
  the database time of the default list query went from 39-159 ms to 0-1 ms, and `EXPLAIN`
  from `key NULL, Using filesort` to the new index. `recordset:check` now hints when a
  recordset's default sort column has no such index.
- **The command palette no longer ships every icon in every page.** It embedded a generated SVG
  for each navigation item in the page (about 31 KB raw, 5 KB gzipped, a tenth of a list
  page's transfer). It now copies the icon from the sidebar when it opens, so it relies on the
  sidebar being in the page.
- **Option lists load when they are needed.** `Field::select()` and `multiselect()` accept a
  closure that runs once, when a form, filter or label needs the options, so `fields()` itself
  runs no query. `recordset:check` reports a resource whose `fields()` does. Premium Accounting's
  currency, expense-category and numbering-series lists use it, which removes the queries its
  field lists ran on every page (they are in the "now" column below only for the core pass).
- **Mail is out of global search.** Its `LIKE '%term%'` over long columns scanned the table on
  every keystroke; the Mail module's own list search is unchanged.
- **The Premium lists are indexed too.** Expenses, Invoices, Projects, Rates and Timesheets each
  have a `(deleted_at, <default sort column>)` index, in their own modules' migrations.
  `recordset:check` flags a list that lacks one. A module's migration needs a name no other
  module uses: Laravel identifies migrations by name only, and the second of two with the same
  name is skipped without a word.
- **An optional background worker, off by default (first slice).** A separate
  `epesi-background` database queue, drained by `php artisan epesi:background:drain` from one extra
  host cron entry (bounded: `--max-time`, `--tries`, `--timeout`), moves the per-account unread-mail
  check off the cron run so a slow IMAP server no longer holds up later tasks. With the worker off
  (`EPESI_BACKGROUND_WORKER_ENABLED=false`, the default), or when queuing throws, the check runs
  synchronously as before. Administration → Server Setup → Background worker shows enabled, last
  successful drain, pending, reserved, oldest age and failed jobs, and a red warning when it is
  enabled but nothing has drained for five minutes. The job is unique per account, so a lagging
  drain cannot pile up copies, and the drain keeps a month of run records. Two traps found in review:
  `whereKey()` exists only on Eloquent builders (on a query builder it silently matches nothing on
  SQLite and is an unknown column on MySQL), and removing a "dead" method can take an import with it
  that another closure in the file still needs.
- **Cron's PHP is checked too.** The command-line PHP that runs `cron.php` can be another
  binary than the web server's (on cPanel, an `alt-php` build can lack mbstring until it is
  enabled), and cron then died inside Laravel with "undefined function mb_split()" while the web
  pages worked. `cron.php` now runs `bootstrap/preflight.php` first; on a failure it prints what
  is missing, leaves a note, and Server Check shows a failing "PHP that runs cron" row until the
  next clean start. A crontab line that calls `artisan` directly is not covered (see
  [cron](cron.md)).

Measured on the development database (466 companies) through Apache with OPcache, in two throwaway
copies of the code (the commit before this pass, 60759e5, and after it, e43cd53), `CACHE_STORE=file`,
`APP_DEBUG=false`, one request per call to a small `bench.php` that signs in the super admin
(see "How it was measured"). Alternating requests, warm; the machine was shared with other work,
so single requests vary by 20-40 %. "Best" is the fastest of 5 warm requests per side for the
View pages and search, and of 14 for the lists; the query counts are exact and the same on every request.

| Page | Best ms before | Best ms now | Queries before | Queries now |
|---|---|---|---|---|
| Company View | 963 | 624 | 253 | 79 |
| Contact View | 881 | 516 | 226 | 70 |
| Task View | 467 | 394 | 83 | 56 |
| Dashboard | 273 | 294 | 26 | 23 |
| Companies list | 331 | 324 | 28 | 25 |
| Contacts list | 391 | 419 | 36 | 33 |
| Tasks list | 382 | 374 | 36 | 33 |
| Global search "kowalski" | 392 | 242 | 95 | 71 |

The View pages and search got 20-40 % faster in real time, in step with their queries; Company
View is the clearest, at about a third of its queries and a third less time. The lists and the
dashboard did not change measurably (the differences are inside the noise): they only lost
three queries, and a list's time is Filament rendering the table (see "Still open"). The query
counts here are higher than the ones from the command line earlier (77 for a Company View) because
`bench.php` counts every query of the request, including the sign-in and panel boot. Premium modules
were present in both copies, so Accounting's `fields()` queries are included in "now".

An early measurement of these changes reused one application instance, so the memo stayed warm
between requests and the "after" side looked better than it is; measure each request with fresh
scoped state.

Two things to keep in mind when measuring. The development `.env` uses the database for cache
and sessions and sets `APP_DEBUG`, which adds four to nineteen `select * from cache` queries per
page and makes development numbers look worse than an installation with `CACHE_STORE=auto`.
And these checks used scratch copies: a scratch database on the same server for the index
numbers, never the live one.

## Stale data on a page is not a server cache (checked 2026-10-12)

The question: a note or ticket added from an addon tab showed up only after a page reload, and
commits `c0605a5` and `5742793` fixed that by making the page redraw. Is `epesi:optimize` still
needed then, and should a database-driven application like epesi cache less?

**Answer: keep `epesi:optimize`. The stale pages had nothing to do with it, and it caches no
records.**

### What the stale pages actually were

Both causes were in the browser, inside Livewire:

1. **Addon tabs are Livewire components of their own.** A View page's relation managers (Notes,
   Mail, Tickets…) are separate Livewire components. When an action on the page saved
   something, Livewire re-rendered the page component, which leaves child components alone. So
   the Notes tab kept the HTML it had. Fix: the action dispatches `addon-changed`
   (`RecordBrowserServiceProvider`), and `resources/views/filament/components/addon-refresh.blade.php`
   sends `$refresh` to every loaded relation manager.
2. **Back/Forward restores a snapshot.** `wire:navigate` keeps a copy of each page it showed in
   the browser's memory, and Back/Forward puts that copy back without asking the server. The
   same view component flags a `popstate` and refreshes every Livewire component on the page
   that comes back.

Clicking a link, or any Livewire request, always ran fresh queries against the database. No
server-side cache stood between the database and the page.

### What `epesi:optimize` caches

Only what is fixed until files change. None of it is a database row:

| Cache | Holds | Changes when |
|---|---|---|
| config | `config/*.php` merged with `.env` | `.env` or config changes |
| routes | the route table | code or modules change |
| events | listener discovery | code changes |
| Filament components | which resources, pages and widgets each panel has | a module is installed, enabled or removed |
| Blade Icons manifest | the list of SVG files in each icon set | a release |

It also probes memcached for `CACHE_STORE=auto`. Without these caches every page costs about
80-100 ms more (the table in "Result"), and the stale-page problem would be exactly the same.
Each one is dropped when the files behind it change (fingerprint, `guard()`, `forget()`, see
section 2).

### The application data epesi does cache

The whole list, from a search of `app/` and `modules/` for `Cache::`, `cache()`, `remember` and
`once()`:

| What | Where | Kept | Can it show old data? |
|---|---|---|---|
| Common Data lists (dropdown choices) | `CommonDataRepository::remember()` | a day | No: every save or delete of a `CommonDataNode` calls `invalidate()` through the model's events. Only a mass `update()`/`delete()` on the query builder would skip those events, and nothing does that today |
| Database-update check | `SystemUpdate::waiting()` | a day | No: keyed by a fingerprint that a migration or manifest change alters |
| Unread mail counts | `UnreadCounter` | minutes | Yes, by design: asking every IMAP server on each page would make the dashboard wait on them. Cron and the widget's 3-minute poll update it |
| PayPal access token | `PayPalClient` | the token's lifetime | Not data |
| Theme, app name, logo, compose button | `once()` | one request | No |

Records themselves (companies, contacts, tasks, notes, tickets, history) are never cached. Every
list, View page and addon tab queries the database.

HTML is not cached either: `public/.htaccess` gives browser caching only to CSS, JS, fonts and
images, by file extension, and those carry a version or content hash in their name.

### So: switch caching off?

There is nothing that holds records to switch off. Turning off the framework caches would cost
every page 80-100 ms and fix nothing. If a page shows old data again, look first at:

- **A child Livewire component** that a parent's re-render doesn't reach: an addon tab, a
  widget, a modal with its own component. Dispatch `addon-changed`, or an event the component
  listens to with `#[On(...)]`.
- **A Livewire property computed once** in `mount()` and kept in the component's state across
  requests, where a `#[Computed]` property or a query in `render()` would read it again.
- **The `wire:navigate` page snapshot** for a way of coming back to a page other than
  Back/Forward.
- **A write that skips model events** (query-builder `update()`/`delete()`), which bypasses
  Common Data's `invalidate()` and the activity log alike.

## Still open

- **List page weight, deferred loading and prefetch: tried, none kept.** Measured on the
  Contacts list (10 rows, local demo data, a manager's account), command line without OPcache
  and in a browser against PHP's built-in server, so the milliseconds are relative:
  - *Where the time goes.* An empty list costs about 195 ms, the Dashboard 185 ms, and 10 rows
    add about 90 ms: the page around the table dominates, not the rows. The 523 KB of HTML is
    43% indentation whitespace (gzip: 48 KB); 143 inline SVGs are 78 KB of it, 72 of them in the
    sidebar and topbar, and 6 per row (View, Edit, Favorite with its spinner, two badge icons).
  - *Fewer row icons or actions.* Dropping all three row actions removes 20 SVGs and 25 KB
    and saves about 10 ms (4%, best of five rounds of ten runs: 257-269 ms against 238-265 ms).
    That does not pay for losing View, Edit and Favorite, and a `<svg><use>` sprite would save
    less, so the rows stay as they are.
  - *`deferLoading()`* on the shared table: first paint came 290 ms sooner (944 → 652 ms) and
    the HTML shrank to 270 KB, but the rows appeared 340 ms later (1456 → 1795 ms median of
    eight loads). A CRM list is opened for its rows, so the page got slower where it matters.
    Not kept.
  - *`->spa(hasPrefetching: true)`* fires one full page request per hovered record (four
    requests for eight hovers) and did not shorten click-to-View: about 950 ms with it and
    930 ms without. Not kept.
  - Breaking down the base 180-200 ms still needs a profiler (Xdebug's or SPX); none is
    installed here.
- **Global search in Mail:** Mail is deliberately left out of Filament's global search. Its own
  list search still covers subject, sender, recipients and body text, while global search no
  longer sends a leading-wildcard query over long mail columns on every keystroke. A FULLTEXT
  migration was not added: MySQL and MariaDB differ in parser and ranking behavior, SQLite
  tests would need a separate implementation, and a subject-only global result would be less
  useful than the module's existing search. Revisit this only with an explicit ranking and
  engine-support contract.
- **Sorting by a collection column** (e-mail, phone, address) runs one subquery per row: about
  1-1.5 s of database time at 100,000 companies, and an index changes it by about a tenth.
  Showing the column is cheap. If sorting becomes a requirement, keep one canonical primary
  value on the owner row and update it in the collection save/delete path (including legacy
  backfill and custom-field semantics); do not make an arbitrary collection item the sort key.
  The owner value must follow the same normalization and visibility rules as the displayed
  field, and clearing the primary item must promote the next deterministic item or null. Until
  that invariant exists, collection-backed columns should remain display-only rather than
  issuing a correlated subquery per row. The full design (stored `cs_<field>` column, writer,
  backfill, custom fields, the alternative) is in
  [Collection-column-sort](Collection-column-sort.md).
- **A list's pagination `COUNT(*)`** takes about 30 ms at 100,000 rows, and jumping to a page
  deep in the list 400-500 ms. Neither is worth changing before the items above.
- **Polling is not worth changing.** Shoutbox (10 s), Reminders (60 s) and unread Mail (180 s)
  cost about 7 requests and 11 queries a minute per foreground tab; Livewire already stops a poll
  in a background tab unless `.keep-alive` is set, and none of them sets it. Only the foreground
  tab of each user pays, about 12 requests a second for 100 users at the very most. The two
  optional refinements are `.visible` on the three polls and a change check on the Reminders
  table like the Shoutbox's. (Command-line milliseconds, exact query counts.)
- **`livewire.min.js` comes through PHP**, which takes about 240 ms, once per browser per
  release. `php artisan livewire:publish --assets` would let Apache serve it, but every Livewire
  update would then need a republish.
- **HTTP/2** (`mod_http2`) would load a page's dozen CSS and JS files in parallel. It is a server
  setting.
- **Queue:** `QUEUE_CONNECTION=sync` sends notification mail inside the request that triggered
  it, and stays the default. The optional worker (below, section 7) moves only the unread-mail
  check off the cron run. The next candidates, in the order of
  [Background workers](Background-workers.md): the Sent-folder copy after a mail is sent,
  archive-on-send, and Watchdog notifications (measure the listener first). Do not switch the
  global queue connection on an installation that has no consumer.

## How it was measured

Building caches in this checkout would disturb the other sessions working in it, and a cached
config would send their test runs to MySQL. So the measurements ran in a throwaway copy of the
checkout in another `htdocs` folder, served by Apache with its real OPcache. A small `bench.php`
in the copy's `public/` signed a user in with `Auth::guard('web')->setUser()`, handled the page,
and returned the time, the query count and the cache store in a response header. Bind the
request into the container before signing the user in, or code that reads the request during
the sign-in fails. Command-line
timings are no substitute: `opcache.enable_cli` is off, and the command line ran about three
times slower than Apache.

When copying for such a test, copy files (`tar`, `cp`), never link folders back into the
checkout. Deleting a copy that holds a junction to the checkout's `public/build` deleted the
checkout's built assets once.

## Files

| File | What it is |
|---|---|
| `app/Routing/Router.php`, `app/Routing/CompiledRouteCollection.php` | cached routes that find a folder installation's home page |
| `app/Support/Optimize/FrameworkCaches.php` | builds, checks, forgets the caches; `guard()` |
| `app/Support/Optimize/CacheStore.php` | `CACHE_STORE=auto` and the memcached probe |
| `app/Support/Optimize/RelativeExpiryMemcachedStore.php` | memcached expiry independent of the server's clock |
| `app/Console/Commands/EpesiOptimize.php` | `epesi:optimize`, scheduled in `routes/console.php` |
| `config/optimize.php` | the switch |
| `php-production.ini`, `app/Support/Optimize/PhpSettings.php` | the recommended `php.ini`, and the comparison on Administration → About and in the setup wizard |
| `bootstrap/app.php` | router binding, memcached driver, `guard()` |
| `public/.htaccess` | compression and browser caching |
| `tests/Feature/OptimizeTest.php` | routes, caches, guard, probe, expiry, login audit |
| `tests/Feature/Modules/ListQueriesTest.php` | no per-row queries on list pages |
| `tests/Feature/SystemUpdateTest.php` | the cached database-update check |
| `app/Support/RequestMemo.php` | answers kept for the rest of one request |
| `database/migrations/*_add_list_sort_indexes_to_crm_tables.php` | the default-sort indexes of the five CRM lists |
| `tests/Feature/ListSortIndexesTest.php` | the indexes exist; `recordset:check` hints when one is missing |
| `tests/Feature/GlobalSearchTest.php` | the search's minimum length and per-group limit |
| `tests/Feature/CommandPaletteTest.php` | the palette opens and takes its icons from the sidebar |
| `config/epesi-background.php`, `app/Console/Commands/DrainBackgroundQueue.php`, `app/Support/Background/BackgroundWorkerHealth.php` | the optional queue, its bounded drain, and the health numbers |
| `modules/Epesi/Mail/src/Jobs/UnreadMailCheck.php`, `Services/UnreadMailCheckDispatcher.php` | the unread check as a unique, ID-only job, and the dispatcher with its synchronous fallback |
| `tests/Feature/BackgroundWorkerTest.php` | queuing, uniqueness, health, overdue warning, pruning, fallback, the drain no-op |
| `bootstrap/preflight.php`, `cron.php`, `Requirements::cronNote()` | cron's PHP is checked before Laravel starts; the note Server Check shows |
| `tests/Feature/CronPreflightTest.php` | `cron.php` under a PHP without extensions says so and leaves the note; a clean start removes it |
