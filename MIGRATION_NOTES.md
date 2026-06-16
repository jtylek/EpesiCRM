# Epesi 1.9.1 → PHP 8.2 Migration Notes

Working notes from the PHP 7.4 → 8.2 migration of Epesi 1.9.1.
Kept in-repo so findings survive and stay versioned with the code.

**Environment:** XAMPP PHP 8.2.12 (Apache + MariaDB 10.4.32), Linux. System PHP 7.4.33 also present for regression checks.
**Baseline:** tag `vanilla-1.9.1` = pristine untouched 1.9.1.
**Tooling:** Rector 2.4.5 installed *isolated* in `~/rector-tool/` (separate from Epesi's vendor/) to avoid a php-parser version conflict — see "Rector setup" below.

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
