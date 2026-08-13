# AI-shared/

Shared, git-tracked notes for AI assistants (and human developers) working on this
codebase — written so a session starting from scratch, on any computer, doesn't have
to rediscover things already learned. This is **checked into git**, unlike an AI tool's
own per-machine/per-user memory: it travels with `git clone`/`git pull`, so it's the
same for every developer and every computer working on this repo.

## How this differs from other docs in this repo

- **`CLAUDE.md`** (repo root) is curated, stable guidance: architecture, conventions,
  commands. Read it first, always — it doesn't change often.
- **`MIGRATION_NOTES.md`** (repo root) is the authoritative log of the PHP 7.4 → 8.2
  migration specifically: root causes, decisions, the upgrade-gap discipline.
- **`AI-shared/`** (this folder) is the living, lower-ceremony layer in between:
  ongoing feature status, deliberate removals that look like bugs, subtle bug patterns
  worth recognizing if they recur, and environment/tooling gotchas that aren't really
  "architecture" but will burn the next person who doesn't know them. It's expected to
  be edited more often than CLAUDE.md, and to occasionally go stale — treat any claim
  here as "true as of the date noted," and verify against current code before relying
  on it for anything consequential.

## Files

- [design-philosophy.md](design-philosophy.md) — the founding principle behind
  Epesi's architecture (from the framework's creator): free the developer to write
  pure business logic in PHP, with the framework generating view/CSS/JS
  automatically. Read this before evaluating any redesign work — it's the test
  for whether a change is "modernizing" or "cutting against the point."
- [Dev-Tutorial.md](Dev-Tutorial.md) — how to write an Epesi module from scratch:
  class hierarchy, install/uninstall lifecycle, RecordBrowser field types, ACL,
  patches, translations. Paired with a complete working example module at
  `modules/Custom/Tutorial/`.
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
- [tooltips-howto.md](tooltips-howto.md) — step-by-step recipe for adding a proper
  mouseover tooltip to a RecordBrowser column: find the generic no-tooltip callback,
  reuse/add a `*_get_tooltip()` builder, wire it up in both `*Install.php` (fresh
  installs) and a patch (existing installs) — two different DB storage mechanisms
  depending on which kind of callback it is.
- [deliberate-removals.md](deliberate-removals.md) — features removed on purpose;
  don't silently reintroduce them or treat their absence as an oversight.
- [standalone-entrypoints.md](standalone-entrypoints.md) — `admin/`, `update.php`,
  `check.php`, `setup.php`: their PHP/view split, and a real security hardening pass
  around `anonymous_setup`.
- [legacy-js-migration.md](legacy-js-migration.md) — Prototype.js/script.aculo.us/old
  jQuery inventory and the planned elimination order.
- [ckeditor-to-quill-migration.md](ckeditor-to-quill-migration.md) — planned CKEditor→
  Quill swap (license + retirement), full scope/decision/plan recorded; not started.
- [generic-browser-responsive-tables.md](generic-browser-responsive-tables.md) —
  generic mobile/responsive 2-line-per-row layout for every `Utils_GenericBrowser`/
  `Utils_RecordBrowser` list table; implemented on the `mobile-gb` branch, not yet
  visually verified or merged.
- [bug-patterns.md](bug-patterns.md) — subtle, already-fixed bugs whose *root-cause
  shape* is likely to recur elsewhere in the codebase.
- [environment-gotchas.md](environment-gotchas.md) — DB/server-level issues that
  looked like application bugs but weren't, plus dev-tooling setup notes (e.g.
  driving a real browser against this app for UI verification) worth not
  rediscovering each session.
- [log-monitoring.md](log-monitoring.md) — one developer's example log-monitoring setup
  (which logs to tail, noise filters, dedicated-window habit). Varies by machine/dev —
  use as a template, not a standard.
- [known-todos.md](known-todos.md) — audited inventory of `TODO`/`FIXME`/`XXX` markers
  in Epesi's own code; which are still genuinely open (all of them, as of the audit
  date) and which are worth prioritizing.
- [TODO.md](TODO.md) — follow-up work *we* deferred (not pre-existing code markers,
  see known-todos.md for those): a real fix shipped now, with a known limitation to
  come back to later, usually because this dev install can't exercise the condition
  that would need testing.

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
