# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Epesi BIM is a web-based CRM/ERP (PHP + MySQL/PostgreSQL, jQuery front end). This checkout is
Epesi 1.9.1 mid-migration from PHP 7.4 to PHP 8.2. The upcoming release is versioned **Epesi 2.0**
(`EPESI_VERSION`/`EPESI_REVISION` in `include/version.php`) rather than continuing the CalVer
`20260701-rcN` pre-release scheme — decided 2026-09-01 once the advisory PHP 8.3 Rector sweep
(`rector-php83.php`) started reporting clean, see `AI-private/archive/MIGRATION_NOTES.md` §84. The full
migration log — root causes, decisions, and a running "upgrade-gap" discipline for shipping fixes so they
also reach existing installs — is distilled in `AI-shared/MIGRATION_NOTES.md` (the full numbered
log is archived at `AI-private/archive/MIGRATION_NOTES.md`). Read the relevant section there before
touching old/legacy code; it usually already explains why something looks the way it does.

**PHP: 8.1 minimum, 8.2 target, 8.3 sweep clean (but not an 8.3 support claim).** The floor is
enforced in one place — `CompatibilityCheck::system_check()`'s `$desired_version`
(`include/compatibility_check.php`) — so read that rather than citing a number from memory. It said `8.0`
until 2026-09-01, which was never runnable: the bootstrap uses **first-class callable syntax**
(`$this->autoload(...)` in `include/autoloader.php`, likewise `error.php`/`session.php`/`patches.php`/
`module.php`) and `: never` return types, both **8.1-only**, in files loaded on every request — parse
errors on 8.0, so the app fatals at startup while the check reported "PHP version: OK". `rector.php`, the
config actually applied to this codebase, independently targets `PhpVersion::PHP_81` — the tooling already
encoded the real floor while the docs said 8.0. Nothing in the tree needs 8.2. See
`AI-private/archive/MIGRATION_NOTES.md` §85, plus `AI-shared/MIGRATION_NOTES.md` and
`AI-shared/environment-gotchas.md`.

**On the clean 8.3 sweep:** `rector-php83.php` (advisory; it lists Rector's six PHP 8.3 rules explicitly, since `SetList::PHP_83` is deprecated in Rector 2.6 — see that file's header) reports **0 files** as of
2026-09-01. Read that as "no pre-8.3 idioms left worth modernizing" — a code-maturity signal, and what
triggered the Epesi 2.0 rename (§84). It is **not** evidence the app runs on 8.3: Rector is a refactoring
tool, not a compatibility checker, and it only rewrites *forward*, so zero findings is equally consistent
with never having tested 8.3 at all. CI still lints at 8.2 only (`PHP_VERSION` in `ci.yml`). Claiming 8.3
support needs `php -l` plus a real run on an 8.3 binary; until someone does that, say "8.3-clean under
Rector", not "supports 8.3".

The trap worth remembering: **a Rector run can raise the language floor as a side effect** (that is where
the first-class callables came from), and neither `php -l` nor PHPStan will catch it, since both run at
the *target* version rather than the floor. Prefer constructs at or below 8.1 in new code — e.g. use
static properties, not constants, in a trait (trait constants are 8.2+).

`AI-shared/` holds lower-ceremony developer notes shared across developers/computers via git — how the
framework's pieces actually work, theming conventions, deliberate removals that look like bugs, recurring
bug-root-cause shapes, and environment gotchas. Check its `README.md` for the index; worth a look before
assuming something is broken/missing rather than intentional.

`AI-private/` is a separate nested git repo (gitignored here, present only on a core developer's
checkout) holding what must not be public: deployment and hosting details, account-specific setup,
internal planning, and `AI-private/archive/`, the full long-form version of several `AI-shared/`
documents. Read its `README.md` if the directory exists, and treat its contents as confidential.

`.claude/` itself is git-tracked (unlike `.vscode/`), so custom Claude Code skills under `.claude/skills/`
(the mechanism behind `/skill-name` triggers) sync across developers/computers the same way `AI-shared/`
does — only `settings.json`/`*.lock` inside it stay personal, excluded individually in `.gitignore`. Author
a new shared skill directly at `.claude/skills/<name>/SKILL.md`, not the legacy `commands/<name>.md` format
(unreliable across Claude Code surfaces). See `AI-private/sharing-skills.md` for the gitignore gotcha
behind that split and other details.

## Environment quirks (this machine)

- **PHP binary:** on Windows dev machines, the bare `php` on PATH resolves to an unrelated XAMPP 7.4 install —
  use `/c/xampp82/php/php.exe` (Bash) / `C:\xampp82\php\php.exe` (PowerShell) instead. On this Linux machine
  there's no bare `php` on PATH at all — use `/opt/lampp/bin/php`. PHP 7.4 can't parse constructor property
  promotion used in this codebase, so `php -l` with the wrong binary gives false parse errors.
- No build step: PHP/theme files are served directly from `modules/` and `theme*/` — there's nothing to
  compile or bundle for normal development.
- `data/` is the runtime data directory (config, cache, uploads, logs, per-instance state) and is gitignored
  except for a few shipped defaults; `temp/` holds Smarty compile output and other generated caches
  and is fully gitignored.
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
/c/xampp82/php/php.exe -l path/to/file.php   # Windows
/opt/lampp/bin/php -l path/to/file.php       # this Linux machine
```

**The analysis tools below live in their own composer project (`tools/composer.json`), not the root one** —
the root `vendor/` is committed to git so a deployment needs no composer run, and adding ~69 MB of dev
tooling to it (plus their bootstraps to `autoload_files.php`, which every request loads) would be wrong.
Install them once with:
```
composer install -d tools     # creates tools/vendor/, which is gitignored
```

Static analysis (level 2, own code only — `include/` + `modules/` + `console/`, minus the gitignored
`Premium`/`Custom` trees so a local run matches CI; baseline in `phpstan-baseline.neon` means CI only fails
on *new* findings). Level 1 adds undefined-variable detection, which is worth more here than the level
number suggests: an undefined read is an `E_WARNING` under PHP 8.2, and `REPORT_ALL_ERRORS` blanks a
module's whole output on the first warning of a request (see Error handling below). Level 2 adds unknown
method/property detection on typed expressions — mostly noise here (`Module::__call()`'s dynamic dispatch
and pre-standardized PHPDoc without the missing PSR-4 autoload look identical to PHPStan), baselined, but
it did catch a few genuinely wrong return-type docblocks on core methods
(`AI-private/REFERENCE-optimization-opus-AI.md`).
Regenerate the baseline with `--generate-baseline` as real bugs get cleared, so it shrinks over time:
```
tools/vendor/bin/phpstan analyse -c phpstan.neon
```

Rector dry-run (advisory in CI, checks for PHP 8.3 syntax opportunities; bumped from the PHP 8.2 set
2026-09-01 once that sweep reported clean). It applies real rules only rarely — the ~10 files it used to
report on every dry-run were whitespace-only re-prints from Rector 2.x, fixed for real 2026-09-01 (a clean
run now reports 0 files) — which is why the CI job is advisory rather than blocking regardless:
```
tools/vendor/bin/rector process --dry-run --config rector-php83.php
```

CI (`.github/workflows/ci.yml`) runs the three above plus a docs check on every push/PR,
and an advisory check that a diff modifying an `*Install.php` also adds a new
`patches/*.php` for that module — see "Working in this codebase" below for why, and add
a `No-Patch-Needed: <reason>` trailer to a commit message or PR description to silence it
for a change that genuinely needs no patch.

CLI console (module management, cache/theme rebuild, backups, patch/module scaffolding). **`list` is the
authoritative command inventory — run it rather than trusting a list written down here, which rots:**
```
/c/xampp82/php/php.exe console.php list             # Windows
/opt/lampp/bin/php console.php list                  # this Linux machine
/c/xampp82/php/php.exe console.php dev:module:create # e.g. scaffold a new module
```

**Tests:** there is no test suite. `codeception.yml` and `tests/` were removed (see
`AI-private/archive/MIGRATION_NOTES.md`); `modules/Tests/*` are demo/example modules, not automated tests.
See `AI-private/test-suite-plan.md` for the (undecided) plan. Don't assume a test command will validate
a change; verify by running the app instead. `php update.php` from the CLI is a real mutating operation
against the live DB, not a dry check — be careful running it outside a disposable environment.

The one automated check that does exist is a *performance* regression guard, not a correctness one —
`php console.php dev:query:budget` asserts that the per-row query counts the 2026-08-31 N+1 work
established stay flat as row count grows. Run it after touching `Utils_RecordBrowser`, `Utils_Watchdog`,
`CRM_Roundcube` or `CRM_Contacts`. It needs a populated database, so it is local-only and not a CI job.

**Profiling:** `MODULE_TIMES`/`SQL_TIMES` in `data/config.php` are still the install-wide default, but a
super-admin no longer has to edit them — Administration → *PHP & SQL Errors to mail* turns either debug
panel on for **their own session only** (`include/profiling.php`). Read the flags as `Profiling::$sql` /
`Profiling::$modules`; reading the constants directly skips the session override.

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
`AI-private/archive/MIGRATION_NOTES.md` §17) is the template engine; `include/EpesiSmartyRenderer.php` wraps it for the
non-legacy (admin/setup/update) views. Smarty 2 template modifier callbacks must be plain functions —
closures don't work.

The front end still loads an old jQuery (1.11.3 + jquery-migrate) on every page alongside AdminLTE's own
JS/Bootstrap. Prototype.js and Scriptaculous were fully removed as of 2026-08-06 (see
`AI-shared/legacy-js-migration.md`) — `$` is jQuery's own default binding now, not
Prototype's. This matters for old/legacy code still assuming Prototype semantics: a bare `$('some_id')`
(no `#`) is jQuery's *tag-name* selector, not an ID lookup — it never returns `null`/`undefined`, so an
`if (!el) return` guard written for Prototype's `$` won't catch it, and the returned (empty) jQuery
collection has no `.style`/`.value`/`.disabled`/`.innerHTML` etc. Old code needing a raw DOM element
(for those properties) should use `document.getElementById(id)`, not `$(id)`/`jQuery(id)`. This bug
shape has bitten several `modules/Premium/` modules already (each is its own gitignored git repo, never
swept by the migration — see that doc's "Step 7" and later entries) and is easy to reintroduce when
porting old inline `onclick`/`eval_js()` strings. When adding
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
  autoload for Epesi's own classes — a real ceiling on how high PHPStan's level can usefully go, though not
  as low a one as it first looks; see "Commands" above). Prefer surgical, convention-matching changes over
  introducing modern-PHP idioms wholesale.
- A fix that changes stored/seed data (an `*Install.php` default, a one-off DB `UPDATE`, a `data/` file) only
  reaches fresh installs and the dev DB — **existing installs need a patch** (see Patches above) or the fix
  never reaches real users. This is the single most common way a "fixed" bug regresses on upgrade.
- Prefer `Read`/`Grep` over shelling out to `find`/`grep` when exploring — except when you specifically need
  to bypass the Premium-module gitignore exclusion (see Environment quirks above).
