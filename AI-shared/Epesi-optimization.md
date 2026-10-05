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

## Still open

- **`LOG_LEVEL=debug`** in a production `.env` writes every debug message. Use `warning`.
- **The remaining 250-300 ms of a list page** is Filament rendering the table: about 150 inline
  SVG icons and 530 KB of HTML. Breaking it down needs a profiler (Xdebug's or SPX), and none is
  installed here. Filament's `deferLoading()` would show the page first and the table in a
  second request, which only improves perceived speed.
- **`livewire.min.js` comes through PHP**, which takes about 240 ms, once per browser per
  release. `php artisan livewire:publish --assets` would let Apache serve it, but every Livewire
  update would then need a republish.
- **HTTP/2** (`mod_http2`) would load a page's dozen CSS and JS files in parallel. It is a server
  setting.
- **Queue:** `QUEUE_CONNECTION=sync` sends notification mail inside the request that triggered
  it. A `database` queue worked by `cron.php` (`queue:work --stop-when-empty`) would take that
  off the page.

## How it was measured

Building caches in this checkout would disturb the other sessions working in it, and a cached
config would send their test runs to MySQL. So the measurements ran in a throwaway copy of the
checkout in another `htdocs` folder, served by Apache with its real OPcache. A small `bench.php`
in the copy's `public/` signed a user in with `Auth::guard('web')->setUser()`, handled the page,
and returned the time, the query count and the cache store in a response header. Command-line
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
