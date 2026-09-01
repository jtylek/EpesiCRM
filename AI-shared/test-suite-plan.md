# Plan — an automated PHP test suite for Epesi

> **Status:** PLAN — **parked 2026-09-01 by Jasiek's decision: left as a plan for now, not being
> implemented.** Nothing here is stale or blocked; it is deliberately not started. Supersedes the
> approach in `PROPOSAL_functional_tests.md` (Codeception + nightly GitHub Actions), which is not viable
> on this repo's constraints. Feasibility probes in section 2 were run and measured 2026-09-01 — they
> are real numbers from this working copy, not estimates, and stay valid until the bootstrap changes.

## 0. Notes from the session that produced this plan (2026-09-01)

Read this before picking the plan back up — several things moved *after* the tiers below were written,
and two of them change the shopping list.

**What shipped that the plan wanted (so don't re-plan it):**

- `demo:generate:contacts --employees=N` — the employee pool, item 1b's first half
  (`GenerateContactsCommand::generate_employees()`). Verified: pool 3 → 16, zero logins created,
  all activity records still assigned employees-only. Main company + own contact remain manual.
- Realism constraints on the generators, which matter because these are the fixture source:
  `BusinessHours` (09:00–20:00, 15-minute slots, duration-aware for meetings) and `ShortTitle`
  (30-char cap on word boundaries), both traits in `console/Demo/`. Meeting durations are now 1h–3h.
  See `demo-data.md`.

**What changed underneath the plan:**

- **The PHP floor is 8.1, not 8.0** (§85 in `MIGRATION_NOTES.md`; the old figure was never runnable).
  Two consequences here: (a) **`phpunit/phpunit ^11` requires PHP 8.2**, so the suite would be runnable
  on the 8.2 dev/CI target but *not* by a developer sitting on the 8.1 floor — either accept that
  (tests are dev-only and never shipped, so the app's floor is unaffected) or pin PHPUnit 10, which
  still supports 8.1. Decide deliberately rather than by default. (b) New first-party code should stay
  at or below 8.1 — this is why the two new traits use static properties rather than trait constants.
- **`rector-php83.php` no longer uses `SetList::PHP_83`** — it lists the six 8.3 rules explicitly.
  Relevant if the plan ever adds a Rector step: `withPhpSets(php83: true)` is cumulative and reports
  **508 files** on this codebase, almost all `array()` → `[]`. Read that config's header before touching it.
- **`tests/` and `phpunit.xml` are still absent from `CreateDistCommand`'s exclude list.** Section 5's
  housekeeping is unchanged and still has to happen first, not after.

**Still true, still the best starting point:** Tier 0 (`dev:test:smoke`) needs no dependency at all, runs
in about a second, and already has two PHP 9 blockers waiting for it (§8). If this gets restarted with a
small budget, start there.

## 1. The complaint is fair, but the obvious fix is the wrong one

Every AI session that opens this repo reads `CLAUDE.md`'s *"there is no test suite"* and correctly
flags it. The gap is real: `php -l` says a file parses, PHPStan level 2 says it type-checks against a
128k-line baseline, and **neither ever executes a line of Epesi**. Nothing in CI asserts that saving a
contact persists.

`PROPOSAL_functional_tests.md` answered this with Codeception acceptance tests behind a nightly CI job
standing up MySQL + Chrome. That plan cannot ship as written, for two reasons discovered since:

- **There is no CI to add a job to.** `.github/workflows/ci.yml` is *manually disabled* on GitHub and has
  never run once — no Actions minutes on this private repo (`ci-workflow.md`). The five jobs described in
  `CLAUDE.md` exist only as `tools/ci-local.bat`. A plan whose payoff is "a nightly CI job" delivers
  nothing here.
- **Codeception is gone**, deliberately (`MIGRATION_NOTES.md` §51/§52 — 43 packages pruned). Re-adding it
  to run browser tests would re-import a PHPUnit + WebDriver + behat/gherkin stack to duplicate a
  capability this repo *already has configured*: Playwright MCP (`.mcp.json`), which is how UI changes are
  actually verified today (`.playwright-mcp/`, the screenshots directory).

So the plan below is **local-first, dependency-light, and built on seams that already exist**. It is
also much cheaper than a week: Tier 0 is a day and pays for itself immediately.

## 2. Four seams, measured — this is why the plan is small

Everything below was probed against this working copy before being written down. The numbers are real.

**A headless bootstrap is fast and clean.** `console.php`'s own three lines
(`define('SET_SESSION', false)` → `require 'include.php'` → `ModuleManager::load_modules()`) bring the
entire app up outside a web request:

```
modules loaded: 110 in 104 ms
diagnostics observed: 0
DB reachable: yes
```

104 ms is cheap enough to pay per test process. This is the PHPUnit bootstrap, already written.

**Transactional rollback works through the real framework API.** Every table is InnoDB, and
`DB::StartTrans()` / `DB::FailTrans()` / `DB::CompleteTrans()` wraps a genuine `Utils_RecordBrowserCommon`
write:

```
contacts before: 107
new_record returned: 111      <- real id, real row
contacts during tx: 108
read back first_name: 'ZZTest'
contacts after rollback: 107  <- ROLLBACK OK
```

This is the single most important finding. **Integration tests can run against a populated database
without a fixture-provisioning project** — the blocker that kept `dev:query:budget` local-only and sank
the old proposal's "install Epesi on the runner" step. Write, assert, roll back, leave no trace.

**Module rendering is reachable headlessly, with caveats.** `ModuleManager::new_instance($mod, null,
$name)` then `->body()` under an output buffer renders a simple module fine (`Base_About`: 10,833 bytes).
Modules that build QuickForm elements need the form-element registration the normal request path does
first (`CRM_Contacts` threw `unregistered element: 'autoselect'`). That is scaffolding to write, not a
wall — and it defines the boundary between Tier 2 and Tier 3 below.

**The runtime gate is additive, not duplicative.** That render probe found two live PHP 8.2 deprecations
in its first run, both **removed in PHP 9**:

| Finding | Static analysis status |
|---|---|
| `strftime()` deprecated — `RegionalSettingsCommon_0.php:229`, ~20 first-party call sites incl. a whole Smarty plugin | **Not in the PHPStan baseline at all** — level 2 doesn't check deprecated-function use. Known only as a prose note in `MIGRATION_NOTES.md` §683. |
| `Creation of dynamic property Utils_RecordBrowser::$watchdog` — `RecordBrowser_0.php:280` | In the baseline (line 3064) as *"Access to an undefined property"* — i.e. **suppressed**, and framed as a typing nit rather than a PHP 9 removal. |

A 30-line probe surfaced PHP 9 blockers that the existing gates had baselined away or never looked for.
That is the argument for Tier 0 in one table.

## 3. The plan — four tiers, each independently shippable

Each tier stands alone and lands green before the next starts. Stopping after any tier leaves the repo
better than it found it; there is no half-built harness state.

### Tier 0 — `dev:test:smoke`, a boot-and-render gate (no framework, ~1 day)

A console command in the shape `dev:query:budget` already established — same directory, same
registration, same non-zero-exit-on-failure contract:

1. Boot headlessly, load all 110 modules, assert **zero** `E_WARNING`/`E_NOTICE`/`E_DEPRECATED`.
2. For every installed module exposing one, instantiate it and call the cheap metadata methods
   (`caption()`, `menu()`, `access()`), asserting no throw and no diagnostic.
3. Print a deprecation inventory grouped by call site.

Why this first: it needs **no new dependency at all**, it runs in ~1 second, and it already has two real
findings waiting for it. It also directly guards `error.php`'s `REPORT_ALL_ERRORS` failure mode
(`CLAUDE.md`) — where the *first* notice blanks a module's whole output — which is the mechanism behind
several entries in `bug-patterns.md`.

Ship it wired into `tools/ci-local.bat` as step 5.

### Tier 1 — PHPUnit + transactional integration tests over the data layer (~2 days)

This is where "does saving a contact actually persist" gets asserted.

- **Dependency:** `phpunit/phpunit ^11` into **`tools/composer.json`**, never the root one. The root
  `vendor/` is committed and production-only, and anything landing in `autoload_files.php` runs on every
  request — the rationale in `tools/composer.json`'s own header applies unchanged.
- **`tests/bootstrap.php`:** the three console.php lines from section 2.
- **`tests/EpesiTestCase.php`:** base class; `setUp()` opens `DB::StartTrans()` and sets an ACL user,
  `tearDown()` calls `DB::FailTrans(); DB::CompleteTrans()` and clears
  `Utils_RecordBrowserCommon::$record_cache` / `$record_info_cache` (reuse the reflection helper in
  `QueryBudgetCommand::reset_record_caches()` rather than writing a second one).
- **First tests — the CRUD core every business module inherits:** `new_record` persists and reads back;
  `update_record` merges old values into a partial edit (the documented trap in `bug-patterns.md`);
  `delete_record`; crits/`build_query` filtering; access-rule evaluation for a non-admin user; the
  RecordBrowser field-type round-trips (date, timestamp, `commondata` select, calculated).

Pure-logic units worth covering in the same pass, needing no transaction: `Base_RegionalSettingsCommon`
date/format handling (`strtotime()` slash-date locale trap — `bug-patterns.md`), `Utils_RecordBrowser`
crits construction, `include/misc.php` helpers.

**Deliberately not unit tests with mocks.** Global state and module singletons make isolation expensive,
and the proven transaction seam makes real-DB integration tests *cheaper* than mocking here. Test through
the real API.

### Tier 2 — render smoke over every module leaf (~1–2 days)

Extend Tier 0 from metadata to actual `body()` rendering, adding the QuickForm/form-element scaffolding
the probe hit. Assert: no throw, no diagnostic, non-empty output.

This targets the **highest-frequency bug class in this repo**. `bug-patterns.md` is dominated by
render-time failures — the blank module, the template variable with no `isset()` guard, the `<script>`
without `{literal}` that fatals every request, the addon `access()` called with two different arities.
None of those are reachable by any static gate; all of them are one `body()` call away.

### Tier 3 — committed Playwright specs for the flows already driven by hand (~1–2 days, optional)

Playwright is already installed and already used interactively for exactly this. The step is to **commit
the flows as `@playwright/test` specs** instead of re-driving them by hand each session: login, logout,
rejected bad credentials, create/edit/delete a contact, and a list-screen search/sort/page round trip.

Pick those browser flows for what is *only* reachable through a browser — the grid's pager appearing at
all (`bug-patterns.md`: a `Utils_GenericBrowser` fed by a raw `DB::GetAll()` loop never gets one), sort
links, the search box. Filtering logic itself is already asserted far more cheaply in Tier 1 against
crits/`build_query`; do not pay browser cost twice for it.

Scoped last and marked optional because it is the only tier that needs a running web server and a
browser, and because Tiers 0–2 catch the defects this codebase actually produces.

## 4. Decisions and their rationale

**PHPUnit, not Codeception.** Codeception's differentiator is acceptance/browser testing; Playwright
already owns that role here and is better at it. PHPUnit is the smaller dependency and the substrate for
Tiers 0–2. Re-adding Codeception would undo §51/§52 to buy a capability the repo has.

**Local-first, not CI-first.** Wire every tier into `tools/ci-local.bat`. The moment Actions minutes exist,
Tiers 0–2 lift into `ci.yml` unchanged *except* that they need a database — so they arrive as one job with
a MySQL service and the preconfigured install below, not five. Do not design around a CI that cannot run.

**Transactions, not a provisioned fixture DB.** Proven in section 2, and it removes the largest cost item
from the old proposal. Two honest limits, which must be documented next to the base class:

- **DDL auto-commits in MySQL.** A test that triggers a schema change (creating a RecordBrowser addon,
  `new_addon()`) escapes the transaction and will leave debris. Such tests need the separate-DB path below,
  or must clean up explicitly. See `recordbrowser-live-schema-changes.md`.
- **`data/` file writes are not transactional** — caches, uploads, `Base_Lang` custom overrides.

**Seed fixtures with the `demo:generate:*` commands that already exist.** Five of them
(`contacts`, `phonecalls`, `meetings`, `tasks`, `shoutbox` — `console/Demo/`, documented in
`demo-data.md`) already write Faker data through **`Utils_RecordBrowserCommon::new_record()`**, the real
framework API rather than raw SQL — so a generated fixture behaves like a real record, which is exactly
what a test needs and what a SQL dump cannot guarantee after a schema change. They also already solve the
problem the test base class has to solve anyway: `Acl::set_user(1)`, because
`new_record()` stamps `created_by` from a session that never exists under CLI (the same trick
`QueryBudgetCommand` uses).

This matters more than it looks. It turns "needs a populated database" from a **manual prerequisite into a
scriptable step** — the exact constraint that keeps `dev:query:budget` local-only
(`query-budget-check.md`), that makes Tier 1 currently lean on whatever happens to be in the dev DB, and
that would otherwise block the whole suite from ever lifting into CI. Three gaps to close first, all small:

- **They are not deterministic.** `\Faker\Factory::create()` is called with no seed (and, minor, *inside*
  the row loop — a fresh factory per record). Every run yields different data, so any assertion sharper
  than a row count is flaky. Fix: a `--seed=N` option calling `$faker->seed($n)`, and hoist the factory
  out of the loop. Perhaps an hour, and it makes the generators better for their existing demo use too.
- **They cannot bootstrap a virgin database alone — and this is the one gap worth closing with new code.**
  Per `demo-data.md`, the three activity generators hard-fail unless an own-company + own-contact already
  exist, and they *never* create employees by design; the intended workflow has you make those by hand and
  clone.

  **Half of this shipped 2026-09-01.** `demo:generate:contacts --employees=N`
  (`GenerateContactsCommand::generate_employees()`) now fills the employee pool, so the activity
  generators no longer need a hand-cloned one. Only the **main company + own contact** remain manual —
  the reasoning below is what that command was built on, and what is still open.

  It is a smaller job than it sounds, because **there is no "main company" setting to wire up**.
  `CRM_ContactsCommon::get_main_company()` is just `get_my_record()['company_name']`, and
  `get_my_record()` is `get_contact_by_user_id(Acl::get_user())`, which resolves through
  `Utils_RecordBrowserCommon::get_id('contact', 'login', $uid)` — the contact's own `login` field
  (`ContactsCommon_0.php:74,138,143`). So the whole bootstrap is three record writes:

  1. `new_record('company', …)` → id `C`.
  2. `new_record('contact', ['login' => 1, 'company_name' => C, …])` — links to the admin user **that
     install already created**. This single row is what makes `get_main_company()` return `C`.
  3. N × `new_record('contact', ['company_name' => C, …])` → employees, which now satisfy
     `employees_crits()` (`f_company_name = C OR f_related_companies LIKE '%__C__%'`) automatically.

  Two things to get right, both cheap and both expensive to debug later:

  - **Do not reintroduce logins.** `demo-data.md` records that `--create-user` was *removed outright*
    because a demo contact receiving a real `base_user` row is a security mistake, not a cosmetic one.
    Nothing here needs it: step 2 links to an already-existing user, and employees in step 3 get no login
    at all. Say so in the command's docblock so it does not get "helpfully" added back.
  - **Invalidate the login→contact cache.** `get_contact_by_user_id()` memoizes through `Cache::` with a
    1-hour TTL *across requests*, invalidated from `submit_contact()`'s added/edited/deleted hooks — **not**
    from a bare `new_record()`. A command that writes the own-contact directly must clear
    `crm_contact_login_uid_<uid>` itself, or the very next `demo:generate:*` run still hard-fails on a
    stale "no main company". This is the afternoon-losing trap in an otherwise trivial command.

**Where the main company gets created — split this in two.** The proposal to create it *during setup*
(rather than in a console command afterwards) is right, but only for half of it, and the split matters:

- **Main company + the admin's own contact: do it in setup, for every install.** `modules/FirstRun`
  already collects the admin's login, e-mail and password (`FirstRun_0.php:91–102`) but creates **no
  company and no contact** — so `get_main_company()` returns `-1` on every fresh install until somebody
  hand-creates both. That is not just a test-fixture inconvenience; it is a live onboarding gap that
  silently breaks the Employees picker in PhoneCalls/Meetings/Tasks for every new install, and it is the
  entire reason `demo-data.md` has to open with a manual steps 1–2. Adding a company-name field next to
  the admin fields FirstRun already has is small, and it improves the real product, not only the tests.
- **The ~10 employees: demo data. Never on a real install.** Gate them behind an explicit opt-in and keep
  them out of the default path.

> **Do not gate demo data on `installation_config.php` merely existing.** That file is exactly what an
> unattended *production* installer would use — Epesi ships to SourceForge and the **Softaculous
> autoinstaller**, "whose audience is end users installing the app" (`REFERENCE-optimization-opus-AI.md`). Treating
> file-presence as "this is a test instance" would hand real customers ten Faker employees. Gate on an
> explicit key instead — `'demo_employees' => 10`, absent/`0` by default — or leave employee generation in
> the console command entirely, where nothing about a normal install can reach it. Given the blast radius,
> the console command is the safer default and `dev:test:fixture` calls it anyway.
- **There is no `demo:generate:companies`.** `demo-data.md` step 3 names one; it does not exist. Companies
  come only from `demo:generate:contacts --create-company`. (Corrected in that doc alongside this plan.)

**An escape hatch for full isolation, already present.** `include/data_dir.php` is
`if (!defined('DATA_DIR')) define('DATA_DIR', 'data')` — so a test bootstrap can define `DATA_DIR` first
and point at a `data-test/config.php` naming a throwaway database. Worth wiring in Tier 1 even if the
default stays the dev DB, because it is three lines now and a refactor later.

With seeded generators behind it, that path becomes: install → `dev:test:fixture` → run. **That is the
whole CI story**, available the day Actions minutes exist, with no install-Epesi-by-hand step — and the
reason the generators are worth an hour of work now even though Tier 1 does not strictly need them.

**And the install step is already preconfigurable.** `setup.php` supports a "fast install" file —
`installation_config.php` in the repo root (`$fast_install_filename`, setup.php:59), holding a `$CONFIG`
array of `user` / `password` / `db` / `host` / `newdb` / `engine` / `direction`. When present, setup
pre-fills those form fields and **freezes** them (setup.php:261–269), and with `newdb => 1` it issues the
`CREATE DATABASE ... utf8mb4` itself (setup.php:359) — so there is no hand-made empty database and no
dump restore in the loop.

Two honest limits on it, neither blocking:

- **It is preconfiguration, not a CLI installer.** The values are pre-filled, not auto-submitted; the
  wizard still has to be walked. And setup.php never creates the admin account — it sets
  `default_module = FirstRun` (setup.php:762) and `modules/FirstRun` does that in-app.
- So a scripted fresh install is still **browser-driven** — which is fine, because Playwright is already
  configured here (`.mcp.json`). Write the file, drive a handful of clicks. That is a Tier 3-shaped job
  measured in hours, not the blocked-on-a-missing-CLI-installer problem it looked like.

> **Before creating one of these, plug two holes.** `installation_config.php` holds **plaintext database
> credentials**, and it is currently in **neither `.gitignore` nor `CreateDistCommand`'s exclude list** —
> so a file created for test provisioning can be committed by accident, and would ship inside every
> release zip built while it exists. Nothing is exposed today (no such file is in the tree), but this plan
> is the thing that would create one. Add it to both, next to the `phpstan.*`/`rector.*`/`^tools/` entries
> that already exist for exactly this reason.

**Adopt the `dev:query:budget` discipline verbatim.** From `query-budget-check.md`: *"confirm a new
scenario can fail — stub out the thing it protects, watch it go red, put it back. A scenario that passes
on the day you write it is not evidence."* This is the single most valuable convention this repo has
about automated checks, and it should govern the test suite from the first test.

## 5. Integration details easy to miss

- **`CreateDistCommand` must exclude `tests/` and `phpunit.xml`.** It currently excludes `phpstan.*`,
  `rector.*` and `^tools/` (line 74/81) — a new `tests/` tree would otherwise ship inside every release
  zip. The dead `codeception\.yml` entry was removed in §52, so there is nothing to reuse.
- **`tools/vendor/` is gitignored**; `composer install -d tools` gains PHPUnit with no root-tree impact.
- **`CLAUDE.md`'s Tests section must change** the moment Tier 0 lands — it is the file every session is
  told to trust, and the `docs` CI job exists precisely because it drifted before. Any new
  `console.php dev:test:*` command named there is verified by that job.
- **`phpstan.neon` should include `tests/`** once it exists, so test code is held to the same bar.

## 6. What this does not cover

- **JavaScript and CSS.** Tiers 0–2 are PHP-side. The jQuery-migration bug shape in `CLAUDE.md`
  (`$('some_id')` returning an empty collection, not `null`) is invisible to all of them; only Tier 3
  touches it.
- **`modules/Premium/`.** Gitignored, separately-licensed, never swept by any tool here
  (`bug-patterns.md`). A test suite in this repo does not reach it.
- **Whole-page query counts and page rendering through `Epesi::process()`**, which needs browser-held
  module-tree state — unchanged from `query-budget-check.md`'s own limits section.
- **Correctness of anything nobody writes a test for.** ~15 tests is a floor to build on, not coverage.

## 7. Effort and decision points

| Tier | Deliverable | Effort | New deps |
|---|---|---|---|
| 0 | `dev:test:smoke` boot + metadata gate | ~1 day | none |
| 1 | PHPUnit + transactional data-layer tests | ~2 days | `phpunit/phpunit` in `tools/` |
| 1a | `--seed=N` on the `demo:generate:*` commands | ~1 hour | none (Faker already in `require`) |
| 1b | ~~Employees generator~~ **done 2026-09-01**; main-company creation + `dev:test:fixture` still open | ~2 hours left | none |
| 2 | Render smoke over every module leaf | ~1–2 days | none |
| 3 | Committed Playwright specs (optional) | ~1–2 days | `@playwright/test` |

Tiers 0–2 total roughly **4–5 days** and need no infrastructure that does not already exist. 1a/1b are
optional within Tier 1 — the tests run against the dev DB without them — but they are what makes the
suite portable to a fresh checkout, the Linux machine, and eventually CI, so do 1a at least.

**For Jasiek:**

1. **Go / no-go on Tier 0.** It is a day, needs no dependency, and has two PHP 9 blockers waiting for it.
   Recommended regardless of the rest.
2. **Tier 1 dependency call:** PHPUnit into `tools/composer.json` — confirm that is the right home
   (it follows the phpstan/rector precedent exactly).
3. **Default test database:** dev DB with transactional rollback (cheap, proven, small DDL caveat), or
   stand up the `data-test/` + throwaway-DB path from the start (fully isolated, more setup)? The seeded
   generators (1a/1b) make the second option meaningfully cheaper than it looks.
4. **Gating policy:** does a red suite block a push locally, or is it advisory like the rector job?
5. **Tier 3 go/no-go** — genuinely optional; Tiers 0–2 catch what this codebase actually breaks.
6. **Does FirstRun start creating the main company + admin contact for every install?** Recommended yes —
   it closes a real onboarding gap, independent of the test suite (see §4). Separate call from whether
   demo employees exist at all, which should stay console-only.

## 8. The two findings already on the table

Independent of the go/no-go, the probe run found these and they need tracking somewhere:

- **`strftime()` — ~20 first-party call sites, removed in PHP 9.** `RegionalSettingsCommon_0.php` (the
  wrapper at :228 and its callers), `RegionalSettingsInstall.php`, and
  `modules/Base/Theme/smarty/plugins/function.html_select_date.php`. Replace with `IntlDateFormatter` or
  explicit `date()` formats. Note `MIGRATION_NOTES.md` §683 already counted 16 of these and concluded
  "works on 8.2" — true, and it stops being true on 9.
- **`Utils_RecordBrowser::$watchdog` dynamic property** — `RecordBrowser_0.php:280`. Declare the property.
  One line, and it clears a baseline entry.
