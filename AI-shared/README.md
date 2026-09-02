# AI-shared/

> **Status:** REFERENCE - the index for this folder. Canonical; there is no second index.

Developer notes for working on Epesi — written so someone starting from scratch, on any
machine, doesn't have to rediscover what has already been learned. Git-tracked, so it travels
with `git clone`/`git pull`.

**What belongs here:** how Epesi works and how to build a module on it. Concepts, conventions,
recipes, and the traps that have cost real time. Concise enough to actually read.

**What doesn't:** anything account-specific, machine-specific, or install-specific; deployment
and hosting details; internal planning and business decisions; and the long-form development
history behind the notes below. That lives in `AI-private/` — a separate nested git repo
(gitignored here, so it is only present on a core developer's checkout). Several files here
end with a pointer into `AI-private/archive/`, where the full version of a distilled document
is kept. **Check whether `AI-private/` exists and read its `README.md` if it does**; treat its
contents as confidential.

## How to use this folder

**Every file opens with a `> **Status:**` line. Read it first.**

| Status | Means |
|---|---|
| `REFERENCE` | Describes how things actually work — true as of the date noted. Verify before relying on it for anything consequential. |
| `DONE` | Shipped. Kept for the rationale and the traps hit on the way. |
| `IN PROGRESS` | Partially shipped; the file says what's left. |

Read `CLAUDE.md` at the repo root first, always — it carries the architecture, conventions and
commands. This folder is the lower-ceremony layer underneath it.

## Files

**Start here**
- [design-philosophy.md](design-philosophy.md) — why Epesi is built the way it is. The test to apply to any redesign.
- [Dev-Tutorial.md](Dev-Tutorial.md) — how to write a module, end to end. Paired with `modules/Custom/Tutorial/`.
- [MIGRATION_NOTES.md](MIGRATION_NOTES.md) — the PHP floor, upgrade-gap discipline, and why old code looks the way it does.

**Building on the framework**
- [recordbrowser-live-schema-changes.md](recordbrowser-live-schema-changes.md) — evolving a schema without losing data; `$cols`; addon tabs.
- [generic-browser-responsive-tables.md](generic-browser-responsive-tables.md) — how grid columns get their widths, and the mobile reflow.
- [how-menu-works.md](how-menu-works.md) — sidebar/menu internals, and the sidebar search as a worked example.
- [tooltips-howto.md](tooltips-howto.md) — adding a RecordBrowser column tooltip.
- [how-to-write-HELP.md](how-to-write-HELP.md) — adding a Base_Help tutorial entry.
- [demo-data.md](demo-data.md) — the `demo:generate:*` commands and their gotchas.
- [branding-epesi-casing.md](branding-epesi-casing.md) — it's "Epesi", and why a rename can bite.

**Front end**
- [adminlte-theme.md](adminlte-theme.md) — theme layout, the icon system, and the recurring CSS/JS traps.
- [legacy-js-migration.md](legacy-js-migration.md) — what JS loads, and what old Prototype-era code silently gets wrong.
- [ckeditor-to-quill-migration.md](ckeditor-to-quill-migration.md) — rich-text fields.

**When something looks broken**
- [bug-patterns.md](bug-patterns.md) — root-cause *shapes* that recur. Check when a bug feels familiar.
- [deliberate-removals.md](deliberate-removals.md) — features removed on purpose. Don't reintroduce.
- [load-bearing-oddities.md](load-bearing-oddities.md) — the converse: code that looks like cruft but isn't. Read before tidying.
- [environment-gotchas.md](environment-gotchas.md) — DB, server and repo issues that looked like app bugs.

**When something is slow**
- [performance-profiling.md](performance-profiling.md) — how to profile, and the caching rules that keep N+1s fixed.
- [query-budget-check.md](query-budget-check.md) — the `dev:query:budget` regression guard, and how to add a scenario.

**Platform internals**
- [standalone-entrypoints.md](standalone-entrypoints.md) — `admin/`, `update.php`, `check.php`, `setup.php`.
- [password-hashing.md](password-hashing.md) — argon2id with rehash-on-login self-healing.

## Conventions

**Never grep `vendor/` looking for code to fix.** Third-party code is out of scope for
patches — if a bug's root cause traces into vendor code, look for where *our own* code calls
into it or configures it. Searching vendor to *understand* behaviour is fine.

**Never write screenshots or scratch output into the repo.** Always target a scratch directory,
with an absolute path — a bare filename resolves to the repo root and lands next to the
codebase.

## Maintaining this folder

If you land on a fact that would have saved real time had you known it up front, add it here
rather than only in your own private memory.

- **Give every new file a `> **Status:**` line directly under its H1.** No exceptions.
- **Update a stale entry rather than letting two files disagree.** Keep entries dated and
  factual.
- **Re-check a status line against the code when you touch a doc.** They go stale in the
  "already done" direction as often as the other way — four docs once claimed work was
  unimplemented that had shipped, two of them contradicting their own bodies.
- **Don't add a second index.** This file is it. A duplicate `INDEX.md` once existed and had
  been silently stale for weeks; a session that opened it first got a confidently-worded index
  that was simply wrong.
- **Keep it short.** These are notes for someone who needs to act, not a changelog. Long-form
  history belongs in `AI-private/archive/`.
- **Don't put anything here that names a specific install, host, account, credential or
  customer.** That goes to `AI-private/`.
