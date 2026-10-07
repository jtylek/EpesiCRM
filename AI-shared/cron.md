# Cron: running epesi's scheduled tasks

epesi does its background work through Laravel's scheduler. As in Epesi, the server's cron runs
`cron.php` every minute, and `cron.php` runs whichever tasks are due. A host that can only call a
web address uses the **cron URL** instead, as Epesi's `cron.php?token=…` was used. Laravel's own
`php artisan schedule:run` works too.

**Administration → Cron** shows whether cron runs, the line to set it up with this installation's
own paths, the cron URL, and every task with its last run, result and output. Tasks can be run
from there as well.

Why `cron.php` rather than `schedule:run`, above all on shared hosting:

- **No `proc_open` needed.** `schedule:run` starts every task as a PHP process of its own, and
  on a host that disables `proc_open` nothing runs, with no error anyone sees. `cron.php` runs
  each task inside its own process.
- **It catches up.** On a host whose cron runs every 15 minutes at odd minutes, `schedule:run`
  never hits 00:00, so the nightly tasks never run. `cron.php` runs any task whose due time has
  passed since it last ran.
- **Once a minute at most** per task, however often cron is called.
- **From any folder.** It needs no `cd` into the epesi folder first.

That one entry is what makes the tasks below run at all — without it, nothing in "What runs"
happens, whatever else is configured. It's a separate thing from the queue, and epesi needs no
queue worker either: notifications and reminders are sent straight away, not queued. `.env` has
`QUEUE_CONNECTION=sync` (also the default in `config/queue.php`), so anything that does queue
work — Laravel's, Filament's or a module's — runs at once, within the request (or within a
cron-triggered task), instead of waiting in the `jobs` table for a worker that never comes.
Cron drives the scheduled tasks; `sync` just means nothing needs a second background process
(a queue worker) on top of it.

An install set up before `sync` became the default has `QUEUE_CONNECTION=database` in `.env`.
Change it to `sync`. Keep `database` only if you run a queue worker yourself
(`php artisan queue:work`, kept running by a process supervisor). Either way, cron still has to
be set up — that setting doesn't replace it.

## What runs

This is what `php artisan schedule:list` shows on an installation with every module enabled:

| Task | When | From | What it does |
|---|---|---|---|
| `reminders:send` | every minute | Reminders module | Delivers due reminders to the bell, and by e-mail if asked |
| `mail:fetch` | every 5 minutes (`MAIL_FETCH_SCHEDULE`) | Mail module | Archives mail from each account's `CRM Archive` IMAP folder, and INBOX/Sent where auto-archive is on |
| `model:prune --model=…\Subscription` | daily at 00:00 | Watchdog module | Deletes watched-record subscriptions with nothing happening for 90 days (see [Watchdog_and_Notifications.md](Watchdog_and_Notifications.md#subscriptions-lapse)) |
| `demo:reset --force` | daily at `DEMO_RESET_AT` (in `DEMO_TIMEZONE`), and every 15 minutes while a reset is unfinished | core, demo mode only | Puts the demo data back (see [Demo-mode.md](Demo-mode.md)) |
| `epesi:optimize` | every minute, on an installation (not a git checkout) or with `CACHE_STORE=auto` | core | Builds Laravel's and Filament's caches, rebuilds them after a change, and picks memcached when it works (see [Epesi-optimization.md](Epesi-optimization.md)) |

- **Modules add their own tasks.** A disabled module's tasks disappear with it. Run
  `schedule:list` for the current list.
- **Times are in the app's timezone**, which is UTC (`config/app.php`), unless a task sets its
  own. So "daily at 00:00" means midnight UTC.
- **Demo mode only.** `demo:reset` is always listed, but it runs only while `DEMO_MODE=true`.

## Without cron

Only Administration → Cron says so: a red mark in its menu, and "Cron has never run", "Cron
isn't running on a schedule" or "Cron has stopped" on the page. Everywhere else, things just
don't happen:

- reminders are never delivered;
- mail is never fetched from IMAP. Uploading `.eml` files and sending from the CRM still
  archive mail;
- Watchdog subscriptions never lapse;
- a demo is never reset;
- the caches that make every page faster are never built, and memcached is never used.

## Setting it up

Administration → Cron shows the crontab line with this installation's paths, ready to copy, and
the cron URL. It shows only the crontab form, for Linux, which is where epesi is installed for
real. Task Scheduler on a Windows machine (a local XAMPP) is described below.

### Linux server or VPS

Add this line to the crontab of the user the web server runs as:

```
* * * * * php /var/www/epesi/cron.php > /dev/null 2>&1
```

- **Use the web server's user.** For example, `sudo crontab -u www-data -e`. If the tasks run as
  root, they create files under `storage/` (logs, the file cache, compiled views) that the web
  server can't write afterwards, and the web pages then fail.
- **Replace `/var/www/epesi`** with the epesi folder, the one that holds `cron.php`. No `cd` is
  needed: `cron.php` finds its folder itself.
- **Only from the command line.** `cron.php` answers 404 to a web request (it checks
  `PHP_SAPI`), in case a web server shows the epesi folder instead of `public/`. With the usual
  set-up it isn't reachable over the web at all: the root `.htaccess` rewrites every address
  into `public/`.
- **`php` must be PHP 8.2 or newer on the command line.** That isn't always the web server's
  version. Check with `php -v`, or write the full path (`/usr/bin/php8.3`).

### Shared hosting (cPanel, DirectAdmin, Plesk)

In the control panel's **Cron Jobs**, add one entry that runs every minute. Write the full
paths:

```
* * * * * /usr/local/bin/php /home/account/epesi/cron.php > /dev/null 2>&1
```

The PHP path differs between hosts (`/usr/bin/php`, `/usr/local/bin/php`,
`/opt/cpanel/ea-php83/root/usr/bin/php`). The cron page or the host's help usually names it.
The rest of a shared-hosting install is in
[Epesi-Laravel-distro.md](Epesi-Laravel-distro.md#setting-up-epesi-on-shared-hosting).

Some hosts allow cron no more often than every 5 or 15 minutes. `cron.php` catches up: a task
whose time has passed since it last ran runs at the next call, so reminders arrive up to that
many minutes late and nothing is skipped. `schedule:run` doesn't: it starts a task only when
its time matches the minute it is called in, and a cron at odd minutes (`3,18,33,48`) never
matches 00:00, so the nightly tasks never run.

How `cron.php` decides what is due (`CronRunner::isDue()`):

1. A task that already started in this minute doesn't run again.
2. A task whose schedule names this minute runs, as under `schedule:run`.
3. Otherwise it runs if the last time its schedule named (`CronRunner::lastDue()`) is later
   than its last run. A task cron has never run counts from when cron first saw it (the
   `created_at` of its `cron_tasks` row), so a new task waits for its next due time rather
   than running at once.
4. The task's own conditions still apply: `when()`/`skip()`, `environments()`, and maintenance
   mode unless `evenInMaintenanceMode()`.

### A host that can only call a web address: the cron URL

Some hosts' cron can only fetch an address, and some hosting has no cron at all. Then have the
**cron URL** called every minute, or as often as allowed. Administration → Cron shows it:

```
https://example.com/epesi/cron?token=<64 hex characters>
```

- **From the host's cron:** `wget -q -O /dev/null "<cron URL>"` or `curl -fsS "<cron URL>" > /dev/null`.
- **From an outside service** that calls an address on a schedule (cron-job.org, EasyCron and
  the like).
- **The token is the only lock.** Anyone with the URL can start cron (only the tasks that are
  due). It is 64 random hex characters (`random_bytes(32)`), compared in constant time
  (`hash_equals`), as in Epesi since its token was hardened. **New cron URL** on the page
  replaces it, and the old one answers 403 at once. It lives in
  `storage/app/private/cron-token.txt` (`config('cron.token_path')`, `CRON_TOKEN_PATH`), created
  the first time it's needed.
- It answers in plain text with what ran, 403 for a missing or wrong token, and 503 before
  setup. It has no session and sets no cookie (the route is outside the `web` middleware
  group).
- **The tasks run within the web request.** A slow one, such as fetching a lot of mail, keeps
  the request open. A service that stops waiting doesn't stop them (`ignore_user_abort`).
- It still answers in maintenance mode (`preventRequestsDuringMaintenance(except: ['cron'])` in
  `bootstrap/app.php`), and then runs only tasks marked `evenInMaintenanceMode()`, as the command
  line does.

### Windows (XAMPP, or a Windows server)

There is no cron, so use Task Scheduler. From a command prompt run as administrator:

```
schtasks /Create /TN "epesi cron" /SC MINUTE /MO 1 /RU SYSTEM /TR "C:\xampp82\php\php.exe C:\xampp82\htdocs\epesi-laravel\cron.php"
```

- **Replace both paths** with your PHP binary and your epesi folder. If a path contains
  spaces, it needs its own escaped quotes inside `/TR` (`\"C:\Program Files\...\"`). The Cron
  page doesn't show this command, only the crontab line.
- **`/RU SYSTEM`** runs the task whether or not anyone is signed in, and without a console
  window flashing up every minute. Without it, the task runs as you, and only while you are
  signed in.
- **To remove it:** `schtasks /Delete /TN "epesi cron" /F`.

### `php artisan schedule:run` instead

Laravel's own way works too, with the same tasks and the same Cron page:
`* * * * * cd /var/www/epesi && php artisan schedule:run > /dev/null 2>&1`. It starts each task
as a process of its own, which needs `proc_open` (see below), and it doesn't catch up.

### Development

On a development machine, run the scheduler in a terminal instead:

```
php artisan schedule:work
```

It calls `schedule:run` every minute until you stop it. `composer run dev` doesn't start it.
That script starts a queue listener, which epesi doesn't need.

## Checking that it works

**Administration → Cron** (`App\Filament\Administration\Pages\Cron`):

- **Whether cron runs on a schedule**, not merely whether the tasks ran. The page tells a run by
  cron from a run by hand, because both leave the same "Done" behind:
  - **"Cron is running"** (green) only while cron is called **regularly**: in at least 3
    different minutes of the last hour, the first and the last at least 10 minutes apart
    (`config('cron.regular_calls')`, `regular_minutes`), and the last call within
    `config('cron.late_after')` minutes (20). A cron that runs every minute turns it green 10
    minutes after it starts; one every 15 minutes, after its third call. It names how cron is
    called and how many calls there were in the last hour.
  - **"Cron isn't running on a schedule"** when the tasks ran some other way: from this page,
    from a terminal, or a few calls of the cron URL (say, opened in a browser). It says when and
    how they last ran ("manually, from this page"). If cron has been called lately but not yet
    regularly, it says so, for a cron that was just set up. Yellow for 20 minutes after the last
    run, red after.
  - **"Cron has stopped"** (red) only when the calls kept (48 hours) show cron did run on a
    schedule, and nothing has called it for 20 minutes.
  - **"Cron has never run"** (red) when nothing has run at all.

  The menu item has a red mark whenever it isn't green (`CronLog::runsOnSchedule()`).
- **Every task** with its schedule, next due time, last run and how it was started ("by cron",
  "through the cron URL", "from a terminal", "manually"), duration and result. A row opens what
  the task printed last time, or its error.
- **Run cron jobs manually** runs what is due, as a call of cron would. **Run now** on a row runs
  that task whatever its schedule, though its `when()`/`skip()` conditions still apply. Both are
  recorded as `via: 'browser'` and never count towards cron running: they answer "did the tasks
  run", not "is the server's cron set up".
- A task that doesn't apply here, such as the demo reset outside demo mode, isn't listed.

How a call was started (`cron_calls.via`, and each task's `cron_tasks.last_via`):

| `via` | Started by | Counts towards "running"? |
|---|---|---|
| `cli` | `cron.php` / `epesi:cron` with no terminal: the server's cron, Task Scheduler as SYSTEM | yes |
| `schedule:run` | `php artisan schedule:run` with no terminal, `schedule:work` | yes |
| `url` | the cron URL | yes |
| `terminal` | `cron.php`, `epesi:cron` or `schedule:run` typed in a terminal (`stream_isatty(STDIN)`) | yes, but alone it never looks regular |
| `browser` | the page's **Run cron jobs manually** and **Run now** | never |

One call looks the same whoever made it (someone opening the cron URL in a browser is a `url`
call), so "running" rests on the pattern of calls, not on the kind of the last one. Terminal
calls count so that Task Scheduler run as a user, which opens a console window and so looks
like a terminal, still turns the page green.

On the command line:

- `php cron.php` (or `php artisan epesi:cron`) runs whatever is due and prints what it ran.
- `php artisan schedule:list` shows each task and when it is next due.
- `php artisan schedule:test` lets you pick one task and run it now.
- **An end-to-end check:** set a reminder for yourself a few minutes ahead. It should arrive
  in the bell on time.

What the page shows is kept in two tables by `App\Services\Cron\CronLog`: `cron_tasks` (each
task's last run, and how it was started) and `cron_calls` (each call of cron, for the last 48
hours). It listens to the scheduler's own events (`ScheduledTaskStarting`/`Finished`/`Failed`,
and `CommandStarting` for `schedule:run`), so every way of running cron is recorded.

`cron_calls.started_at` was first created as a plain `NOT NULL` timestamp. MySQL and MariaDB give
a table's first such column `DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` unless
`explicit_defaults_for_timestamp` is on (XAMPP's MariaDB has it off), so finishing a call
overwrote its start with the database's own clock: two hours ahead of the app's UTC on a
database running on Polish time, and "Last run 1 hour from now" on the page. It is nullable
since `2026_09_30_010000_record_how_cron_ran`, which gets neither. SQLite, which the tests run
on, never does this.

**Confirmed working end-to-end in production**, 2026-09-26, on `nb.epesicrm.com` (a real,
non-demo install): a `* * * * * php /path/cron.php`
crontab line, `mail:fetch` and `reminders:send` both showing "Done" runs a minute apart, "Cron is
running" with 42 calls in the last hour, and `Cron::isRunning()` true from the database alone,
without ever clicking "Run cron jobs manually".

## Requirements and traps

- **`cron.php` runs every task in its own process** (`App\Services\Cron\CronRunner`). An
  Artisan command goes through the console kernel, a closure is called, and only a shell
  command (`Schedule::exec()`, which nothing uses so far) needs a process of its own. That is
  what lets the cron URL work at all: a web request has no command-line PHP to start.
- **`schedule:run` needs `proc_open`.** It starts every task as its own PHP process (Symfony
  Process), using the same PHP binary that runs `schedule:run`. Some hosts disable `proc_open`
  in `disable_functions`, and the command-line `php.ini` can differ from the web's. Then
  nothing runs, however the cron line is written. `cron.php` doesn't have this problem.
- **The crontab line's PHP path is a guess, not a fact.** `Cron::cronLine()` builds it from
  Laravel's `php_binary()`, which asks Symfony's `PhpExecutableFinder` for the running process's
  own executable. That only works from the command line: the Cron page is a Filament panel page,
  so it always renders from a web request, and a web SAPI's `PHP_BINARY` (LiteSpeed's `lsphp`,
  PHP-FPM's own binary, …) isn't something the finder will ever offer back as a CLI path — it
  falls back to the literal string `php`. Checked live on `nb.epesicrm.com` (2026-09-26): the web
  process's `PHP_BINARY` was `/opt/alt/php83/usr/bin/lsphp`, not runnable from cron at all, and
  the page duly showed a bare `php` — which happened to still resolve to a supported version
  (8.3) on that account, but on a host whose default `php` is older than 8.2, copying the shown
  line verbatim would silently install a cron job that can never run. The helper text under the
  line already says the path differs per host and to check the control panel; that's the real
  defence, not the line itself.
- **Tasks run one after another.** A slow task holds up the rest of that call. Under
  `schedule:run` a task marked `runInBackground()` (`mail:fetch`) doesn't, but it needs a shell,
  `sh` or `cmd`. Under `cron.php` it runs like any other, and the next minute's call runs the
  other due tasks meanwhile.
- **Once a minute at most.** However often `cron.php` or the cron URL is called, a task runs at
  most once a minute. "Run now" on the Cron page is the exception.
- **A crashed task can stay locked for a day.** `withoutOverlapping()` takes a lock in the cache
  (`CACHE_STORE`: `auto` on an installation, which is memcached or files). A task killed
  mid-run keeps its lock until it expires, 24 hours by default, and is skipped until then.
  `php artisan schedule:clear-cache` removes the locks. The cron and the web server must use
  the same cache store, which `auto` ensures: both read the same probe file. The `array` store
  would never lock at all. `epesi:optimize` takes no such lock, because it is the task that
  switches away from a memcached that stopped answering.
- **Maintenance mode stops everything but the demo reset.** While `php artisan down` is in
  effect, `cron.php`, the cron URL and `schedule:run` skip every task not marked
  `evenInMaintenanceMode()`. Only `demo:reset` is, because an unfinished reset is what keeps
  the demo down.
- **Before setup, cron does nothing.** `cron.php` prints "epesi is not set up yet." and exits
  successfully, so a cron job set up before the wizard runs does no harm.

## Adding a scheduled task to a module

Schedule it from the module's service provider, in `boot()`, as Reminders does:

```php
use Illuminate\Console\Scheduling\Schedule;

$this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
    $schedule->command('reminders:send')
        ->everyMinute()
        ->withoutOverlapping();
});
```

- **The provider, not `routes/console.php`.** A disabled module's provider doesn't boot, so its
  tasks go with it. `routes/console.php` is for the core app's own tasks (the demo reset).
- **Register the command outside `runningInConsole()`.** The cron URL and "Run now" on the Cron
  page run it within a web request, where a command registered only on the command line doesn't
  exist ("isn't registered here"). `$this->commands([...])` costs nothing until Artisan starts.
- **`withoutOverlapping()`** is for anything that could run longer than its interval.
- **`runInBackground()`** is for anything slow, so it doesn't hold up the other tasks.
- **Catch up rather than assume a run every interval.** Cron may have been down, or the host
  may run it only every 15 minutes. `reminders:send` sends every reminder that is due and not
  yet sent, not just this minute's.
- **Say that it needs cron.** Mention it in the module's row in [PORTING.md](../PORTING.md),
  and add it to the table at the top of this file.
- **Test that it is scheduled.** Look for it in `app(Schedule::class)->events()`, as
  `WatchdogTest` does.
- **Name a closure.** `$schedule->call(...)->name('Prune read bell notifications')` is what the
  Cron page shows for it. A command shows its own `$description` under its name, translated
  when the module's `lang/pl.json` has it.

### Testing scheduled tasks

- **Never run `schedule:run` on the real schedule in a test.** It starts each due task as a
  process of its own, and that process reads the app's own `.env`, so it runs against the
  development MySQL database, not the test's in-memory SQLite. `CronTest` puts an empty
  `Schedule` in the container first (`$this->app->instance(Schedule::class, new Schedule)`) and
  schedules only closures when it runs `schedule:run`.
- **A command run by the runner under `$this->artisan()` writes to the test's console, not to
  the runner's buffer**, because the test binds `OutputStyle` to its mock. To check what a task
  printed, call `app(CronRunner::class)->runDue(...)` directly.
- **`CommandStarting` isn't dispatched in unit tests.** Laravel reroutes Symfony's console
  events only outside tests, so a test of the `schedule:run` record calls
  `rerouteSymfonyCommandEvents()` and `setArtisan(null)` on the console kernel first.
- The token file goes to `storage/framework/testing/cron-token-<pid>.txt` (`Tests\TestCase`),
  never the app's own.

## Files

| File | What it is |
|---|---|
| `cron.php` | What the server's cron runs. It refuses a web request, and hands over to `epesi:cron` |
| `app/Console/Commands/RunCron.php` | `php artisan epesi:cron`: runs what is due and prints a line per task |
| `app/Services/Cron/CronRunner.php` | Decides what is due, runs it in this process, and dispatches the scheduler's events |
| `app/Services/Cron/CronLog.php` | The event subscriber that fills `cron_tasks` and `cron_calls`, and reads them back for the page |
| `app/Services/Cron/CronToken.php` | The cron URL's token: read, replace, compare, and the URL |
| `app/Http/Controllers/CronController.php` | The cron URL, `GET /cron?token=…` (route `cron` in `routes/web.php`) |
| `app/Filament/Administration/Pages/Cron.php` | Administration → Cron |
| `config/cron.php` | `token_path`; `late_after` (minutes without cron before the page warns, 20); `regular_calls` and `regular_minutes` (what counts as cron on a schedule: 3 calls, 10 minutes apart) |
| `database/migrations/2026_09_30_000000_create_cron_tables.php` | `cron_tasks` and `cron_calls` |
| `database/migrations/2026_09_30_010000_record_how_cron_ran.php` | `cron_tasks.last_via`, and `cron_calls.started_at` no longer overwritten |
| `tests/Feature/CronTest.php` | The tests |
| `routes/console.php` | The core app's tasks: `demo:reset`, `epesi:optimize` |

## Epesi's version, and how it maps here

| Epesi | Here |
|---|---|
| `cron.php`, run from cron every minute | `cron.php` (`epesi:cron`), run from cron every minute; or `php artisan schedule:run` |
| A module's `cron()` returning `['method' => minutes]` (Messenger: `['cron2' => 1]`) | the module schedules an Artisan command from its service provider |
| One job per call, the one waiting longest | every due task per call, one after another |
| A job ran again once its interval had passed since its last run | a task runs in the minutes its schedule names, and catches up on one it missed |
| `cron.php?token=…` over HTTP, for hosts without cron | the cron URL, `/cron?token=…` |
| `data/cron_token.php`, "New Token" | `storage/app/private/cron-token.txt`, "New cron URL" |
| `data/cron.lock`, one cron at a time | no lock for the whole call; `withoutOverlapping()` per task |
| Administration → Cron: each job's last run and whether it is running | Administration → Cron: the same, plus the result, output and next run, whether cron runs at all, and "Run now" |
| `data/logs/cron.log`, what the jobs printed | each task's last output, on the Cron page |
