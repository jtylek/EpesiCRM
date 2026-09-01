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
- [query-budget-check.md](query-budget-check.md) — the `dev:query:budget` N+1 regression guard: how it works, how to add a scenario.
- [REFERENCE-optimization-opus-AI.md](REFERENCE-optimization-opus-AI.md) — **REFERENCE.** Measured baseline, the N+1/bootstrap findings, what was deliberately not done, and the implementation logs. Section numbers are cited from code/CI — don't renumber.

**Working on a specific area**
- [adminlte-theme.md](adminlte-theme.md) — theme status and the recurring CSS/JS traps.
- [how-menu-works.md](how-menu-works.md) — sidebar/menu internals.
- [REFERENCE-menu-search.md](REFERENCE-menu-search.md) — **REFERENCE.** How the sidebar search box works, plus three browser-only bugs.
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
- [ci-workflow.md](ci-workflow.md) — CI is disabled on GitHub (no Actions minutes); how to run the same checks locally.
- [log-monitoring.md](log-monitoring.md) — example log-tailing setup (a template, not a standard).
- [sharing-skills.md](sharing-skills.md) — how to make a `/skill-name` skill shared across developers.
- [dependency-upgrades.md](dependency-upgrades.md) — composer bump findings. Read before re-attempting TCPDF/Symfony.
- [legacy-install-cleanup.md](legacy-install-cleanup.md) — the in-place-upgrade orphaned-file cleanup patch.
- [CROSS_PLATFORM_RESULTS.md](CROSS_PLATFORM_RESULTS.md) — hosting-environment pass/fail matrix.
- [Claude-settings.md](Claude-settings.md) — Remote Control vs. cross-session messaging.
- [TODO.md](TODO.md) — follow-up work *we* deferred (vs. known-todos.md's pre-existing code markers).

**Not implemented (plans only)**
- [test-suite-plan.md](test-suite-plan.md) — an automated PHP test suite, in four independent tiers. **Parked 2026-09-01** (deliberately not started, not blocked); §0 has the notes to resume from.
- [PROPOSAL_functional_tests.md](PROPOSAL_functional_tests.md) — the earlier Codeception+CI take, superseded. Don't add to it.
- [release-packaging-plan.md](release-packaging-plan.md) — clean upgrade from a manual release zip. Still genuinely unbuilt: the `update:apply` command it names does not exist (`console.php list` has only `dev:dist:create`).

**Shipped, kept for the design rationale** — these were filed under "plans only" while their status
lines claimed they were unbuilt. All three were re-verified against the code on 2026-09-01 and corrected;
don't move them back on the strength of a header alone.
- [REFERENCE-import-wizard.md](REFERENCE-import-wizard.md) — **REFERENCE.** `Premium/Import/Import_0.php:314` builds the flow on `Utils_Wizard`. Kept for the five browser-only bugs it records.
- [Epesi-Google-Calendar-sync.md](Epesi-Google-Calendar-sync.md) — **ON HOLD, built.** Module exists and reached live "Connected"; paused on a Google Console scope issue, not a code defect.
- [mail-account-encryption-and-gmail-oauth.md](mail-account-encryption-and-gmail-oauth.md) — **Phase 1 DONE** (`encrypt_account_secret()`); Gmail OAuth Phase 2 deferred by decision.

## Continuous integration

`.github/workflows/ci.yml` exists as of 2026-08-31 and runs on push/PR: `php -l` over all
first-party PHP, PHPStan level 2 (baselined — fails only on *new* findings), an advisory
Rector 8.3 dry-run (bumped from 8.2 on 2026-09-01; the config lists the six 8.3 rules explicitly rather than using the deprecated `SetList::PHP_83`), a docs check that every `console.php` command named in `CLAUDE.md`
actually exists, and an advisory check (item B6, `REFERENCE-optimization-opus-AI.md`) that a diff
modifying an `*Install.php` also adds a new `patches/*.php` for that module — silenced for
a genuinely patch-free change with a `No-Patch-Needed: <reason>` trailer in a commit
message or the PR description.

Before that date there was **no CI at all**, despite `CLAUDE.md` and `phpstan.neon` both
describing jobs in the present tense — and neither PHPStan nor Rector was even installed.
Noted because several documents were written against that false assumption.

As of 2026-09-01 the workflow is **manually disabled on GitHub** (no Actions minutes
available on this repo, so it could never run remotely anyway) and has had zero runs. See
[ci-workflow.md](ci-workflow.md) for the reasoning and how to run the equivalent checks
locally instead.

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
- **When a plan ships, change its status line — and rename the file.** A doc that ships
  stops being a plan, so `<topic>-plan.md` becomes **`REFERENCE-<topic>.md`**. Repoint the
  references when you do (`grep -rn '<old-name>' --include=*.md --include=*.php .` — code
  comments cite these too). Done for `REFERENCE-menu-search.md` and
  `REFERENCE-import-wizard.md` on 2026-09-01.
- **Status lines go stale in the "already done" direction, not just the "not yet" one.**
  On 2026-09-01 *four* docs still said NOT implemented for work that had shipped —
  the menu search, the Import wizard, Google Calendar Sync, mail-password encryption
  Phase 1 — and `generic-browser-responsive-tables.md` had done the same earlier. Two of
  those had a contradicting "implemented and verified live" paragraph in their own body.
  When you touch a doc, re-check its header against the code rather than trusting it; for
  anything under `Premium/` assume nobody ever has, since it is gitignored and invisible
  to every tool here.
