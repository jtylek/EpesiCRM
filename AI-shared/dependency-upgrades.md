# Dependency upgrades (composer)

> **Status:** FINDINGS, dated 2026-08-24 - composer upgrade results. Read before re-attempting a TCPDF or Symfony bump.

Findings from bumping composer dependencies flagged by GitHub Dependabot, so the next
upgrade pass doesn't re-discover the same platform blockers and breaking-API changes from
scratch. This repo has **no automated test suite** (`codeception.yml`/`tests/` are an empty
skeleton — see `CLAUDE.md`), so every finding below came from live browser/CLI verification,
not tests. Also several `composer.json` files exist, not just the root one — see "Multiple
composer.json files" below before assuming a single `composer update` covers everything.

## 2026-08-24 session: 10 Dependabot PRs, symfony/console/http-foundation, guzzlehttp/psr7, phpdocumentor/reflection-docblock, phpoffice/phpspreadsheet, tecnickcom/tcpdf, adodb/adodb-php, tedivm/fetch

**Gotcha found first:** the two `actions/checkout`/`actions/upload-artifact` Dependabot PRs
were already stale before this session started — GitHub Actions CI workflows and
`.github/dependabot.yml` were deliberately removed the day before (commit `429475acc`,
"Remove GitHub Actions CI workflows and dependabot config"; see `deliberate-removals.md`
pattern — same "don't silently reintroduce" logic applies to workflow files as to app
code). If a fresh batch of Dependabot PRs shows up expecting to bump GitHub Actions
versions, this repo doesn't use GitHub Actions anymore — close them, don't merge.

**Also:** the specific PR numbers from a Dependabot screenshot were not resolvable via
`gh pr view`/`gh search prs` in either `jtylek/epesi` or `jtylek/EpesiCRM` — don't assume a
screenshot's PR numbers are live/searchable; verify with `gh pr list --repo ... --state all`
before trying to act on them by number.

### Platform blocker: Symfony capped at `^7.4`, not `^8.x`

**`symfony/console` and `symfony/http-foundation` cannot go to Symfony 8.x on this
codebase.** Symfony 8.1 requires `php >= 8.4.1`; this codebase's migration target is PHP
8.2 (see `MIGRATION_NOTES.md`). Bumped to `^7.4` instead (6.4.42 → 7.4.17) — highest line
actually installable on PHP 8.2. Don't re-attempt 8.x without a separate, deliberate
decision to also raise the PHP floor to 8.4 — that's a much bigger call than a dependency
bump and should be its own discussion, not a side effect of clearing a Dependabot PR.

### Breaking change: Symfony Console 7.x requires `execute(): int`

Symfony 6.4's `Command::execute(InputInterface $input, OutputInterface $output)` had no
declared return type. Symfony 7.x declares `: int`. Every `console/*Command.php` subclass
in this repo overrides `execute()` without a return type, which is a PHP fatal
(`Declaration ... must be compatible with ...: int`) the instant `console.php` tries to
autoload/list commands — not a corner case, it breaks the CLI entirely.

**Fixed for all 25 files** (added `: int` to every `execute()` override) as part of this
session. If a *new* console command is added later using an older command as a template,
make sure it also declares `execute(InputInterface $input, OutputInterface $output): int`
— easy to copy-paste from a stale example and lose the return type.

**Multi-session gotcha:** a concurrent Claude Code session working the same checkout at
the same time fixed this exact same issue in parallel via its own pass, and our two edits
collided on ~15 files, briefly producing a duplicated `): int: int` parse error. If you
suspect another session might be touching the same files, re-run `php -l` across the
affected files (or re-`git diff`) before trusting your own edit is the only one that landed.

### Breaking change: `phpdocumentor/reflection-docblock` v2 → v6 API

Old (v2, `^2.0.4`) constructor API: `new \phpDocumentor\Reflection\DocBlock($reflectionMethod)`,
then `->getShortDescription()` / `->getLongDescription()->getContents()`. This constructor
and both methods are gone in v5/v6. New API:

```php
$doc_comment = $reflection_method->getDocComment(); // false if none
if ($doc_comment !== false) {
    $docblock = \phpDocumentor\Reflection\DocBlockFactory::createInstance()->create($doc_comment);
    // ->getSummary(): string, ->getDescription(): DocBlock\Description (render with ->render())
}
```

Only one call site in tracked app code:
[RecordBrowser_0.php:3049](../modules/Utils/RecordBrowser/RecordBrowser_0.php#L3049)
(renders the docblock of a custom access-crit callback method in the admin UI). Fixed as
part of this session. If `modules/Premium/`/`modules/Custom/` (gitignored, not swept by
this pass) has its own direct usage of the old DocBlock constructor API, same fix applies.

### Safe: `phpoffice/phpspreadsheet` 2.x → 5.x

Bumped 2.4.7 → 5.9.0 without code changes. Only real consumer in this codebase is
`modules/Premium/Import/File/*.php` (gitignored Premium module), via `IOFactory::identify()`,
`IOFactory::createReaderForFile()`, and the `Reader\IReadFilter` interface — all long-stable,
unchanged across this version range. `Libs_PHPExcelCommon::load()`
(`modules/Libs/PHPExcel/PHPExcelCommon_0.php`) just wraps `IOFactory::load()`, also unchanged.
Not live-tested (no quick Excel-import entry point reachable in the time available), but low
risk given the API surface didn't move.

### Reverted: `tecnickcom/tcpdf` 7.x is fully broken here — stayed on `^6.2`

**Do not re-attempt bumping `tecnickcom/tcpdf` past 6.x without first solving the font
problem below.** Confirmed live: every `new TCPDF(...)` call fatals immediately
(`Com\Tecnick\Pdf\Font\Exception: unable to read file: helvetica.json`) — this isn't an edge
case, it's the constructor's own default `setFont()` call, so **all PDF generation** in the
app is dead the moment this package is bumped to 7.x.

**Root cause:** TCPDF 7.x was rewritten on top of a new `tecnickcom/tc-lib-pdf-font`
sub-package that stores font metrics as generated JSON files, not the old bundled PHP font
files. Generating those JSON files requires a `make deps fonts` step — but that step is
wired up as a `post-install-cmd`/`post-update-cmd` script inside **`tecnickcom/tcpdf`'s own
`composer.json`**, and Composer only ever runs the *root* package's lifecycle scripts, never
a dependency's. Since `tecnickcom/tcpdf` is a dependency of `modules/Libs/TCPDF/composer.json`
(itself a non-root, nested composer project — see below), that hook silently never fires no
matter how the update is run. There is no prebuilt font-JSON package on Packagist as of this
date, and the real `make deps fonts` pipeline shells out to `curl` (downloads an external
static-analysis tool) and expects a Linux/Debian-style toolchain — not something to run
unattended on a Windows dev box.

If this needs solving for real in a future session: either (a) find/generate the font JSON
files some other way (e.g. running the `make` pipeline manually in a disposable Linux
container, then vendoring the output), or (b) wait for upstream to fix the packaging gap
(dependency lifecycle scripts not firing is arguably a TCPDF/Composer-ecosystem bug, worth
checking if it's already reported upstream before re-solving it locally).

## Multiple `composer.json` files — not just the root one

`composer install`/`update` at the repo root only covers the root `composer.json`. Three
more exist, each its own independent composer project (root `composer.json`'s
`post-install-cmd` runs `composer install` in each on a fresh `composer install`, but **not**
on `composer update` — Composer's `post-install-cmd` doesn't fire for `update`, and there's
no `post-update-cmd` defined to cover it):

- `modules/CRM/Mail/composer.json` (`tedivm/fetch`)
- `modules/Libs/PHPExcel/composer.json` (`phpoffice/phpspreadsheet`)
- `modules/Libs/TCPDF/composer.json` (`tecnickcom/tcpdf`)

To update a package in one of these, run composer *in that directory* explicitly:
`composer -d modules/Libs/TCPDF update tecnickcom/tcpdf` (note: the `-d="path"` quoted form
used inside `composer.json`'s own scripts array does **not** work the same from Git Bash —
use `-d path` unquoted, or it errors with "Invalid working directory specified").

## Verification method that worked well (no test suite available)

1. `php -l` across changed files for syntax-level checks (fast, catches nothing API-level).
2. `console.php list` / `console.php module:list` as a CLI smoke test — boots the full
   autoloader and exercises real DB queries, catches fatals in every console command at once.
3. Started the four `AI-shared/log-monitoring.md` log tails (`Monitor` tool, persistent)
   *before* browser testing, so fatals/warnings surface as they happen rather than requiring
   a manual re-check afterward.
4. Drove the actual app with Playwright against the already-running local XAMPP instance
   (`http://localhost/euroleader/`) — logged in, exercised the Calendar (HttpFoundation via
   `ajax.php`), Contacts list Print (TCPDF) and Export (CSV, not PhpSpreadsheet) buttons.
   This is what actually caught the TCPDF break — none of steps 1-3 would have.
