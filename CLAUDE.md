# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

A from-scratch rewrite of [Epesi](https://github.com/jtylek/epesiCRM) (a legacy PHP/AdminLTE
CRM) on **Laravel 12 + Filament 5 + Livewire**. Not a code migration — Epesi's modules are
reimplemented feature by feature against the real legacy source. See [README.md](README.md)
for the project overview and [AI-shared/](AI-shared/) for architecture and conventions
documents — read those before making non-trivial changes.

## Commands

```bash
composer install                 # PHP dependencies
npm install                      # JS dependencies

php artisan epesi:install        # real install: checks the server, .env, database, tables → then /setup wizard
                                 # (or skip it: the /setup wizard does all of that in the browser too)
php artisan epesi:package        # release zip with vendor/ + public/build/, installs from the browser
php artisan epesi:update         # after unpacking a new release: core + module migrations (also Administration → Database update)
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
(see [AI-shared/Epesi-Laravel-Translations.md](AI-shared/Epesi-Laravel-Translations.md) — UI strings are JSON keys in `lang/` and
each module's `lang/`; add new strings to `lang/pl.json` or the module's, or `TranslationsTest` fails). Legacy cutover: `import:legacy {tab=all}
{--dry-run} {--no-history}`.

Tests run on in-memory SQLite (`phpunit.xml`) and boot every module straight from its
`module.json` (`MODULES_FROM_MANIFESTS`) instead of the `modules` table, so they never touch the
MySQL database. They cover the dashboard, `import:legacy`, and the Attachments, Watchdog,
Followup, Shoutbox, Mail and Reminders modules (`tests/Feature/Modules/`). SQLite is laxer
than MySQL, so a green run doesn't prove a migration will run on MySQL (see the module
constraints below).

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
so both core and module resources build on one foundation. `Epesi/Notes` is the reference
implementation of a resource built entirely on the engine.

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

Existing modules: `Epesi/RecordBrowser` (core), `Epesi/Notes` (worked example on the engine),
`Epesi/Store` + `Epesi/StoreServer` (module store client/server, both `panels: ["administration"]`),
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

### Legacy data import

`App\Services\LegacyImport\*` + `php artisan import:legacy` reads any RecordBrowser-shaped
table (`<tab>_field`/`<tab>_data_1`/`<tab>_edit_history*`) out of a live legacy Epesi database
over a separate `legacy` DB connection, and imports records plus fully reconstructed
field-level edit history (into the same `spatie/laravel-activitylog` table every model already
uses) via per-tab `Importer` subclasses. Upsert-by-`legacy_id`; refuses to run against a
database that already has non-imported (`legacy_id IS NULL`) rows in the target tables, to
avoid silently corrupting id alignment.

## Conventions

See [AI-shared/conventions.md](AI-shared/conventions.md) for the UI/page conventions applied
to every resource (navigation, page actions, "addon" terminology, custom fields) — follow
these instead of Filament's raw defaults when adding or modifying a resource.
