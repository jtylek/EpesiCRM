# claude-shared/

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
- **`claude-shared/`** (this folder) is the living, lower-ceremony layer in between:
  ongoing feature status, deliberate removals that look like bugs, subtle bug patterns
  worth recognizing if they recur, and environment/tooling gotchas that aren't really
  "architecture" but will burn the next person who doesn't know them. It's expected to
  be edited more often than CLAUDE.md, and to occasionally go stale — treat any claim
  here as "true as of the date noted," and verify against current code before relying
  on it for anything consequential.

## Files

- [adminlte-theme.md](adminlte-theme.md) — status of the `adminlte`/`adminltedark`
  themes, plus the recurring CSS/JS architecture traps hit while building them.
- [deliberate-removals.md](deliberate-removals.md) — features removed on purpose;
  don't silently reintroduce them or treat their absence as an oversight.
- [standalone-entrypoints.md](standalone-entrypoints.md) — `admin/`, `update.php`,
  `check.php`, `setup.php`: their PHP/view split, and a real security hardening pass
  around `anonymous_setup`.
- [legacy-js-migration.md](legacy-js-migration.md) — Prototype.js/script.aculo.us/old
  jQuery inventory and the planned elimination order.
- [bug-patterns.md](bug-patterns.md) — subtle, already-fixed bugs whose *root-cause
  shape* is likely to recur elsewhere in the codebase.
- [environment-gotchas.md](environment-gotchas.md) — DB/server-level issues that
  looked like application bugs but weren't.

## Maintaining this folder

If you're an AI assistant and you land on a fact that would have saved real time had
you known it up front — a deliberate removal, a non-obvious architectural trap, a
root cause that cost several round trips — consider adding it here (or updating an
existing file) rather than only keeping it in your own private memory, so the next
session/developer/computer benefits too. Keep entries dated and factual; prefer
updating a stale entry over letting two files disagree.
