# AI-shared/

> **Status:** REFERENCE - the index for this folder. Canonical; there is no second index.

Shared, git-tracked notes for AI assistants (and human developers) working on this
codebase — written so a session starting from scratch, on any computer, doesn't have to
rediscover things already learned. Unlike an AI tool's own per-machine memory, this
travels with `git clone`/`git pull`.

## How to use this folder

**Every file opens with a `> **Status:**` line. Read it first.** It tells you whether the
file describes code that exists, a plan for code that doesn't, or something implemented
but never verified. Acting on a PLAN as though it described the current codebase is the
most expensive mistake this folder can cause, and it has happened.

| Status | Means |
|---|---|
| `REFERENCE` | Describes how things actually work. Verify before relying on it for anything consequential — entries are "true as of the date noted." |
| `DONE` | Shipped. Kept for the rationale and the traps hit on the way. |
| `IN PROGRESS` | Partially shipped; the file says what's left. |
| `PLAN` / `PROPOSAL` | **Not implemented.** Nothing described here exists in the code yet. |
| `LIVING LIST` / `AUDIT` / `FINDINGS` | A dated snapshot or a running list. |

## How this differs from other docs

- **`CLAUDE.md`** (repo root, auto-loaded by Claude Code) — curated, stable guidance:
  architecture, conventions, commands. Read it first, always.
- **`MIGRATION_NOTES.md`** (here, but treat as stable) — the authoritative PHP 7.4 → 8.2
  migration log.
- **`AI-shared/`** (this folder) — the living, lower-ceremony layer in between. Edited
  often; expected to go stale in places.

## Files

**Start here**
- [design-philosophy.md](design-philosophy.md) — why Epesi is built the way it is. The test to apply to any redesign.
- [Dev-Tutorial.md](Dev-Tutorial.md) — how to write a module, end to end.
- [MIGRATION_NOTES.md](MIGRATION_NOTES.md) — the PHP 8.2 migration log. Check before touching legacy code.

**When something looks broken**
- [bug-patterns.md](bug-patterns.md) — fixed bugs whose root-cause *shape* recurs. Check when a bug feels familiar.
- [deliberate-removals.md](deliberate-removals.md) — features removed on purpose. Don't reintroduce.
- [load-bearing-oddities.md](load-bearing-oddities.md) — the converse: code that looks like cruft but isn't. Read before "tidying up".
- [environment-gotchas.md](environment-gotchas.md) — DB/server/tooling issues that looked like app bugs.
- [known-todos.md](known-todos.md) — audited TODO/FIXME markers in Epesi's own code.

**When something is slow**
- [performance-profiling.md](performance-profiling.md) — how to profile, and the N+1 fixes already applied.
- [optimization-plan-opus.md](optimization-plan-opus.md) — measured baseline + the sequenced optimization plan.

**Working on a specific area**
- [adminlte-theme.md](adminlte-theme.md) — theme status and the recurring CSS/JS traps.
- [how-menu-works.md](how-menu-works.md) — sidebar/menu internals.
- [menu-search-plan.md](menu-search-plan.md) — the sidebar search box's design rationale.
- [generic-browser-responsive-tables.md](generic-browser-responsive-tables.md) — grid layout: column sizing (why declared weights still matter) + mobile reflow.
- [recordbrowser-live-schema-changes.md](recordbrowser-live-schema-changes.md) — evolving a schema without losing data.
- [tooltips-howto.md](tooltips-howto.md) — adding a RecordBrowser column tooltip.
- [how-to-write-HELP.md](how-to-write-HELP.md) — adding a Base_Help tutorial entry.
- [standalone-entrypoints.md](standalone-entrypoints.md) — admin/, update.php, check.php, setup.php.
- [password-hashing.md](password-hashing.md) — login hashing: argon2id with rehash-on-login self-healing.
- [legacy-js-migration.md](legacy-js-migration.md) — Prototype/script.aculo.us removal; jQuery bump still open.
- [ckeditor-to-quill-migration.md](ckeditor-to-quill-migration.md) — the editor swap.
- [Simple-setup-ESS.md](Simple-setup-ESS.md) — the Simple Setup "Readme..." button.
- [demo-data.md](demo-data.md) / [demo-mode.md](demo-mode.md) — seeding demo records; how DEMO_MODE works.
- [branding-epesi-casing.md](branding-epesi-casing.md) — it's "Epesi", not "EPESI".

**Tooling and process**
- [log-monitoring.md](log-monitoring.md) — example log-tailing setup (a template, not a standard).
- [sharing-skills.md](sharing-skills.md) — how to make a `/skill-name` skill shared across developers.
- [dependency-upgrades.md](dependency-upgrades.md) — composer bump findings. Read before re-attempting TCPDF/Symfony.
- [legacy-install-cleanup.md](legacy-install-cleanup.md) — the in-place-upgrade orphaned-file cleanup patch.
- [CROSS_PLATFORM_RESULTS.md](CROSS_PLATFORM_RESULTS.md) — hosting-environment pass/fail matrix.
- [Claude-settings.md](Claude-settings.md) — Remote Control vs. cross-session messaging.
- [TODO.md](TODO.md) — follow-up work *we* deferred (vs. known-todos.md's pre-existing code markers).

**Not implemented (plans only)**
- [PROPOSAL_functional_tests.md](PROPOSAL_functional_tests.md) — a test suite. Still undecided.
- [Epesi-Google-Calendar-sync.md](Epesi-Google-Calendar-sync.md) — one-way Epesi → Google Calendar sync.
- [import-wizard-plan.md](import-wizard-plan.md) — Premium/Import as a Utils_Wizard stepper.
- [release-packaging-plan.md](release-packaging-plan.md) — clean upgrade from a manual release zip.
- [mail-account-encryption-and-gmail-oauth.md](mail-account-encryption-and-gmail-oauth.md) — encrypt rc_accounts passwords; Gmail OAuth.

## Continuous integration

`.github/workflows/ci.yml` exists as of 2026-08-31 and runs on push/PR: `php -l` over all
first-party PHP, PHPStan level 0 (baselined — fails only on *new* findings), an advisory
Rector 8.2 dry-run, and a docs check that every `console.php` command named in `CLAUDE.md`
actually exists.

Before that date there was **no CI at all**, despite `CLAUDE.md` and `phpstan.neon` both
describing jobs in the present tense — and neither PHPStan nor Rector was even installed.
Noted because several documents were written against that false assumption.

## Conventions for investigating/fixing bugs

- **Never grep `vendor/` when looking for code to fix.** Third-party code
  (`vendor/openpsa/quickform`, etc.) is out of scope for patches — if a bug's root cause
  traces into vendor code, look for where *our own* code calls into it or configures it.
  (Searching vendor purely to *understand* behavior is fine.)

## Conventions for AI assistants' own tool usage

- **Never write screenshots or other scratch/test output into the repo — always target the
  session's own scratchpad directory, with an absolute path, never a bare filename.** A
  bare filename resolves to the repo root and lands next to the actual codebase. Hit
  during the 2026-08-04 `<table>`→`<div>` pass: ~34 verification screenshots ended up
  loose in the repo root and the user had to ask for them to be cleaned up. If a scratch
  file does end up in the wrong place, clean it up as soon as it's noticed.

## Maintaining this folder

If you land on a fact that would have saved real time had you known it up front, add it
here rather than only in your own private memory.

- **Give every new file a `> **Status:**` line directly under its H1.** No exceptions —
  the table above is only useful if it's complete.
- **Update a stale entry rather than letting two files disagree.** Keep entries dated
  and factual.
- **Don't add a second index.** This file is it. A duplicate `INDEX.md` existed until
  2026-08-31 and had been silently stale since 2026-08-05, omitting eleven files
  including `performance-profiling.md`; a session that opened it first got a
  confidently-worded index that was simply wrong.
- **When a plan ships, change its status line.** `menu-search-plan.md` and
  `generic-browser-responsive-tables.md` both described themselves as unimplemented long
  after they had shipped.
