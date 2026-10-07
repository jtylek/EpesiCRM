# Working on Epesi Laravel

## Read first

This is a from-scratch Epesi CRM/ERP reimplementation on Laravel 12, Filament 5,
Livewire, and Tailwind 4. Preserve Epesi's behavior by consulting the real legacy
source when porting features; do not infer the specification from Laravel defaults.
The legacy rendering and EAV storage engines are not being ported.

Don't read [README.md](README.md), [CLAUDE.md](CLAUDE.md), the
[shared notes index](AI-shared/README.md), or any AI-shared doc as a standing checklist at
the start of a session — that's token overhead most tasks don't need. Open one only when it's
relevant to the task at hand: [architecture](AI-shared/architecture.md) or
[conventions](AI-shared/conventions.md) before a non-trivial change in that area, or the notes
for the specific feature being touched. Check implementation against the notes: some documents
include proposals or older descriptions. In particular, there are five panel providers, and
CRM `fields()` declarations belong to resources.

## Architecture and ownership

- `app/` holds shared infrastructure, panel providers, vocabulary in `App\Enums`,
  setup/update services, file storage, and legacy importers. Core CRM migrations
  remain in `database/migrations`; module-specific migrations live with their modules.
- `modules/<Vendor>/<Name>/` contains a `module.json` manifest and PSR-4 `src/`.
  Modules ship as ZIP archives and install at runtime, not as Composer packages.
  Declare dependencies in the manifest and use the registry/installer lifecycle.
  `"core": true` prevents disabling or uninstalling a module.
- `modules/Epesi/CRM/{Companies,Contacts,Tasks,Meetings,PhoneCalls}` owns each CRM
  model, policy, resource, and any calendar event provider. `CRM` is just a grouping
  directory, with no manifest or namespace of its own.
- `modules/Epesi/RecordBrowser` is the shared recordset engine. Build resources on
  `RecordsetResource`, declaring `fields()` with the `Field` DSL and `addons()` for
  related data. Start from `CompanyResource`. Use existing field customization hooks
  before introducing new field types or custom Filament components.
- Custom fields use real `cf_<id>` columns and `HasCustomFields`; they must participate
  in the same forms, tables, views, and history as shipped fields. Shared reference
  lists, including countries and zones, belong to `Epesi/CommonData`.
- Calendar events, dashboard applets, and module setup steps use their existing
  registries/contracts. Extend these rather than adding module-specific branches
  to shared pages.

### Panels and access

Providers live in `app/Providers/Filament/`:

| Panel | Path | Purpose |
| --- | --- | --- |
| `main` | `/` | Day-to-day CRM for `super_admin`, `manager`, and `employee` |
| `administration` | `/administration` | Infrastructure and administration, `super_admin` only |
| `user-settings` | `/user-settings` | Staff preferences, including appearance and regional settings |
| `portal` | `/portal` | Separate customer experience, gated by the `customer` role |
| `setup` | `/setup` | Installation and module setup |

`User::canAccessPanel()` rejects inactive accounts and disables administration and
the portal in demo mode. Setup has its own installation guards. Module resources
join supported panels through `ModuleRegistry::pluginsFor()`, not hard-coded lists.

CRM row visibility comes from `HasOwnershipVisibility`: public, created by the
current user, or owned through a model-specific rule; managers and super admins
bypass that scope. Policies add action permissions. Preserve both layers.
Shield grants super admins access before policy checks, so invariants that must
also apply to them cannot rely on a policy denial alone.

### Boot and persistence constraints

- Keep `ModuleServiceProvider` first in `bootstrap/providers.php`. It registers
  module autoloaders/providers before application and panel providers use them.
  `config/modules.php` lists the always-autoloadable core namespaces.
- Do not query Eloquent from provider `register()`; its connection resolver is not
  ready. Follow the registry's query-builder/cache approach where boot-time reads
  are necessary. Module plugins installed mid-request appear on the next request.
- Core code that must work before a module is registered should use the existing
  resolver pattern (`Locales`, `CurrentTheme`, `AppName`) with an appropriate
  fallback, rather than directly referencing unavailable module classes.
- Register stable morph aliases for polymorphic models through the appropriate
  core or module provider. The morph map is enforced; stored aliases must survive
  namespace changes.
- Module route groups outside `web`/`api` need explicit `SubstituteBindings` for
  implicit route-model binding.
- Support MySQL/MariaDB, even though tests use SQLite. Keep index/foreign-key names
  within 64 characters; explicitly name long generated identifiers. Review timestamp
  defaults: a first non-null timestamp can acquire implicit `ON UPDATE` behavior
  on some MariaDB installations. Use explicit defaults or nullable columns as appropriate.
- Preserve legacy identifiers and import safeguards. `App/Services/LegacyImport`
  uses a separate `legacy` connection and upserts by `legacy_id`; do not bypass its
  checks against mixing imported and locally created records.

## UI and feature conventions

Follow [conventions](AI-shared/conventions.md) rather than raw Filament defaults.
Use RecordBrowser's shared List/View/Create/Edit pages. A relation-manager tab is
called an **addon**. Rows open View before Edit; History is the last View tab.
Shared pages supply the icon breadcrumb, hidden large heading, header Save/Cancel
actions, and consistent record information. Standalone pages need the matching
page-header and translation concerns; `PageHeaderTest` checks the header contract.

Use `LinkedRecords` for linked-record badges. Navigation sorts by translated label;
do not assign arbitrary sort values. Read the navigation and keyboard notes before
changing these shared behaviors.

The main panel uses Livewire SPA navigation. Hand-written internal links should use
Filament's `generate_href_html()`, page redirects should respect SPA mode, and page
scripts needed before Alpine initialization belong in `@assets`. Downloads remain
ordinary links. See the SPA section of the architecture notes.

All panels share `resources/css/filament/epesi/theme.css`. Appearance themes are
admin-authored choices; user preferences select a theme. Read the theme notes before
changing CSS, density, or color application.

**Work on the English version only.** When making changes or developing new
features, write English UI strings (as JSON translation keys) and do not
hand-translate them into Polish, German, Spanish, French or any other language.
The target is about 40 languages (as many as the old Epesi had), which is too
many to translate on the fly; translations are produced separately by a
dedicated skill using the DeepL API. Reuse the existing label hooks and
translation traits so every new string goes through `__()` with an English key.
`TranslationsTest` reports untranslated strings (marked incomplete) instead of
failing; `TRANSLATIONS_STRICT=1` makes it fail, for use after the DeepL pass.
Actions restricted in demo mode must use the existing `Demo::guard()` or
`Demo::enabled()` mechanisms.

## Commands and verification

Run commands from the repository root. PHP requires 8.2 or later. Check the local
database configuration when relevant; `.env.example` is not evidence of the running
database. Do not print credentials or copy installation-specific settings into docs.

```text
composer install
npm install
npm run build
php artisan serve
npm run dev
php artisan test --filter=Name
php artisan test tests/Feature/SomeTest.php
composer test
php vendor/bin/pint --dirty
php artisan recordset:check --strict
```

`composer run dev` starts the server, queue listener, log tail, and Vite together.
Use focused tests for affected behavior; use `npm run build` for frontend changes.
Documentation-only changes need link/content and diff checks, not the application suite.

Before running tests, read [concurrent-session-tests](AI-shared/concurrent-session-tests.md).
Coordinate with other active sessions using available agent communication tools;
announce the scope and completion of a test run. SQLite databases are isolated, but
fake disks, compiled views, application caches, and the working tree are shared.
Do not run overlapping suites in the same checkout. Do not stash, revert, or fix
another session's changes to make tests pass.

`phpunit.xml` uses in-memory SQLite, array cache/session/mail, a synchronous queue,
and `MODULES_FROM_MANIFESTS=true`. `Tests/TestCase.php` isolates test views from the
running application's views and fakes shared storage. Preserve these protections;
passing SQLite tests does not validate MySQL-specific DDL.

Installation/operations commands are not routine test setup:

- `php artisan epesi:install`: install, followed by the browser setup steps.
- `php artisan epesi:update`: apply core and module updates after a release.
- `php artisan module:register Vendor/Name`: register a local module.
- `php artisan make:epesi-module` / `make:epesi-recordset`: generators.
- `php artisan epesi:package`: build a release ZIP with dependencies and built assets.
- `php cron.php`: run due tasks in-process; the deployment schedules this every minute.

Use `migrate --seed` only for an intended development/demo database. Read the relevant
notes before running installers, legacy imports, module lifecycle operations, or resets
against an existing installation.

## Further reading and repository boundaries

Use [AI-shared/README.md](AI-shared/README.md) as the topic index. In particular:

- Recordsets: [CRM port](AI-shared/Epesi-CRM-Laravel-port.md),
  [fields and collections](AI-shared/Epesi-custom-fields.md), [CommonData](AI-shared/Common-data.md).
- UI: [themes](AI-shared/Epesi-custom-themes.md),
  [translations](AI-shared/Epesi-Laravel-Translations.md),
  [navigation](AI-shared/Navigation-groups.md), [keyboard](AI-shared/Keyboard-shortcuts.md).
- Operations: [setup](AI-shared/Setup-wizard.md), [distribution](AI-shared/Epesi-Laravel-distro.md),
  [cron](AI-shared/cron.md).
- Integrations: [Roundcube](AI-shared/Epesi-Laravel-Roundcube.md),
  [file storage](AI-shared/Notes-Files-storage.md),
  [Watchdog](AI-shared/Watchdog_and_Notifications.md), [dashboard](AI-shared/Dashboard.md).
- Accounts: [users](AI-shared/User-management.md), [portal](AI-shared/Customer-portal.md),
  [mail settings](AI-shared/Mail-server-settings.md), [demo mode](AI-shared/Demo-mode.md).

`modules/Premium/` is a separate, ignored repository. Do not fold
its contents into the main repository. Runtime modules outside `modules/Epesi`,
downloaded Roundcube, credentials, `vendor/`, `node_modules/`, and `public/build/`
are also excluded from main-repository commits; respect `.gitignore`.

Keep shared notes factual and reusable, with one owning document per topic and links
instead of duplicated explanations. Update the `AI-shared/README.md` index when a
note is added, removed, or changes purpose. Do not include private deployment details,
dates in prose, or brittle line-number references. Comment on non-obvious reasons,
not obvious code behavior, and keep abstractions within the requested scope.

## Other agents work in this checkout

Other AI agents (Claude, Codex, …) and developers may be editing this tree and running tests
at the same time. A test run takes the lock `storage/framework/testing/test-run.lock`
(`tests/bootstrap.php`): a second run waits for the first instead of colliding with it, and
`storage/framework/testing/test-run.info` names the holder. Don't delete or bypass the lock,
and don't change the PHPUnit bootstrap. A failure outside your change may come from another
agent's half-finished edits — check `git status` before blaming your own work.

Commit only your own files. Never `git add -A`/`git commit -a` or commit other agents'
changes — unless the user says "commit all". Don't stash, revert or "fix" another agent's files.
See [AI-shared/concurrent-session-tests.md](AI-shared/concurrent-session-tests.md).

## When to run tests

- While working: run scoped tests only (`php artisan test --filter=Name` or a test file path).
- Before committing: run the full suite.
- Also run the full suite right after a change to shared infrastructure (the RecordBrowser
  engine, migrations, module loading, `app/Support`), since its effects reach far beyond the
  files you touched.
