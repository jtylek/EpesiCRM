# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

EPESI BIM is a web-based CRM/ERP (PHP + MySQL/PostgreSQL, jQuery/Prototype front end). This checkout is
Epesi 1.9.1 mid-migration from PHP 7.4 to PHP 8.2, currently released as CalVer `20260701-rc1`. The full
migration log — root causes, decisions, and a running "upgrade-gap" discipline for shipping fixes so they
also reach existing installs — lives in `MIGRATION_NOTES.md`. Read the relevant section there before
touching old/legacy code; it usually already explains why something looks the way it does.

`AI-shared/` holds lower-ceremony, more frequently updated notes shared across developers/computers via
git — ongoing feature status (e.g. the AdminLTE theme rewrite), deliberate removals that look like bugs,
recurring bug-root-cause shapes, and environment/tooling gotchas. Check its `README.md` for the full index;
worth a look before assuming something is broken/missing rather than intentional.

## Environment quirks (this machine)

- **PHP binary:** the bare `php` on PATH resolves to an unrelated XAMPP 7.4 install. Always use
  `/c/xampp82/php/php.exe` (Bash) / `C:\xampp82\php\php.exe` (PowerShell) for this project — PHP 7.4 can't
  parse constructor property promotion used in this codebase, so `php -l` with the wrong binary gives false
  parse errors.
- No build step: PHP/theme files are served directly from `modules/` and `theme*/` — there's nothing to
  compile or bundle for normal development.
- `data/` is the runtime data directory (config, cache, uploads, logs, per-instance state) and is gitignored
  except for a few shipped defaults; `temp/` holds Smarty compile/cache output and is fully gitignored.
- `modules/Premium/` is a separately-licensed, gitignored tree (each premium module is its own git repo).
  Claude Code's Grep tool silently skips gitignored paths, so an exhaustive sweep that must include Premium
  needs plain `grep`/`git grep --no-index` via Bash instead.

## Commands

Install PHP deps (also installs nested composer projects for CRM/Mail, Libs/PHPExcel, Libs/TCPDF):
```
composer install
```

Lint (what CI's `lint` job runs, minus vendor/Roundcube/dev-tool exclusions):
```
/c/xampp82/php/php.exe -l path/to/file.php
```

Static analysis (level 0, own code only — `include/` + `modules/`; baseline in `phpstan-baseline.neon` means
CI only fails on *new* findings):
```
vendor/bin/phpstan analyse -c phpstan.neon
```

Rector dry-run (advisory in CI, checks for PHP 8.2 syntax opportunities — should report zero changes):
```
vendor/bin/rector process --dry-run --config rector-php82.php
```

CLI console (module management, cache/theme rebuild, backups, patch/module scaffolding — see
`console.php` for the full command list):
```
/c/xampp82/php/php.exe console.php list
/c/xampp82/php/php.exe console.php dev:create:module
/c/xampp82/php/php.exe console.php dev:create:patch
```

**Tests:** `codeception.yml` and `tests/` are an empty skeleton (no real `*Cest.php`/`*Cept.php` suite yet;
`modules/Tests/*` are demo/example modules, not automated tests) — see `PROPOSAL_functional_tests.md` for
the (undecided) plan. Don't assume a test command will validate a change; verify by running the app instead.
`php update.php` from the CLI is a real mutating operation against the live DB, not a dry check — be careful
running it outside a disposable environment.

## Architecture

### Bootstrap chain

- `include.php` is the shared bootstrap: config → maintenance mode → error handler → DB → module system
  (`module_primitive.php` → `module_install.php` → `module_common.php` → `module.php` → `module_manager.php`)
  → session → variables → patches → login. Almost every entry point (`index.php`, `process.php`, `ajax.php`,
  `console.php`, `cron.php`) requires it (directly or via `include/include_path.php`).
- `index.php` renders the initial page shell only (`theme/index.tpl` + legacy JS bundle via
  `libs/minify`); it does not render the module tree itself.
- The module tree is actually rendered by `process.php`, which calls `Epesi::process()` — this returns
  **JavaScript** that the client executes to patch the DOM. This is an old-style AJAX-push SPA, not a
  REST/JSON API. `ajax.php` is a separate, newer callback mechanism using Symfony HttpFoundation
  Request/Response for code that needs a real response object instead of generated JS.
- `update.php`/`check.php`/`setup.php` have their own PHP/view split (AdminLTE templates under
  `setuptheme/`), separate from the main app's rendering path.

### Module system

Everything (features, screens, admin tools) is a `Module` (`include/module.php`, extends `ModulePrimitive`),
instantiated by `ModuleManager` into a parent/child tree via `$this->init_module(...)`/`pack_module`. A DI
container (`Pimple\Container`) is available via `$module->get($name)`.

A module named e.g. `CRM_Mail` lives at `modules/CRM/Mail/` (path = underscores → directories) with files
following a fixed naming convention:
- `MailInstall.php` — install/upgrade class (schema, default data, ACL). Registers `patches/` for this module.
- `MailCommon_0.php` — shared/static logic callable without a live instance (`CRM_MailCommon::method()`).
- `Mail_0.php` — the actual `Module` subclass with display/instance logic. (The `_0` suffix is the module
  class's own version number.)
- `patches/<YYYYMMDD>_<description>.php` — one-off upgrade steps applied via `runpatches.php`/`update.php`.
  **Patches are identified by filepath, not content** — editing an already-applied patch file is a silent
  no-op; ship a new file instead.
- `lang/<code>.php` — shipped translation defaults, source-controlled. Per-instance custom overrides (entered
  via the admin Translate screen) live outside modules/ entirely, at `data/Base_Lang/custom/<module>/<code>.php`
  (gitignored, created on first write) — never written into modules/ (see `Base_LangCommon::append_custom()`).
- `theme/` — legacy default-theme templates/CSS for this module; `theme_adminlte/` — the AdminLTE reskin.
  CSS is loaded **per rendering module**, not globally, so don't assume AdminLTE's own class names are safe
  to reuse inside a module's `theme_adminlte/` — collisions are easy.

`modules/Utils/RecordBrowser` (+ `GenericBrowser`) is the generic data-grid/CRUD framework most business
modules (Contacts, Companies, Tasks, ...) are built on top of — read it before reimplementing list/search/
filter/CRUD behavior from scratch.

### Rendering

Smarty **2** (vendored/patched-in-place under `modules/Base/Theme/smarty/`, deliberately not upgraded — see
`MIGRATION_NOTES.md` §17) is the template engine; `include/EpesiSmartyRenderer.php` wraps it for the
non-legacy (admin/setup/update) views. Smarty 2 template modifier callbacks must be plain functions —
closures don't work.

The front end still loads legacy libraries on every page: Prototype.js, Scriptaculous, and an old jQuery
(`$` is bound to Prototype via `noConflict()`, not jQuery) alongside AdminLTE's own JS/Bootstrap. When adding
interactive AdminLTE-themed UI, prefer native attributes/AdminLTE's own JS over hand-rolled listeners —
e.g. Bootstrap modal autofocus needs the `autofocus` attribute, not a `shown.bs.modal` listener, since
`adminlte.min.js` already runs its own focus-stealing script. Native `confirm()` has been replaced app-wide
by a styled AdminLTE modal — use `Module::create_confirm_href()` / `window.epesi_confirm()` instead of
`confirm()` (falls back to real `confirm()` automatically off-AdminLTE).

### Error handling

`error.php`'s `REPORT_ALL_ERRORS` mode is not just extra logging — the *first* `E_WARNING`/`E_NOTICE`
anywhere during a request blanks that module's rendered output, including inside a compiled Smarty template
(which routinely triggers notices via `{if $var.optional_key}` on a legitimately-absent key). Keep that in
mind before enabling it broadly or treating a blank module as a hard crash.

## Working in this codebase

- This is a 16+ year old codebase with layered legacy patterns (PHP4-style code migrated forward, no PSR-4
  autoload for Epesi's own classes — hence PHPStan running at level 0). Prefer surgical, convention-matching
  changes over introducing modern-PHP idioms wholesale.
- A fix that changes stored/seed data (an `*Install.php` default, a one-off DB `UPDATE`, a `data/` file) only
  reaches fresh installs and the dev DB — **existing installs need a patch** (see Patches above) or the fix
  never reaches real users. This is the single most common way a "fixed" bug regresses on upgrade.
- Prefer `Read`/`Grep` over shelling out to `find`/`grep` when exploring — except when you specifically need
  to bypass the Premium-module gitignore exclusion (see Environment quirks above).
