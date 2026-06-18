# Epesi 1.9.1 → PHP 8.2 Migration Notes

Working notes from the PHP 7.4 → 8.2 migration of Epesi 1.9.1.
Kept in-repo so findings survive and stay versioned with the code.

**Environment:** XAMPP PHP 8.2.12 (Apache + MariaDB 10.4.32), Linux. System PHP 7.4.33 also present for regression checks.
**Baseline:** tag `vanilla-1.9.1` = pristine untouched 1.9.1.
**Tooling:** Rector 2.4.5 installed *isolated* in `~/rector-tool/` (separate from Epesi's vendor/) to avoid a php-parser version conflict — see "Rector setup" below.

---

## ✅ STATUS: Rector ladder complete

The full Rector migration ladder has been applied to all Core code (own code, excluding bundled libraries):

- **PHP 7.0 → 7.4** — applied to all directories
- **PHP 8.0** — applied (switch→match reviewed individually; one skipped in Administrator_0.php, see §5)
- **PHP 8.1** — applied (first-class callable, readonly, never; null→string cast rule deferred, see §5)
- **PHP 8.2** — verified: 685 files scanned, **zero changes** — code is already 8.2 syntax-clean

**Core is now PHP 8.2 syntax-compatible.** Verified via `php -l` (zero fatals) on all migrated code.

**NOT yet done** (separate efforts, mostly composer/architecture — Jasiek's domain):
- Composer dependency migration (QuickForm, Smarty, Roundcube, Memio) — blocks installer & full app test
- Deferred decisions documented in §5 below
- modules/Tests (220 files, low priority)
- 50 premium modules beyond Core
- Full runtime app testing (blocked by installer loop until QuickForm is migrated)

---

## 1. Fixes already applied (committed to `main`)

### PEAR::isError() made static
- **File:** `PEAR.php` line ~266
- **Problem:** `function isError(...)` was non-static but called statically as `PEAR::isError()`. Fatal on PHP 8.0+ ("Non-static method cannot be called statically").
- **Fix:** added `static` keyword. Method body only touches `$data` (the argument), never `$this`, so the change is safe.
- **Impact:** unblocked the installer past the License screen. Fixes every `PEAR::isError()` call across the codebase.

### timezone_identifiers_list() polyfill guarded
- **File:** `modules/Base/RegionalSettings/tz_list.php`
- **Problem:** defines `timezone_identifiers_list()` unconditionally. This is a polyfill from when PHP lacked the function — but it's native since long ago, so PHP 8.2 throws "Cannot redeclare".
- **Fix:** wrapped the definition in `if (!function_exists('timezone_identifiers_list')) { ... }`.
- **Note:** native PHP returns a fresher timezone list than this ~2010 hardcoded one — a bonus.
- **Pattern for future:** any "Cannot redeclare X" → same fix (guard with `function_exists`). Expect more polyfill relics in a 16-year-old codebase.

### Rector PHP 7.0–7.4 sets applied to all own code
Applied directory-by-directory, each reviewed + verified (`php -l` = no fatals) + committed + merged:
- `include/`, `admin/`, `console/`, root `.php` files
- `modules/FirstRun`, `Data`, `Tools`, `Apps`, `Applets`
- `modules/Utils` (233 files, 50 changed)
- `modules/Base` (299 files, 22 changed)
- `modules/CRM` (92 own files of 515; rest is Roundcube — skipped)

Rules applied (all behavior-preserving): null-coalescing (`isset()?:` → `??`), `dirname(dirname())` → `dirname(,2)`, PHP4 constructors → `__construct`, `create_function()` → anonymous functions, `{0}` → `[0]` array access, `list()` → `[]`, closures → arrow fns, static/instance call corrections, setcookie() options-array form, heredoc indentation.

---

## 2. KNOWN BLOCKERS — need decisions / the "proper order" composer work

### ★ QuickForm PHP4 constructor — root cause of the installer loop
- **File:** `modules/Libs/QuickForm/3.2.14-php7/HTML/QuickForm.php` line 284
- **Problem:** `function HTML_QuickForm(...)` is a PHP4-style constructor with NO `__construct`. On PHP 8.2 it is NOT recognized as a constructor → never runs on `new` → form's `name`/`method`/`action` attributes never set → renders a bare `<form>` → form submits as GET losing the license/htaccess params → installer bounces back to language select (the "loop").
- **Status:** NOT fixed. QuickForm is a vendored library (in `modules/Libs/`), excluded from Rector.
- **Recommended fix:** replace QuickForm 3.2.14 with a PHP-8-compatible version via composer (the "proper order" approach), rather than hand-patching the vendored copy.
- This is almost certainly the same loop seen on the earlier 8.x attempt.

### Installer loop — secondary findings (in `setup.php`)
While diagnosing, two installer issues surfaced (separate from the QuickForm root cause):
- Password field is marked `required` (line ~212), but XAMPP root has a blank password → validation always failed. (Worked around locally by commenting the rule during diagnosis; setup.php has since been restored to vanilla.)
- mysqli throws exceptions by default since PHP 8.1; the old `if ($link->connect_errno)` style check (setup.php ~line 299) assumes the pre-8.1 return-false behavior.
These are moot once QuickForm is fixed and the installer can be properly exercised.

### Memio dependency — console/Develop tools broken
- **Files:** `console/Develop/CreateModuleCommand.php`, `CreateTestModuleCommand.php` (line 13)
- **Problem:** `use Memio\Model\Object;` — `Object` is a reserved/special class name since PHP 8.0, so this is a fatal. The problem is inside the **Memio** library itself, not the Epesi files.
- **Status:** excluded from Rector (`console/Develop` in withSkip). These are developer tools (module scaffolding), not runtime app code.
- **Decision needed (Jasiek):** migrate/replace Memio, or remove these dev tools if no longer used.

### Roundcube — bundled webmail (CRM)
- **Location:** `modules/CRM/Roundcube/` — ~423 of CRM's 515 files. Has its own vendored PEAR, plugins, tinymce, etc.
- **Status:** entirely excluded from Rector.
- **Recommended approach:** replace the whole old Roundcube package with a current release, rather than migrating its internals.

---

## 3. Libraries excluded from Rector (third-party → composer, not Rector)

Rector is scoped to Epesi's OWN code only. These are skipped (`withSkip`) and should be handled via the composer dependency migration:
- `modules/Libs/` (QuickForm, etc.)
- `vendor/`
- `modules/CRM/Roundcube/` (entire bundled webmail)
- `modules/Base/Theme/smarty/` (Smarty 2 template engine)
- `console/Develop/` (depends on Memio)

**Principle followed:** migrate our own code with Rector; replace third-party libraries via composer. Don't hand-patch vendored libs (creates divergence from upstream).

---

## 4. Stale dev dependencies in composer.lock (PHP 7-only)

These block a clean `composer install` under PHP 8.2 (require `--ignore-platform-reqs` as a workaround). All are dev/testing tools, not runtime:
- `fzaninotto/faker` v1.9.2 (also marked **abandoned**)
- `memio/memio` v1.1.1
- `codeception/aspect-mock` 3.1.1 (pulls in `goaop/parser-reflection` → old php-parser)
- `symfony/var-dumper` v4 / `psy/psysh` (old)
- `symfony/debug` (abandoned → use `symfony/error-handler`)

**For the composer migration:** these need updating or removing.

---

## 5. Potential hidden bug to verify (not migration-caused)

### BBCode::strip() called with a dead argument
- **File:** `modules/Utils/BBCode/BBCodeCommon_0.php` line ~43
- **Found by:** Rector's `RemoveExtraParametersRector` removed a 2nd argument:
  `self::strip($match[4], self::$optimize_only)` → `self::strip($match[4])`
- **Why flagged:** `strip()` only accepts one parameter, so `self::$optimize_only` was ALWAYS ignored. Someone may have *intended* the optimize-only behavior to apply — but it never did. Possible latent logic bug worth a look. (Rector's removal is behavior-preserving; the arg was dead either way.)

### continue inside switch — Filters_0.php
- **File:** `modules/Utils/RecordBrowser/Filters/Filters_0.php` lines 189, 249
- **Warning:** `"continue" targeting switch is equivalent to "break". Did you mean "continue 2"?`
- PHP 7.3+ warns that `continue` inside a `switch` acts like `break` — it does NOT skip to the next iteration of the enclosing loop. May be intentional, or a latent bug (author may have meant `continue 2`). Pre-existing, not migration-caused. Needs the author's intent to resolve.

### switch→match skipped — Administrator_0.php ($row['admin'] type risk)
- **File:** `modules/Base/User/Administrator/Administrator_0.php`
- `switch($row['admin'])` with integer cases (1 = Administrator, 2 = Super Administrator). DB values may arrive as strings ("1"/"2") depending on ADOdb fetch mode. `switch` uses loose `==` (matches), `match` uses strict `===` (would NOT match string "2" against int 2 → admins would fall through to "User"). Affects admin role display in the user list UI.
- **Decision:** left as `switch` (safe under any type). `switch→match` is excluded for this file in `rector.php`. Revisit when DB fetch types are verified — if `$row['admin']` is reliably int, the conversion is safe.

### PHP 8.1: null→string deprecation NOT addressed (NullToStrictStringFuncCallArgRector)
- **Scope:** ~199 files would get `(string)` casts wrapping args to built-in string functions (strlen, preg_match, str_contains, etc.)
- **Why:** PHP 8.1 deprecates passing `null` to non-nullable string params of internal functions (fatal in PHP 9.0).
- **Why skipped:** (1) it's a deprecation, not a fatal — code runs fine on 8.2; (2) our target is 8.2, this is PHP 9 prep; (3) Rector's auto-fix blindly wraps everything in `(string)`, mostly unnecessary (vars are rarely null) and potentially masking real null-bugs instead of surfacing them.
- **Recommendation:** review the actual null-prone call sites individually and fix meaningfully (default values, proper null handling) rather than mass-casting. Revisit when targeting PHP 9.
- Rule excluded globally in `rector.php` (`NullToStrictStringFuncCallArgRector::class` in withSkip).

---

## 6. Rector setup (for reproducing)

Rector could NOT be installed inside Epesi's `vendor/`: Epesi's `goaop/parser-reflection` (via aspect-mock) needs php-parser v4, Rector needs v5 — a hard conflict that crashes on autoload (`ParserFactory::create()` undefined).

**Solution:** isolated install.
```bash
mkdir ~/rector-tool && cd ~/rector-tool
composer require rector/rector --ignore-platform-reqs
```
Run it pointed at Epesi, using Epesi's `rector.php` config:
```bash
~/rector-tool/vendor/bin/rector process --config rector.php --dry-run
```

---

## 7. Workflow used (per directory)

1. `git checkout -b rector/<step>` (branch per chunk)
2. Edit `rector.php` paths + sets
3. `php -l rector.php` (config valid)
4. `rector process --config rector.php --dry-run` → review every new rule's diffs
5. Apply for real (drop `--dry-run`)
6. `php -l` changed files → must be zero fatals
7. Browser check (reaches language screen = nothing newly broken)
8. Commit → merge to `main` → push → delete branch

Safety net: `vanilla-1.9.1` tag = floor you can never fall below.
`git diff vanilla-1.9.1 -- <file>` shows everything changed since pristine.

---

## 8. Remaining work

- [ ] Rector PHP_80, PHP_81, PHP_82 sets (the big one is 80 — match, union types, named args, removed functions — do it as its own focused pass per directory)
- [ ] `modules/Tests` (220 files) — low priority, old Codeception tests
- [ ] **Composer dependency migration** (the "proper order" — Jasiek's call): QuickForm, Smarty, Roundcube, Memio, stale dev deps
- [ ] Once QuickForm is fixed: complete the installer, log in, test the actual app (CRM and post-login code is untestable until the installer passes)

**Important testing limitation:** the browser test only exercises code up to the installer loop. Post-login code (most of CRM, dashboards, modules) is NOT visually tested yet — currently relying on `php -l` (zero fatals) + the behavior-preserving nature of Rector changes. Full app testing comes after QuickForm is migrated and the installer completes.

---

## 9. Dependency reconnaissance (composer) — recon done, action pending

State captured after the Rector ladder was complete, before touching any dependencies.
All findings below are READ-ONLY recon — nothing was changed yet.

### Composer itself
- Was 2.0.14 (2021), self-updated to **2.10.1** (`sudo /opt/lampp/bin/php /usr/bin/composer self-update`).
- Must run composer under PHP 8.2 explicitly: `/opt/lampp/bin/php /usr/bin/composer ...` (system `composer` defaults to PHP 7.4).
- `composer diagnose`: PHP 8.2.12 OK, packagist connectivity OK, git/zip/openssl OK.

### Only ONE hard platform conflict
`check-platform-reqs` (everything in vendor/ vs PHP 8.2) came back almost clean:
- **fzaninotto/faker** — requires php ^5.3.3 || ^7.0 → fails on 8.2. ABANDONED, dev-only (fake data for tests). Not runtime.
- Everything else in vendor/ (symfony 2.x, twig 1.x, memio, pimple, moneyphp, phpfastcache, etc.) PASSES check-platform-reqs.
- CAVEAT: check-platform-reqs only checks declared PHP/ext version constraints — it does NOT catch runtime API incompatibilities (e.g. QuickForm's PHP4 constructor, which is vendored and not even in vendor/). "Passes" = no version conflict, not "definitely works".

### composer.lock format is stale
- `composer diagnose` → `composer.lock: FAIL — platform: Array value found, but an object is required`.
- The lock was generated by an old composer; 2.10.1 reads it but will rewrite it to the new format on the next install/update. Expect composer.json/lock to change on first composer operation → ISOLATE on an experiment branch.

### Dependency layout (why libraries are scattered — historical, not laziness)
- Epesi predates composer (Epesi ~2006, composer 2012). Early libs were vendored by hand into `modules/Libs/` because that was the only option then.
- `modules/Libs/` vendored libs: CKEditor, Codepress, Leightbox, OpenFlashChart, ScriptAculoUs (mostly old JS — do NOT block PHP 8.2), plus **QuickForm** (the real PHP blocker) and PHPExcel/TCPDF.
- PHPExcel and TCPDF have their OWN composer.json (nested composer, run via `composer -d="modules/Libs/..." install` in post-install-cmd) — deliberate dependency isolation, not a mistake.
- composer.json also flags: psr-0 empty-namespace autoload (perf), and unbound `@stable` constraints on psy/psysh and enyo/dropzone (risky — could pull anything).

### Plan for next session (ACTION, on an experiment branch)
1. Create `experiment/composer-deps` branch (full reversibility; main stays clean). Use a throwaway DB (epesi_test) so installs can be dropped.
2. Tackle QuickForm (vendored, main installer blocker) — replace with an 8.2-compatible version, ideally via composer (e.g. pear/html_quickform2) to consolidate under composer where possible.
3. Run the app, see what breaks NEXT (expect a cascade: Smarty, possibly others). Fix iteratively, driven by real errors, not bulk.
4. Goal: get the installer to complete so the migrated code can finally be tested at runtime (the whole point — php -l proves syntax, not behavior).