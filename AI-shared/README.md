# AI-shared/

Shared, git-tracked notes for AI assistants (and human developers) working on this
codebase — written so a session starting from scratch, on any computer, doesn't have
to rediscover things already learned. This is **checked into git**, unlike an AI tool's
own per-machine/per-user memory: it travels with `git clone`/`git pull`, so it's the
same for every developer and every computer working on this repo.

## How this differs from other docs in this repo

- **`CLAUDE.md`** (repo root — the only `.md` file kept there, since Claude Code
  auto-loads it from the project root) is curated, stable guidance: architecture,
  conventions, commands. Read it first, always — it doesn't change often.
- **`MIGRATION_NOTES.md`** (in this folder, but treat it as the stable exception to
  "living/lower-ceremony" below) is the authoritative log of the PHP 7.4 → 8.2
  migration specifically: root causes, decisions, the upgrade-gap discipline.
- **`AI-shared/`** (this folder) is the living, lower-ceremony layer in between:
  ongoing feature status, deliberate removals that look like bugs, subtle bug patterns
  worth recognizing if they recur, and environment/tooling gotchas that aren't really
  "architecture" but will burn the next person who doesn't know them. It's expected to
  be edited more often than CLAUDE.md, and to occasionally go stale — treat any claim
  here as "true as of the date noted," and verify against current code before relying
  on it for anything consequential.

## Files

- [MIGRATION_NOTES.md](MIGRATION_NOTES.md) — the authoritative PHP 7.4 → 8.2 migration
  log: root causes, decisions, the upgrade-gap discipline. See "How this differs" above
  for why it's treated as more stable than the rest of this folder.
- [CROSS_PLATFORM_RESULTS.md](CROSS_PLATFORM_RESULTS.md) — pass/fail matrix of the
  PHP 8.2 migration across hosting environments (Windows/XAMPP, cPanel, DirectAdmin,
  macOS); any ❌ gets logged back into `MIGRATION_NOTES.md`.
- [PROPOSAL_functional_tests.md](PROPOSAL_functional_tests.md) — undecided proposal for
  a small, high-ROI functional/Codeception test suite; the `codeception.yml`/`tests/`
  skeleton it would have started from was since removed (see `MIGRATION_NOTES.md`).
  (Three sibling proposals — `instance_singleton_fix`, `mail_attachments_filestorage`,
  `mcrypt_compat` — were removed 2026-08-22 once confirmed implemented in the codebase;
  see `MIGRATION_NOTES.md` §22/§36/§44 for the designs they described.)
- [design-philosophy.md](design-philosophy.md) — the founding principle behind
  Epesi's architecture (from the framework's creator): free the developer to write
  pure business logic in PHP, with the framework generating view/CSS/JS
  automatically. Read this before evaluating any redesign work — it's the test
  for whether a change is "modernizing" or "cutting against the point."
- [Dev-Tutorial.md](Dev-Tutorial.md) — how to write an Epesi module from scratch:
  class hierarchy, install/uninstall lifecycle, RecordBrowser field types, ACL,
  patches, translations. Paired with a complete working example module at
  `modules/Custom/Tutorial/`.
- [how-to-write-HELP.md](how-to-write-HELP.md) — how to add a `Base_Help`
  search/tutorial entry for a module: the `help/tutorials.hlp` DSL, the
  `helpID` attribute (not a DOM `id`) that resolves `STEPS` targets — menu
  items via `Menu_0.php`, ActionBar buttons for free, QuickForm fields by
  `[name=X]` (not `#id` — most have no matching id). Documents four real bugs
  hit getting the first new tutorial since Contacts' working end to end, two
  in `Base_Help`'s own shared JS (fixed): `Helper.hooks`' one-time-snapshot
  timing, and never chaining a `finish` step onto a `click` step's own
  (about-to-be-destroyed) target. Also flags a second, older `help/main.html`
  article mechanism that looks unreachable from the live UI today; don't use
  it for new content.
- [adminlte-theme.md](adminlte-theme.md) — status of the `adminlte`/`adminltedark`
  themes, plus the recurring CSS/JS architecture traps hit while building them. Also
  covers the module-icon convention's history: `adminlte_icon()`/`Base_AdminlteIcons`
  → `bootstrap_icon()`/`Base_BootstrapIcons` (2026-08-14), made theme-agnostic ahead
  of any future non-AdminLTE theme.
- [how-menu-works.md](how-menu-works.md) — how the sidebar/left menu tree is built,
  cached, and rendered: `Base_MenuCommon::get_menus()`'s session cache, the two
  independent AdminLTE-vs-default-theme render paths, the AdminLTE sidebar's DOM
  shape (Bootstrap collapse, not AdminLTE's own classes), and the `#MenuBar` shell
  wiring/JS-rebind convention. Written ahead of planning a menu search/filter feature.
- [menu-search-plan.md](menu-search-plan.md) — approved plan for the AdminLTE sidebar
  search/filter box (client-side, AdminLTE-only, cascading auto-expand on match).
  Pairs with how-menu-works.md; check here before re-deriving the design.
- [Epesi-Google-Calendar-sync.md](Epesi-Google-Calendar-sync.md) — approved design for a new
  `modules/CRM/GoogleCalendarSync/` module: one-way (Epesi → Google), per-user OAuth, cron-polling sync
  of `crm_meeting` to each user's own Google Calendar, surfaced as a "Google Calendar Sync" tile in My
  Settings. Not yet implemented — check here before re-deriving the design.
- [import-wizard-plan.md](import-wizard-plan.md) — approved plan turning Premium/Import's
  icon-grid flow into a guided `Utils_Wizard` step-by-step wizard, plus a new shared
  `Utils_Wizard` AdminLTE stepper template (`theme_adminltedark/default.tpl`) that
  FirstRun and Develop/ModuleCreator pick up automatically too, no code changes needed
  in either. Check here before re-deriving the wizard-template design.
- [tooltips-howto.md](tooltips-howto.md) — step-by-step recipe for adding a proper
  mouseover tooltip to a RecordBrowser column: find the generic no-tooltip callback,
  reuse/add a `*_get_tooltip()` builder, wire it up in both `*Install.php` (fresh
  installs) and a patch (existing installs) — two different DB storage mechanisms
  depending on which kind of callback it is.
- [deliberate-removals.md](deliberate-removals.md) — features removed on purpose;
  don't silently reintroduce them or treat their absence as an oversight. Paired with
  the `/fix-old-epesi-module` skill, which scans a given `modules/Premium/`/`Custom/`
  module for reintroduced instances of these (Quick Jump, Theme installation, ...) plus
  general PHP 8.x compatibility issues.
- [standalone-entrypoints.md](standalone-entrypoints.md) — `admin/`, `update.php`,
  `check.php`, `setup.php`: their PHP/view split, and a real security hardening pass
  around `anonymous_setup`.
- [legacy-js-migration.md](legacy-js-migration.md) — Prototype.js/script.aculo.us/old
  jQuery inventory and the planned elimination order.
- [ckeditor-to-quill-migration.md](ckeditor-to-quill-migration.md) — CKEditor→Quill
  swap (license + retirement): done, merged as `8d47bec1`, plus a 2026-08-12 follow-up
  restoring the Notes toolbar-switch button. `modules/Libs/CKEditor/` still has 2 inert
  wrapper files left on purpose — see `deliberate-removals.md`.
- [generic-browser-responsive-tables.md](generic-browser-responsive-tables.md) —
  generic mobile/responsive 2-line-per-row layout for every `Utils_GenericBrowser`/
  `Utils_RecordBrowser` list table; implemented on the `mobile-gb` branch, not yet
  visually verified or merged.
- [bug-patterns.md](bug-patterns.md) — subtle, already-fixed bugs whose *root-cause
  shape* is likely to recur elsewhere in the codebase.
- [environment-gotchas.md](environment-gotchas.md) — DB/server-level issues that
  looked like application bugs but weren't, plus dev-tooling setup notes (e.g.
  driving a real browser against this app for UI verification) worth not
  rediscovering each session. Includes: a gitignored `modules/Premium/`
  checkout changing mid-session from concurrent work elsewhere (ask, don't
  revert); and a transient file-write lock inside one patch's loop aborting
  the entire update run because `die_on_error` operates at the whole-queue
  level, not per-item — per-item resilience has to live inside the patch.
- [log-monitoring.md](log-monitoring.md) — one developer's example log-monitoring setup
  (which logs to tail, noise filters, dedicated-window habit). Varies by machine/dev —
  use as a template, not a standard. Has a "Quick start" block up top: once you've
  confirmed the four paths for your own machine, launch straight from there instead of
  re-deriving the setup each session. Driven by the `/monitor-error-logs` skill at
  `.claude/skills/monitor-error-logs/SKILL.md` — see `sharing-skills.md` for why that
  path is git-tracked and how it's kept that way.
- [sharing-skills.md](sharing-skills.md) — how to make a custom Claude Code skill
  (`/trigger-name` action) work the same for every developer/computer: use
  `.claude/skills/<name>/SKILL.md` (not the legacy `commands/` format, which isn't
  reliably picked up in every Claude Code surface), and the `.gitignore` gotcha that
  broke a first attempt at un-ignoring just that subdirectory. Also inventories the
  currently-shared skills (`/monitor-error-logs`, `/fix-old-epesi-module`).
- [legacy-install-cleanup.md](legacy-install-cleanup.md) — the epesi-adminlte
  migration reorganized several bundled libraries in place (TCPDF, PHPExcel,
  CKEditor, OpenFlashChart, ScriptAculoUs, QuickForm, Roundcube's location,
  the front-end `libs/` stack); any install upgraded in place (dist zip or
  git checkout) is left with the old versions as untracked cruft since
  upgrading only adds/overwrites paths the new release has. Fixed by a
  whitelist-diff patch in `Base/patches/`, not a one-off script, so it runs
  automatically for every install via the normal update flow.
- [known-todos.md](known-todos.md) — audited inventory of `TODO`/`FIXME`/`XXX` markers
  in Epesi's own code; which are still genuinely open (all of them, as of the audit
  date) and which are worth prioritizing.
- [dependency-upgrades.md](dependency-upgrades.md) — findings from bumping composer
  dependencies (Dependabot-flagged): platform blockers (Symfony 8.x needs PHP ≥8.4, out of
  scope here), breaking-API changes and their fixes (Symfony Console 7's `execute(): int`,
  phpdocumentor/reflection-docblock v6), and `tecnickcom/tcpdf` 7.x's font-packaging gap
  (reverted to 6.x — don't re-attempt without reading this first). Also covers the
  multiple-`composer.json` layout and the no-test-suite verification method that caught it.
- [demo-data.md](demo-data.md) — the `demo:generate:contacts`/`:phonecalls`/`:meetings`/`:tasks`
  console commands: why "Employees" must be restricted to contacts belonging to your own company
  (and these tools never create employees themselves - clone your own contact via the UI first),
  why `--create-user` was removed outright, the `phonecall.phone` field's selector-not-a-number
  gotcha, and the checkbox-coercion trap (`trim(false)` → `''` → fails a `%d` bind).
- [demo-mode.md](demo-mode.md) — how `DEMO_MODE` actually works: the login screen's
  select-a-username dropdown (`$demo_users`, a global nothing in this repo currently populates),
  why it doesn't weaken auth (each demo account's real password must equal its own username), the
  full list of admin-surface areas it locks app-wide, and the gap between that mechanism (keyed to
  real login accounts) and "let a visitor pick an Employee contact" - which conflicts with
  `demo-data.md`'s no-new-logins rule and isn't automated anywhere yet.
- [TODO.md](TODO.md) — follow-up work *we* deferred (not pre-existing code markers,
  see known-todos.md for those): a real fix shipped now, with a known limitation to
  come back to later, usually because this dev install can't exercise the condition
  that would need testing.
- [Simple-setup-ESS.md](Simple-setup-ESS.md) — the Simple Setup screen's "Readme..."
  button (top-level package cards and per-row inside a bundled package's "Optional"
  dropdown, e.g. CRM's Contact Photo/Fax/...): why it only applies to locally-installed/
  available modules and never to Epesi Store cards, the dependency-free Markdown
  renderer behind it, and two real rendering bugs it caught before shipping.
- [branding-epesi-casing.md](branding-epesi-casing.md) — the product name is "Epesi",
  not "EPESI"; the codebase itself is inconsistent (most UI strings/translation keys
  are still all-caps today, fixed one at a time via `lang/en.php` overrides, not by
  editing call sites), and the word-boundary-regex approach (plus its two failure
  shapes: code-quotes, before/after narratives) for a repo-wide markdown casing sweep.
- [performance-profiling.md](performance-profiling.md) — how to profile a slow
  page in this app (`MODULE_TIMES`/`SQL_TIMES` in `data/config.php`, and why
  devtools' Network tab "Initiator" column is misleading here), plus fixed
  N+1 query patterns on RecordBrowser grids (`Utils_WatchdogCommon`,
  `Utils_CommonDataCommon`'s `get_id`/`get_value`/`get_array`/`get_nodes`, and
  `Utils_RecordBrowserCommon::get_record()`/`get_record_info()` - the latter
  had zero caching at all, not just a partial-cache-miss shape, so its fix
  needed real mutator-side invalidation rather than an optional one) and the
  general fix shape to reapply if another one turns up. Also covers
  `CRM_ContactsCommon::get_contact_by_user_id()`'s login→contact_id mapping,
  the one cache in this doc that's cross-request (via `Cache::`, not just
  request-scoped) since it's read on nearly every request but changes almost
  never - and the gotcha that testing cross-request caching from inside a
  single PHP process is misleading (the pre-existing request-scoped cache
  masks it), so verification used separate CLI-invoked processes per step.
  Also notes a known-but-deprioritized slow external call in Simple
  Setup/EpesiStore, and
  the 2026-08-28 `#debug_content` redesign (fixed bottom bar, collapsible
  error cards, `symfony/var-dumper` for SQL query args).
- [release-packaging-plan.md](release-packaging-plan.md) — plan (not yet implemented) for
  cleanly upgrading an existing install from a manually-uploaded SourceForge release zip:
  what `console.php dev:dist:create` already excludes vs. two gaps found (`AI-shared/`,
  `.gitattributes`), how `update.php`'s existing network auto-updater already solves the
  "delete files the new release no longer ships" problem via wipe-then-extract (but only
  for its own `ess.epe.si` channel), and a manifest-file mechanism to bring the same
  cleanup (plus a wholesale `vendor/` rebuild) to the manual-zip path.
- [mail-account-encryption-and-gmail-oauth.md](mail-account-encryption-and-gmail-oauth.md) —
  plan (not yet implemented) to encrypt `CRM_Mail`'s `rc_accounts` IMAP/SMTP passwords at
  rest (AES-256-GCM, following the `CRM_GoogleCalendarSync` precedent and the
  `Premium/PasswordManager` plan's `update_record()`-merge gotcha) and add a "Gmail (OAuth)"
  account type alongside plain IMAP, reusing Roundcube's own vendored XOAUTH2 support in its
  IMAP/SMTP clients (confirmed present) rather than PHP's `imap_open`/c-client.

## Conventions for investigating/fixing bugs

- **Never grep `vendor/` when looking for code to fix.** Third-party/vendor-provided
  code (`vendor/openpsa/quickform`, etc.) is out of scope for patches — if a bug's
  root cause traces into vendor code, look for where *our own* code calls into it or
  configures it instead, since that's what's actually fixable here. (Searching vendor
  purely to *understand* behavior, not to find something to edit, is fine.)

## Conventions for AI assistants' own tool usage in this repo

- **Never write screenshots (Playwright `browser_take_screenshot`, etc.) or other
  scratch/test-output files into the repo itself — always target the session's own
  scratchpad directory, with an absolute path, never a bare filename.** A bare
  filename resolves relative to the current working directory, which for this repo
  is the project root — a bare filename there lands as an untracked file sitting
  right next to the actual codebase. Hit during the legacy `<table>`→`<div>`
  conversion pass (2026-08-04): ~34 verification screenshots ended up loose in the
  repo root this way, and the user had to notice and ask for them to be cleaned up.
  If a scratch file ever does end up in the wrong place, clean it up as soon as it's
  noticed, without waiting to be asked.

## Maintaining this folder

If you're an AI assistant and you land on a fact that would have saved real time had
you known it up front — a deliberate removal, a non-obvious architectural trap, a
root cause that cost several round trips — consider adding it here (or updating an
existing file) rather than only keeping it in your own private memory, so the next
session/developer/computer benefits too. Keep entries dated and factual; prefer
updating a stale entry over letting two files disagree.
