# SourceForge release packaging & upgrade plan

> **Status:** PLAN, dated 2026-08-24 - mostly NOT implemented; the packaging-exclusion gap (step 1) was closed 2026-08-31. Names an `update:apply` console command that does not exist yet.

Planned 2026-08-24, not yet implemented. Written at Jasiek's request after describing the SourceForge
(https://sourceforge.net/projects/epesi/) release process: a zip is generated and published there; new
installs just unzip it; **upgrading an existing install by overwriting with the new zip leaves behind
files the new release no longer ships** — most visibly in `vendor/` (composer dependency churn between
releases), but potentially anywhere in the Core tree.

## What should NOT be in the release zip

Jasiek's list: `.claude/`, `.git/`, `.github/`, `AI-shared/`, `.gitattributes`, `.gitignore`, `CLAUDE.md`,
`rector-php83.php`, `rector.php`. `data/` must be preserved (excluded from the zip, never touched on
upgrade).

## Current state: this is partially built already

Two existing pieces are directly relevant — read both before building anything new here.

1. **`console.php dev:dist:create`** (`console/Develop/CreateDistCommand.php`) already builds the release
   zip via `BackupArchive::create()` (`include/backups.php`), walking the whole tree with an exclude-regex
   list. It already excludes `.git`, `.claude`, `.github`, `.history`, everything under `data/` (keeping the
   directory entry itself), `temp/`, stray root `*.zip` files, all root `*.md` except `README.md` (this
   already catches `CLAUDE.md`), and a root-tooling group: `.htaccess`, `.gitignore`, `debug.php`,
   `PEAR.php`, `phpstan*`, `playbook.yml`, `rector*` (already catches both `rector.php` and
   `rector-php83.php`).
   - ~~**Gap found while researching this plan: `AI-shared/` and `.gitattributes` are NOT in the current
     exclude list**, even though Jasiek named both. First implementation step is adding
     `'^AI-shared(' . $sep . '|$)'` and `.gitattributes` to the `$exclude` array in
     `CreateDistCommand.php` (mirrors the existing `.git`/`.claude`/`.github` entries and the
     `.htaccess`/`.gitignore` group respectively).~~
     **DONE 2026-08-31** — both added exactly as described, plus `^tools(sep|$)` for the new
     `tools/` static-analysis project. Verified by building a real package: 38 `AI-shared/`
     entries and `.gitattributes` gone, `README.md`/`index.php`/`htaccess.txt`/`modules/Tools/`
     all still present. Two things worth knowing for the rest of this plan:
     - **`modules/Tools/` is a real, shipped module tree** (SessionKeeper, SetDefaults,
       WhoIsOnline). The `^` anchor is what keeps `^tools(sep|$)` from eating it — any future
       exclusion pattern here needs the same care.
     - **This command archives the working tree, not `git ls-files`**, so a *gitignored* file
       that exists locally still ships. That is what makes the `AI-shared/` exclusion a
       correctness fix and not just a size one: `AI-shared/DirectAdmin-git-sync.md` is
       gitignored because it holds one developer's own hosting account details, and without
       the folder-level rule, building a release on that machine would have put them in the
       SourceForge zip.
2. **`update.php`'s in-app self-updater already solves the exact "leftover files" problem this plan is
   about — for a different distribution channel.** `EpesiPackageDownloader`/`EpesiUpdatePackage`
   (`update.php`) drive an update against `http://ess.epe.si/update.json`: it downloads *both* the package
   matching the currently-installed revision (`current_package`) and the new one (`update_package`), then:
   - `$current_package->wipe()` — deletes every file listed in the **old** package's own zip index that
     still exists on disk (so it only ever removes files Core itself shipped for that exact old version —
     never `data/`, `modules/Premium/`, `modules/Custom/*`, since those were never in a Core zip to begin
     with),
   - `$update_package->extract()` — extracts the new zip on top, repopulating everything the new version
     ships (including a completely fresh `vendor/`, since wipe already cleared every old vendor file the
     old package's index knew about),
   - then `PatchUtil::apply_new()` runs DB/schema patches (`include/patches.php`), same mechanism as the
     `patches/` folders described in `CLAUDE.md`.
   - This wipe-then-extract approach is exactly right for the "which leftover files are safe to delete"
     question: it uses the old release's own authoritative file list rather than trying to infer intent
     from whatever happens to be sitting on disk.
   - **But it never runs against a manually-uploaded SourceForge zip.** `net_update_blocked()` returns true
     whenever `.git` or `.noupdate` exists, and — per `AI-shared/MIGRATION_NOTES.md` §71 — pointing
     `ess.epe.si` at a currently-live host for this fork is still an open pre-release item, not confirmed
     working. So today, nothing in the codebase performs this wipe/extract sequence against a zip a hosting
     admin downloaded from SourceForge and uploaded by hand (FTP/cPanel/SSH `unzip`).

The problem, restated: **the wipe-then-extract *mechanism* already exists and is correct; it just isn't
wired to the SourceForge manual-zip distribution channel.** The plan below reuses the same mechanism rather
than inventing a new one, adapted to not require a live download of the *old* package.

## Why "delete anything not in the new zip" is unsafe

A naive recursive diff (walk the installed tree, delete anything absent from the new zip) would also catch
`modules/Premium/*` (separately-licensed, gitignored, per-install), `modules/Custom/*` other than
`Tutorial` (per-installation, meant to be its own nested repo — see `environment-gotchas.md`), and anything
else a hosting admin added locally. None of that may ever be touched by a Core release upgrade — same
constraint the existing `wipe()` already respects by construction, since it only deletes paths that were
*in a Core package* in the first place.

## Proposed mechanism: a manifest shipped inside every release zip

Since the manual-zip flow can't download "the old package" the way `ess.epe.si` does, it needs an
equivalent record of "which files did the *currently installed* Core release ship" that survives on disk
between releases:

1. **`dev:dist:create` also writes a manifest file at `update/MANIFEST.txt`** (one relative path per line,
   root-relative — kept out of the repo root itself, in a dedicated `update/` directory that can hold other
   future release-management artifacts too), built from the exact same file walk/exclude-regex
   `BackupArchive::create()` already performs. This ships inside every release zip from now on (the
   `update/` directory is a normal, included part of the tree — not on the exclude list), so every install
   that has ever been upgraded via this path has, on disk, the authoritative file list for the version it's
   currently running.
2. **Upgrade procedure** (new console command, e.g. `console.php update:apply-package <path-to-new-zip>`,
   living alongside the other `console/Develop/*Command.php` files):
   - **Refuse to run at all when `.git` (or `.noupdate`) is present** — reuse
     `update.php`'s existing `net_update_blocked()` check rather than inventing a new one. This command is
     *more* destructive than the network updater it's modeled on (wholesale `rm -rf vendor/`, plus deleting
     manifest-diff "leftover" files), and none of that is git-tracked/recoverable if run against a working
     tree with uncommitted changes — a git checkout should be updated via `git pull`, full stop. Offer an
     explicit `--force` flag for the edge case of a genuinely git-deployed production install that wants a
     zip upgrade anyway, same spirit as `update.php`'s existing CLI `-f`/`cli_force_update` flag — but the
     default must refuse, not just warn.
   - Read the **old** `update/MANIFEST.txt` already sitting in the current install (if absent — e.g. an
     install that predates this mechanism — skip cleanup and just warn).
   - Turn on maintenance mode (`MaintenanceOnCommand` already exists as a model for this).
   - Delete `vendor/` wholesale before extracting. `vendor/` is committed but 100% composer-generated build
     output (never hand-edited — see `dependency-upgrades.md`), and its internal structure can restructure
     significantly between releases (packages added/removed/replaced, namespaces reshuffled), so a
     wholesale delete-then-reextract is simpler and more robust than trying to diff its contents
     path-by-path — mirrors what `wipe()` already achieves implicitly for `vendor/` in the network-update
     path.
   - Extract the new zip over the tree (same as `EpesiUpdatePackage::extract()` — overwrites/repopulates
     everything the new zip contains; never touches `data/` since dist zips never contain `data/*`
     content).
   - Diff **old manifest − new manifest** (paths the old release shipped that the new one no longer does)
     and delete any that still exist on disk. Since both manifests are themselves built from
     `dev:dist:create`'s exclude list, this diff can never name a `data/`, `modules/Premium/`, or
     `modules/Custom/*` (other than `Tutorial`) path — but keep an explicit path-prefix guard against those
     three anyway as defense in depth, the same belt-and-braces spirit as `orphaned_modules_gate()`
     (`update.php`) already applies for orphaned module *data*.
   - Run `PatchUtil::apply_new()` (`include/patches.php`) for DB/schema patches — unchanged from today's
     flow, and from the ordinary `runpatches.php`/`update.php` path described in `CLAUDE.md`.
   - Turn maintenance mode back off.
3. New installs stay exactly as simple as today: unzip, run `setup.php`. The manifest is inert until a
   future upgrade needs it.

## Open questions (not decided — flag before building)

- Should this become a real `console/Develop/*Command.php` now, or stay a documented manual sequence
  (`rm -rf vendor && unzip -o new.zip && php console.php ...`) until there's an actual second release to
  test an upgrade against? Nothing here has been exercised against a real prior-version → new-version
  upgrade yet.
- Should the manual-zip path eventually replace the `ess.epe.si` network auto-updater entirely (repointing
  `update.json` at SourceForge/GitHub releases), or coexist as a separate CLI-only path? `MIGRATION_NOTES.md`
  §71 already flags `ess.epe.si`'s live status as an unconfirmed pre-release item — worth resolving together
  rather than separately.
- Manifest format/versioning: plain newline-delimited relative-path list is the minimum viable version;
  no need for checksums (`EpesiUpdatePackage::files_modified()`'s CRC32 comparison already exists as a
  building block if "warn before deleting a locally-modified file" ever becomes a requirement here).
