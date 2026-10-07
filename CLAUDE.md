# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Token efficiency

- Don't re-read a file right after Edit/Write touches it just to "verify" the change — the
  tool call already errors if the edit failed, and the result is tracked without a re-read.
- Prefer Grep/Glob with a targeted pattern over Bash `cat`/`grep`/broad file reads. When
  reading a file, use offset/limit to pull the relevant slice instead of the whole thing when
  the file is large and the target is known.
- Don't spawn a subagent (Agent tool) for something doable directly in 1-3 tool calls — reserve
  subagents for genuinely broad/open-ended exploration or when the user asks for one by name.
- See also "Session start" below for which docs to read (and when) in this repo specifically.

## What this repo is

A from-scratch rewrite of [Epesi](https://github.com/jtylek/epesiCRM) (a legacy PHP/AdminLTE
CRM) on **Laravel 12 + Filament 5 + Livewire**. Not a code migration — Epesi's modules are
reimplemented feature by feature against the real legacy source.

## Session start

Don't read [README.md](README.md) or anything under [AI-shared/](AI-shared/) just because a
session is starting — both cost tokens for context most tasks don't need. Open README.md only
when the project overview itself is actually needed (e.g. explaining the project, or genuine
uncertainty about what this repo is). Open an AI-shared doc only when it's relevant to the
change at hand: this file links the specific doc for a topic inline (e.g. `AI-shared/cron.md`)
wherever it's relevant — read that one doc, not the whole directory, before a non-trivial
change in that area.

## Commands

```bash
composer install                 # PHP dependencies
npm install                      # JS dependencies

php artisan epesi:install        # real install: checks the server, .env, database, tables → then /setup wizard
                                 # (or skip it: the /setup wizard does all of that in the browser too)
php artisan epesi:package        # release zip with vendor/ + public/build/, installs from the browser
                                 # --translate [--legacy=<old epesi>]: translations skill + strict
                                 # translation tests first; --test: whole suite first (AI-shared/Epesi-Laravel-distro.md)
php artisan epesi:update         # after unpacking a new release: core + module migrations (also Administration → Database update)
php artisan epesi:optimize       # cron's every-minute cache check: builds config/route/event/Filament/icon caches
                                 # on an installation (never in a git checkout), picks memcached for CACHE_STORE=auto;
                                 # --clear removes them (AI-shared/Epesi-optimization.md)
php cron.php                     # what the server's cron runs every minute: the due tasks, in this process
                                 # (= epesi:cron; also the /cron?token= URL; see AI-shared/cron.md, Administration → Cron)
php artisan migrate --seed       # fresh schema + demo data (development)
php artisan serve                # dev server
composer run dev                 # server + queue listener + log tail + vite, concurrently

composer test                    # or: php artisan test
php artisan test --filter=Name   # single test
php artisan test tests/Feature/SomeTest.php

vendor/bin/pint                  # code style (run --dirty for changed files only)
npm run build                    # production frontend assets
npm run dev                      # vite dev server (also started by `composer run dev`)
```

The app runs on **MySQL** (`.env`'s `DB_CONNECTION=mysql`) — `.env.example` still defaults to
SQLite but that's stale and not what's actually used; don't assume SQLite without checking
`.env`.

**Module CLI** (see Architecture below): `php artisan module:register <Vendor/Name>`,
`module:install {--zip=}`, `module:enable`/`disable`/`uninstall`, `module:list`,
`module:package`, `make:epesi-module`. RecordBrowser engine: `make:epesi-recordset`,
`recordset:check [--strict]`, `customfields:sync`. Languages: `lang:import-epesi <code> <epesi path>`
(see [AI-shared/Epesi-Laravel-Translations.md](AI-shared/Epesi-Laravel-Translations.md) — UI strings are English JSON keys; develop in
English only, see "Languages" below — `TranslationsTest` reports untranslated strings, and
fails on them only with `TRANSLATIONS_STRICT=1`). Legacy cutover: `import:legacy {tab=all}
{--dry-run} {--no-history}`. Demo mode (`DEMO_MODE=true`, see [AI-shared/Demo-mode.md](AI-shared/Demo-mode.md)):
`demo:reset {--force}` empties the database and reinstalls with demo data, keeping `login_audits`
(it refuses outside demo mode; the scheduler runs it nightly); `demo:audit {--days=} {--csv}`.
Anything a visitor could use to spoil a demo for the next one needs `Demo::guard()` or a
`Demo::enabled()` check.

Tests run on in-memory SQLite (`phpunit.xml`) and boot every module straight from its
`module.json` (`MODULES_FROM_MANIFESTS`) instead of the `modules` table, so they never touch the
MySQL database. They cover the dashboard, `import:legacy`, and the Attachments, Watchdog,
Followup, Shoutbox, Mail, Reminders and PriorityList modules (`tests/Feature/Modules/`). SQLite is laxer
than MySQL, so a green run doesn't prove a migration will run on MySQL (see the module
constraints below).

Several sessions often work in this checkout at once. Test runs still share the fake disks and
compiled views under `storage/framework/testing/`, and every run tests the other sessions'
half-finished edits too. Before running tests, read
[AI-shared/concurrent-session-tests.md](AI-shared/concurrent-session-tests.md): it asks you to tell
the other sessions (`ListAgents`, `SendMessage`) before a run and again with the result.

Other AI agents (Claude, Codex, …) and developers may be working in this checkout too. Test runs
take a lock (`storage/framework/testing/test-run.lock`, set up in `tests/bootstrap.php`): a second
run waits for the first, and `test-run.info` beside it names the holder. Don't delete or bypass it.
**Commit only your own files** — never `git add -A`/`commit -a` or other agents' changes — unless
the user says "commit all".

Don't run the full suite (`composer test` / `php artisan test` with no filter) speculatively
while iterating — it's slow and, per above, disruptive to other sessions' runs. Save it for when
the user asks to commit; use a `--filter`/path-scoped run for changes made along the way.

## Architecture

### Two Filament panels

- `App\Providers\Filament\MainPanelProvider` — the main CRM panel, mounted at the app root
  (`path('')`), open to all three roles (`super_admin`/`manager`/`employee`, via
  `spatie/laravel-permission` + `filament-shield`). It discovers no resources of its own —
  every CRM recordset is a module under `modules/Epesi/CRM` and arrives through its plugin.
- `App\Providers\Filament\AdministrationPanelProvider` — a separate panel at `/administration`,
  gated to `super_admin` only (`User::canAccessPanel()`), for cross-cutting infrastructure
  concerns (login/session audit, module management) kept out of the day-to-day CRM UI.

Both panels take their plugin list from `App\Support\Modules\ModuleRegistry::pluginsFor(...)`
rather than a static array, so installing/enabling a module never means editing a panel
provider.

`bootstrap/providers.php` order matters: `ModuleServiceProvider` must run before
`AppServiceProvider`/the panel providers, since installed modules need their PSR-4 namespaces
and service providers registered before anything tries to use them.

### Ownership-based row-level ACL

Every CRM model (`Company`, `Contact`, `PhoneCall`, `Task`, `Meeting`, …) uses
`Epesi\Modules\RecordBrowser\Models\Concerns\HasOwnershipVisibility`, a global Eloquent scope
that restricts a query to records that are public, created by the current user, or "yours" by
a model-specific hook (e.g. your own company). `manager`/`super_admin` bypass the scope.
Filament Policies layer create/update/delete gates on the same rules. This is the Laravel
equivalent of Epesi's original per-module `install_permissions()` crits — see
[AI-shared/architecture.md](AI-shared/architecture.md) for the full explanation.

### RecordBrowser engine (`modules/Epesi/RecordBrowser`)

A **core module** (cannot be disabled/uninstalled — `"core": true` in its manifest) that
builds a full Filament CRUD experience (List/View/Create/Edit, filters, search, History addon,
page registration) from a model's `fields()` declaration, plus administrator-added custom
fields via `HasCustomFields`. Shared base pages
(`ListRecords`/`ViewRecord`/`CreateRecord`/`EditRecord`) and `HasOwnershipVisibility` live here
so both core and module resources build on one foundation. The CRM recordsets under
`Epesi/CRM` are resources built entirely on the engine; `Companies` is the smallest.

Every model used in a polymorphic relationship must register a **morph alias**
(`AppServiceProvider::registerMorphAliases()`) — a short string instead of the FQCN — so it can
move between namespaces (e.g. into a module) without breaking stored polymorphic references. A
new polymorphically-used model with no alias throws.

### Module system (`modules/<Vendor>/<Name>/`)

First-party and third-party features ship as a directory with a `module.json` manifest and
PSR-4 `src/`, distributed/installed as a **zip archive** — deliberately not Composer packages,
so install/enable/disable happens from the Admin GUI with no code deploy. Config in
`config/modules.php`. `App\Support\Modules\ModuleRegistry` (backed by the `modules` table, with
a `bootstrap/cache/epesi-modules.php` cache) drives what's autoloaded/booted; `MODULES_LOAD=false`
skips loading modules entirely as a recovery switch if one throws.

Existing modules: `Epesi/RecordBrowser` (core),
`Epesi/Store` (core, `panels: ["administration"]`: browses a module catalog and installs from
it; the catalog server it talks to is a separate module kept outside this repository),
`Epesi/Currencies` (core, administration: currencies, the dated home currency and daily ECB/NBP
exchange rates through `RateResolver`; see [AI-shared/Epesi-currencies.md](AI-shared/Epesi-currencies.md)),
and the five CRM recordsets under `Epesi/CRM` — `Contacts`, `Companies`, `Tasks`, `Meetings`,
`PhoneCalls`, all `"core": true` and all built on the engine. `Epesi/Roundcube` embeds the
Roundcube webmail on a Mailbox page; Roundcube itself is downloaded by `php artisan
roundcube:install` into `storage/roundcube`, never committed — see
[AI-shared/Epesi-Laravel-Roundcube.md](AI-shared/Epesi-Laravel-Roundcube.md) before touching it.

A module path may nest (`Epesi/CRM/Contacts`), where `CRM` is a plain grouping directory that
owns nothing — no `module.json`, no namespace of its own. Each CRM module owns its model,
policy, Filament resource and (where it has one) its calendar event provider; the core app
keeps the shared vocabulary (`App\Enums\Record*`), the migrations, and the legacy importers.
Because the core app still names these models directly (`User::contact()`, the importers) and
`AppServiceProvider` touches them during `register()`, their PSR-4 prefixes are listed in
`config('modules.core_namespaces')` alongside RecordBrowser's rather than waiting for the
`modules` table.

Non-obvious constraints worth knowing before touching module code:
- Code in a service provider's `register()` cannot use Eloquent (no DB connection resolver
  yet) — use the query builder, or read from the registry's cache.
- A Filament panel is built during provider registration, so a module installed mid-request
  isn't in the panel until the next request.
- A route group outside `web`/`api` gets no `SubstituteBindings` automatically — module route
  groups must list it explicitly or implicit route-model binding fails silently (injects a
  blank model instead of 404ing).
- MySQL caps identifier names at 64 characters and SQLite doesn't, so the test suite won't
  catch an over-long one. Laravel's generated index and foreign-key names
  (`<table>_<columns>_index`) go past that on a long module table: `morphs('subscribable')` on
  `epesi_watchdog_subscriptions` came out at 67. Name them explicitly
  (`morphs('subscribable', 'epesi_watchdog_subscriptions_subscribable_index')`). Before
  `module:register`, run `php artisan migrate --pretend --path=modules/<Vendor>/<Name>/database/migrations`
  to see the generated names. MySQL doesn't roll back table creation, so a failed module
  migration leaves a half-built table behind that has to be dropped before retrying.
- A table's first `NOT NULL` `timestamp()` column gets `ON UPDATE CURRENT_TIMESTAMP` from
  MySQL/MariaDB (XAMPP's has `explicit_defaults_for_timestamp` off): every update of the row
  overwrites it with the database's clock, local time rather than the app's UTC. SQLite doesn't,
  so tests don't catch it. Make such a column `nullable()` or give it `useCurrent()`
  (`cron_calls.started_at` had this, see [AI-shared/cron.md](AI-shared/cron.md)).

### Legacy data import

`App\Services\LegacyImport\*` + `php artisan import:legacy` reads any RecordBrowser-shaped
table (`<tab>_field`/`<tab>_data_1`/`<tab>_edit_history*`) out of a live legacy Epesi database
over a separate `legacy` DB connection, and imports records plus fully reconstructed
field-level edit history (into the same `spatie/laravel-activitylog` table every model already
uses) via per-tab `Importer` subclasses. Upsert-by-`legacy_id`; refuses to run against a
database that already has non-imported (`legacy_id IS NULL`) rows in the target tables, to
avoid silently corrupting id alignment.

## Languages: English only while developing

When making changes or developing new features, work only on the English version. Write UI
strings as English JSON translation keys (via `__()`), and do not hand-translate them into
Polish, German, Spanish, French or any other language. The target is ~40 languages (as many as
the old Epesi had) — too many to translate on the fly. Translations are produced separately by a
dedicated skill using the DeepL API, before a release; after it, run
`TRANSLATIONS_STRICT=1 php artisan test --filter=TranslationsTest`. Until then the test marks
itself incomplete and lists the gaps instead of failing. See
[AI-shared/Epesi-Laravel-Translations.md](AI-shared/Epesi-Laravel-Translations.md).

## Conventions

See [AI-shared/conventions.md](AI-shared/conventions.md) for the UI/page conventions applied
to every resource (navigation, page actions, "addon" terminology, custom fields) — follow
these instead of Filament's raw defaults when adding or modifying a resource.
