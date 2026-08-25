# Legacy install cleanup (epesi-adminlte migration)

2026-08-19. The epesi-adminlte migration reorganized several bundled/vendored
libraries in place rather than adding new paths alongside old ones:

| Old path | New path |
|---|---|
| `modules/Libs/TCPDF/tcpdf5.9/` (hand-bundled) | `modules/Libs/TCPDF/vendor/` (composer `tecnickcom/tcpdf`) |
| `modules/Libs/PHPExcel/lib/` (hand-bundled) | `modules/Libs/PHPExcel/vendor/` (composer `phpoffice/...`) |
| `modules/Libs/QuickForm/3.2.11/` (bundled PEAR) | `vendor/openpsa/quickform` (composer) |
| `modules/Libs/CKEditor/*` (392 files) | `modules/Libs/Quill/` — see [ckeditor-to-quill-migration.md](ckeditor-to-quill-migration.md) |
| `modules/Libs/OpenFlashChart/2-lug/...` | `modules/Libs/ChartJS/` |
| `modules/Libs/ScriptAculoUs/` | dropped, no replacement — see [legacy-js-migration.md](legacy-js-migration.md) |
| `modules/CRM/Roundcube/RC/` | `modules/Libs/RoundCube/RC/` |
| `libs/adodb/` | `vendor/adodb/` (composer-managed now) |
| `libs/UiUIKit/`, `libs/Services/`, `libs/prototype.js` | `libs/adminlte-4.1.0/`, `libs/bootstrap-5.3.8/`, etc. |

**Why this needed a fix, not just the migration itself:** any install that
upgrades by overwriting files in place — a dist zip extracted over an
existing install (`console/Develop/CreateDistCommand.php`'s output), or a
plain `git checkout`/`git pull` — only adds/overwrites paths the *new*
release has. Whatever an older release had that the new one doesn't just
keeps sitting on disk, untracked and orphaned. Confirmed hands-on doing
this migration on a real pre-adminlte install: pulling the branch into the
existing checkout left **471 stale untracked paths** behind (verified via
`git status`/`git clean -n` — every one of them was gitignored under the
*old* `.gitignore` rules, so `git diff`/`git checkout` never touched them).

**The fix:** [modules/Base/patches/20260819_cleanup_legacy_adminlte_migration_dirs.php](../modules/Base/patches/20260819_cleanup_legacy_adminlte_migration_dirs.php).
For each reorganized directory above, it whitelists the entries the
*current* codebase actually ships there and deletes anything else found
alongside them (via the existing `recursive_rmdir()` helper), rather than
hardcoding old version-specific paths like `tcpdf5.9` or `3.2.11` — so it
doesn't need to know which exact older bundled-library version a given
install was running. Idempotent, and runs automatically for every install
through the normal update flow (admin "Update Epesi" screen, `console.php`,
or `update.php`/cron), the same as any other patch — it lives in `Base`
specifically because `Base` is the only module guaranteed installed
everywhere, so its `patches/` dir is always scanned (see
`PatchUtil::list_patches()` in `include/patches.php`).

**Deliberately out of scope:** the old root `vendor/` composer
dev-tooling (`behat`, `codeception`, `phpunit`, `doctrine`, `sebastian`,
etc.) that composer.lock no longer references. That directory's exact
stale contents vary per install's composer history, it's genuinely
composer's territory rather than app data, and leftover packages there
cost disk space only — no functional risk. Not worth hardcoding into an
automatic, DB-tracked, run-once patch.

**Git-based syncs specifically:** for a git checkout (as opposed to a dist
zip), `git clean -fd` (excluding any local DB backup files by name) reaches
the same untracked cruft more broadly/mechanically than the patch's fixed
whitelist — used once by hand to clean this install before the patch above
existed. The patch is what reaches every install type going forward,
including plain zip-upgrade installs that were never a git checkout at all.
