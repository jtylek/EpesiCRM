# CI workflow: disabled on GitHub, how to run it locally

> **Status:** REFERENCE - true as of 2026-09-01.

## Disabled on GitHub

`.github/workflows/ci.yml` (added 2026-08-31 — see `README.md`'s Continuous integration
section) is **manually disabled** on GitHub and has never had a single run:

```
$ gh workflow list --all
CI    disabled_manually    346849738
$ gh run list --workflow=ci.yml
(empty)
```

Reason: this repo has no GitHub Actions minutes available (private repo, no billing set
up), so any push/PR trigger would just fail to run anyway — disabling it avoids a
permanently-red/greyed-out check on every commit for a workflow that can never execute
remotely. It is not disabled because the checks themselves are broken or inapplicable.

Re-enable with `gh workflow enable ci.yml` once Actions minutes are available.

## Running the same checks locally

No `act`/Docker on the Windows dev machine, so there's no way to replay the actual
workflow file locally — these are the equivalent commands per job (PowerShell; swap
`C:\xampp82\php\php.exe` for `/opt/lampp/bin/php` on the Linux machine). `tools/vendor` is
already installed here (`composer install -d tools`), so all of these work as-is.

**`tools\ci-local.bat`** runs lint + phpstan + rector + the console command list in one
go (Windows only, hardcodes the `C:\xampp82\php\php.exe` path from CLAUDE.md's Environment
quirks) — `cmd /c tools\ci-local.bat` or just double-click it. Exits non-zero only if lint
or phpstan fail; rector/console output is printed for manual review, same as the two
`continue-on-error: true` jobs in `ci.yml`. The individual commands below are what it runs,
useful if you want just one check or you're on the Linux machine.

**lint** — `php -l` over first-party PHP (mirrors the job's exclusions: vendor/,
RoundCube, Smarty, Tests):
```powershell
Get-ChildItem -Recurse -Include *.php include,modules,admin,console,*.php |
  Where-Object { $_.FullName -notmatch '\\(vendor|Libs\\RoundCube\\RC|Base\\Theme\\smarty|Tests)\\' } |
  ForEach-Object { & C:\xampp82\php\php.exe -d error_reporting=E_ALL -l $_.FullName } |
  Select-String -NotMatch "No syntax errors"
```
For a single file you're actively editing, just `php -l path\to\file.php` — faster than
the full sweep.

**phpstan** (level 2, baselined — only *new* findings matter):
```powershell
tools\vendor\bin\phpstan.bat analyse -c phpstan.neon --no-ansi --no-progress
```

**rector** (advisory PHP 8.3 dry-run; applies real rules only rarely — see CLAUDE.md):
```powershell
tools\vendor\bin\rector.bat process --dry-run --config rector-php83.php --no-ansi --no-progress-bar
```
`--no-ansi`/`--no-progress`/`--no-progress-bar` matter mainly when redirecting output to a
file (as `tools\ci-local.bat` and the `/ci-local` skill do) — without them the file fills
up with raw ANSI color escapes and progress-bar carriage-return noise, unreadable outside
a terminal. Rector still prints one small ANSI-colored warning box (deprecated per-version
PHP set notice) even with `--no-ansi` — a Symfony/Rector deprecation-handler quirk that
ignores the flag; harmless, not worth chasing.

**Don't "modernize" `rector-php83.php` to silence that deprecation notice** (tried
2026-09-01). The warning tells you to replace `->withSets([SetList::PHP_83])` with
`->withPhpSets(php83: true)`, but the new API is cumulative, not a like-for-like rename:
it re-applies every per-version set from PHP 5.3 up through 8.3 (each rule self-gates by
target version internally), where the old constant loaded only `config/set/php83.php`'s
six PHP-8.3-specific rules. Tested: dry-run findings went from 0 files to 517, almost all
`LongArrayToShortArrayRector` (`array()` → `[]`, a PHP 5.4-era style rule) — noise
unrelated to this config's actual purpose (surfacing 8.3-level changes the core migration
didn't target). Rector's own `SetList` constants no longer have a non-deprecated
equivalent for "just this one version's rules" — that granularity was deliberately
removed upstream, not an oversight here. Leave the deprecated `SetList::PHP_83` constant
in place; the console warning is cosmetic on an already-advisory job.

The ~10 files Rector used to report on every dry-run (CLAUDE.md's "whitespace-only
re-prints from Rector 2.x") were fixed for real on 2026-09-01 — ran
`rector process --config rector-php82.php` (no `--dry-run`) once to apply the trailing-tab
strip, confirmed with `git diff --ignore-all-space` that every changed file had zero
non-whitespace diff. A fresh dry-run now reports 0 files. If it starts reporting files
again, don't assume it's the same harmless noise — confirm with `--ignore-all-space`
before treating a new report as advisory-only.

**docs** (every `console.php` command CLAUDE.md names must exist):
```powershell
C:\xampp82\php\php.exe console.php list --no-ansi
```
then eyeball that every `console.php X:Y` mentioned in CLAUDE.md's Commands section
appears in the output. The CI job scripts this diff in bash; not worth reproducing exactly
for a local check — a manual compare is enough.

(`console.php` itself was silently broken for one commit, 2026-09-01: `f22340cf6` deleted
`$input = new ArgvInput()` but left `$application->run($input)`, so every invocation hit
an undefined-variable warning that blanked all output under `REPORT_ALL_ERRORS` — see
Error handling in CLAUDE.md. Fixed same day by calling `$application->run()` with no args,
found while writing `tools\ci-local.bat` above.)

**upgrade-gap** (advisory: a modified `*Install.php` should ship a new `patches/*.php`
alongside it): no script to run — just apply the rule by eye against `git status`/
`git diff --name-status` before committing. See CLAUDE.md's "Working in this codebase"
section for when a patch is actually needed vs. a genuinely cosmetic/pre-release change
that isn't (judgment call, not a reflex).
