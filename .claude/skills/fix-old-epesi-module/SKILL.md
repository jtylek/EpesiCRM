---
name: fix-old-epesi-module
description: Scan an old/never-migrated Epesi module for PHP 8.x compatibility issues and reintroduced deliberately-removed dependencies (Quick Jump, Theme installation, etc.), then fix them
argument-hint: "[Namespace/ModuleName] e.g. Premium/Domains — omit to be asked"
allowed-tools: [Bash, Read, Edit, Glob, AskUserQuestion]
---

# Fix an old Epesi module for PHP 8.x

## 1. Identify the target module

If `$ARGUMENTS` is empty, ask the user which module to fix. They'll typically answer in
`<Namespace>/<Name>` shorthand (e.g. "Premium/Domains") — map that onto the real path by prefixing
`modules/` (`Premium/Domains` → `modules/Premium/Domains`). Strip any leading `/` or redundant
`modules/` the user might also type, so `/modules/Premium/Domains` and `modules/Premium/Domains`
resolve the same way.

Confirm the resulting directory actually exists before doing anything else — if it doesn't, say so and
ask again rather than guessing at a similar-looking name.

## 2. Treat it as what it is: a separate, gitignored, independently-versioned repo

Per `CLAUDE.md`'s "Environment quirks" section, `modules/Premium/*` (and any real `modules/Custom/*`
module) is its own git repo, gitignored from this one — the `Grep` tool silently skips gitignored
paths, so use plain `grep`/`git grep --no-index` via `Bash` for anything in this tree instead.

Before editing, check the module's *own* `git status`/`git log` (from inside its directory) — it has
independent history from the main repo. Per `AI-shared/environment-gotchas.md`'s "can change under you
mid-session" entry: if anything in there looks unexpectedly already-fixed or looks like corruption,
don't assume and don't revert — flag the specific diff to the user and ask; it may be legitimate
concurrent work from another session on the same nested repo.

## 3. Scan for PHP 8.x compatibility issues

Run the project's existing static-analysis tooling, scoped to just this module directory (both configs
already cover `modules/` broadly, Premium included — see `rector.php`, `rector-php83.php`,
`phpstan.neon` at the repo root). `AI-shared/environment-gotchas.md`'s "Rector and PHPStan are
installed globally" entry explains why these are bare commands, not `vendor/bin/rector`/
`vendor/bin/phpstan`, on a machine that's had the global Composer install done — if they're not found
on PATH, fall back to how CI installs them (`.github/workflows/php-checks.yml`, isolated `/tmp`
installs) or ask the user:

```
rector process <module-path> --config=rector.php --dry-run
rector process <module-path> --config=rector-php83.php --dry-run
phpstan analyse <module-path> -c phpstan.neon
```

These cover the bulk of real PHP 8.x breakage (removed functions like `each()`/`create_function()`,
curly-brace string/array offsets, PHP4-style constructors, PHP 8.2 dynamic-property deprecations,
undefined methods/functions/wrong arg counts) far more reliably than hand-written regex checks — a
module that's never been swept by the PHP 7.4→8.2 migration (see `AI-shared/MIGRATION_NOTES.md`,
especially §49's removed-function list and §50) should be expected to have real findings, not false
positives.

As a cheap supplementary check (catches anything outside those tools' rule sets), also grep the module
for the specific removed-function names §49 already catalogued as having bitten this codebase before:
`create_function`, `each(`, `get_magic_quotes_gpc`, `get_magic_quotes_runtime`, `money_format`,
`convert_cyr_string`, `ezmlm_hash`, `image2wbmp`, `read_exif_data`, `call_user_method`.

## 4. Scan for reintroduced deliberately-removed dependencies

Read `AI-shared/deliberate-removals.md` fresh each time (it's a living document, expected to grow —
don't rely on a memorized list) and grep the module for every removed API/feature documented there that
a never-migrated module could still be calling. Two confirmed-recurring ones as of 2026-08-21, both
already hit real Premium modules and documented there with the exact fix:

- **`Utils_RecordBrowserCommon::set_quickjump()`** ("A-Z quick jump" removal) — a leftover call in an
  `*Install.php` fatals with "Call to undefined method" on install/upgrade. Fix: delete the call
  (`install_new_recordset()` is idempotent, safe to just remove and re-run install).
- **`Base_ThemeCommon::install_default_theme($this->get_type())`** (Theme/lang storage removal) — kept
  as a core no-op so it won't fatal, but it's dead code calling a removed system. Fix: delete the line,
  no replacement call needed (themes resolve straight from `modules/` now).

Also worth a quick check, lower priority (won't fatal, but flag if seen): `'ckeditor'` as a QuickForm
element type (→ port to `Libs_Quill`), any `Libs_OpenFlashChart`/old chart class usage (→
`Libs_ChartJS`), any `requires()` on or reference to a `Develop_*` module (entire tree deleted), and
old mobile-subsystem dispatcher methods (`mobile_menu()` etc. — dead-but-harmless, low-priority cleanup
only).

## 5. Present findings, then fix with confirmation

Group findings by severity before showing them: (a) fatal — removed-API calls that break
install/upgrade or a live code path outright, (b) Rector/PHPStan findings — real but not necessarily
fatal today, (c) low-priority dead-code cleanup. Fix surgically, matching this codebase's existing
conventions (no drive-by refactors beyond what's needed — see `CLAUDE.md`'s "Working in this
codebase"). No patch file is needed for pure code/syntax fixes like these (patches are for
install/upgrade data/schema steps) — only add one if a fix actually changes what `install()` writes to
the DB.

## 6. Suggest verification, and feed new findings back

Suggest the user actually install/upgrade the module (Administration → Modules Administration & Store,
or `console.php module:install`) to confirm the fix worked, since that's how the exact same fixes were
validated originally.

If this scan turns up a genuinely new broken pattern not already in `AI-shared/deliberate-removals.md`
or `MIGRATION_NOTES.md` §49, that's worth recording there afterward (per that folder's own "Maintaining
this folder" section) so the next module doesn't need to rediscover it — offer to add it, don't do it
silently.
