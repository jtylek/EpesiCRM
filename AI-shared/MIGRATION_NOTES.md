# Epesi 1.9.1 → PHP 8.2 Migration Notes

Working notes from the PHP 7.4 → 8.2 migration of Epesi 1.9.1.
Kept in-repo so findings survive and stay versioned with the code.

**Environment:** XAMPP PHP 8.2.12 (Apache + MariaDB 10.4.32), Linux. System PHP 7.4.33 also present for regression checks.
**Baseline:** tag `vanilla-1.9.1` = pristine untouched 1.9.1.
**Tooling:** Rector 2.4.5 installed *isolated* in `~/rector-tool/` (separate from Epesi's vendor/) to avoid a php-parser version conflict — see "Rector setup" below.

---

## ✅ STATUS: Rector ladder complete, app running on PHP 8.2, released as 20260701-rc1

The full Rector migration ladder has been applied to all Core code (own code, excluding bundled libraries):

- **PHP 7.0 → 7.4** — applied to all directories
- **PHP 8.0** — applied (switch→match reviewed individually; one skipped in Administrator_0.php, see §5)
- **PHP 8.1** — applied (first-class callable, readonly, never; null→string cast rule deferred, see §5)
- **PHP 8.2** — verified: 685 files scanned, **zero changes** — code is already 8.2 syntax-clean; reconfirmed clean post-hardening (636 files, zero changes — see PHASE 5 STATUS)

**Core is now PHP 8.2 syntax-compatible.** Verified via `php -l` (zero fatals) on all migrated code.

**Since resolved** (this section originally listed these as "NOT yet done" — kept here only as a pointer to where each was actually closed out):
- Composer dependency migration — DONE: QuickForm → openpsa/quickform (§11), ADOdb → adodb/adodb-php (§11), Roundcube 1.2.1→1.7.1 (§30). Smarty deliberately patched-in-place rather than replaced, by design (§17). Stale dev deps partially cleaned (aspect-mock removed, §51); faker/memio/psysh still open, see §4.
- Full runtime app testing — DONE: installer completes, app logs in and renders the full dashboard (§15); Core modules CRUD-tested (§23–§41); hardening pass completed and released as CalVer `20260701-rc1` (PHASE 5 STATUS).

**Still open:**
- Deferred decisions documented in §5 below
- modules/Tests (220 files, low priority)
- 50 premium modules beyond Core (see §59 for the upgrade-safety gate)

---

## ⚠️ UPGRADE-GAP DISCIPLINE (critical — read before "fixing" data)

The real 7.4→8.2 upgrade is **not just deploying code**. A fix only reaches real users if it ships in a form that
runs on their **existing** database/files. Classify every fix:

- **CODE fix** (PHP logic in `.php`) → applies automatically on upgrade (deploy the code). ✅ No gap.
- **DATA fix** (edited `*Install.php` defaults, a one-off `UPDATE` on the dev DB, or changed `data/` files) →
  reaches **fresh installs + the dev DB only**. Existing/upgraded DBs keep the OLD data. ❌ **Upgrade gap** unless
  it also ships as a **patch**.

**Rule: a data fix MUST ship a patch** (`modules/<M>/patches/<YYYYMMDD>_<name>.php`) — it runs on existing
instances via `runpatches.php`/`update.php`. Example: §25 fixed the clipboard pattern only in `ContactsInstall.php`
+ dev DB → worked on `epesi82_test`, **broke on the real client** → fixed by the §45 patch.

**Detection (the catch-all) — fresh-vs-upgraded DB diff:**
1. Build a **clean fresh 8.2 install** (installer on an empty DB) → reference DB + `data/`.
2. Run the **full upgrade** on a real copy (deploy code + `runpatches.php` + Roundcube DB migration).
3. **Diff** the two:
   - **Schema** (tables/columns/indexes) → differences = missing schema migrations.
   - **Seed/config data** (`recordbrowser_*` field defs, `recordbrowser_clipboard_pattern`, `commondata` arrays,
     access rules, default settings) → differences = default-data gaps where fresh got fixes the upgrade didn't.
4. Each difference → write a patch. (Mechanical; finds gaps clicking never will.)

**Make the upgrade complete + automatic:** the full upgrade = code + `runpatches.php` (themeup/translations/caches/
patches) + RC DB migration. Bump `EPESI_VERSION` so `update.php` auto-runs it on first load → real users upgrade by
*deploy code → open app → done*.

**Result (gap hunt 2026-06-30, fresh `epesi82_test` vs upgraded client `epesi_upgrade_test`):**
- **Schema diff CLEAN** — 222 common tables; every column fresh has, the upgrade also has. Only reverse-direction
  difference is `rc_mails_attachments.file_id` (the §44 mail column). The patch system handles schema correctly.
- **§23–§45 classified:** the *only* DATA fixes are **§25** (clipboard pattern) → shipped as the **§45 patch**
  (verified: zero broken nested patterns remain on the client; `clipboard_pattern` matches fresh exactly) and §45
  itself. **All other fixes are CODE** → auto-apply on deploy. §30 = separate Roundcube DB migration. No other gap.
- **Conclusion:** no outstanding upgrade gaps. Keep the diff as a regression check before each release; keep applying
  GOLDEN_RULES §11 (any new data fix ⇒ patch).

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
- **STATUS: RESOLVED — see §11.** QuickForm 3.2.14 was replaced with `openpsa/quickform` via composer (the "proper order" approach recommended below was the path taken).
- **File:** `modules/Libs/QuickForm/3.2.14-php7/HTML/QuickForm.php` line 284
- **Problem (history):** `function HTML_QuickForm(...)` is a PHP4-style constructor with NO `__construct`. On PHP 8.2 it is NOT recognized as a constructor → never runs on `new` → form's `name`/`method`/`action` attributes never set → renders a bare `<form>` → form submits as GET losing the license/htaccess params → installer bounces back to language select (the "loop"). This is almost certainly the same loop seen on the earlier 8.x attempt.

### Installer loop — secondary findings (in `setup.php`)
**RESOLVED — moot.** QuickForm is fixed and the installer now completes end-to-end (§15). While diagnosing, two installer issues had surfaced (separate from the QuickForm root cause), kept as history:
- Password field is marked `required` (line ~212), but XAMPP root has a blank password → validation always failed. (Worked around locally by commenting the rule during diagnosis; setup.php has since been restored to vanilla.)
- mysqli throws exceptions by default since PHP 8.1; the old `if ($link->connect_errno)` style check (setup.php ~line 299) assumes the pre-8.1 return-false behavior.

### Memio dependency — console/Develop tools broken
- **Files:** `console/Develop/CreateModuleCommand.php`, `CreateTestModuleCommand.php` (line 13)
- **Problem:** `use Memio\Model\Object;` — `Object` is a reserved/special class name since PHP 8.0, so this is a fatal. The problem is inside the **Memio** library itself, not the Epesi files.
- **Status:** excluded from Rector (`console/Develop` in withSkip). These are developer tools (module scaffolding), not runtime app code.
- **Decision needed (Jasiek):** migrate/replace Memio, or remove these dev tools if no longer used.

### Roundcube — bundled webmail (CRM)
- **STATUS: DONE — see §30.** Replaced with a full Roundcube 1.2.1 → 1.7.1 upgrade (the recommended approach below was the path taken).
- **Location:** `modules/CRM/Roundcube/` — ~423 of CRM's 515 files. Has its own vendored PEAR, plugins, tinymce, etc.
- Entirely excluded from Rector.

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
- ~~`codeception/aspect-mock` 3.1.1 (pulls in `goaop/parser-reflection` → old php-parser)~~ **DONE — removed, see §51.**
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
**DONE — executed in §10–§15.**
1. Create `experiment/composer-deps` branch (full reversibility; main stays clean). Use a throwaway DB (epesi_test) so installs can be dropped.
2. Tackle QuickForm (vendored, main installer blocker) — replace with an 8.2-compatible version, ideally via composer (e.g. pear/html_quickform2) to consolidate under composer where possible.
3. Run the app, see what breaks NEXT (expect a cascade: Smarty, possibly others). Fix iteratively, driven by real errors, not bulk.
4. Goal: get the installer to complete so the migrated code can finally be tested at runtime (the whole point — php -l proves syntax, not behavior).

---

## 10. QuickForm fix-once experiment (branch: experiment/composer-deps) — findings

Goal was "fix once": quickly patch QuickForm to see if the installer runs and whether
the Rector-migrated Core code actually works. NOT a long-term fix — QuickForm will be replaced.

### Result: hypothesis PARTIALLY confirmed
Got the installer from "fatal on every load" all the way to "license form renders, checkboxes
work, values submit". CRUCIAL: every single blocker was inside QuickForm/PEAR (vendored libs) —
NOT ONE was in the Rector-migrated Core code. Strong evidence Rector broke nothing. BUT: full
confirmation needs a RUNNING app (past the installer), which is why we're moving to the drop-in
replacement rather than finishing the manual patching.

### The QuickForm blocker chain (5 layers, each uncovered by fixing the previous)
1. **PHP4 constructors** — `function HTML_QuickForm()` etc. not recognized on 8.2 → caused the
   original installer loop. Fixed by Rector's Php4ConstructorRector (on the sandbox branch).
2. **get_magic_quotes_gpc()** — removed in PHP 8.0. QuickForm.php:292. Patched to `false`
   (the call was inside dead magic-quotes logic that never runs on modern PHP anyway).
3. **Constructor call sites** — element.php:411 and file.php:188 called constructors the old
   PHP4 way (`call_user_func_array(array($this,$className),...)` / `$this->$className(...)`).
   Patched to call `__construct` directly.
4. **PEAR::raiseError() called statically** — PEAR.php:511, non-static method called statically
   (same class of bug as PEAR::isError fixed earlier). Made `static`. Note: raiseError has
   dual-mode `isset($this)` branches that become dead under static — fine in practice (all Epesi
   calls are static) but worth a proper rewrite in fix-twice.
5. **RemoveParentCallWithoutParentRector REMOVED needed parent::__construct calls** ⚠️ CRITICAL
   — this Rector rule stripped `parent::__construct($elementName,...)` from element constructors.
   Result: `name` attribute never set → form elements rendered as `<input>` with NO name →
   browser submits empty data → installer loops back to language select. Confirmed by fixing
   checkbox.php (added parent::__construct) → `name="tos1"` appeared, values started submitting.
   27 element files in QuickForm/3.2.14-php7/HTML/QuickForm/ are affected (grep -L parent::__construct).

### LESSON for any future Rector run on PHP4-era code
**EXCLUDE `RemoveParentCallWithoutParentRector`** when migrating old PHP4-constructor code.
When Php4ConstructorRector and RemoveParentCallWithoutParentRector run together, the latter can
wrongly decide a parent "has no constructor" (because it's mid-conversion) and delete a needed
parent call. This silently breaks behavior (php -l stays clean — it's a logic bug, not syntax).

### Decision: replace QuickForm (fix-twice), don't finish manual patching
Patching all 27 elements by hand = throwaway work. Instead, replace the whole vendored QuickForm.

### Replacement candidates (researched)
- **openpsa/quickform** ← BEST CANDIDATE. Explicit drop-in replacement for the OLD HTML_QuickForm
  API (keeps `addElement('checkbox','tos1',...)` style → minimal Epesi code changes). Bundles its
  own HTML_Common, replaces PEAR_Errors with exceptions, composer autoloading. Caveat: may still
  need some static→nonstatic call adjustments.
- pear/HTML_QuickForm2 (2.3.2, tested on PHP 8.2/8.3) — but it's a REWRITE with a DIFFERENT API
  (chainable, HTML_QuickForm2_* classes). Would require rewriting all QuickForm usage in Epesi. Big.
- mistralys/html_quickform2 — modernized fork of QuickForm2 (strict typing). Same different-API issue.

### Next step
Try openpsa/quickform as a composer drop-in on a fresh branch. Goal: get the installer to COMPLETE
and the app to RUN, so the Rector migration can be tested end-to-end on a live application.

### Cleanup note
The fix-once patches (this branch, uncommitted) were reverted after capturing these findings.
The knowledge lives here in the notes; the code stays clean.

---

## 11. Drop-in replacement: QuickForm → openpsa, ADOdb → composer (branch: experiment/composer-deps)

Decision from §10: instead of patching 27 vendored QuickForm elements by hand (throwaway work),
replace the vendored libs with composer packages. This is the "fix twice" approach. Goal: get the
installer to COMPLETE and the app to RUN, proving the Rector migration works end-to-end on a live app.

### 11.1 QuickForm → openpsa/quickform v3.4.2
- Installed: `composer require openpsa/quickform --ignore-platform-reqs`
  (--ignore-platform-reqs needed because old dev pkgs faker/memio/aspect-mock/psysh still pin PHP ≤7.x;
   they're dev-only, unused at runtime — cleanup is a separate task.)
- openpsa is a TRUE drop-in: defines the same `class HTML_QuickForm extends HTML_Common`, same old API
  (`addElement('checkbox','tos1',...)`), bundles its own HTML_Common, replaces PEAR_Errors with
  exceptions, uses composer classmap autoload. No PEAR dependency.
- **Disabled old loading**: `modules/Libs/QuickForm/requires.php` — wrapped the entire original
  loading logic (include_path manipulation + `require_once('HTML/QuickForm.php')`) in a /* */ comment.
  Now the old 3.2.14-php7 class never loads; openpsa is provided via autoload. Verified with
  `ReflectionClass('HTML_QuickForm')->getFileName()` → confirms openpsa path loads, not the old one.
- **setup.php didn't load composer autoload** (it's a lightweight entrypoint, separate from the main
  include.php bootstrap). Added `require_once('vendor/autoload.php')` near line 19, before QuickForm use.
  Without this: "Class HTML_QuickForm not found" even though classmap was correct.

### 11.2 ADOdb v5.20.2 (2015) → adodb/adodb-php v5.22.11 (2025)
- Old vendored `libs/adodb/` was v5.20.2 from Dec-2015, used `each()` (removed in PHP 8.0) → fatal in
  clean_database() during install. 6 `each()` occurrences across the old lib.
- Installed: `composer require adodb/adodb-php --ignore-platform-reqs` → vendor/adodb/adodb-php/.
  Same library (not a rewrite), so same API (`NewADOConnection()`), php: ^7.0||^8.0, officially 8.2+.
- **Repointed includes** in `include/database.php` lines 17-18 from `libs/adodb/` to
  `vendor/adodb/adodb-php/` (both adodb-errorhandler.inc.php and adodb.inc.php).
- **Then DISABLED the errorhandler** (database.php line 20, commented out): new ADOdb's
  adodb-errorhandler.inc.php does `trigger_error($s, E_USER_ERROR)` on EVERY sql error. Epesi handles DB
  errors via `@` suppression + return values (e.g. check.php probes for a 'test' table existence and
  expects a soft failure). `@` does NOT suppress E_USER_ERROR → fatal. Not loading the errorhandler
  returns ADOdb to its default quiet mode (errors return false), which is what Epesi's `@`+return-value
  pattern expects. This is the correct architectural choice — Epesi has its own error handling.

### 11.3 Core PHP 8 fixes surfaced by running the live installer (NOT Rector's fault — PHP 8 changes)
These are real PHP 8 incompatibilities that php -l can't catch — only running the app revealed them.
This validates insisting on a live-app test, not just Rector + php -l.

- **handle_epesi_error() arg count** (`include/error.php:207`): PHP 8.0 calls error handlers with 4 args,
  not 5 (dropped $errcontext). Signature required 5 → ArgumentCountError on the first DB error. Fixed:
  added ` = null` to 5th param → `function handle_epesi_error($type,$message,$errfile,$errline,$errcontext=null)`.
  NOTE for Jasiek: other handlers may have the same 5-arg contract — audit all set_error_handler targets.

- **get_magic_quotes_gpc()** removed in PHP 8.0, scattered in Core. Found via grep, fixed in 5 Core files
  (all the `if(get_magic_quotes_gpc()) {...undoMagicQuotes...}` dead-block pattern → replaced call with
  `false` so the block is dead, matching PHP 8.2 behavior where magic quotes never exist):
    include/epesi.php:271, modules/Utils/FileUpload/upload.php:102, modules/CRM/Tasks/TasksCommon_0.php:179,
    modules/CRM/Meeting/MeetingCommon_0.php:352, modules/CRM/PhoneCall/PhoneCallCommon_0.php:209
  (also earlier: include/magicquotes.php:11). DELIBERATELY EXCLUDED: include/database.php:953 (it's a
  docblock comment) and 3 Roundcube files (vendored webmail — never touch). Bulk sed was applied ONLY to
  the explicitly-listed Core files, never a blind tree-wide sed — analysis first, then targeted fix.

### 11.4 Epesi's own QuickForm extensions need PHP4→PHP8 constructor fixes for openpsa (RESOLVED — see §12.2)
After the installer COMPLETED (license→htaccess→db config→system check all green) and entered the app
(process.php → FirstRun wizard), hit "Cannot call constructor" at QuickForm_0.php:37 (`new HTML_QuickForm`).
Root cause: NOT openpsa (isolated `new HTML_QuickForm(...)` test → SUCCESS). It's Epesi's OWN renderer/
field-type subclasses (loaded by QuickForm_0.php lines 15-20) which still have PHP4-style constructors
calling now-nonexistent parent constructors. openpsa's HTML_QuickForm_Renderer is `abstract` with no
constructor, so the old `$this->HTML_QuickForm_Renderer()` call fails.

- **FIXED** `modules/Libs/QuickForm/Renderer/TCMSDefault.php:150` — had a DEAD php4 ctor mis-named
  `HTML_QuickForm_Renderer_Default()` (parent's name, not this class — Rector missed it because the name
  ≠ class name). It only called the nonexistent parent ctor. Replaced with empty `__construct()` (class
  properties are initialized at declaration, no ctor logic needed).

- **FIXED — see §12.2** (same family, found via grep — these have name == own class, so they're REAL php4
  ctors that DO initialize; renamed to __construct + fixed internal parent call to parent::__construct):
    Renderer/TCMSArray.php:157         (calls $this->HTML_QuickForm_Renderer())
    Renderer/TCMSArraySmarty.php:123   (calls $this->HTML_QuickForm_Renderer_TCMSArray(...))
    FieldTypes/autoselect/autoselect.php:32
    FieldTypes/automulti/automulti.php:71
    FieldTypes/autocomplete/autocomplete.php:27
    FieldTypes/multiselect/multiselect.php:60
  Each must be inspected individually (different ctor args, different parents) — NOT a blind sed.
  Plan: rename `function HTML_QuickForm_X(...)` → `function __construct(...)`, and rewrite inner
  `$this->HTML_QuickForm_Y(...)` → `parent::__construct(...)` mapping the args.

### LESSON for Jasiek / fix-twice
Epesi's QuickForm renderer + field-type subclasses (Renderer/, FieldTypes/) carry PHP4 constructors that
Rector did NOT convert — either out of scope (modules/Libs excluded) or because the ctor name didn't match
the class name. When moving to openpsa these must become proper `__construct` with `parent::__construct`.

### State of play
- Installer runs end-to-end on PHP 8.2, DB schema starts building, app reaches FirstRun wizard.
- The 6 extension constructors above were fixed (§12.2), and FirstRun/login/live app were reached (§15).
- Open flags: "Modules dir writable: No" (yellow on system-check; may matter for module install — chmod
  modules/ if needed). Old libs/adodb/ + old modules/Libs/QuickForm/3.2.14-php7/ were unused → cleanup
  candidates AFTER proving they're dead (grep whole codebase first, same discipline as before).
  **`3.2.14-php7/` removed, see §50. `libs/adodb/` still present on disk — not yet deleted.**
- --ignore-platform-reqs still needed for composer ops until dev pkgs (faker/memio/aspect-mock/psysh) cleaned.

### Test DB (for resuming)
DB/user/pass all `epesi82_test`. Reset between full retries:
  /opt/lampp/bin/mysql -u root -e "DROP DATABASE IF EXISTS epesi82_test; CREATE DATABASE epesi82_test CHARACTER SET utf8 COLLATE utf8_general_ci;"
  rm -f data/config.php
Installer DB form: localhost / epesi82_test ×3 / Create new database: No.

---

## 12. Drop-in continued: openpsa QuickForm extensions + ADOdb + live app reached (branch: experiment/composer-deps)

MAJOR MILESTONE: Epesi RUNS on PHP 8.2. The installer completes, FirstRun wizard renders,
admin account created, mail config passed, MODULES INSTALLED (Base, Dashboard, CKEditor, Codepress…).
Currently in the final FirstRun step (Contacts install) on a custom-element-type registration issue.
This is the live-app proof the whole migration was aiming for — Rector's work runs end-to-end.

### 12.1 ADOdb v5.20.2 → adodb/adodb-php v5.22.11 (recap, done in §11)
Old `libs/adodb/` (2015) used `each()` (removed PHP 8.0). Installed adodb/adodb-php via composer,
repointed include/database.php:17-18 to vendor/, disabled adodb-errorhandler (it triggers E_USER_ERROR
on every sql error, breaking Epesi's @-suppressed checks). Same library, same API — clean drop-in.

### 12.2 openpsa QuickForm — Epesi extension subclasses fixed (PHP4 ctors → __construct)
Epesi's renderer + field-type subclasses had PHP4-style constructors that Rector didn't convert
(out of scope, or ctor name ≠ class name). Each: renamed `function HTML_QuickForm_X(...)` →
`function __construct(...)`, and rewrote the inner PHP4 parent call `$this->HTML_QuickForm_Y(...)`
or `HTML_QuickForm_element::HTML_QuickForm_element(...)` → `parent::__construct(...)`:
  Renderer/TCMSDefault.php   (dead mis-named ctor → empty __construct; parent is abstract, no call)
  Renderer/TCMSArray.php     (ctor + removed call to abstract parent; ALSO added 2 missing abstract
                              methods finishForm()/renderHtml() openpsa requires)
  Renderer/TCMSArraySmarty.php (ctor → __construct, inner call → parent::__construct)
  FieldTypes/autoselect, automulti, autocomplete, multiselect (ctor → __construct + parent::__construct)

### 12.3 openpsa — the REAL "Cannot call constructor" cause: stale class via include_path (CRITICAL)
The persistent "Cannot call constructor" was NOT the ctors above (those were real PHP4 relics, needed
fixing anyway, but weren't the cause). Root cause found by ReflectionClass diagnostics + error_log:
Epesi's renderer/field-type files load their QuickForm parents via RELATIVE require_once that resolves
through include_path to the OLD 3.2.14-php7 copy:
  TCMSArray.php:30 / TCMSDefault.php:30  →  require_once 'HTML/QuickForm/Renderer.php'
  FieldTypes/*/*.php                     →  require_once 'HTML/QuickForm/select.php' (or text.php)
This pulled the OLD HTML_QuickForm_element (accept() WITHOUT type) into memory, then openpsa's
HTML_QuickForm_hidden (accept() WITH type HTML_QuickForm_Renderer) → E_COMPILE_ERROR "must be compatible".
The error surfaced as "Cannot call constructor" because the compile failure happened inside the
openpsa ctor's addElement('hidden') call.
FIX: commented out ALL those relative require_once lines (6 files). openpsa provides every QuickForm
class via composer autoload, so the old 3.2.14-php7 files must never be loaded. After this, all
classes resolve consistently to openpsa → signatures compatible → form builds.
KEY LESSON: with a composer drop-in, hunt down EVERY relative require_once of the old lib — include_path
will otherwise silently load stale classes that conflict with the new ones.

### 12.4 openpsa — renderHidden signature mismatch
TCMSArray/TCMSDefault `renderHidden(&$element)` (1 arg) incompatible with openpsa abstract
`renderHidden(&$element, $required, $error)` (3 args). Added `$required = false, $error = null`
to both (defaults keep existing 1-arg calls working). Body unchanged.

### 12.5 Smarty (template engine) — 2 PHP-8-removed functions patched
Old vendored Smarty (~2005) in modules/Base/Theme/smarty/. Only 2 real relics (rest of grep hits
were preg_split, which is fine):
  Smarty_Compiler.class.php:265  create_function(...) → anonymous function (PHP 8.0 removed it)
  Smarty_Compiler.class.php:566  list(,$block)=each(...) → $block=current(...); next(...);
Note: Smarty WILL be replaced long-term (Smarty 5 is PHP 8-native). Only 2 micro-patches needed to
pass it for now → don't deep-patch, replace later (fix-twice).

### 12.6 openpsa — registerElementType() called statically (CKEditor, Codepress) (RESOLVED — see §79)
modules/Libs/CKEditor/CKEditorCommon_0.php:18 and modules/Libs/Codepress/CodepressCommon_0.php:16 call
`HTML_Quickform::registerElementType(...)` statically; openpsa declares it non-static → fatal.
The method only writes to $GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES'] (no $this), so made it `static` in
vendor (QuickForm.php:296). This unblocked module installation (modules then installed successfully).
⚠️ VENDOR EDIT (lost on composer update). FIX-TWICE plan (user's decision): move the change OUT of
vendor by rewriting the 2 Epesi calls to write the global directly, like Epesi does everywhere else:
  $GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['ckeditor'] = 'HTML_Quickform_ckeditor';  // + codepress
Then revert the vendor `static` edit. (Not yet done — verify-first approach: confirmed static works,
trad-off change pending.)

### 12.7 openpsa — custom element type registration format mismatch (timing RESOLVED — see §15.1; format mismatch itself RESOLVED — see §79)
Epesi registers ~8 custom element types as ARRAY: 
  $GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['commondata'] = array('file.php', 'ClassName');
(commondata, commondata_group, datepicker, timestamp, critsvalue, currency, multiselect, autocomplete,
automulti, autoselect). openpsa expects a STRING (classname only) and instantiates via ReflectionClass
(autoload, no file include). Patched openpsa `_loadElement` (QuickForm.php:477) to accept BOTH formats:
if array → require_once($reg[0]) then use $reg[1]; else use string. (Vendor edit — fix-twice candidate.)
BUT the blocker was actually EARLIER: `isTypeRegistered()` (QuickForm.php:1128) checks the global and
throws at line 476 BEFORE reaching the format handler. **Final root cause + fix: §15.1** (openpsa resets
the types global on first autoload; fixed by forcing that reset to happen before Epesi's eager
registration runs).

---

## 13. QUICK-FIXES TO RESOLVE PROPERLY (fix-twice checklist)

All of these got Epesi running on PHP 8.2 but are temporary. Each needs a permanent solution.

### VENDOR EDITS (lost on `composer update` — highest priority to relocate)
1. **openpsa registerElementType() made static** — vendor/openpsa/quickform/lib/HTML/QuickForm.php:296
   **DONE — see §79.** CKEditor's caller was already gone (superseded by Quill); rewrote Codepress's
   remaining static call to write $GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES'] directly. Vendor no longer
   needs the `static` edit (nothing in Epesi calls the method anymore) — left un-reverted since it's
   harmless and dead code, not because it's still needed.

2. **openpsa _loadElement() dual-format patch** — vendor/openpsa/quickform/lib/HTML/QuickForm.php:477
   **DONE — see §79.** This exact vendor patch was found lost (composer reset), which is what
   surfaced this item. Fixed properly per the option below: converted all 8 Epesi registration
   sites to openpsa's plain-string format, each paired with an explicit `require_once` of the
   class file (no PSR-4 autoload for Epesi's own classes). No vendor edit needed at all now.

### EPESI CODE FIXES (survive composer update, but some are band-aids)
3. **Relative require_once of old QuickForm classes** — these load STALE classes via include_path
   (old 3.2.14-php7 OR system PEAR /opt/lampp/lib/php/HTML/). Disabled in:
     Renderer/TCMSArray.php:30, Renderer/TCMSDefault.php:30 (Renderer.php)
     FieldTypes/{autoselect,automulti,multiselect}/*.php (select.php), autocomplete (text.php)
     Utils/CommonData/qf.php:12 (select.php), qf_group.php:2 (group.php)
     Utils/PopupCalendar/timestamp.php:27,32 (group.php, date.php)
   Proper fix: SYSTEMATIC sweep — grep ALL modules for `require.*'HTML/QuickForm|require.*'HTML/Common`
   (excluding 3.2.14, Roundcube) and remove them all; openpsa autoload provides every class.
   ALSO consider removing /opt/lampp/lib/php (system PEAR) from include_path so stale HTML_Common
   can never load (caused "Cannot declare HTML_Common, already in use").

4. **commondata type registered directly in ContactsInstall.php:222** (country_element) —
   band-aid for timing: CommonData module not loaded when Contacts uses 'commondata' during FirstRun.
   **DONE — proper fix implemented in §15.2** (`register_custom_qf_types()` eager-registers all 9
   custom types, including commondata, before FirstRun/module install runs). Not confirmed whether the
   original ContactsInstall.php:222 band-aid line was removed as redundant — worth a quick check.

5. **Core PHP8 fixes** (these are legit, keep — not band-aids): error.php:207 ($errcontext=null),
   get_magic_quotes_gpc→false in 5 files, magicquotes.php, QuickForm extension __construct fixes (7 files),
   renderHidden signatures, TCMSArray finishForm/renderHtml.

### SMARTY (replace, don't patch — Smarty 5 is PHP 8-native)
6. create_function + each() patched in Smarty_Compiler.class.php (265, 566). Replace whole Smarty later.

### ADODB / OPENPSA (drop-ins — keep, but clean up)
7. Old libs/adodb/ and old modules/Libs/QuickForm/3.2.14-php7/ now UNUSED → delete AFTER proving dead
   (grep whole codebase). Keeping them risks more include_path stale-loads (see #3).
   **`3.2.14-php7/` — DONE, removed (§50). `libs/adodb/` — still on disk, not yet deleted.**

### COMPOSER
8. --ignore-platform-reqs still needed (dev pkgs faker/memio/aspect-mock/psysh pin old PHP). Clean up.

---

## 14. Rector first-class callable → Closure breaks Epesi's callback handlers (FIXED)

Rector (PHP 8.1 pass) converted `array($this,'method')` → `$this->method(...)` (first-class callable
syntax) in places like Dashboard_0.php:39-41. This creates a **Closure**. But Epesi's callback
machinery in include/module.php was written for the old string / array($Module,'method') contract and
REJECTED closures ("Invalid function passed", "Invalid callback function").

Fixed by decomposing the Closure back into array($Module, 'methodName') via Reflection, at the entry
of each handler:
- **create_callback_name()** include/module.php:~600 — added Closure branch using
  ReflectionFunction::getClosureThis() + getName().
- **set_callback()** include/module.php:~692 — same decomposition at entry, so the closure is stored
  as array($obj,'method') and can be replayed via AJAX.

Why array($obj,'method') and not the raw closure: Epesi serializes callbacks (md5 of path+method) and
replays them on AJAX requests by calling method on the module. A closure can't be serialized/replayed;
the decomposed array reproduces exactly what the pre-Rector code stored.

LESSON for Jasiek: this is a SYSTEMIC consequence of Rector's first-class-callable conversion. Any
Epesi mechanism that inspects/validates/stores a callback by structure (string vs array) may choke on
closures. Either (a) handle Closure everywhere callbacks are validated/stored, or (b) reconsider the
first-class-callable Rector rule for code that feeds Epesi's callback system.

---

## 15. MILESTONE: Epesi 1.9.1 fully runs on PHP 8.2 — dashboard renders, user logged in

Reached a complete, logged-in dashboard ("Congratulations! You've just installed EPESI!!") on
PHP 8.2.12. Full install → FirstRun → modules installed → Contacts → HomePage → Dashboard with all
applets rendering (Watchdog, Tasks, Phonecalls, Agenda, Clock, Shoutbox, Welcome). Commit 82a3333
on branch experiment/composer-deps.

### 15.1 The custom QF element-type registration saga — ROOT CAUSE + final fix
Symptom: "unregistered element: Element 'autoselect' does not exist" when the Shoutbox applet rendered,
even though Epesi registers autoselect (and 8 other custom types) in $GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES'].

Diagnosis (via error_log in both our code and openpsa):
- Our eager-registration function set the types correctly (autoselect = YES).
- But at point-of-use openpsa saw only its 23 BUILT-IN types — none of our 9 custom ones.
- ROOT CAUSE: vendor/openpsa/quickform/lib/HTML/QuickForm.php **line 17** does a wholesale
  `$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES'] = array(...23 built-ins...)` at FILE-LOAD time (outside the
  class). When openpsa autoloads (on first `new HTML_QuickForm`), this RESETS the global, wiping any
  custom types registered earlier.

FINAL FIX (no vendor edit — survives composer update): in our eager-registration function
`Base_Epesi::register_custom_qf_types()` (include/epesi.php), call `class_exists('HTML_QuickForm')`
FIRST. This forces openpsa's autoload (and its line-17 reset) to happen BEFORE we write our 9 types,
so ours are added AFTER the reset and survive. openpsa's line 17 runs once (file-level), so later
`new HTML_QuickForm` calls don't re-reset.

### 15.2 register_custom_qf_types() — the eager registration (include/epesi.php)
Reversible function (BEGIN/END comments, `static $done` guard) called at the top of `go($m)`.
Forces openpsa autoload, then directly writes 9 custom types to the global (array format
'file.php','ClassName' — handled by our _loadElement dual-format patch §12.7):
  multiselect, autocomplete, automulti, autoselect (Libs_QuickForm)
  commondata, commondata_group (Utils_CommonData)
  datepicker, timestamp (Utils_PopupCalendar)
  currency (Utils_CurrencyField)
NOTE: include_common() did NOT work here — it loads <Module>Common_0.php, but the type registrations
live in QuickForm_0.php (the main module file, not Common). Direct global writes are the reliable path.

### 15.3 Closure callbacks (recap of §14, both confirmed working)
Rector's first-class-callable (`$this->m(...)`) created Closures that Epesi's callback machinery
rejected. Fixed in include/module.php: create_callback_name() (~600) and set_callback() (~692), both
decomposing the Closure → array($Module,'method') via ReflectionFunction::getClosureThis()+getName().

### 15.4 Relative require_once sweep (recap of §12.3 + extended)
More stale-class-via-include_path culprits found + disabled while reaching the dashboard:
  Utils/CommonData/qf.php:12 (select.php), qf_group.php:2 (group.php)
  Utils/PopupCalendar/timestamp.php:27,32 (group.php, date.php)
These loaded old/system-PEAR HTML_* classes, causing "Cannot declare HTML_Common, already in use"
(system PEAR at /opt/lampp/lib/php/HTML/). Same fix pattern as the renderers/field-types.

### State of play — WORKING
- Epesi installs, runs, logs in, renders full dashboard on PHP 8.2.12. ✓
- Branch experiment/composer-deps, commit 82a3333, pushed.
- All diagnostic error_log lines removed from epesi.php + openpsa QuickForm.php (php -l clean).

### Next steps (no rush — core is proven)
1. Test individual modules (Contacts, Companies, Calendar, CRM) — may surface more per-module relics.
2. FIX-TWICE (§13) when ready: relocate the 3 remaining vendor edits out of openpsa
   (registerElementType static, _loadElement dual-format — line 17 reset fix is already non-vendor);
   systematic relative-require sweep across ALL modules; remove /opt/lampp/lib/php from include_path;
   replace Smarty (Smarty 5); delete dead libs/adodb (3.2.14-php7 already removed, §50) after grep-proving unused;
   clean dev composer pkgs so --ignore-platform-reqs isn't needed.
3. Revert the point commondata

---

## 16. Module testing — where we stopped (PHP 8 type/named-arg relics in RecordBrowser + Smarty)

After the dashboard worked, started testing modules. Two findings:

### 16.1 FIXED — call_user_func_array named-args (RecordBrowser tooltips)
RecordBrowserCommon_0.php:2474 create_record_tooltip → call_user_func_array($cb, $args) where $args has
STRING keys ('tip','args','help'). PHP 8 turns string keys into NAMED arguments → "Unknown named
parameter $tip". Fix: wrap in array_values() to force positional. This unblocked record LISTS in all
CRM modules (Contacts/Companies/Tasks/Agenda all share RecordBrowser display).
SYSTEMIC: grep `call_user_func_array` across modules — any call passing an assoc array hits this on
PHP 8. Likely more occurrences.

### 16.2 record DETAIL view (originally deferred — later pulled back into scope and fixed, see §17.2/§18)
Opening a single record (Contacts → view a contact) throws:
  TypeError: Unsupported operand types: int / string
  data/Base_Theme/compiled/...View_entry.tpl.php:21  (compiled from Utils/RecordBrowser/View_entry.tpl)
PHP 8 forbids arithmetic on non-numeric strings (PHP 7 silently coerced). The division is in a Smarty
template (layout/column math). DEFERRED ON PURPOSE: the old Smarty 2 engine is slated for replacement
(Smarty 5), so patching compiled templates is throwaway work. Revisit as part of the Smarty migration.
To resume: sed -n '18,24p' the compiled .tpl.php + find the View_entry.tpl source; the fix is either a
template guard/int-cast or (better) the Smarty replacement.

### Module test status (branch experiment/composer-deps)
- Dashboard: WORKS (all applets).
- Record LISTS (Contacts etc.): WORK after 16.1.
- Record DETAIL view: blocked by 16.2 (Smarty int/string) — RESOLVED, see §18.

---

## 17. Strategiczna decyzja: Smarty — łatać teraz, wymienić później (NIE teraz)

Po analizie (z wyszukiwaniem): wymiana Smarty 2.6 → Smarty 4/5 NIE jest drop-inem.
- Smarty 4/5 USUWA bloki {php} — Epesi ma ich 25 w szablonach → każdy do przepisania.
- Brak utrzymywanego nowoczesnego silnika czytającego składnię Smarty 2 .tpl z {php}.
  (Dwoo — stary/niemaintainowany; Twig/Blade/Plates — inna składnia = pełne przepisanie.)
- Epesi ma własną warstwę integracji na wnętrznościach Smarty 2 (ThemeCommon, display_smarty,
  modyfikacje Smarty_Compiler) — też do przepisania przy wymianie.

DECYZJA: łatać vendored Smarty 2.6 punktowo, wypuścić Epesi na PHP 8.2 ASAP. Wymiana silnika =
osobny późniejszy projekt, ZBUNDLOWANY z redesignem (nowy wygląd — reguły już zbudowane z Claude
Designer) + usunięciem {php}. Powód: wszystkie trzy dotykają szablonów; robienie wymiany teraz =
przepisywanie 25 szablonów dwa razy (raz pod usunięcie {php}, raz pod nowy wygląd).
Bloki {php} to zresztą naruszenie zasady separacji logika/prezentacja → ich usunięcie należy do
redesignu, nie do migracji. Silnik (17 reliktów) i szablony (25 {php}) to ROZŁĄCZNE zbiory plików —
łatanie silnika nie rusza szablonów, więc praca nad wyglądem zostaje nietknięta.

### 17.1 Triage 17 "reliktów" silnika Smarty — w większości NIE-blokujące
- 1× create_function (Smarty_Compiler:265) — już zakomentowane (nasza naprawa). Szum.
- 16× strftime() — DEPRECATED od PHP 8.1, ale DZIAŁA na 8.2 (notice, nie fatal). Pęknie dopiero
  na PHP 9. Część błaha (data w komentarzu nagłówka skompilowanego pliku); reszta w widżetach
  html_select_date/time. NIE łatać teraz — throwaway (zniknie z wymianą Smarty), nie blokuje 8.2.
  (Do sprawdzenia kiedyś: czy error handler nie eskaluje E_DEPRECATED na ekran — dotąd na ekranie
  błędu były tylko fatale, więc strftime po cichu działa.)

### 17.2 REALNY ship-blocker (wraca do zakresu) — int/string w View_entry
Wejście w pojedynczy rekord (Contacts → podgląd kontaktu) rzuca:
  TypeError: Unsupported operand types: int / string
  data/Base_Theme/compiled/default^%%FD^FDC^FDC465EC%%View_entry.tpl.php:21
  (skompilowany z Utils/RecordBrowser/View_entry.tpl)
PHP 8 zabrania arytmetyki na nie-liczbowym stringu (PHP 7 cicho konwertował). To TWARDY fatal i
blokuje podstawową funkcję (podgląd rekordu) → mimo że to Smarty, trzeba OBEJŚĆ pod 8.2 (niekoniecznie
elegancko). Wcześniej odłożone jako "Smarty=wymiana", ale dla wypuszczalnej 8.2 wraca do zakresu.

### Następna sesja — START TUTAJ
1. Naprawić int/string w View_entry (§17.2). Pierwsze polecenia:
   sed -n '18,24p' "data/Base_Theme/compiled/default^%%FD^FDC^FDC465EC%%View_entry.tpl.php"
   find modules -name "View_entry.tpl" -path "*RecordBrowser*"
   (po naprawie źródła: sudo rm -f data/Base_Theme/compiled/*View_entry* — przekompilować)
2. Dokończyć testy modułów, łapiąc FATALE (nie deprecation): Companies, Tasks, Agenda,
   Administrator, formularze add/edit. Wzorce już znane: Closure-callback (Reflection), named-args
   w call_user_func_array (array_values), custom QF typy (register_custom_qf_types), względne
   require HTML/*, each/create_function.
3. Otagować wydanie PHP 8.2 gdy moduły przechodzą.

### Stan działania (bez zmian od §16): dashboard OK, listy rekordów OK, podgląd rekordu = §17.2 (RESOLVED, patrz §18).

## 18. View_entry int/string FIXED + ważna lekcja o ścieżkach szablonów

Podgląd rekordu (Contacts → view) rzucał int/string w {php} bloku View_entry.tpl (cols dzielone jako
string). Fix: (int)$cols + guard <1→1 przed pierwszym dzieleniem.

KLUCZOWA LEKCJA (kosztowała kilka prób):
- Smarty template_dir = data/Base_Theme/templates/default (ThemeCommon_0.php:30), NIE modules/.
- Epesi kopiuje szablony z modules/<M>/theme/ → data/Base_Theme/templates/ przy instalacji theme.
- data/Base_Theme/ należy do daemon/Apache → edycje wymagają SUDO (bez sudo: "Permission denied",
  które python/sed zgłaszały cicho jako "0 zmian").
- Dla obejść 8.2: edytuj data/ z sudo + sudo rm compiled cache. Dla trwałości: edytuj też
  modules/.../theme/ (źródło, własność usera).
- 8 szablonów ma arytmetykę w {php} (potencjalne kolejne int/string) — naprawiać tym samym wzorcem.

## 19. Custom QF field types — relative HTML requires + PHP4 ctors (portable fix)
Custom QF types (datepicker, timestamp, currency, crits) padały dwojako na openpsa+PHP8:
1. require_once('HTML/QuickForm/input.php' itp.) — względny, przez include_path trafiał do
   SYSTEMOWEGO PEAR (/opt/lampp/lib/php/HTML/) → "Cannot declare HTML_Common". 
   UWAGA PRZENOŚNOŚCI: include_path.php:10 dokleja ini_get('include_path') = systemowy PEAR danej
   MASZYNY. Naprawa NIE może usuwać konkretnej ścieżki (różna per maszyna) — zamiast tego
   zakomentowano względne require (openpsa dostarcza klasy przez autoload). Przenośne, działa
   niezależnie od systemowego include_path. Pliki: datepicker.php, timestamp.php:104, currency.php,
   quickform_crits.php (+ wcześniej qf.php, qf_group.php).
2. PHP4 ctor: HTML_QuickForm_input::HTML_QuickForm_input(...) / $this->HTML_QuickForm_element(...)
   → parent::__construct(...). Pliki: datepicker:15, currency:16, quickform_crits:15, timestamp:54.
ZASADA NACZELNA (potwierdzona z userem): naprawiać dla OGÓŁU/przenośnie, nie pod jedną maszynę.
User będzie testować na innych maszynach po doprowadzeniu tej do działania.

---

## 20. DLA JAŚKA — bug storage plików (DIAGNOZA, BEZ ZMIAN — wrażliwy obszar)

> **ROOT CAUSE ZIDENTYFIKOWANY I ZWERYFIKOWANY — patrz §36.** To jest objaw zmiany semantyki `static`
> w dziedziczonej metodzie `ModuleCommon::Instance()` na PHP 8.x. Fix zweryfikowany empirycznie.
>
> **WĄSKI FIX-TWICE ZASTOSOWANY** (gałąź `experiment/filestorage-prefix-fix`): `get_storage_file_path()`
> liczy teraz ścieżkę deterministycznie przez `DATA_DIR.'/'.self::module_name().'/'` zamiast
> `self::Instance()->get_data_dir()`. To odblokowuje §20 bez ruszania serca frameworka. Gdy wejdzie
> root fix §36, ten workaround (i `__DIR__` z §33) należy cofnąć do idiomu `Instance()` — patrz komentarz
> FIX-TWICE w kodzie. Pozostałe 7 min (EpesiStore, Theme, Fax, Print, Attachment, MainModuleIndicator)
> NIE tknięte — uśpione do czasu root fixu.

OBJAW: zapisany załącznik (notatka kontaktu) → view/download rzuca
"file not found: data/CRM_Roundcube/<hash-split>". Plik FIZYCZNIE istnieje, ale w
data/CRM_Tasks/<ten-sam-hash-split>. Hash i podział katalogów identyczne — różni się TYLKO prefiks.

ŁAŃCUCH PRZYCZYNOWY (zdiagnozowany, nie ruszany):
1. Utils_FileStorageCommon::get_storage_file_path($hash) (FileStorageCommon_0.php:136):
   $path = self::Instance()->get_data_dir() . <hash split na podkatalogi>
2. get_data_dir() (module_primitive.php:31): return DATA_DIR.'/'.$this->type.'/';
   → prefiks = $this->type (nazwa modułu instancji)
3. Instance() (module_common.php:28): ma JEDEN współdzielony `static $obj`. Wywołanie z argumentem
   NADPISUJE go globalnie; bez argumentu zwraca OSTATNIO ustawiony moduł.
   => get_data_dir() zwraca prefiks "ktokolwiek ostatnio ustawił Instance()", nie konkretnie FileStorage.

MECHANIZM: zapis notatki ustawia Instance na Tasks (→ data/CRM_Tasks/), ale przy odczycie/view wcześniej
w żądaniu Roundcube ustawił Instance na CRM_Roundcube (→ data/CRM_Roundcube/). Singleton z mutowalnym
stanem globalnym: prefiks zależy od KOLEJNOŚCI wywołań Instance() w danym żądaniu.

PRAWDOPODOBNIE NIE czysty PHP8-relikt, lecz krucha architektura (global mutable singleton) ujawniona
przez zmienioną kolejność ładowania/wywołań po migracji (Closure-callbacki, eager-rejestracje, inny
timing modułów). Na PHP7 kolejność dawała spójny prefiks "przypadkiem".

PYTANIE DO JAŚKA: czy get_storage_file_path powinno używać USTALONEGO backendu FileStorage (np.
Instance('Utils_FileStorage') albo stała ścieżka data/Utils_FileStorage/), zamiast polegać na
"ostatnio ustawionym" module? To zmiana w sercu storage — decyzja autora.

NIE RUSZAMY (dane użytkownika, integralność). Pliki są bezpieczne (w data/CRM_Tasks/), tylko odczyt
patrzy w zły prefiks.

KOLEJNY OBJAW (2026-06-27): ten sam bug widać też w **Administrator → Files**. Lista plików renderowana
przez Utils_FileStorageCommon::get_file_label() → file_exists() zwraca false (zły prefiks) → link
dostaje pusty href, tylko tooltip "Missing file: <hash>" (FileStorageCommon_0.php:76-80). Stąd objaw
zgłoszony przez użytkownika: link nieklikalny (kursor-tekst zamiast rączki), dymek "missing: <długi hash>".
Potwierdzone na dysku read-only: pliki hash-split leżą w data/CRM_Tasks/, data/CRM_Mail/,
data/CRM_Roundcube/ — NIE w data/Utils_FileStorage/. Ten sam hash (d/5/0/2/5/babea2dd...) występuje
jednocześnie w CRM_Tasks/ i CRM_Mail/ — potwierdza, że prefiks zależy od ostatniego Instance().
To NIE osobny bug — to ten sam §20. Nieklikalny link to objaw, nie przyczyna.

---

## 21. Znaleziska do śledzenia (niefatalne / odłożone)

### 21.1 Off-by-one w klikalności załączników (UI, niefatalne — na później)
W liście plików załączonych do rekordu: OSTATNIO dodany plik NIE jest klikalnym linkiem (kursor jak na
tekście, nie na linku). Poprzednie pliki stają się linkami dopiero PO dodaniu kolejnego. Klasyczny
off-by-one — link/href generowany z opóźnieniem o jeden (ostatni plik nie ma jeszcze ID/ścieżki w
momencie renderowania listy, albo pętla renderuje linki dla wszystkich oprócz ostatniego).
Niefatalne, kosmetyczne. Do naprawy po wypuszczalnej 8.2.

### 21.2 view/download załącznika "file not found" — duplikat §20, root cause + fix: §36
Ten sam bug co §20 (storage prefix Tasks vs Roundcube) — nie powtarzam diagnozy. Jedyna nowa
informacja: user NIE korzystał z Roundcube ani Tasks — te moduły ładują się automatycznie w tle
(start/render kontaktu) i same ustawiają współdzielony Instance() singleton, co potwierdza że
prefiks zależy od KOLEJNOŚCI ŁADOWANIA modułów, nie od akcji użytkownika.

### 21.3 Loader/spinner zostaje na wierzchu po AJAX — znika po kliknięciu (niefatalne, JS)
Stała warstwa loadera nie chowa się sama po zakończeniu AJAX — trzeba kliknąć. POTWIERDZONE że to NIE
PHP: php_error_log całkowicie czysty od ostatniej naprawy (21:36 HTML_Common) mimo intensywnego
klikania/testowania — backend nie rzuca błędów podczas tych akcji. Problem czysto JS/frontend (handler
"ukryj loader po zakończeniu requestu" nie odpala automatycznie). Niefatalne. Debug JS (DevTools
Console/Network) w osobnej sesji polish. Możliwe że tak było też na PHP7 — do weryfikacji.

Przy teście klonowania mojego kontaktu do kontaktu Jaśka, klonuj user najpierw do kontaktu, nie usera 

### 21.4 PHP 8 "" != 0 quirk — guard login_id w ContactsCommon
FIXED — pełna diagnoza i diff: §23.1 (ContactsCommon_0.php:1022). Nie powtarzam tu treści.

PYTANIE PROJEKTOWE DLA JAŚKA (intencja, NIE ruszane): czy logika "gdy kontakt MA konto user i brak
emaila → skopiuj email z user_password" jest poprawna wobec unikalności emaila? Karina wskazuje, że
email jest unikalny per contact/user i kopiowanie może być podejrzane. Możliwa intencja: contact z
kontem = ta sama osoba (1:1), więc email konta = email kontaktu (nie duplikat). Do potwierdzenia przez
autora — czy relacja contact↔user jest 1:1 i czy to dogrywanie jest zamierzone. Zostawione jak było.

SYSTEMOWE: PHP 8 "" == 0 → false. Każdy guard $x!=0 / $x==0 na polu mogącym być "" odwraca działanie.
Szukać podobnych: grep "!=0"/"==0" na wartościach z formularzy.

---

## 22. DLA JAŚKA — mcrypt usunięty w PHP 7.2 → zaszyfrowane notatki nie działają (DECYZJA ARCHITEKTONICZNA)

**Plik:** `modules/Utils/Attachment/AttachmentCommon_0.php:225-238`
**Symptom:** Fatal przy zapisie notatki z hasłem: `Call to undefined function mcrypt_module_open()`
**Kiedy:** Każda operacja na zaszyfrowanych notatkach (zapis, odczyt, zmiana hasła, zaszyfrowanie pliku).

### Co robi kod

`crypt()` (linia 225) używa `rijndael-256` w trybie CBC przez `mcrypt_*`. Przechowywany format:
`base64($ciphertext) . "\n" . base64($iv) . "\n" . $hint`

Parametry mcrypt:
- Algorytm: rijndael-256 (blok 256-bit, klucz 256-bit)
- IV: 32 bajty (= rozmiar bloku rijndael-256)
- Klucz: `substr(sha1($password), 0, 32)` (pierwsze 32 znaki hex SHA1)

### Dlaczego to jest decyzja dla Jaśka

Każda ścieżka migracji wymaga decyzji architektonicznej:

**Opcja A — `phpseclib/mcrypt_compat` (composer):**
Drop-in polyfill emulujący mcrypt w czystym PHP. Zero zmian w kodzie Epesi — stare notatki
zaszyfrowane na PHP 7.x **nadal działają**. Dodaje zależność composera.
`composer require phpseclib/mcrypt_compat`

**Opcja B — Zamiana na openssl (zmiana kodu, ŁAMIE WSTECZNĄ KOMPATYBILNOŚĆ):**
Zastąpić `crypt()` przez `openssl_encrypt/decrypt` z `AES-256-CBC`. Zero nowych zależności.
AES-256-CBC używa IV 16 bajtów zamiast 32 → stare notatki zaszyfrowane na PHP 7.x
**nigdy się nie otworzą** po upgrade (inny rozmiar bloku i IV).

Można połączyć: mcrypt_compat tymczasowo + jednorazowy skrypt re-szyfrowania starych notatek
(openssl decrypt wymaga mcrypt_compat do etapu migracji danych).

### Tymczasowy workaround do momentu decyzji
Nie ma — funkcja jest zwykłym `fatal`. Instrukcja dla użytkowników: **nie używać szyfrowanych notatek
do czasu naprawy.** Nieszyfrowane notatki działają normalnie.

### Zakres w kodzie
- `crypt()` linia 225-238 — główna funkcja
- Wywołana przez `encrypt()` (linia 209) i `decrypt()` (linia 216)
- `decrypt()` linia 216 ma `rtrim($ret, "\0")` — to jest usuwanie paddingu NUL z mcrypt (openssl
  usuwa padding PKCS#7 sam, więc przy opcji B to `rtrim` trzeba by usunąć)
- Szyfrowanie plików: `submit_attachment` linia 486 (`file_put_contents(..., self::encrypt(...))`)
  — dotyczy też plików, nie tylko tekstu notatki

### RESOLUTION 2026-06-28 — Option A (mcrypt_compat), on branch `experiment/mcrypt-compat`

**Decision: Option A.** Deeper analysis made it the ONLY viable choice for preserving old data:
- `rijndael-256` means **256-bit BLOCK** Rijndael — NOT AES. **openssl cannot decrypt it** (openssl
  only implements AES = 128-bit block). So Option B (openssl/AES) can never read existing notes.
- The note password is **not stored server-side** (`$_SESSION['client']['cp'.$id]` only), so a bulk
  server-side re-encryption migration is **impossible** — you can't decrypt without each user's
  password. (Correction to the earlier "combine A+B migration" idea — not feasible in bulk.)
- → mcrypt_compat is the only path that keeps users' old encrypted data readable.

**Implemented:** `composer require phpseclib/mcrypt_compat` → installed `mcrypt_compat 2.0.8`,
`phpseclib/phpseclib 3.0.55`, `paragonie/constant_time_encoding`, `paragonie/random_compat`.
**Zero Epesi code changes** — drop-in. The `mcrypt_*` functions are defined by
`vendor/phpseclib/mcrypt_compat/lib/mcrypt.php` via composer `files` autoload (loaded with
`vendor/autoload.php`), guarded by `if (!function_exists('mcrypt_list_algorithms'))` so a native
ext/mcrypt (where present) still takes precedence. `rijndael-256` is supported.

**Verified (Level 1):** roundtrip encrypt→decrypt works on BOTH php7.4 and php8.2.12 via the
polyfill — same deterministic ciphertext on both (`9D58dj…`), rijndael-256, key/iv = 32 bytes,
UTF-8 preserved. **The fatal blocker is resolved — encrypted notes function again on PHP 8.2.**

**NOT yet verified (residual):** byte-compatibility with **native ext/mcrypt** — neither PHP on the
test Dell has the native extension, so both runs used the polyfill (proves consistency, not
native-compat). Assurance rests on mcrypt_compat being purpose-built + CI-tested byte-identical to
ext/mcrypt.

**HARD GATE before any production upgrade (Karina's call, option B of verification):** on a
**staging copy of a real instance that has old encrypted notes**, confirm those exact notes decrypt
with the user's password — and/or, on a host that has native mcrypt (e.g. the cPanel/old-hosting
portability phase), compare the ciphertext. Do NOT upgrade a production instance with encrypted
notes until this passes. FOR JASIEK: sign-off on adopting mcrypt_compat.

---

## 23. Runtime testing session — fixes applied (Contacts + Companies)

Session: first full CRUD run-through of Contacts and Companies on PHP 8.2.
All fixes below are confirmed working (tested in browser, no fatal in php_error_log).
Commit: `b58f439` on branch `experiment/composer-deps`.

---

### 23.1 FIXED — PHP 8 `"" != 0` quirk in login_id guard (ContactsCommon_0.php:1022)

**Symptom:** Fatal when cloning a contact that has no email and no user account:
`Argument 0 is not number(%d): SELECT mail FROM user_password WHERE user_login_id=%d Array([0] => '')`

**Root cause:** PHP 7: `"" == 0` was `true` (loose comparison coerced empty string to 0).
PHP 8: `"" == 0` is `false`. The guard `$values['login'] != 0` was meant to skip the
email-copy logic when there is no user account (login = 0). On PHP 8 an empty string
`""` is no longer equal to `0`, so `"" != 0` is now `true` — the guard fires with an
empty login, DB::GetOne receives `""` for a `%d` placeholder → fatal.

**Fix:** `modules/CRM/Contacts/ContactsCommon_0.php:1022`
```php
// Before:
if (isset($values['email']) && $values['email']=='' && $values['login']!=0 && $mode=='add')
// After:
if (isset($values['email']) && $values['email']=='' && !empty($values['login']) && is_numeric($values['login']) && $mode=='add')
```

**Systemic note:** Any `$x != 0` / `$x == 0` guard on a field that can hold an empty
string `""` silently inverted its logic in PHP 8. Worth grepping for similar patterns
across the codebase.

**Design question FOR JASIEK (not touched):** Is the logic "if a contact has a user
account and no email, copy the email from user_password" actually correct? The email
field is unique per contact/user; copying it from the user account may or may not be
intentional (1:1 contact↔user assumption). See §21.4.

---

### 23.2 FIXED — Wrong module directory from mutable singleton (TCPDFCommon_0.php:68)

**Symptom:** Print PDF → fatal: `Failed opening required 'modules/Base/Print/tcpdf_config.php'`
(the Base_Print module directory was returned instead of Libs_TCPDF's directory).

**Root cause:** `self::Instance()->get_module_dir()` — `Instance()` (module_common.php:28)
holds one shared `static $obj`. The last caller that passed an argument overwrites it
globally. By the time PDF printing ran, another module had overwritten the singleton,
so `get_module_dir()` returned the wrong path.

**Fix:** `modules/Libs/TCPDF/TCPDFCommon_0.php:68`
```php
// Before:
require_once(self::Instance()->get_module_dir() . 'tcpdf_config.php');
// After:
require_once(__DIR__ . '/tcpdf_config.php');
```

**General rule:** Any `self::Instance()->get_module_dir()` or `get_data_dir()` call
inside a static method is unreliable because of the mutable singleton. Use `__DIR__`
for paths relative to the current file. (See also §20 — the same singleton causes the
storage-prefix bug in FileStorage.)

---

### 23.3 FIXED — New Meeting: wrong field name + addRule timing error (MeetingCommon_0.php)

Two bugs hit in sequence when opening "New Meeting" from a contact's action bar.

**Bug A — typo:** Field `emp_id` referenced in `addRule` — the actual field name is
`employees`. Would have thrown `HTML_QuickForm_Error: nonexistent html element: emp_id`.

**Bug B — timing (PHP 8 / openpsa QuickForm):** After fixing the typo, the next error:
`HTML_QuickForm_Error: nonexistent html element: Element 'employees' does not exist`
openpsa QuickForm validates `addRule` immediately at call time (strict). `employees`
is added to the form later (its QFfield callback fires during a `duration` field callback
at a specific processing-order step), so it does not yet exist when `addRule` runs.
Old QuickForm 3.2.14 silently ignored nonexistent elements.

**Fix:** `modules/CRM/Meeting/MeetingCommon_0.php` — replaced `addRule` with
`addFormRule` (validates after all elements are built) + new callback method:
```php
// Removed:
$form->registerRule('check_my_user', 'callback', array('CRM_MeetingCommon','check_my_user'));
$form->addRule(array('messenger_on','emp_id'), __('...'), 'check_my_user');

// Added (after the existing check_my_user static method):
$form->addFormRule(array('CRM_MeetingCommon', 'check_my_user_form'));

public static function check_my_user_form($values) {
    if (empty($values['messenger_on']) || $values['messenger_on'] !== 'me') return true;
    $emp = $values['employees'] ?? '';
    $sub = array_filter(explode('__SEP__', $emp));
    $me = CRM_ContactsCommon::get_my_record();
    if (!in_array($me['id'], $sub))
        return array('messenger_on' => __('You have to select your contact to set alarm on it'));
    return true;
}
```

---

### 23.4 FIXED — Frozen checkboxes show `[ ]` / `[x]` instead of images

**Symptom:** Checkboxes in frozen (view) mode display plain text `[ ]` / `[x]` instead
of the `checkbox_on.png` / `checkbox_off.png` images from the active theme.

**Root cause:** openpsa QuickForm uses plain-text `getFrozenHtml()`. The original Epesi
used a custom QF build that had image-based frozen display.

**Two field types affected:**
- `checkbox` — used in custom forms (e.g. Meeting's "Timeless" field)
- `advcheckbox` — used by RecordBrowser standard boolean fields (e.g. Task's
  "Timeless", "Longterm"); always appends `_getPersistantData()` in both states

**Fix:** Two new QF type subclasses created:
- `modules/Libs/QuickForm/FieldTypes/epesi_checkbox/epesi_checkbox.php`
  — extends `HTML_QuickForm_checkbox`, overrides `getFrozenHtml()`
- `modules/Libs/QuickForm/FieldTypes/epesi_advcheckbox/epesi_advcheckbox.php`
  — extends `HTML_QuickForm_advcheckbox`, overrides `getFrozenHtml()` (keeps `_getPersistantData()`)

Registered in `include/epesi.php` → `register_custom_qf_types()` under the built-in
type names `checkbox` and `advcheckbox`, so all existing field definitions pick them up
automatically without any per-module changes.

---

### 23.5 FIXED — "Access denied" on notes created from the contact/company action bar

**Symptom:** Clicking the "New Note" icon in the top-right action bar of a contact or
company detail view → save → immediate "Access denied" when trying to open the note.

**Root cause:** `Utils_AttachmentCommon::get_access($id)` iterates over the note's
`attached_to` tokens and returns `false` if the array is empty. The custom access
callback `rb_access` then denies the 'view' action.

Notes created via the **Notes TAB** work correctly because `Attachment_0.php:body()`
explicitly sets `$defaults['attached_to'] = array($group)` (e.g. `'contact/4'`).

Notes created via the **action bar** went through `RecordBrowser_0.php:add_note_button_href()`,
which passed `local`, `func`, `args` to the new-record defaults but **never set
`attached_to`**. The `local` field is deprecated (overwritten to `''` in `get()`).
The `func`/`args` fields are display-label callbacks stored in the DB — they are NOT
related to access control. So `attached_to` defaulted to `[]` → saved as NULL.

This was a **pre-existing bug** (not a PHP 8 regression): the action bar New Note
button always created unattached notes. The "Access denied" symptom was always there
but may not have been noticed before.

**Fix:** `modules/Utils/RecordBrowser/RecordBrowser_0.php:270`
```php
// Before (single line, no attached_to):
return Utils_RecordBrowserCommon::create_new_record_href('utils_attachment',
    array('permission'=>'0','local'=>$key,'func'=>...,'args'=>...));

// After:
return Utils_RecordBrowserCommon::create_new_record_href('utils_attachment', array(
    'permission'=>'0',
    'local'=>$key,
    'attached_to' => $key !== null ? array($key) : array(),
    'func'=>serialize(array('Utils_RecordBrowserCommon','create_default_linked_label')),
    'args'=>serialize(explode('/',$key))
));
```

**Affects:** all modules that use `add_note_button_href()` — currently Contacts
(list view + detail view) and Companies (list view + detail view).

**Note on pre-existing orphaned notes:** any notes created via the action bar before
this fix have `f_attached_to = NULL` in `utils_attachment_data_1` and will still show
"Access denied". They can be deleted from the Notes TAB or left as test artifacts.

---

## 24. FIXED — Flash clipboard button dead (RecordBrowser_0.php)

**Symptom:** "Copy to clipboard" box shows gray puzzle icon instead of Copy button; description says "Click Copy under the box" but nothing to click.
**Cause:** `copyButton.swf` (Flash) — removed from all browsers ~2020. Pre-existing, not PHP 8.
**Fix:** Replaced Flash `<object>` block with HTML button using `navigator.clipboard.writeText()`. Updated `<h3>` instruction text. `selecttext.js` (mouseover + Ctrl-C) kept.
**File:** `RecordBrowser_0.php:1178`

---

## 25. FIXED — Clipboard pattern nested `%{}` bug (ContactsInstall.php + DB)

**Symptom:** Copied address shows literal `%{Springfield Pennsylvania {postal_code}}` — `%` sign and `{postal_code}` placeholder visible in output.
**Cause:** Pattern used nested `%{%{{city} }%{{zone} }{postal_code}<BR>}`. The regex in `replace_clipboard_pattern` excludes `%` from block content, so inner blocks process but outer wrapper never matches and stays literal. Pre-existing, not PHP 8.
**Fix:** Simplified to `%{{city} {zone} {postal_code}<BR>}` — one block, all three fields optional (empty → empty string, clean output). Fixed in `ContactsInstall.php:92,103` (both `company` and `contact`) and in DB (`UPDATE recordbrowser_clipboard_pattern`).


---

## 26. FIXED — Timestamp / time-picker field layout broken (Deadline, Alert time, PhoneCall, Meeting…)

**Symptom:** In edit forms the time selects (hour : minute am/pm) stacked into separate rows; the timestamp field also had the date floated apart with the time running off-screen in narrow popups.
**Causes (two, both PHP-8-era CSS, surfaced after the openpsa QuickForm/theme migration):**
1. **Time selects wrap into rows** — `.epesi_data input,select,textarea { width: 97% }` (Base/Box theme) gives each select 97% of the cell, so three can't share a line.
2. **Timestamp date/time split + overflow** — `.Utils_RecordBrowser__View_entry .timestamp > div > div { float: right }` (equal specificity) wins over the edit cell, floating the time off-screen.
**Fix (CSS only, in the Epesi module theme — chose option 3, logic/appearance separated, no renderer change):** `modules/Libs/QuickForm/theme/default.css`:
- Universal — target time selects by their `[h]/[i]/[a]` field names with `width:auto; display:inline-block` (higher specificity than `.epesi_data select`) → fixes **every** time picker regardless of cell class.
- Timestamp cell — `td.data.timestamp > div` as `display:flex` (date first, wraps when narrow); `float:none` on the sub-divs to override the View_entry float; date input `width:150px`.
**Delivery:** Epesi-side theme CSS → reaches users via `runpatches.php` → `Base_ThemeCommon::themeup()` (rebuilds `data/Base_Theme`). Validated live: Deadline + meeting Alert time render inline.

---

## 27. FIXED — PhoneCall Watchdog: employees not subscribed (pre-existing Epesi bug)

- **Symptom:** Adding/editing a PhoneCall — assigned employees don't see it in Watchdog applet.
- **Cause:** `PhoneCallCommon_0.php:249` `subscribed_employees()` copy-pasted from Meeting; `'crm_meeting'` never changed to `'phonecall'`. Present in vanilla Epesi 1.9.1, not a PHP 8 regression.
- **Fix:** `PhoneCallCommon_0.php:249` — `'crm_meeting'` → `'phonecall'`.

---

## 28. FIXED — Activity Report: User dropdown shows "front-end user" (PHP 8 + MySQLi)

- **Symptom:** User Activity Report search bar User dropdown shows "front-end user" for all users instead of contact names.
- **Cause:** `ActivityReport_0.php:29` — `SELECT id, id FROM user_login` selects same column twice. PHP 8 MySQLi collapses duplicate assoc keys, leaving the value column empty → `get_user_label("")` → "front-end user".
- **Fix:** `SELECT id, id AS uid FROM user_login` — aliasing second column avoids duplicate name collision.

---

## 29. FIXED — Activity Report Files checkbox: FetchRow on bool (pre-existing Epesi bug)

- **Symptom:** Checking "Files" in User Activity Report → fatal `Call to a member function FetchRow() on bool`.
- **Cause:** `ActivityReport_0.php` file query joined `utils_attachment_file` + `utils_attachment_local`, both dropped in Epesi's own 2017 patch (`20171024_use_generic_file_field.php`). ActivityReport never updated. Not a PHP 8 regression.
- **Fix:** Rewrote file query to use `utils_attachment_data_1` directly (`f_files`, `f_attached_to`). Changed `$af_where` aliases from `ual`/`uaf` → `ua`.

---

## 30. UPGRADE — Roundcube 1.2.1 → 1.7.1 (branch: experiment/rc-upgrade)

- **Symptom (old):** RC 1.2.1 (2016) incompatible with PHP 8. Three classes of warnings broke the UI: `$GLOBALS['env']` undefined, `$rcmail_config` undefined, `$aliases[$from] ?: $from` needed `??`.
- **Decision:** Patch-fix of 1.2.1 was impractical. Upgraded to RC 1.7.1 (current stable, 2025).
- **Branch:** `experiment/rc-upgrade` (sub-branch of `experiment/composer-deps`).

### What was replaced
- `RC/program/`, `RC/vendor/`, `RC/skins/`, `RC/SQL/`, `RC/plugins/` — full replacement with 1.7.1 files.
- `RC/index.php` — replaced (1.7.1 root index just redirects to `public_html/`).
- `RC/public_html/`, `RC/bin/` — new directories from 1.7.1.

### What was kept
- `RC/config/config.inc.php` — Epesi custom config, already used `$config = []` format (compatible with 1.7.1 without changes).
- `RC/plugins/epesi_addressbook`, `epesi_archive`, `epesi_autologon`, `epesi_autorelogon`, `epesi_init`, `epesi_mailto` — 6 Epesi plugins restored after core replacement. All use standard RC `rcube_plugin` API, compatible with 1.7.x.

### Epesi integration change
- **File:** `modules/CRM/Roundcube/Roundcube_0.php:49`
- **Change:** iframe `src` updated from `RC/index.php` to `RC/public_html/index.php` (1.7.1 moved the web entry point).

### Config change
- `$config['skin']` changed `'classic'` → `'elastic'` (`classic` skin removed in RC 1.5+; `elastic` is the only skin in 1.7.1 complete package).

### DB migration
- RC schema was at `2015030800`. Applied all migrations through `2025092300` (16 files).
- RC CLI `bin/updatedb.sh` could not run (XAMPP PHP missing `libcrypt.so.1`). Migration applied manually via MySQL with `rc_` prefix substituted in SQL.
- New tables created: `rc_filestore`, `rc_collected_addresses`, `rc_responses`, `rc_uploads`.
- `rc_session.changed` renamed to `rc_session.expires_at` (2025092300 migration).
- Schema version updated to `2025092300` in `rc_system`.

### Post-upgrade fixes required (found during browser testing)

| Fix | Cause |
|-----|-------|
| `dirname(__DIR__, 6)` in epesi_init | Entry point moved from `RC/` to `RC/public_html/` — cwd shifted one level |
| `: void` on `set_search_set()` + `reset()` | RC 1.7.1 added void return types to abstract parent; PHP 8 enforces child compatibility |
| `verify_peer_name => false` in conn options | PHP 8 made peer name check independent; shared hosting cert CN didn't match |
| `changed` → `expires_at` in cron query | DB column renamed in 2025092300 migration |

### Test status — DONE
- Email UI opens, IMAP connects (SSL), inbox loads.
- Compose + send to `test@client-b.example` confirmed working.
- Address book autocomplete works (no more server error on To: field).

### Future RC upgrades — what is safe and what needs attention

**Safe — user data is never at risk:**
- All emails and folders live on the IMAP server; RC is just a client, it never owns the messages.
- Email account credentials are in `rc_accounts_data_1` (Epesi RecordBrowser) — RC core does not touch this table.
- Archived emails (`rc_mails_data_1`, `rc_mail_threads_data_1`) are Epesi's own records.

**Needs attention on each RC upgrade:**
1. **6 Epesi plugins** — check for abstract method signature changes in `rcube_addressbook` and other RC base classes; PHP 8 enforces child compatibility strictly.
2. **`config.inc.php`** — check for renamed or removed config keys in the new RC version.
3. **DB migrations** — apply new SQL files from `SQL/mysql/` with `rc_` prefix substituted on all table names (RC CLI `bin/updatedb.sh` requires `libcrypt.so.1` missing on XAMPP; apply manually via MySQL).
4. **Skin** — verify `elastic` skin still exists; RC has dropped skins before (`classic` was removed in 1.5).

**Recipe for future RC upgrades:**
1. Replace `RC/program/`, `RC/vendor/`, `RC/skins/`, `RC/SQL/`, `RC/plugins/` with new RC core.
2. Restore the 6 `epesi_*` plugins from backup.
3. Keep `RC/config/config.inc.php` — check for config key changes.
4. Apply new DB migration files with `rc_` prefix.
5. Check plugin method signatures against updated RC base classes.
6. Test: open Email, confirm IMAP connects, send a test message.

---

## 31. DEAD EXTERNAL SERVICE — Telegram bot notifications broken (post-upgrade task)

- **Symptom:** User connects to `@EpesiBot` in Telegram desktop app, but bot never responds.
- **Root cause:** Not a PHP 8 issue. The integration relies on two Telaxus-hosted services that are no longer live:
  - `https://telegram.epesicrm.com/` — relay server (confirmed dead)
  - `@EpesiBot` — Telaxus-managed Telegram bot
- **How it works (current code):** Cron calls `Base_NotifyCommon::telegram()` every 5 minutes → POSTs pending notifications to `telegram.epesicrm.com` → relay forwards to `@EpesiBot` → bot sends message to user. Code is in `modules/Base/Notify/NotifyCommon_0.php:252`.
- **Status:** Non-fatal — rest of the app works fine. Telegram simply never delivers.

### Fix options (post-upgrade, FOR JASIEK decision)
1. **Replace relay with direct Telegram Bot API** — create own bot via `@BotFather`, rewrite `telegram()` to POST directly to `https://api.telegram.org/bot{TOKEN}/sendMessage`. No external dependency.
2. **Restore the relay** — only if Jasiek can bring `telegram.epesicrm.com` back up.

---

## 32. FIXED — EssClient registration form: addRule on nonexistent element

- **Symptom:** Entering Epesi Store registration → `HTML_QuickForm_Error: nonexistent html element: Element 'admin_email' does not exist`.
- **Cause:** Pre-existing Epesi 1.9.1 copy-paste bug in `EssClient_0.php:203` — `addRule('admin_email', ...)` called before `admin_email` was added to the form (line 213). The rule was intended for `tax_id` (added on line 202). PEAR QuickForm swallowed this silently; openpsa QuickForm throws visibly.
- **Fix:** `addRule('admin_email', ...)` → `addRule('tax_id', ...)` on line 203.
- **Status:** Registration form works and Epesi was successfully registered.
- **TODO:** Registration success/status page layout is messy — needs cosmetic cleanup (non-fatal, post-upgrade task).

---

## 33. FIXED — EpesiStore crashes: ClientRequester.php not found

- **Symptom:** Clicking "EPESI Store" → `Failed opening required 'modules/Base/Setup/ClientRequester.php'`.
- **Cause:** `EssClientCommon_0.php:150` used `self::Instance()->get_module_dir()` to locate `ClientRequester.php`. When called from within `Base_Setup`'s display context, the shared mutable `Instance()` singleton had been overwritten by `Base_Setup`, returning the wrong directory. Same root cause as §20.
- **Fix:** Replaced `self::Instance()->get_module_dir() . 'ClientRequester.php'` with `__DIR__ . '/ClientRequester.php'` — always resolves to `modules/Base/EssClient/` regardless of call context.
- **Note:** This `__DIR__` fix was a local workaround for the **same root cause now identified in §36** (Instance() singleton broken by PHP 8.x static-variable semantics). If the §36 root fix is applied, this workaround could be reverted.

---

## 34. FIXED — Password recovery: silent mail failure

- **Symptom:** User submits password recovery form → sees no error even if email is never sent; hash IS inserted in `user_reset_pass` table but email never arrives.
- **Cause:** Pre-existing Epesi 1.9.1 bug in `Login_0.php:submit_recover()` — `$sendMail = Base_MailCommon::send_critical(...)` result was computed but never checked. Function always returned `true`, so the UI always showed "Password reset instructions were sent." even on SMTP failure.
- **Affected:** All environments where SMTP is misconfigured or `mail_method = mail` on a system without a local MTA (e.g., XAMPP dev). On production with correct SMTP this is silent but can mask outages.
- **Fix:** Return `$sendMail` instead of `true`; print a user-visible error when sending fails.
- **File:** `modules/Base/User/Login/Login_0.php` — `submit_recover()` method.
- **Note:** `mail_method = mail` on XAMPP/LAMPP (no local MTA) → emails silently fail. Fix via Epesi Admin → Server Configuration → Mail server settings → switch to SMTP with real credentials.

---

## 35. FIXED — Administrator → Access restriction: rule clearances render blank

- **Symptom:** In Administrator → Access restriction, every rule row appeared empty — only permission titles (e.g. "Calendar", "Dashboard - manage applets") showed, with no clearance text ("Admin", "Superadmin") underneath. Affected ALL rules, including install defaults — not just newly added ones. Rendered HTML showed `<span class="Base_Acl__permissions_clearance"></span>` (empty).
- **Diagnosis (via temporary error_log):** `display_clearances()` received an **empty** `$clearances` array, while `get_clearance(true)` returned the correct full map. The rule-id lookup one level up was returning `null` values.
- **Root cause:** `Acl_0.php:55` ran `DB::GetAssoc('SELECT id, id FROM base_acl_rules WHERE permission_id=%d')` — selecting the `id` column **twice**. `GetAssoc` builds `key => value` from the two columns. On PHP 7.4 + old ADOdb (numeric fetch) both `$row[0]` and `$row[1]` resolved to the id, yielding `[id => id]`. On PHP 8 + current ADOdb (mysqli associative fetch) two columns with the same name `id` **collapse** into a single key — the second becomes `null` — yielding `[id => null]`. The code then did `foreach ($perms as $r_id)`, iterating the **values** (all `null`), so the clearance query ran `WHERE rule_id=null` → empty → blank rows.
- **Fix:** Replaced with `DB::GetCol('SELECT id FROM base_acl_rules WHERE permission_id=%d')` — returns a flat list of ids `[3, 14]`, so `$r_id` is the real rule id. Also renamed the local var to `$rule_ids` (the old code reused `$perms`, clobbering the outer loop variable — harmless due to PHP foreach-copy semantics, but now cleaner).
- **File:** `modules/Base/Acl/Acl_0.php` — `edit_permissions()`, 2 lines.
- **Important:** Only the **display** was broken. Rules were always saved and enforced correctly (`base_acl_rules` + `base_acl_rules_clearance` were intact). Verified: no other `SELECT col, col` + `GetAssoc` duplicate-column pattern exists in `modules/`.
- **Pattern for the relic table:** `SELECT col, col` + `GetAssoc` → `null` values on PHP 8 mysqli; use `GetCol` when you only need a flat list of one column.

---

## 36. ROOT CAUSE — `ModuleCommon::Instance()` broken by PHP 8.x static-variable change (covers §20 + §33) — FOR JASIEK

This is the shared root cause behind **§20** (file storage wrong prefix) and **§33** (EssClient `ClientRequester.php` not found), and potentially any other use of `SomeModuleCommon::Instance()`.

### The mechanism

`ModuleCommon::Instance()` (`include/module_common.php:28`) is a `final` static method with a `static $obj` local:
```php
public static final function Instance($arg=null) {
    static $obj;
    if(isset($arg)) $obj = $arg;
    elseif(is_string($obj)) { $cl = $obj.'Common'; $obj = new $cl($obj); }
    return $obj;
}
```
`module_manager.php:100` seeds it on **every module load**: `call_user_func(array($x, 'Instance'), $class_name)` → e.g. `CRM_TasksCommon::Instance('CRM_Tasks')`, setting `$obj = 'CRM_Tasks'`.

The whole pattern assumes **each subclass has its own `$obj`** (a per-module singleton). That was true on PHP 7.4. **PHP 8.x changed the semantics of `static` locals in inherited (not overridden) methods: they are now SHARED across all inheriting classes.** So on 8.x there is ONE `$obj` for every module — whichever module was loaded/seeded **last** wins. `Utils_FileStorageCommon::Instance()` then returns whatever module was last loaded, so `get_data_dir()` / `get_module_dir()` return the wrong module's path.

This is why it depends on call/load order, why files scatter across `data/CRM_Tasks/`, `data/CRM_Mail/`, `data/CRM_Roundcube/`, and why PHP 7.4 works but 8.2 does not. The code is byte-identical to vanilla 1.9.1 — only the language semantics changed.

### Empirically verified (PHP 8.2.12, standalone, no Epesi)

A 2-class reproduction (`Base` with `static $obj`, `ChildA`/`ChildB` inheriting) showed:
- `ChildA::Instance('Utils_FileStorage')` then `ChildB::Instance('CRM_Tasks')` → **`ChildA::Instance()` returns `'CRM_Tasks'`** (clobbered). Confirms SHARED storage.
- The proposed fix (below) made `ChildA::Instance()` return `'Utils_FileStorage'` again. Confirms per-class restored.

### Verified fix (key the static per-class via late static binding)

```php
public static final function Instance($arg=null) {
    static $objs = [];
    $cls = static::class;                       // the actual subclass
    if(isset($arg)) $objs[$cls] = $arg;
    elseif(isset($objs[$cls]) && is_string($objs[$cls])) {
        $name = $objs[$cls];
        $objs[$cls] = new ($name.'Common')($name);
    }
    return $objs[$cls] ?? null;
}
```
Restores **exactly** the PHP 7.4 per-class behavior. Does not change the data model. Fixes §20 + §33 (and the §33 `__DIR__` workaround could then be reverted) at the root.

### FOR JASIEK — decision + sequencing

- This touches the **heart of the framework** (`Instance()` is used everywhere) → author's call to apply.
- **Plan agreed with Karina:** document now (this section); apply + test on a **separate review branch** (revertable), NOT on `experiment/composer-deps` directly, then full module re-test before any merge.
- **STATUS 2026-06-28:** root fix APPLIED on branch `experiment/instance-singleton-fix` (`module_common.php` — per-class keying via `static::class`). This is a PROPOSAL for Jasiek, NOT merged. Testing in two steps: (1) re-test Core with §20/§33 workarounds still in place → confirm the Instance change introduces no regressions; (2) revert the §20 narrow fix + §33 `__DIR__` workaround → confirm the root fix alone covers them.
- **RESULT 2026-06-28: BOTH STEPS PASS.** Step 1 — Core re-tested (Dashboard, Contacts, Companies, Tasks, Print, attachments, Help, theme rendering): no regressions, log clean. Step 2 — with both workarounds REMOVED, file view/download/get-link (§20) and EPESI Store (§33) work purely on the root fix. Conclusion: the single `Instance()` fix replaces both targeted workarounds at the source. Branch is a verified, reversible proposal ready for Jasiek's review. If accepted: this becomes the canonical fix and the §20/§33 workarounds stay reverted; the 7 other latent landmine sites (EpesiStore, Theme, Fax, Print, Attachment, MainModuleIndicator) are then also covered.
- **Data caveat:** on a clean production 7.4→8.2 migration, files are already correctly in `data/Utils_FileStorage/`, so the code fix alone is enough. On THIS test instance, files written while the bug was live got scattered to `data/CRM_Tasks/` etc. — those would need a one-time move into `data/Utils_FileStorage/` (separate data step, handle with care).

---

## 37. FIXED — "Mail" file option: class name casing broke PSR-0 autoload (+ FOR JASIEK: dormant remote-attach id bug)

### 37a. FIXED — `Class "CRM_RoundCube_RemoteAttachment" not found`

- **Symptom:** File leightbox → "Mail" option → `Class "CRM_RoundCube_RemoteAttachment" not found` (`RoundcubeCommon_0.php:65`).
- **Cause:** The class is named `CRM_Round**C**ube_RemoteAttachment` (capital C), but the module/dir is `CRM/Roundcube` (lowercase c), and all other module classes are `CRM_Roundcube*`. composer's PSR-0 autoload (`"": "modules/"`) maps the class name to a path by `_`→`/`, i.e. `modules/CRM/RoundCube/RemoteAttachment.php`. On a case-sensitive FS (Linux) that ≠ the real `modules/CRM/Roundcube/RemoteAttachment.php` → file not found → class not loaded. (The Epesi autoloader only handles `*Common`/`*Install`/registered modules, so it never loaded this helper either.)
- **Fix:** Renamed the class `CRM_RoundCube_RemoteAttachment` → `CRM_Roundcube_RemoteAttachment` in 3 spots (`RemoteAttachment.php:5`, `:18`, `RoundcubeCommon_0.php:65`). PSR-0 now resolves it; the base `Utils_FileStorage_ActionHandler` already has correct casing and autoloads fine.
- **Note:** This feature was effectively **dormant in vanilla 1.9.1** — without composer PSR-0 the class could never autoload, so "Mail" would have thrown class-not-found there too. We revived it (PSR-0 + casing fix).

### 37b. FOR JASIEK — dormant remote-attach passes wrong id (RB record id vs filestorage id)

- **Symptom (after 37a):** "Mail" now composes a message with a remote link `remote.php?id=0&token=...`; opening it → "File has expired".
- **Cause:** `mail_file_field($backref)` (`RoundcubeCommon_0.php`) → `callCreateRemote("rb:utils_attachment/14/6")` parses tab/id/field and the base `createRemote` (`ActionHandler.php:188`) inserts `file_id = $params['id']` = **14** — the **RecordBrowser record id**, NOT a `utils_filestorage.id` (max 7 here). `utils_filestorage_remote.file_id` has a **FOREIGN KEY** → `utils_filestorage(id)`, so the INSERT violates the FK and silently fails (`DB::Execute` return value is not checked). `DB::Insert_ID()` then returns **0** → URL `id=0` → `remote.php` finds no row → `strtotime(null) < time()` → "File has expired".
- **Contrast:** the "Get link" option works because it passes the real filestorage id (`$meta['id']` = 7) via `getActionUrls`. The remote rows that exist (file_id=7) all came from "Get link".
- **Not a PHP 8 regression:** code is vanilla-identical (only our rename). This is a pre-existing logic bug in a feature that never ran in vanilla. Touches RB↔FileStorage id mapping (data model) → author's call.
- **Candidate direction (NOT applied):** the Mail path should resolve to a real `utils_filestorage.id` before `createRemote` — e.g. use `$meta['id']` (available at the `FileLeightbox.php:75` call site) instead of re-deriving from the RB backref, or have `createRemote` map backref→filestorage id. Also `createRemote` should check the `DB::Execute` return and not build a URL on a failed INSERT.
- **Priority:** low — dormant feature, not a migration blocker.

---

## 38. FIXED — Common data edit: first-class callable rejected by QuickForm rule (Rector over-applied)

- **Symptom:** Administrator → Common data → e.g. Contacts_Groups → add/edit node → `E_USER_ERROR: Invalid parameter specified for rule definition for field akey` (`QuickForm_0.php:261`).
- **Cause:** Rector converted the QuickForm callback-rule funcs in `CommonData_0.php` from array callables `array($this,'check_key')` to **first-class callables** `$this->check_key(...)` (PHP 8.1 syntax → `Closure`). But `Libs_QuickForm::add_array()` only recognizes `is_string()` or `is_array()` for a rule's `func` (QuickForm_0.php:256-261); a `Closure` falls through to the `else` → `trigger_error`.
- **Fix:** Reverted the two funcs back to array callables `array($this,'check_key')` / `array($this,'check_key2')` — the form the `is_array($r['func'])` branch handles and what vanilla used.
- **Scope:** verified this is the ONLY first-class-callable in a QuickForm rule `func` (grep). The 6 similar `$this->method(...)` in `RecordBrowser_0.php:1500-1530` are **Utils_TabbedBrowser tab defs**, a different mechanism that handles closures fine (clipboard pattern already tested) — left untouched.
- **Pattern:** Rector's first-class-callable rule can break APIs that type-check callbacks as `string|array`. When a `*(...)` callable feeds an old QuickForm/PEAR-style consumer, revert to `array($obj,'method')`.

---

## 39. FIXED — RecordBrowser add field: exportValue on not-yet-added element

- **Symptom:** Administrator → RecordBrowser → any recordset (e.g. Companies) → Add new field → `HTML_QuickForm_Error: nonexistent html element: Element 'select_data_type' does not exist` (`QuickForm.php:1576`, via `RecordBrowser_0.php:1991`).
- **Cause:** In `view_field()` the "add" branch calls `$form->exportValue('select_data_type')` at line 1991, but that element is only added later (~line 2000). openpsa QuickForm's `exportValue()` **throws** when the element isn't registered; PEAR returned null silently. Code is vanilla-identical — pure PEAR→openpsa behavior difference. The variable `$selected_data` is in fact **dead** (assigned at 1987/1991, never read).
- **Fix:** Guarded the export with `elementExists()`: `$selected_data = $form->elementExists('select_data_type') ? $form->exportValue('select_data_type') : null;`. On the add path the element isn't registered yet → `null`, matching the old PEAR behavior. (`elementExists` is proxied to openpsa via `Libs_QuickForm::__call`.)
- **Pattern (openpsa vs PEAR, same family as §32):** openpsa throws on nonexistent-element access (`exportValue`, `addRule`, etc.) where PEAR was silent — guard with `elementExists()` / add the element first.

---

## 40. FIXED — RecordBrowser Permissions edit: addElement('crits') on unregistered type

- **Symptom:** Administrator → RecordBrowser → any recordset → Permissions → Edit rule → `HTML_QuickForm_Error: unregistered element: Element 'crits' does not exist` (`QuickForm.php:476`, via `RecordBrowser_0.php:2976` `addElement('crits', ...)`).
- **Cause:** `'crits'` is **not** a registered QF element type — only `'critsvalue'` is (eager list `epesi.php:290`, and `QueryBuilder_0.php:167`). `Libs_QuickForm::__call` intercepts `addElement('crits', name, label, tab, crits)` and runs the QueryBuilder integration (`add_to_form` → `init_form` → `addElement('critsvalue', name, ...)`), which adds the REAL element. But `__call` then **still forwarded** the raw `addElement('crits', ...)` to the underlying QuickForm (line 100-102). openpsa throws on the unregistered `'crits'` type; PEAR returned a silently-ignored `PEAR_Error`, so the redundant forward was harmless there.
- **Fix:** In `QuickForm_0.php::__call`, after the `type=='crits'` integration block, `return` early so the raw `'crits'` addElement is not forwarded to openpsa. The real `'critsvalue'` element was already added by the integration. (`$selected`/return value of `addElement('crits',…)` is unused by callers.)
- **Scope:** affects every `addElement('crits', …)` caller — RecordBrowser Permissions form (`:2976`), `add_array` crits case (`QuickForm_0.php:241`), Tasks `crits` field. The filters/search path tested earlier uses `'critsvalue'` directly, so it was unaffected.
- **Pattern (openpsa vs PEAR):** openpsa throws on `addElement` of an unregistered type where PEAR returned an ignored `PEAR_Error`. Where a wrapper adds the real element via a side path and then redundantly forwards the pseudo-type, stop forwarding it.

---

## 41. FIXED — RSS/Weather applet: set_error_handler callback 5th arg removed in PHP 8

- **Symptom:** Dashboard with Weather/RssFeed applet → when a feed can't be fetched → `Uncaught ArgumentCountError: Too few arguments to function handle_rss_error(), 4 passed and exactly 5 expected` (`Applets/Weather/refresh.php:22`, same in `Applets/RssFeed/refresh.php`).
- **Cause:** `handle_rss_error($type, $message, $errfile, $errline, $errcontext): never` is registered via `set_error_handler()`. PHP 8.0 **removed the 5th `$errcontext`** argument passed to error-handler callbacks, so PHP calls it with 4 args; the required 5th param → `ArgumentCountError`. Result: instead of the intended graceful `die('Error getting RSS')`, the applet fatals on any feed error.
- **Fix:** Made the 5th param optional — `$errcontext=null` — in both `Applets/Weather/refresh.php` and `Applets/RssFeed/refresh.php`.
- **Verified:** applet now degrades gracefully to "Error getting RSS" (feeds don't load on this offline localhost — a network limitation, not a code issue).
- **Pattern:** any `set_error_handler` callback declared with a required 5th `$errcontext` param breaks on PHP 8 — make it optional or drop it.

---

## 42. FIXED — Add note to Task: empty crits_callback segment → `array('')` breaks Crits

- **Symptom:** Task → Add note → `E_USER_ERROR: Invalid criteria in build query: missing word. Crits: Array([0] => '')` (`Crits.php:670`), via the "Attached to" recordpicker.
- **Cause:** The `utils_attachment.attached_to` field param is the string `__RECORDSETS__::;`. `decode_select_param()` does `explode(';', …)` → `$param[1] = ''` (empty crits_callback segment). The guard `isset($param[1]) && $param[1] != '::'` lets the empty string through, so `explode('::', '')` returns `array('')` — an empty crit "word". Passed as `$param['crits_callback'] ?: $tab_crits` to the recordpicker → `Utils_RecordBrowser_Crits::build_from_array(array(''))` → "missing word".
- **Pre-existing, not the Instance work:** verified the identical error on `experiment/composer-deps` without the §36 branch — independent of the Instance fix. `decode_select_param` is vanilla-identical; the newer Crits-object system rejects the empty crit more strictly than the old array-crits path did.
- **Fix:** Treat an empty segment like the `'::'` case → `null` (no callback). Added `$param[1] !== ''` (and same for `$param[2]`) to the guard in `decode_select_param`. Downstream already handles `null` crits_callback (the `'::'` path always produced `null`).
- **File:** `modules/Utils/RecordBrowser/RecordBrowserCommon_0.php` — `decode_select_param()`.

---

## 43. FIXED — Patch system error handler: 5th arg ($errcontext) required (PHP 8) — blocks any patch that warns

- **Symptom:** Running `runpatches.php`/`update.php` → `Uncaught ArgumentCountError: Too few arguments to function Patch::error_handler(), 4 passed and exactly 5 expected` (`include/patches.php:411`). Surfaced during the real-upgrade test the moment a patch emitted a warning (e.g. an `ob_end_clean()` notice).
- **Cause:** Same family as §41. `Patch::error_handler($errno,$errstr,$errfile,$errline,$errcontext)` is installed via `set_error_handler()`; PHP 8.0 dropped the 5th `$errcontext` arg, so PHP calls it with 4 → fatal. This **blocks any patch run that triggers a warning** — important because the upgrade procedure relies on `runpatches.php`.
- **Fix:** `$errcontext = null` (made the 5th param optional). `include/patches.php:411`.
- **Why it matters for upgrades:** the real 7.4→8.2 upgrade (validated on a client copy) requires `runpatches.php`; this made the patch runner robust to warnings.

---

## 44. DONE (branch `experiment/mail-attachments-filestorage`) — archived e-mail attachments → Utils_FileStorage (Jasiek request)

Requested by Jasiek (2026-06-29). (The original design proposal, `PROPOSAL_mail_attachments_filestorage.md`,
was removed 2026-08-22 once confirmed implemented — see this section for the design it described.)

- **Before:** `archive_message()` wrote attachment bytes raw to `data/CRM_Mail/attachments/<mail_id>/<mime_id>` (no dedup, bypassing the central store); `get.php`/`get_remote.php` read straight from there.
- **Change:** `rc_mails_attachments` gains `file_id` (install + idempotent patch); write path stores via `Utils_FileStorageCommon::write_content()`; read paths serve from FileStorage when `file_id` is set, else **fall back** to the legacy folder; a patch (`modules/CRM/Mail/patches/20260629_mail_attachments_to_filestorage.php`) **moves** legacy rows into FileStorage.
- **MOVE (Jasiek decided 2026-06-29):** the patch stores each legacy file in FileStorage, sets `file_id`, then — only after `Utils_FileStorageCommon::file_exists($file_id)` confirms it — **deletes** the legacy `data/CRM_Mail/attachments/<mail_id>/<mime_id>` and the now-empty per-mail dir. Verify-before-delete = no data-loss window; idempotent.
- **CORRECTION (2026-06-30) — storage-id bug found on real data:** the first cut used `add_data_from_content()`, which returns the low-level **content** id (`utils_filestorage_files.id`), but `read_content()`/`file_exists()`/`meta()` expect a **storage-object** id (`utils_filestorage.id`). With dedup the two id-spaces diverge — on the client copy only 15/39 happened to line up. Fixed to use **`write_content()`** (stores content + creates the storage object, returns the storage id) in both the write path and the patch — the same API the standard attachment flow uses (`AttachmentCommon` `write_file`/`write_content`). The dry-run move check (verify-before-delete) caught this before any file was deleted.
- **First cut validated dedup** on the client copy (197 vs 160 physical files for 39 attachments → 2 reused) but had the id bug above; needs a **clean re-migration** to re-validate the read path + move end-to-end.
- **Status:** code fixed and merged — confirmed shipped by the 2026-06-30 gap hunt (top of file, §"Result"), which found `rc_mails_attachments.file_id` present on the upgraded system.

---

## 45. FIXED — Clipboard pattern garbled on upgrade (§25 was dev-only) + Copy button didn't copy (§24)

Found on the real client upgrade: "Copy to clipboard" showed literal `%{{postal_code}}` and the Copy button did nothing.

- **Cause A (the §25 upgrade gap):** §25 fixed the nested-`%{}` clipboard pattern only for **fresh installs** (`ContactsInstall.php`) and via a one-off `UPDATE` on the **dev** DB. **Existing** databases (the client) still hold the old nested `%{%{{city} }%{{zone} }{postal_code}<BR>}`, which `replace_clipboard_pattern()` can't render (its regex excludes `%` from a block's content, so the outer `%{…}` stays literal — pre-existing limitation). That's why it was clean on `epesi82_test` but broken on the client copy.
- **Fix A:** migration patch `modules/Utils/RecordBrowser/patches/20260630_fix_nested_clipboard_pattern.php` — surgically rewrites the broken nested block to the §25 simplified form (`%{{city} {zone} {postal_code}<BR>}`) in any existing `recordbrowser_clipboard_pattern` row that contains it (custom patterns untouched; idempotent). Runs via `runpatches.php` on upgrade.
- **Cause B (the Copy button):** §24's button built `onclick="…writeText(<json_encode>)…"` — `json_encode` emits a **double-quoted** JS string inside the **double-quoted** `onclick` attribute, breaking it, so the click silently did nothing (the mouseover-select + Ctrl-C still worked, which is why it seemed fine).
- **Fix B:** `htmlspecialchars($handler, ENT_QUOTES)` the whole onclick (and `json_encode` the "Copied!" label); the button now copies and flips to **"Copied!"** as a calm confirmation. `RecordBrowser_0.php` ~1177.
- **Lesson:** data-only fixes (like §25's dev-DB `UPDATE`) don't reach existing installs — upgrade fixes that touch data need a **patch**.

---

## 46. FIXED — "Download all attachments": `in_array()` on non-array + wrong field (vanilla 1.9.1, fatal on PHP 8)

Found on the client upgrade: opening a note's **Download all attachments** → `TypeError: in_array(): Argument #2 ($haystack) must be of type array, string given` (`Utils/RecordBrowser/FileActionHandler.php:49`).

- **Cause (two vanilla 1.9.1 bugs, surfaced by PHP 8):**
  1. `AttachmentCommon_0.php:364` (download-all) passes `$field` (the **note text** field, e.g. `'note'`) to the access check instead of `'files'` — so it tested the requested file ids against the note's text. (Single-file download at :302 correctly passes `'files'`, which is why single worked.)
  2. `checkRecordAccess()` did `in_array($filestorageId, $record[$fieldId])` — and for download-all `$filestorageId` is an **array** of ids while the haystack was the note **string**. On PHP 7.4 this was a silent warning → `null` → access denied (download-all never actually worked); on PHP 8 it's fatal.
- **Fix:** (a) `:364` pass `'files'`; (b) `checkRecordAccess()` normalises the haystack with the idempotent `decode_multi()` and accepts a scalar **or** array `$filestorageId`, granting only when **every** requested id belongs to the field (`array_diff` subset check). Both files vanilla baseline.
- **Result:** download-all now zips the record's files; single download unchanged.

---

## 47. FIXED — Sending mail aborts when "check spelling before send" is on (dead googie + guzzle/psr7 fatal)

Found on the client upgrade: composing and sending `user@example.com → user@example.com` (to self) failed; browser showed "Connection error, failed to reach the server" (the send request 500'd).

- **Cause:** the user's RC preference `spellcheck_before_send` is `true` (stored in `rc_users.preferences`). On send, `send.php` runs `rcube_spellchecker` with the default `spellcheck_engine='googie'`, which POSTs to a **defunct external Google spell service**. The failed HTTP then hits a **guzzle/psr7 version mismatch** in the RC 1.7.1 vendor — `GuzzleHttp\Exception\RequestException::create()` calls `GuzzleHttp\Psr7\Utils::redactUserInfo()`, which doesn't exist in the bundled psr7 → `Uncaught Error: Call to undefined method` → fatal, send aborts. (`send.php:144` gate needs both `spellcheck_before_send` AND `enable_spellcheck`.)
- **Fix:** `modules/CRM/Roundcube/RC/config/config.inc.php` — `$config['enable_spellcheck'] = false;`. The before-send gate is now false → no spell HTTP call → send works for everyone, regardless of the per-user pref. The only bundled engine is the dead googie service, so nothing functional is lost. Portable (shipped config, reaches all upgrades).
- **RESOLVED (Phase 5):** the underlying guzzle↔psr7 mismatch made *any* failed external HTTP via guzzle fatal (not just spellcheck). Root cause: Epesi's main `vendor/` carried an **old psr7 (1.x, no `Utils::redactUserInfo`)** as a transitive dep, shadowing the RC bundle's psr7 2.x via the autoloader. Fixed by `composer require guzzlehttp/psr7:^2.7 -W` (→ psr7 2.12.3 + psr/http-factory, psr/http-message, symfony/http-foundation). No Epesi code uses guzzle directly, so low-risk. Spellcheck stays disabled regardless. (Test after: file download/upload via `Symfony\…\HttpFoundation\Request`, since http-foundation also bumped.)

---

## 48. FIXED — "Archive to CRM" button vanished after RC 1.2.1→1.7.1 (Larry/Classic dropped → Elastic-only)

Found on the client upgrade: the message-view **Archive** button (archives an e-mail into a CRM record) disappeared.

- **Cause:** RC 1.7.1 ships **only the `elastic` skin** (Larry/Classic removed in 1.6+); the user's `larry` pref silently falls back to elastic. The `epesi_archive` plugin was written for the old skins/API: (1) old **PNG image buttons** (`imageact`/`imagepas` under `skins/larry|classic`) that Elastic doesn't render; (2) the action handler used **removed** helpers `get_input_value()/RCUBE_INPUT_POST` and the global `rcmail_wash_html()` — so even if shown, clicking it would fatal.
- **Fix (port to Elastic):**
  - Buttons → Elastic CSS-class style (`'class'=>'button archive'`, `innerclass`, `label`) like the bundled `markasjunk`; kept in the `toolbar` container. Because `epesi_archive` loads before `markasjunk` in the plugins list, the button lands **between the core Delete button and the Junk button** (its original spot).
  - New `skins/elastic/archive.css` (`.toolbar a.archive:before{content:"\f187";font-family:Icons;font-weight:900}`) — reuses Elastic's bundled `Icons` font / archive-box glyph (`.toolbar a:before` already assigns the font, we add the glyph). Label shortened to `Archive`.
  - API: `get_input_value(x,RCUBE_INPUT_POST)` → `rcube_utils::get_input_value(x,rcube_utils::INPUT_POST)` (×3).
- **Runtime fixes found in live testing (the button only renders + works after all of these):**
  - **Wash:** `rcmail_action_mail_index::wash_html()` fatals here — it calls `$rcmail->output->asset_url()`, which exists only on the HTML output; the archive action runs as an AJAX/JSON request (`rcmail_output_json`). Use `rcube_washtml` directly (what the old `rcmail_wash_html` wrapper did).
  - **CSS load:** must include `skins/elastic/archive.css` **explicitly** — `local_skin_path()` resolved to the plugin's legacy `skins/larry` dir under the user's stored `larry` pref, loading the wrong (PNG) stylesheet → no icon.
  - **Attachment write:** `archive()` chdir'd to the Epesi root via a fragile `str_replace(getcwd())` that the RC 1.7.1 bundle's CWD broke → FileStorage's relative `data/…` path threw `Utils_FileStorage_WriteError`. Use `chdir(EPESI_LOCAL_DIR)`.
- **Auto-archive-on-send re-port (`f_archive_on_sending`):** the old `auto_archive()` hooked `attachments_cleanup` and read `$store_folder/$saved/$store_target` as **globals** — in RC 1.7.1 that hook fires from `rcube_uploads.php` (upload cleanup) and those send vars are **locals** in `send.php`, so it was a silent no-op (sent replies landed in `CRM Archive` only because of the `reply_same_folder` pref). RC 1.7.1 has **no post-store hook**. New approach: hook **`message_sent`** (fires during delivery, *before* `save_message` reads `$_POST['_store_target']`) → set `$_POST['_store_target']` to `CRM Archive Sent` so the sent copy is filed there directly (overrides `reply_same_folder`); then `add_shutdown_function()` (shutdown runs registered fns **before** the IMAP/storage close — `rcube::shutdown()`) locates the stored message by Message-ID and creates the Epesi record via the existing `archive()`. Validated live: sent mail saved to `CRM Archive Sent` + CRM record created.
- **Validated live on the client copy:** archives to the CRM record, attachment stored in FileStorage (downloadable), and the mail moves to `INBOX.CRM Archive` (`f_use_epesi_archive_directories=1`). Note: a *partial* failure leaves an `rc_mails` record with no attachment, and the `message_id` duplicate-guard then blocks retry ("Message already archived") — pre-existing, not migration-caused.
- **Files:** `modules/CRM/Roundcube/RC/plugins/epesi_archive/epesi_archive.php`, new `skins/elastic/archive.css`, `localization/en_US.inc`. Epesi's own plugin (in the RC bundle), so in-scope & portable.

---

# PHASE 5 — PHP hardening (branch `experiment/php8-hardening`, post-release)

## 49. FIXED — PHP-8-removed functions surviving in live cold paths

After the v1.9.2-php8.2 release, a hardening sweep for functions **removed in PHP 8.0** (fatal only if the code path is hit — so missed during runtime testing). Scope: `modules/` + `include/` minus vendor, RC bundle, and the dead `3.2.14-php7/` dir.

- **`Base/Mail/class.phpmailer.php` `encodeFile()`** — `get_magic_quotes_runtime()` (removed in 8.0) would fatal when **Base_Mail sends a mail with an attachment** (recovery/system mail without attachment never hit it). Always `false` on PHP 7+ → set to `false`.
- **`Libs/QuickForm/Rule/CompareString.php` `validate()`** — `create_function()` (removed in 8.0) for the registered `comparestring` rule → replaced with a direct `strcmp()` + `switch`.
- **Dead, since removed (§50):** `modules/Libs/QuickForm/3.2.14-php7/**` (old vendored QuickForm — `requires.php` disabled it, openpsa/composer is loaded instead) still contained `create_function`/`get_magic_quotes_gpc`, but was never included.
- **Clean:** broader removed-function scan (money_format, convert_cyr_string, ezmlm_hash, image2wbmp, read_exif_data, call_user_method, reversed `implode` args, …) found nothing else live.

---

## 50. DONE — Remove the dead vendored QuickForm `3.2.14-php7/` (46 files)

Disabled in `requires.php` (openpsa/quickform via composer is loaded instead) and referenced nowhere else, but still carried PHP-8-removed functions (`create_function`, `get_magic_quotes_gpc`) that polluted the §49 scans. Removed the dir; `requires.php` reduced to a no-op note. Premium modules use the QuickForm **API** (`QuickForm_0.php` → openpsa), not these internal files.

---

## 51. DONE — Drop stale dev dependency `codeception/aspect-mock` (§4 cleanup)

`composer remove --dev codeception/aspect-mock` pruned **7 PHP-7-only packages** (aspect-mock, goaop/framework, goaop/parser-reflection, doctrine annotations/cache/lexer, dissect — ~527 vendor files). This was the §4 stale-dev-dep **and** the cause of the Rector `ParserFactory::create()` fatal (goaop's bootstrap ran on `vendor/autoload.php`). `modules/Tests` doesn't use aspect-mock directly. (`codeception/codeception` still in require-dev — removable later with the Tests-exclusion decision.)

---

## 52. DONE — Remove `codeception/codeception` + its skeleton (closes the §51 loose end)

The Tests-exclusion decision from §51 landed on "remove": `composer remove --dev codeception/codeception` pruned **36 packages** (Codeception itself, PHPUnit, behat/gherkin, symfony/finder+yaml+event-dispatcher, the sebastian/* internals). `codeception.yml` and `tests/` (the two example files — `tests/acceptance/LoginCept.php`, `tests/unit/StaticMockExampleTest.php` — plus suite configs/bootstrap, both dating to the original vanilla-1.9.1 baseline commit and never expanded or wired into CI) were deleted outright rather than left pointing at an uninstalled library. Also dropped the now-dead `codeception\.yml` entry from `console/Develop/CreateDistCommand.php`'s dist-zip exclude list. `PROPOSAL_functional_tests.md` (still open, undecided) describes building a real suite on Codeception starting from that skeleton — it now needs to note the skeleton is gone, not just unexpanded, if that plan is picked back up.

---

## 53. DONE — `require` dependency audit: drop unused `moneyphp/money`; reclassify `psy/psysh`

Full pass over every `composer.json` `require` entry, verifying each has a real call site (not just declared). Two findings:

- **`moneyphp/money` was entirely unused** — zero references anywhere in Epesi's own code (root or Premium), only in its own vendor files. `Utils_CurrencyField` (currency formatting/parsing) is hand-rolled string/decimal logic with no dependency on the `Money\Money` value object. `composer remove moneyphp/money` — clean, no other package depended on it.
- **`psy/psysh` moved from `require-dev` to `require`**: `console/ShellCommand.php` (`console.php shell`) calls `\Psy\Shell::debug()` from shipped code, not test/dev tooling — same category as `fakerphp/faker`, already in `require` for the same reason (`console/Demo/GenerateContactsCommand.php`).

**Don't remove `guzzlehttp/psr7` on a similar grep-based sweep** — it has zero *direct* call sites in Epesi's own code either, but §47 pins it deliberately: an old psr7 in root `vendor/` was shadowing RoundCube's own psr7 2.x through the shared per-module autoloader (`module_manager.php::load_modules()`), fataling mail send. It's a structural/version-pin dependency, not a code dependency — "no grep hits" isn't sufficient evidence of dead weight for a package that's there to control autoload resolution order for a *different* module's vendored copy. Checked and confirmed the reasoning still holds before leaving it in place.

**Follow-up, same sitting**: the `setup.php:302` stale-duplicate-library issue noticed in passing above — `include_once('libs/adodb/adodb.inc.php')`, the old pre-composer v5.20.2 copy (`each()` still present, §50's sibling QuickForm cleanup was modeled on this exact shape) — is fixed. `setup.php` now points at the same composer-managed `vendor/adodb/adodb-php/adodb.inc.php` `include/database.php` already used, and the dead `libs/adodb/` directory (96 files) is deleted entirely.

---

## PHASE 5 STATUS (as of 2026-07-01)

**Done:**
- Release renamed to CalVer **`20260701-rc1`** (Jasiek's date scheme; supersedes the interim `1.9.2`). `EPESI_VERSION` is `version_compare`-safe: `> 1.9.1` (auto-update triggers) and `< final 20260701`.
- Repo hygiene: `.gitignore` covers runtime `data/` + `cron_token.php`; client email anonymized in the current notes.
- **§47** psr7 2.x re-pin · **§49** removed-function landmines · **§50** dead 3.2.14 removed · **§51** aspect-mock/goaop dev-dep cleanup.
- **Rector PHP 8.2 dry-run (`rector-php82.php`): CLEAN — 636 files, zero changes.** The code is solid for 8.2.

**Open — low urgency:** PHPStan/Psalm pass; dynamic properties (`AddAllowDynamicPropertiesAttributeRector` — *not* in the default 8.2 set, opinionated; only `E_DEPRECATED` on 8.2, matters for 8.3/9.0); CI workflow (`.github/workflows/php-checks.yml` — pushing it needs the token's `workflow` scope; Rector also runs locally).

**Open — pre-public:** `composer audit` (composer flagged ~23 advisories in 6 old packages — likely the legacy symfony/twig stack); history scrub of email/client name/personal names (`git filter-repo --replace-text`, keeps commits); `README.md` / `UPGRADE.md`.

---

### §53 — PHPStan baseline committed + CI enforcement on; benign findings fixed (2026-07-02)

The CI `phpstan` job's first run generated **`phpstan-baseline.neon` (316 findings)**; it is now committed
and `phpstan.neon` carries a top-level `includes: [phpstan-baseline.neon]`, so **CI fails only on NEW issues
(regressions)** — the pre-existing noise is frozen. `actions/upload-artifact` bumped v4→v5.

**~95 % of the 316 is no-PSR-4-autoload noise** (Epesi uses a custom module loader, not PSR-4): 51× OFC
`require_once` "path not found", class case-mismatches (PHP is case-insensitive → they work), PEAR / Minify /
OFC / TCPDF classes PHPStan can't scan, include-scope "undefined variable". Not bugs.

**Genuine findings triaged:**
- **Duplicate array keys — FIXED (4).** Removed benign copy-paste dups where key **and value** were identical
  (zero behaviour change): `'visible'=>true` twice in the *Status* field def of `CRM/Meeting/MeetingInstall.php`
  and `CRM/Tasks/TasksInstall.php`; `'russian'=>'CP1251'` twice in
  `Base/RegionalSettings/RegionalSettingsCommon_0.php`; `'winw'=>'winw'` twice in `include/misc.php`. The
  identical dup in the already-applied 2012 patch `Base/Acl/patches/20120626_new_permission.php` was left as-is
  (historical patch, harmless).
- **Dead `set_magic_quotes_runtime` — FIXED.** In `Base/Mail/class.phpmailer.php::encodeFile()`, §49 had set
  `$magic_quotes = false` but left the now-unreachable `if ($magic_quotes) { set_magic_quotes_runtime(…) }`
  save/restore blocks (PHPStan-flagged, removed in PHP 8). Removed the dead blocks — read + encode directly.
- **`mobile_stack_href()` undefined — LEFT BASELINED.** Called at 5 sites in the **mobile UI**
  (`Utils/RecordBrowser/mobile.php`, `Utils/RecordBrowser/RecordBrowserCommon_0.php`, `Utils/Tray/mobile.php`,
  `Utils/Calendar/CalendarCommon_0.php`, `CRM/Calendar/CalendarCommon_0.php`) but **defined nowhere** —
  pre-existing (undefined before PHP 8 too), *not* a migration regression. The mobile UI is vestigial and
  defining the helper blind is risky → flagged for a **separate mobile-UI assessment**, frozen in the baseline.
- **`CRM_RoundcubeCommon::create_thread()` wrong class — LEFT BASELINED.** The method lives in
  `CRM_MailCommon::create_thread` (`CRM/Mail/MailCommon_0.php:344`); three 2013 Roundcube patches call it on
  `CRM_RoundcubeCommon`. A **fresh 8.2 install was validated OK**, so it is either unreachable there or resolves
  another way — not touching historical patches on unverified behaviour. Documented; baselined.

**Non-baselineable errors — the enforce run surfaced them in waves (10, then 4) that `--generate-baseline`
had silently DROPPED.** Key lesson: PHPStan marks certain errors as **non-ignorable** (`canBeIgnored: false`)
→ `--generate-baseline` excludes them, so they were never in the 316, yet the enforce run reports them and
fails; and because PHPStan re-analyses after each fix, clearing one wave can reveal the next. They can only be
**fixed**, not baselined — and they were all real. Covariance was the main category; a preemptive grep
(`implements (ArrayAccess|Iterator|Countable|…)` + `extends ArrayObject|…`) confirmed only two of our classes
are affected, both now done:
- **`include/session.php` (6):** `EpesiSession` implements `SessionHandlerInterface`; `open/close/read/write/`
  `destroy/gc` returned `mixed`, not covariant with the interface's *tentative* return types (PHP 8.1+).
  Added `#[\ReturnTypeWillChange]` to each (zero runtime change; `gc()` returns `true` while the interface
  wants `int|false`, so the attribute is safer than declaring a type).
- **`modules/Utils/RecordBrowser/object_wrapper/Record.php` (4):** `RBO_Record implements ArrayAccess`;
  `offsetExists/offsetGet/offsetSet/offsetUnset` returned `mixed` vs the tentative `bool/mixed/void/void`.
  Same `#[\ReturnTypeWillChange]` fix. RBO_Record is the array-style wrapper around RecordBrowser rows —
  used widely, so this is core, but the attribute changes nothing at runtime.
- **`include/backups.php:264` (1):** `BackupArchive::extractTo()` vs `ZipArchive::extractTo()` tentative
  return type → `#[\ReturnTypeWillChange]`.
- **`modules/Utils/GenericBrowser/GenericBrowser_0.php` (2 flagged, 4 fixed):** the A–Z quick-jump built
  links with `$letter_links[] .= '…'` — `$arr[] .=` **reads** `$arr[]` ("Cannot use [] for reading"), a real
  runtime fatal (passes `php -l`, so lint missed it). Corrected all four to `$letter_links[] = '…'` (append).
  Fixed all four, not just the 2 PHPStan flagged, because fixing the first 2 would expose the other 2 next run.
- **`modules/Libs/OpenFlashChart/OpenFlashChart_0.php:35` (1):** `__call($func_name, $args)` made `$args`
  **required**, breaking LSP vs `Module::__call($name, $args = …)`; defaulted `$args = array()`.

After the fixes the matching baseline entries go stale, but `reportUnmatchedIgnoredErrors: false` keeps CI
green; regenerate the baseline from the CI artifact on the next convenient run to shrink it.

---

### §54 — Roundcube fresh-install: restore `rc_` table prefix in `mysql.initial.sql` (2026-07-06)

**Found by the Windows cross-platform test (first real FRESH install of Roundcube).** Opening Mail on Windows
gave RC's generic "Oops"; the real error was in the *correct* RC log (`data/CRM_Roundcube/log/errors`, NOT
`modules/CRM/Roundcube/RC/logs/` — Epesi overrides `log_dir` in `RC/config/config.inc.php:26`):
`DB Error: [1146] Table 'epesi82.rc_session' doesn't exist … INSERT INTO rc_session …`.

**Root cause — a regression introduced by our own §30 RC 1.2.1→1.7.1 upgrade.** `CRM_RoundcubeInstall::install()`
runs `RC/SQL/mysql.initial.sql` **raw** (no prefix substitution), and everything else expects the `rc_` prefix
(`db_prefix='rc_'`; `drop_all_rc_tables()` drops `rc_session`/`rc_users`/…; RC queries `rc_*`). The pre-upgrade
baseline `mysql.initial.sql` created **`rc_`-prefixed** tables. The §30 upgrade replaced it with the **stock
Roundcube** file, which creates **unprefixed** tables (`session`, `users`, …) → on a fresh install the `rc_*`
tables are never created (and `CREATE TABLE session` even collides with Epesi's own `session` table) → RC fatals.
Never caught before because mail was only ever validated via **upgrade** (manual `rc_` SQL migration), never a
fresh install.

**Fix:** re-add the `rc_` prefix to `mysql.initial.sql` — all 18 `CREATE TABLE`, all 14 `REFERENCES` (FKs), and
the final `INSERT INTO rc_system` (roundcube-version). Column names untouched. Platform-independent fix (fresh
install was broken on Linux too; Windows is just where we first ran one). Verify: fresh install → `rc_*` tables
exist → Mail opens past "Oops". Secondary/known: `drop_all_rc_tables()` still lists only the old subset (misses
`rc_cache_shared`/`rc_collected_addresses`/`rc_responses`/`rc_filestore`/`rc_uploads`) → harmless for a first
install, but a clean *reinstall* would hit "table exists"; worth updating that drop list later.

---

### §55 — default root `.htaccess` template: fix mis-guarded `Header` directives (2026-07-07)

**Found by the DirectAdmin cross-platform test.** On DA, `setup.php` showed *"Your hosting is not compatible with
default EPESI root .htaccess file"* — a warning cPanel never showed. Cause: in `htaccess.txt` the security-header
lines were wrapped in the **wrong** `<IfModule>` guard:
```
<IfModule mod_alias.c>
  RedirectMatch 404 /\.svn(/|$)      ← mod_alias  (correct)
  Header always append X-Frame-Options SAMEORIGIN   ← needs mod_headers, WRONG guard
```
`Header` is a **mod_headers** directive. On a host that has `mod_alias` but **not `mod_headers`** (DA), Apache
enters the `mod_alias` block, hits `Header`, and 500s (*"Invalid command 'Header'"*) → `check_htaccess()` in
`setup.php` (which copies the template and HTTP-tests it) correctly reports incompatible. cPanel had `mod_headers`
loaded, so it passed. **Fix:** split the `Header` lines into their own `<IfModule mod_headers.c>` block (so they're
skipped, not errored, when mod_headers is absent). Also cleaned up while here: dropped the PHP-5 `magic_quotes_gpc`
line (removed in PHP 8), bumped the mod_php `memory_limit` 64M→256M and added a PHP-8 `<IfModule mod_php.c>` guard,
and fixed the `\\.` → `\.` regex escaping in the VCS-dir RedirectMatch. `.htaccess` is security hardening, not
required to run — Epesi installs fine without it (Karina clicked "Ok" to proceed on DA). Not a migration bug, but a
real shipped-template defect that breaks the setup compat-check on non-`mod_headers` hosting.

---

### §56 — file MIME detection: don't hard-depend on `passthru()` (disabled on shared hosting) (2026-07-07)

**Found by the DirectAdmin test** (attaching a file to a note): `Call to undefined function passthru()` at
`modules/Utils/FileStorage/FileStorageCommon_0.php:669`, in `get_mime_type()` → breaks file attachments entirely.
Root cause: `get_mime_type()` **first** shells out to the unix `file` command via `@passthru("file …")`, and only
falls back to PHP's fileinfo (`mime_content_type`) *after*. Shared hosts routinely put `passthru`/`exec`/`shell_exec`
in `disable_functions`; on **PHP 8 a disabled function is treated as UNDEFINED** (not a suppressible warning), so the
`@passthru(...)` throws a fatal `Error` *before* the fallback is reached — the `@` can't catch it. (The mime+charset
`$encoding` path already used `finfo` — only the plain mime-type path shelled out.) **Fix:** make **PHP `fileinfo`
the primary method for BOTH cases** — `new finfo($encoding ? FILEINFO_MIME : FILEINFO_MIME_TYPE)` (portable, no
shell-out) — and **guard the legacy `passthru` fallback with `function_exists('passthru')`** so it can never fatal.
`fileinfo` is on by default in PHP, so real hosts get correct MIME detection with zero shell-out. Genuine
portability bug (would break attachments on any host that disables passthru — very common); real fix on `main`.

---

### §57 — soften the multi-window Roundcube warning (2026-07-07)

The red *"your hosting does not support multiple Roundcube sessions"* alarm showed on **every** mail open when the
`RCWIN_` URL-rewrite (multiwin) is unavailable (mod_rewrite/AllowOverride — e.g. DirectAdmin), which is scary for a
non-problem (the limit only bites if a user opens a *second* mail window). Softened to a calm muted note shown
**once per session** (module variable) in Epesi's `modules/CRM/Roundcube/Roundcube_0.php` (the wrapper, NOT the RC
vendor). On `main`.

---

### §58 — Roundcube schema migration on UPGRADE — RESOLVED with a migration patch (2026-07-07/08)

**RESOLVED (2026-07-08):** built `modules/CRM/Roundcube/patches/20260708_rc_schema_migrate.php` (approach A) and
**validated on Karina's real DA upgrade instance** — `runpatches.php` returned `1` (clean, no skipped statements),
`rc_session.expires_at` now exists, `rc_system` = `2025092300`, and Mail **send + receive + archiving** all work.
Applied via `modules/Base/EpesiStore/runpatches.php` (`PatchUtil::apply_new()`) because `update.php` short-circuits
when the version is already current. On `main`.

**Found by the DirectAdmin upgrade test** (old Epesi 1.9.1/PHP-7.4 → 8.2): opening Mail after the auto-update gave
`DB Error [1054] Unknown column 'expires_at' in 'rc_session'`. Root cause: Epesi's Roundcube schema-migration
patches (`modules/CRM/Roundcube/patches/*_update_*.php`) **stop at ~2016** (last one `20160816_update_121` → sets
`rc_system` roundcube-version to 2015111100), but the §30 RC 1.2.1→1.7.1 upgrade bundled RC whose schema is
**2025092300** (35 migration files in `RC/SQL/mysql/`). **No patch bridges 2016→2025**, so on upgrade the `rc_*`
schema stays old and the new RC 1.7.1 code queries columns (`expires_at`, …) that don't exist. Worse: the old
patches applied the *unprefixed* stock update SQL via `@DB::Execute` (the `@` silently swallows the "no such table
`users`" errors on the `rc_`-prefixed DB), so they were partly no-ops anyway. **This hits EVERY real upgrade**, not
just the test — Phase-2 (Linux, client data) only worked because the RC migration was done **manually**. This is the
upgrade-path counterpart of §54 (which fixed *fresh* install).

**Design for the fix (build deliberately + test on the upgrade instance BEFORE `main`):** a `CRM/Roundcube` patch
that migrates the `rc_` schema from the stored `rc_system` version → 2025092300. Two options —
**(A, preferred)** pure Epesi patch: read `rc_system` roundcube-version, then for each `RC/SQL/mysql/<ver>.sql` newer
than it (in order) apply the statements with the **`rc_` prefix added** to table names, then set `rc_system`. First
scan all 35 files for the statement types present (ALTER TABLE / CREATE TABLE / CREATE|DROP INDEX … ON / INSERT INTO
/ TRUNCATE / RENAME TABLE / REFERENCES) and build a complete prefixer. No RC bootstrap → robust. **(B)** invoke RC's
native `rcmail_utils::db_update(RC/SQL, 'roundcube', <ver>)` which handles the prefix itself — but its bootstrap
(`bin/.bootstrap.php`) is **CLI-only** (`exit if PHP_SAPI != 'cli'`) and Epesi's RC `config.inc.php` has auth `die()`
logic, so calling it from a *web* upgrade patch needs a careful minimal RC bootstrap. Idempotent either way (guarded
by the stored version). **Core Epesi 7.4→8.2 upgrade itself is validated** (patches ran, login + app work); this is
the RC-webmail piece. Also noted during the test: `config.php` hard-codes `define('EPESI_URL', …)` (from
`setup.php` write_config) — when *moving/cloning* an instance to a new URL you must update it (or delete the line to
auto-detect); a real *in-place* upgrade keeps the same URL so this doesn't arise.

---

### §59 — pre-public: premium/custom-module upgrade GATE (Signal-1 BUILT, pending validation; manifest TODO) (2026-07-08)

**Why.** The PHP 8.2 upgrade is all-or-nothing per instance: you can't run old 7.4 premium-module code
alongside the new 8.2 Core (one PHP version). So a client who has premium/custom modules (e.g. Timesheet,
Premium_Projects) and self-upgrades Core will either (a) *lose* those modules from the UI if they deployed
Core-only (code gone; **data is safe** in the DB), or (b) hit fatals if they kept the old 7.4 module code
(`each()` etc.). Either way → support tickets. The distribution plan is therefore: **public release
(SourceForge/GitHub/Softaculous/Store) = new installs + Core-only instances**; **existing premium clients =
coordinated migration (Core + their premium modules together), as a service, NOT self-serve**. This gate is
the technical safety net so a premium client can't break their instance by accident.

**Design (like §58 — build deliberately, on hardening, before public).** A pre-upgrade check that lists
installed modules that are **not part of this Core build**, and warns/blocks with a clear message + the list
("These modules aren't in this release: … — upgrading breaks them until they're migrated to PHP 8.2. Contact
your provider."). Two detection signals:
- **Code-missing (orphaned), no manifest needed:** every module in the `modules` table (`SELECT name FROM
  modules`) whose code dir is absent — check via `ModuleManager::get_module_dir_path($name)` +
  `file_exists('modules/'.$path.'/'.<file>.'Install.php')` (the pattern already at `include/module_manager.php:334`).
  Catches scenario (a).
- **Non-Core (needs a shipped Core manifest):** a build-time-generated list of Core module names (e.g.
  `include/core_modules.php`); any installed module not in it = premium/custom. Catches (a) **and** (b)
  (old code kept). More robust; the manifest is the extra piece to generate at release.
- **Hook:** `update.php` in `EpesiUpdate::run()` before `perform_update_patches()` (the run() flow is at
  `update.php:~298`) → show the gate + require an explicit "I understand, proceed" (so an informed admin can
  override, but nobody breaks it by accident); and mirror it as an advisory line in `check.php`.
- **Reuse:** `SELECT name FROM modules ORDER BY priority` (`module_manager.php:153`),
  `ModuleManager::get_module_dir_path()` (`:272`), the Install.php-existence pattern (`:334`).
Not built yet — recorded as a pre-public deliverable (alongside the check.php 5-extension gate and the IMAP-Root
/ README docs).

**BUILT (2026-07-08, on `experiment/php8-hardening`) — Signal-1 (code-missing/orphaned), manifest-free:**
- **Detector:** `ModuleManager::get_orphaned_modules()` (`include/module_manager.php`, right after `exists()`).
  `SELECT name FROM modules`, keep each whose `ModuleManager::exists($name)` is false (no `<Module>Install.php`
  on disk). Reuses Epesi's own canonical existence check → no separate "official-modules" list needed.
- **Gate:** `EpesiUpdate::orphaned_modules_gate()` in `update.php`, called at the **top of `update_process()`**
  (before any `perform_update_patches`). **Fails open** — empty list (normal Core-only instance) is a complete
  no-op, so ordinary upgrades are untouched; only instances that actually have orphaned modules hit the new code.
  Browser: warning page listing the modules + "Your data is not deleted — it stays in the DB… contact your
  provider" + an explicit **"I understand — continue anyway"** link (`?confirm_orphaned=1`; sets a session flag,
  reloads clean). CLI: prints the same warning but **proceeds** (expert/automated context — never trap a script).
  Guarded by `method_exists('ModuleManager','get_orphaned_modules')` so the script can still update an older
  codebase that lacks the helper.
- **Advisory:** read-only list in `check.php` (yellow "Code missing" rows) — blocks nothing; informational.
- **VALIDATION (before ff → main, §58 discipline — DB/upgrade path, never ship untested):** run on the **DA
  premium upgrade instance** (it has real premium modules, e.g. Timesheet, whose code isn't in this Core build):
  (1) `check.php` → the premium modules appear under "Additional modules / Code missing"; (2) `update.php` (or a
  version-differing upgrade) → the gate page lists them and stops before patches; clicking "continue anyway"
  proceeds normally; (3) a Core-only instance (e.g. cPanel fresh) shows an empty list → gate is a silent no-op,
  `check.php` shows nothing extra. Then ff `main`.

**Still TODO (Signal-2, the manifest):** a build-time `include/core_modules.php` (list of official Core module
names) to also flag modules whose code IS present but isn't official Core (scenario (b) — old 7.4 premium code
kept). Lower priority: that case fails visibly on its own (PHP-8 fatals), whereas Signal-1 (silent disappearance)
is the quiet data-safety risk this gate primarily addresses.

---

### §60 — pre-public: consolidate outdated URLs to one current link (TODO) (2026-07-08)

**Ask (Karina, 2026-07-08):** replace all outdated links — `telaxus.com`, `epe.si`, `epesibim.com` — with **one
current link**. **Target URL still TBD** (likely `epesi.org` — the product site already used in the footers'
"epesi-powered" image link; confirm with Karina/Jasiek before doing it).

**NOT a blind find-replace** — the scan (own code, excl. vendor/ + RC bundle) splits into categories that must be
handled differently:
- **`@author …@telaxus.com` e-mails — 471 files. LEAVE.** Historical authorship (pbukowski@, abisaga@, etc.), same
  rule as Georgi Hristov's attribution (§ copyright work). Not links.
- **Marketing links → swap to the canonical URL:** `http://www.telaxus.com` (~19 files, e.g. the footers already
  changed in the copyright work), `http://epe.si` / `www.epe.si` / `epe.si/donate`, `http://www.epesibim.com`.
- **⚠️ SERVICE endpoints → do NOT point at a marketing page; map to the CURRENT live host or they break:**
  - `http://ess.epe.si/update.json` — the **auto-update / EPESI Store check endpoint** (swapping this to a
    marketing URL breaks update.php's package check).
  - `https://ess.epe.si/` , `ess.epe.si/payments/` , `ess.epe.si/invoice/` , `ess.epesibim.com/` — EPESI Store /
    subscription / payments service.
  - `http://forum.epesibim.com` (community forum), `http://translate.epesibim.com` (translation server).
  Each of these needs its **actual current host** confirmed, not the marketing link.
- **Dead manual images:** `http://www.epesibim.com/manual/images/*.png` (in help content) — old manual host,
  probably 404 now → replace with current docs or drop.

**Where the constants live (for the service/store ones):** the ESS/store base is defined centrally (EssClient /
EpesiStore config) — change it in one place, not per-file. Confirm before public release. Recorded as a pre-public
deliverable alongside §59, the check.php 5-ext gate, and the IMAP-Root / README docs.

---

### §61 — widen IP-address columns C(32) → C(45) for full IPv6 (2026-07-16)

**Why (Jasiek).** `base_login_audit.ip_address` was `C(32)` — too short for an IPv6 address (max textual form
`::ffff:255.255.255.255` = 45 chars), so IPv6 client addresses were silently truncated. `get_client_ip_address()`
([include/misc.php:333](include/misc.php)) already returns the full address; the narrow column was the only place
it was lost. Systemic — the same `C(32)` truncation existed in **3 IP columns across 3 core tables**.

**Fix — per table: the fresh-install schema line + a one-line ALTER patch** (mirrors the module's own precedent
`modules/CRM/LoginAudit/patches/20170123_extend_hostname_length.php`; `PatchUtil::db_alter_column` is guarded —
no-op if the column is absent, idempotent, re-set-to-C(45) on re-run):

| Table | Column | Install.php line → C(45) | New ALTER patch |
|---|---|---|---|
| `base_login_audit` | `ip_address` | LoginAuditInstall.php:17 | CRM/LoginAudit/patches/20260716_extend_ip_length.php |
| `user_login_ban` | `from_addr` | LoginInstall.php:28 | Base/User/Login/patches/20260716_extend_ip_length.php |
| `utils_filestorage_access` | `ip_address` | FileStorageInstall.php:67 | Utils/FileStorage/patches/20260716_extend_ip_length.php |

`host_name` columns left alone (already `C(255)`/`C(64)`). The historical **applied** patch
`Utils/FileStorage/patches/20170419_create_remote_and_access.php` still shows `C(32)` for that column but is
intentionally NOT edited (editing an applied patch is a no-op for existing DBs; the new ALTER patch + Install.php
fix cover both fresh and upgrade paths).

**Verify:** after `update.php`/runpatches, `SHOW COLUMNS` shows each IP column as `varchar(45)`; a 45-char IPv6
stores whole (no truncation); fresh install creates the tables at `C(45)`; re-running the patches is a no-op.
**STATUS: code done on `experiment/php8-hardening`; pending in-app verification (login from IPv6 / SHOW COLUMNS).**

---

### §62 — Notes/CKEditor: three stacked PHP 8 bugs killed rich-text editing + image paste entirely (2026-07-23)

**Why (Jasiek, local hardening testing).** Reported as "CKEditor doesn't load in Notes (Utils_Attachment)"; after
the first fix, "paste stopped saving multiple images." Turned out to be three independent, stacked bugs —
CKEditor had never actually worked end-to-end in this migrated codebase until all three were found, since the
constructor bug alone silently degraded the editor to a plain `<textarea>` (no JS error, nothing to notice).

**Bug 1 — dead PHP4-style constructor.** [modules/Libs/CKEditor/ckeditor.php:7](modules/Libs/CKEditor/ckeditor.php)
declared `function HTML_QuickForm_ckeditor(...)` (class-name method) with no `__construct`. Same bug family as
11.4/12.2/19 (`Renderer/TCMSDefault.php`, `TCMSArray.php`, `TCMSArraySmarty.php`,
`FieldTypes/{autoselect,automulti,autocomplete,multiselect}.php`, `datepicker.php`, `timestamp.php`,
`currency.php`, `quickform_crits.php`) — `ckeditor.php` was simply missed back then. On PHP 8, object creation
silently fell through to the parent's real `__construct()`, so the `load_js()` calls for `ckeditor.js`/`ck.js`
never ran. Fix: renamed to `__construct()`, inner call → `parent::__construct(...)` — identical pattern to the
others.

**Bug 2 — HtmlPurifier stripping pasted images.** Once the editor actually loaded, pasting a clipboard image
produces a `data:` URI `<img>` (plain browser contenteditable paste — this CKEditor 4 build has no custom
upload/plugin.js in `plugins/clipboard/`, confirmed by grep).
[modules/Utils/SafeHtml/HtmlPurifier.php](modules/Utils/SafeHtml/HtmlPurifier.php), called on every note display
via [AttachmentCommon_0.php:273](modules/Utils/Attachment/AttachmentCommon_0.php), used
`HTMLPurifier_Config::createDefault()` with zero customization; HTMLPurifier's default `URI.AllowedSchemes`
excludes `data:` (XSS/phishing hardening), so the whole `<img>` was silently dropped on render. `git log -L`
confirms both the class and the `AttachmentCommon_0.php` call site are unchanged since the vanilla 1.9.1
baseline — not a migration regression, just unreachable until Bug 1 was fixed. **Decision (Jasiek):** allow
`data:` globally in this purifier instance rather than build a proper paste→file-attachment pipeline; accepted
tradeoff — any URI-bearing attribute through this purifier (not just `img src`) can now carry a `data:` URI, low
real-world risk for an internal CRM.

**Bug 3 — note column silently truncated.** RecordBrowser's `'long text'` field type maps to ADODB meta-type
`'X'` ([RecordBrowserCommon_0.php:1121](modules/Utils/RecordBrowser/RecordBrowserCommon_0.php)) →
`ActualType('X')` = plain MySQL **`TEXT`** (64KB cap), not `LONGTEXT`, despite the field-type's name. Base64
inflates ~33% over binary size, so two-plus pasted images routinely exceeded 64KB combined; with `sql_mode`
lacking `STRICT_TRANS_TABLES` (see MySQL tuning, same session), MySQL/MariaDB **silently truncated** the excess
on save instead of erroring — exact match for "first image saves, second is gone." Fix — scoped to this one
field only, NOT the global `'long text'`→`X` mapping (used by many other modules, out of scope here):
[modules/Utils/Attachment/patches/20260723_note_longtext.php](modules/Utils/Attachment/patches/20260723_note_longtext.php)
widens `utils_attachment_data_1.f_note` from `TEXT` to `LONGTEXT` via `DB::dict()->alterColumnSQL(...)`
(non-destructive; confirmed via `SHOW CREATE TABLE` before/after).

**Verify:** CKEditor loads in Notes; single and multiple pasted clipboard images both save and redisplay
correctly. **STATUS: fixed and verified working on `experiment/php8-hardening` (commit `d880cb43`).**

---

### §63 — Login Audit: cache users/contacts instead of per-row lookups (2026-07-24)

**Why (Jasiek).** `CRM_LoginAudit::admin()` ([modules/CRM/LoginAudit/LoginAudit_0.php](modules/CRM/LoginAudit/LoginAudit_0.php))
rendered each displayed audit-log row by calling `CRM_ContactsCommon::get_contact_by_user_id()` (itself 2 queries:
`Utils_RecordBrowserCommon::get_id('contact','login',$uid)` + `get_contact($cid)`) and the uncached
`Base_UserCommon::get_user_login()` — up to 3 queries per row, so a page of N rows with N distinct users ran up to
3N queries just to resolve login/contact names. Not a SQL JOIN in the code, but functionally the same N+1 pattern,
done row-by-row in PHP instead.

**Fix.** Build two lookup caches once, before the row loop, instead of per-row: `$logins` (all `user_login` rows,
one query) and `$contacts` (all contacts with a linked login, one query — reusing the exact batched-fetch pattern
the file already used for its filter dropdown, `CRM_ContactsCommon::get_contacts(array('!login'=>''))`). The
row-rendering loop now does plain array lookups (`$logins[$uid_num]`, `$contacts[$uid_num]`) instead of querying
per row. The now-redundant duplicate query inside the dropdown's "few users" branch was removed — dropdown and
row rendering share the same cache. One narrow fallback to `Base_UserCommon::get_user_login()` is kept for the
edge case of an audit row whose `user_login` was since deleted (not in the cache) — same graceful degradation as
before, just no longer the common path.

**Result:** audit log page load goes from **O(rows displayed)** queries down to **2 fixed queries**, regardless
of page size or how many distinct users appear in the log.

**Verify:** Login Audit page displays correctly (logins, contact names, Duration) both filtered to a single user
and unfiltered; filter dropdown/autocomplete still populates correctly. **STATUS: fixed and verified working on
`experiment/php8-hardening`.**

---

### §64 — "Invalid date - clearing" popup on optional, untouched date fields (2026-07-24)

**Why (Jasiek).** Creating a contact triggered a JS `alert('Invalid date - clearing')` just from clicking into (or
tabbing through) the optional "Birth Date" field without typing anything — no server round-trip involved.
Initially suspected the PHP 8 migration (`strftime()` is deprecated since 8.1, and
[modules/Base/RegionalSettings/RegionalSettingsCommon_0.php:194](modules/Base/RegionalSettings/RegionalSettingsCommon_0.php)
calls it directly), but that was ruled out empirically: `error.php` already excludes `E_DEPRECATED` from
`error_reporting()` project-wide, and testing `Base_RegionalSettingsCommon::time2reg()` with a real authenticated
user (not a CLI/no-session artifact, which misleadingly returns `null`) confirmed it correctly returns e.g.
`'07/24/2026'`. The backend date formatting is fine.

**Real root cause — pure client-side, pre-existing (not migration-related).**
[modules/Utils/PopupCalendar/datepicker.js](modules/Utils/PopupCalendar/datepicker.js)'s `format2regexp()` builds a
validation regex from the configured date format (`%m/%d/%Y` default) by recursively wrapping *separators* in
optional groups so partial typing doesn't fail immediately — but tracing its regex transforms by hand shows it
only ever wraps the **last** separator (before the year) in an optional group; the first separator (between month
and day) stays a mandatory literal `/` in the final compiled regex: `^[0-1]?[0-9]?/[0-3]?[0-9]?(/[0-9]{0,4})?$`.
That pattern requires at least one `/` character, so an **empty** value fails it. Since Birth Date is optional and
normally left blank, simply focusing then blurring it (`validate_blur` in the same file) with no input fires this
regex against an empty string → fails → the alert, even though the user never intended to set a date.

**Fix:** wrap the whole compiled pattern in an optional group so an empty/untouched field always passes —
`init_re()` changed from `'^'+this.format2regexp(f)+'$'` to `'^('+this.format2regexp(f)+')?$'`. Minimal, one-line,
doesn't touch the separator-optionality logic itself. Verified: alert no longer fires on an untouched Birth Date
field; typing an actual valid date still validates correctly.

**STATUS: fixed and verified working on `experiment/php8-hardening`.**

---

### §65 — RC: auto-archive-on-send default OFF + Elastic toggle-icon state (2026-07-25)

**Why (Jasiek).** Two related issues with the "Archive this message after sending" feature in the mail composer:
1. **Auto-archiving of SENT mail was ON by default** — Jasiek judged that wrong; it should be opt-in.
2. The compose **toggle icon didn't reflect its state** (no colour change on click) — in the previous Epesi the icon
   was coloured when enabled, grey when disabled.

**Fix 1 — default OFF.** [modules/CRM/Mail/MailCommon_0.php:49](modules/CRM/Mail/MailCommon_0.php) `submit_account()`
set `$param['archive_on_sending']=1` on account **adding** (the only place this default is set — verified repo-wide).
Changed to `0`. Pure CODE fix → applies on upgrade automatically; **no data patch** (Karina's call: existing accounts
keep their value; users can uncheck manually; `use_epesi_archive_directories` stays `1`). The feature, the manual
Archive button and the archive folders are untouched — only the per-account default flips to opt-in.

**Fix 2 — toggle icon state (Elastic).** The active RC skin is **elastic** (`RC/config/config.inc.php:150`), but the
plugin only shipped `larry`/`classic` skins, and `archive.js` toggled the state by swapping an `<img>.src`
(`archive_pas.png`↔`archive_act.png`) — the button is `type=>'link'` → an `<a>` with no `.src`, so the swap was a
no-op. Reworked to a **CSS-class toggle** (one icon, no second image):
- `RC/plugins/epesi_archive/archive.js` — `rcmail_epesi_auto_archive()` now toggles the `pressed` class on
  `#epesi_auto_archive_button` (via jQuery) and POSTs the new state.
- NEW `RC/plugins/epesi_archive/skins/elastic/archive.css` — OFF = `opacity:.35 + grayscale`; `.pressed` = full
  colour. Server-side seed of the `pressed` class (`epesi_archive.php` compose button) already sets the initial state.
- `RC/plugins/epesi_archive/epesi_archive.php` — `include_stylesheet(local_skin_path().'/archive.css')` on compose.
- **Follow-up:** the first toggle version kept the old `rcmail.set_busy(true,'loading')` + passed the msgid as the
  `http_post` lock. The server toggle branch just sets the session and `return`s (no AJAX payload to release the
  lock), so **rapid toggling stacked locks and hung on "loading"**. Fixed by making it fire-and-forget (drop
  `set_busy`/lock) — the toggle only needs to persist the session state; the class is toggled client-side already.

**STATUS: default-OFF done; toggle-icon (state + rapid-toggle) verified working on `experiment/php8-hardening`.**

---

### §66 — `FORCE_CACHE_COMMON_FILES`: combined-common-file generator broken by the no-closing-tag convention (2026-08-13)

**Why (Jasiek).** Investigating dashboard/page-load performance, Jasiek enabled `FORCE_CACHE_COMMON_FILES` — an
existing (default-OFF) perf toggle that concatenates every installed module's `*Common_X.php` into one
pre-built `data/cache/common.php`, so `ModuleManager::load_modules()` does one `require_once` instead of a
`stat()`+`open()` per module (128 files / ~1.1MB of source in this install — measured ~15ms/request of raw
file-I/O overhead saved). Turning it on immediately broke **every** request that reaches
`ModuleManager::load_modules()` — i.e. nearly the whole app (`process.php`, `ajax.php`-style refresh endpoints,
`indexer.php`, etc.) — with `ParseError: syntax error, unexpected token "<", expecting end of file` in the
generated `data/cache/common.php`.

**Root cause.** `ModuleManager::create_common_cache()`
([include/module_manager.php:949](include/module_manager.php)) builds the combined file by looping over every
installed module and doing `$ret .= file_get_contents($file_url)` (the module's raw Common-file source)
followed by `$ret .= '<?php $x = ...; ... ?>'` (a small trailer that instantiates the Common singleton). This
assumes every source file's content **ends with a closing `?>` tag** — true when this generator was written,
no longer true after the PHP 8 modernization: files in this codebase now conventionally omit the closing tag
(standard PSR-2-ish practice, avoids stray-whitespace/header bugs). With the closing tag gone, the *next*
appended segment's literal `<?php` lands **inside the still-open PHP block** from the previous file — not a
new open tag, just the four characters `<`, `?`, `p`, `h`, `p` as tokens — and PHP chokes on the stray `<`.
Since `FORCE_CACHE_COMMON_FILES` defaults OFF and (per repo history) nobody had exercised this path since the
close-tag convention changed, this has apparently been silently broken for any install that turns it on,
unnoticed until now.

**Fix.** Force-close before each trailer: `$ret .= '?><?php $x = ...'` instead of `'<?php $x = ...'`
([include/module_manager.php:961-965](include/module_manager.php)). Safe in both cases — if the file already
closed itself, the extra `?>` just emits harmless literal text (silently swallowed: `load_modules()` wraps the
`require_once` of this cache file in `ob_start()`/`ob_end_clean()`); if it didn't, `?>` acts as PHP's implicit
statement terminator (same rule that lets a file end mid-statement with no trailing `;`), then reopens cleanly
for the trailer. **Meta note:** the first attempt at this fix documented the change with a `//` line comment
that itself contained the literal text `` `?>` `` — which reproduced the *exact same bug*, since a `//`/`#`
comment in PHP is terminated by `?>` wherever it appears, not just by the newline. Switched to a `/* */` block
comment phrased without literal tag syntax.

**Upgrade-gap note:** pure code fix in a core include, reaches every install on update — no DB patch needed.
But `data/cache/common.php` is a **stale on-disk artifact**, not stored business data: `create_common_cache()`
only (re)runs when the file doesn't already exist, so any install that already hit this bug needs its broken
cache file deleted once (`data/cache/common.php`) after updating, or it'll keep loading the old broken version
forever. Not worth a formal patch (purely a rebuildable cache directory) — noted here instead.

**Verify:** regenerated `data/cache/common.php` (1.26MB) lints clean; full page load + AJAX round-trip
(`process.php`, `Utils/RecordBrowser/indexer.php`, `Utils/Messenger/refresh.php`, `Base/Notify/refresh.php`)
all verified 200 with zero console errors via a real browser, with `FORCE_CACHE_COMMON_FILES=1` live.
**STATUS: fixed and verified working.**

---

### §67 — `JSMin` space-collapse bug broke the entire shared JS bundle (`+ ++x` → `+++x`) (2026-08-13)

**Why (Jasiek).** Immediately after §66, Chrome incognito showed a blank white page (non-incognito windows
loaded fine, masking this via aggressive `serve.php` caching — see below). Console showed
`serve.php:9 Uncaught SyntaxError: Invalid left-hand side expression in postfix operation`, then
`Uncaught ReferenceError: Epesi is not defined` — the second error is just fallout from the first: the
combined `serve.php` JS bundle (`jquery` + `jquery-migrate` + `jquery-ui` + `HistoryKeeper` + `include/epesi.js`)
failed to parse at all, so `window.Epesi` (defined inside it) never existed by the time `init_js.php`'s
`Epesi.init(...)` ran.

**Root cause.** `MINIFY_SOURCES` (on in this install's `data/config.php`, independent of `FORCE_CACHE_COMMON_FILES`)
runs the combined bundle through `libs/minify/JSMin.php`
([Minify/Controller/Base.php:74](libs/minify/Minify/Controller/Base.php) wires `Minify::TYPE_JS` to
`JSMin::minify`) — a straight PHP port of Crockford's classic char-by-char jsmin.c. jQuery UI's
`uniqueId()` contains `this.id="ui-id-"+ ++n` — string concat (`+`) with a pre-incremented `n` (`++n`), the
space required to keep `+` and `++` as separate tokens. JSMin's minifier has a **long-standing, well-known
limitation** here: its "collapse a trailing space" shortcut
([JSMin.php, the `elseif (! $this->isAlphaNum($this->a))` branch](libs/minify/JSMin.php)) decides to drop a
space based only on the token *before* it, without any lookahead to what follows — so it collapsed
`+ ++n` to `+++n`. Retokenized, `"ui-id-"+++n` is `"ui-id-"`, `++`, `+n` — a postfix `++` applied to a
string literal, which V8 rejects outright as `Invalid left-hand side expression in postfix operation`.
Confirmed the vendored `libs/jquery-ui-1.10.1.custom.min.js` source is correct as shipped (has the space);
JSMin introduces the bug at request time, only when (re-)minifying.

**Why it surfaced now, not earlier:** `serve.php` sends `Cache-Control: max-age=31536000` and Minify has its
own on-disk cache (`data/cache/minify/`) keyed off the request's minify options — today's `MINIFY_ENCODE`
toggling (§ this session) forced a fresh minification pass that (deterministically) hit this bug for the
first time in this install; a browser profile that already had this exact bundle URL cached from before kept
loading the old, unaffected copy, which is why non-incognito windows (and this session's own curl/Playwright
checks, which never actually parsed the JS as JS) appeared fine while a cache-free incognito window didn't.

**Fix.** Two coordinated changes in `JSMin::min()`'s state machine ([libs/minify/JSMin.php](libs/minify/JSMin.php)):
1. The `$this->a === ' '` branch (deciding whether to drop a space that's already `$a`) now also checks
   whether the last emitted output char and the upcoming `$b` are both `+` or both `-`, and keeps the space
   if so.
2. That alone wasn't sufficient — the *actual* code path that ate this specific space was a different
   shortcut (`elseif (! $this->isAlphaNum($this->a))`, when `$a` is punctuation and `$b` is a space): it
   skips the space via lookahead **before ever seeing what follows it**, without promoting the space to `$a`
   at all, bypassing fix (1) entirely. Excluded `$a === '+'`/`'-'` from that shortcut so the space is instead
   handled character-by-character through fix (1), which can see far enough ahead to decide correctly.
   Traced and confirmed both changes together are necessary via an isolated `JSMin::minify()` repro before
   touching the live bundle. **Tradeoff:** conservative by design — also keeps the space in the ordinary safe
   case `a + b` → `a+ b` (a few bytes less compression), since a true fix would need two-character lookahead
   past the space, a larger change to this state machine than justified for a size-only optimization.

**Not done:** this codebase already vendors a structurally better minifier, unused —
`libs/minify/JSMinPlus.php`, a real JS parser (ported from Mozilla's Narcissus engine) where this whole bug
*class* can't occur, since it emits from parsed tokens rather than character adjacency. Same static
`::minify()` interface, so rebinding `Minify::TYPE_JS` to it is mechanically a one-line change — deferred as
a separate, properly-tested follow-up (stricter parser, needs a broad regression pass across every JS file the
app serves before trusting it as the default) rather than folded into this incident fix. Tracked in
[AI-shared/TODO.md](../AI-shared/TODO.md).

**Verify:** isolated `JSMin::minify()` repro confirms `+ ++n`, `- --b`, and plain `a+b`/`a + b` all round-trip
correctly; regenerated `data/cache/minify/*` bundle passes `node --check`; full page load verified clean
(zero console errors, all requests 200) in a fresh/incognito-equivalent browser context.
**STATUS: fixed and verified working.**

---

### §68 — MySQL `utf8` → `utf8mb4`: 4-byte characters (emoji) rejected/mangled on save (2026-08-14)

**Why (Jasiek, relayed from another session).** A note containing an emoji silently failed to save. Traced to
[include/database.php:58](include/database.php) hardcoding `SET NAMES "utf8"` on every MySQL connection.
MySQL's `utf8` type is a legacy alias capped at 3 bytes/char; most emoji need 4 bytes (`utf8mb4`), so sending
one over a connection declared `utf8` gets rejected or mangled by the driver before it ever reaches a column.

**Root cause, part 1 — code defaults.** Three call sites hardcoded the 3-byte charset, all fixed to `utf8mb4`/
`utf8mb4_unicode_ci`:
- [include/database.php:58](include/database.php) — `DB::Connect()`'s `SET NAMES`, applied to every connection.
- [include/database.php:113](include/database.php) — `DB::CreateTable()`'s MySQL default (`DEFAULT CHARACTER
  SET ...`), used by every module's `*Install.php` and by any upgrade patch that creates a new table.
- [setup.php:356](setup.php) and [setup.php:692](setup.php) — fresh-install `CREATE DATABASE`/`ALTER DATABASE`.

These three are pure code, no stored data touched — they only change what happens for connections/tables
created *after* the fix, so they were applied directly rather than gated behind a patch.

**Root cause, part 2 — existing data.** Fixing the connection/creation defaults doesn't touch tables created
before the fix. A read-only `information_schema` audit of this dev DB (whose own schema default was already
`utf8mb4`, from some earlier manual change) found **413 tables / 815 columns** still physically stored as
`utf8`/`utf8_unicode_ci` — essentially the whole schema (Contacts, Meetings, Tasks, Notes/dashboard settings,
RecordBrowser data tables, Roundcube's `rc_*` tables, etc.) — still rejecting 4-byte characters regardless of
the part-1 fix. This is expected to be the norm on any pre-existing install, not a quirk of this one DB.

**Fix, part 2 — upgrade patch.** New patch
[modules/Base/patches/20260814_utf8mb4_migration.php](modules/Base/patches/20260814_utf8mb4_migration.php):
`ALTER DATABASE` to `utf8mb4`, then walks every table returned by
`information_schema.TABLES WHERE TABLE_COLLATION NOT LIKE 'utf8mb4%'` and runs `CONVERT TO CHARACTER SET
utf8mb4 COLLATE utf8mb4_unicode_ci` (also forcing `ROW_FORMAT=DYNAMIC` first, defensively, so an indexed
`VARCHAR(255)` column doesn't hit the old 767-byte InnoDB index-prefix limit on older MySQL — moot on this
dev DB, whose MariaDB 10.4 already defaults every table to `ROW_FORMAT=Dynamic`, but not guaranteed on every
install this patch has to serve). Lives in `Base/patches/` (not any one table's owning module) because Base
is the only module always installed, so it's the only `patches/` dir `PatchUtil::list_patches()` is
guaranteed to scan — and because the migration is genuinely cross-module, not owned by any single one.
Idempotent (re-queries `information_schema` fresh each run, so an already-converted table just drops out of
the list) and resumable (`Patch::require_time()` between tables lets a slow/large install spread the work
across multiple `runpatches.php`/cron invocations instead of timing out mid-run); a failure on one table is
logged via `error_log()` and skipped rather than aborting the rest.

**Deliberately not run automatically:** unlike the part-1 code fix, this patch was written but left for Jasiek
to run (via the normal patch/update flow) on his own schedule, ideally after a backup — an `ALTER TABLE ...
CONVERT TO CHARACTER SET` across ~400 tables is a real, if low-risk, live-data operation on an existing install,
not a pure code change.

**Verify:** read-only audit query (`information_schema.TABLES`/`COLUMNS` filtered to non-`utf8mb4%`) confirmed
the 413/815 figures above before writing the patch; the patch's own table-selection query was independently
re-run read-only and returned the identical 413-table list. Post-run verification (re-running the same audit
expecting zero rows, and confirming an emoji actually saves) is still open.
**STATUS: code fix (part 1) done; migration patch (part 2) written, not yet run against this DB.**

---

### §69 — §58's RC schema-migration patch never actually ran: wrong glob path (2026-08-14)

**Symptom:** user reported "Opening Mail module generates an error" (generic Epesi error page).
`data/CRM_Roundcube/log/errors.log` had the exact §58 signature recurring within the last few
minutes: `DB Error: [1054] Unknown column 'expires_at' in 'field list'` on `rc_session` queries —
i.e. the bug §58 claimed to have resolved on 2026-07-08 was still live on this dev DB on
2026-08-14, despite `data/logs/patches.log` showing
`[modules/CRM/Roundcube/patches/20260708_rc_schema_migrate.php] ... SUCCESS` from the day before
(2026-08-13 16:20:16). Direct `DESCRIBE rc_session` confirmed the table still only has
`sess_id`/`created`/`changed`/`ip`/`vars` — the pre-2025 schema — even though `rc_system.roundcube-
version` read `2025092300` (the target §58 was supposed to reach).

**Root cause:** §58's patch (`modules/CRM/Roundcube/patches/20260708_rc_schema_migrate.php:49`)
globs `modules/CRM/Roundcube/RC/SQL/mysql/*.sql`. That directory has **never existed** — the
Roundcube vendor tree (including its 35 SQL migration files) lives at
`modules/Libs/RoundCube/RC/SQL/`, exactly where `CRM_RoundcubeInstall::install()` already reads
`mysql.initial.sql`/`postgres.initial.sql` from for a fresh install. `glob()` on the wrong path
silently returns `array()` (no warning, no exception), so the migration `foreach` ran **zero**
files — but the patch's last line unconditionally runs
`UPDATE rc_system SET value='2025092300' WHERE name='roundcube-version'` regardless of whether any
migration actually happened. Net effect: the patch always "succeeds" and always marks the schema
as migrated, without ever touching it. This makes the §58 write-up's claimed validation ("`rc_session
.expires_at` now exists ... on Karina's real DA upgrade instance") suspect — either that check
wasn't actually re-run after the marker update, or it was inferred from the absence of errors
rather than confirmed directly; worth re-verifying there too.

**Why a plain edit of the 2026-07-08 file won't fix this:** patches are identified by filepath —
editing `20260708_rc_schema_migrate.php` is a silent no-op on every instance that already "ran" it
(this dev DB included), because `runpatches.php`/`PatchUtil::apply_new()` only apply patch files
it hasn't seen before. Worse, the broken run already bumped `rc_system.roundcube-version` to the
target on those instances, so a corrected patch can't trust that marker either — it would see
`current >= target` and skip on the very installs that need it most.

**Fix:** new patch
[modules/CRM/Roundcube/patches/20260814_rc_schema_migrate_fix.php](modules/CRM/Roundcube/patches/20260814_rc_schema_migrate_fix.php),
same migration logic as §58 but: (1) globs the correct `modules/Libs/RoundCube/RC/SQL/<mysql|
postgres>/*.sql` path (also fixing a second latent bug — §58 hardcoded the `mysql` subdirectory
regardless of driver, which would have broken Postgres installs too); (2) ignores the
`rc_system.roundcube-version` marker as the "already done" signal and instead checks for
`rc_session.expires_at` directly via `DB::MetaColumns()` — the concrete column the 2025092300
migration adds — since that marker is now known-unreliable on any instance that ran the broken
§58 patch; falls back to the same `2015030800` floor §58 used when actually replaying migrations.
Idempotent either way (a fresh install or an instance where §58 somehow did work correctly just
sees `expires_at` already present and returns immediately).

**STATUS: applied and verified on this dev DB (2026-08-14 20:10).** Run directly via the `Patch`
class (not `PatchUtil::apply_new()`, which sweeps and runs *every* pending patch in date order —
that would have also triggered §68's utf8mb4 migration, deliberately left for Jasiek to run on
his own schedule) to keep the blast radius to this one patch. `patches.log` records `SUCCESS`;
`DESCRIBE rc_session` now shows `expires_at` (with its index) in place of the old `created`/
`changed` columns; re-running the exact query from the original error log
(`SELECT ... expires_at ... FROM rc_session WHERE sess_id = ...`) now executes cleanly instead of
throwing `Unknown column 'expires_at'`. Not yet confirmed by actually opening Mail in a browser —
no browser-automation tool was available in this session to do that end-to-end check.

---

### §70 — Premium/GeneralContractor: dead `install_default_theme()` calls removed (2026-08-21)

`Base_ThemeCommon::install_default_theme()`/`uninstall_default_theme()` were made no-ops back on
2026-07-31 when theme storage under `data/` was removed — themes are served straight from
`modules/` now with zero build step (see `AI-shared/deliberate-removals.md`'s "Theme/lang storage
under `data/`" entry). The no-ops were kept only so pre-existing call sites in core wouldn't fatal;
they were never meant to still be called from new/edited code.

`modules/Premium/GeneralContractor` — a separate, gitignored Premium repo, so not touched by that
core-only removal sweep — had 9 `*Install.php` files (`GeneralContractorInstall`, `ChangeOrders`,
`Planner`, `Tickets`, `ProgBilling`, `Activities`, `LiftEquipment`, `ShopEquipment`, `Visit`) still
opening `install()` with a call to `Base_ThemeCommon::install_default_theme($this->get_type())`.
Harmless (the call is a no-op) but pointless dead weight; removed all 9, no replacement needed.

**How to apply:** if another Premium/Custom module (any gitignored tree not swept by the core
migration) is found calling `install_default_theme()`/`uninstall_default_theme()`, remove the call
outright rather than looking for a replacement API — same treatment as the `Libs/ScriptAculoUs`
and `Base_LangCommon::install_translations()` dead-API removals hit in this same module (see
`modules/Premium/GeneralContractor/docs/required-premium-modules.md`). `console/Develop/
CreateModuleCommand.php`'s `install()` scaffold is already clean (just `return true;`), so newly
scaffolded modules won't reintroduce this.

---

### §71 — First real client cutover: `client-instance.example` converted from an ungit'd production install to `migration` (2026-08-22)

**Context:** `client-instance.example` is a real (non-dev), client-confidential Epesi 1.9.1
install, not a test box — under this same Windows/XAMPP setup, previously deployed by copying files with no
`.git` anywhere in the tree. Needed to become a working copy of `jtylek/epesi`'s `migration` branch
while preserving `data/` (795MB: `config.php`, uploads, cache, logs) and the 12 Premium modules
already installed, without a rename/downtime window (user's explicit choice — see next paragraph).

**Problem:** `git clone` refuses a non-empty target directory, and `git init` + fetch + `git
checkout migration` aborts on every core file it would touch ("untracked working tree files would
be overwritten by checkout") — the directory already had a full previous install's files at every
path the new repo tracks.

**Approach (offered as a 3-way choice; user picked "merge in-place" over a rename-and-swap or
asking-for-manual-approval on the rename):** clone the core repo into a sibling temp directory,
check out `migration` there, then `robocopy <temp> <live> /E /XD <temp>/data` — **no `/MIR`, no
`/PURGE`**. Plain `/E` overwrites any file that exists at the same relative path in the new repo,
adds anything new, but never deletes a file that only exists in the destination. `data/` was
excluded from the copy entirely and left 100% untouched (confirmed after: `data/config.php`'s DB
host/credentials survived unchanged). This is the general trick: robocopy's merge semantics do
exactly what `git checkout` refuses to — overwrite in place without deleting local-only files.
Robocopy's own exit codes 1–7 are informational ("files copied", "extras present", etc.), not
failures — only ≥8 is a real error; don't treat a non-zero `$LASTEXITCODE` from robocopy as failure
without checking which bit is set.

The `.git` directory from the temp clone was copied over too (robocopy doesn't special-case
dotdirs) — that's what actually turns the live directory into a real git working tree already on
`migration`, with no separate `git init`/remote/fetch ever run against the live path itself.

**Result:** `git status` in the live directory is clean against `migration` for everything the new
repo tracks, plus ~264 leftover untracked entries — all identified as harmless pre-migration cruft,
not data loss: old CKEditor files (superseded, see `ckeditor-to-quill-migration.md`), old bundled
libs now replaced by Composer equivalents (`modules/Libs/{QuickForm,OpenFlashChart,ScriptAculoUs,
PHPExcel}` vs. the new tracked `vendor/`), old testing-stack vendor packages (`codeception`/
`behat`/`phpunit` families — matches §51/§52's "removed the skeleton"), and old custom `Base/Lang`
overrides that predate the `data/Base_Lang/custom/` convention. None block the app; safe to clean
up later, not urgent.

**Premium modules:** same merge-in-place pattern applied per module (`modules/Premium/<Name>` ←
clone `jtylek/Premium-<Name>` to a temp dir, robocopy merge, drop the temp). All 12 modules this
instance had installed — Accounts, CaseManagement, ExchangeRate, Expenses, Invoice, KnowledgeBase,
MultipleAddresses, Payments, PrintTemplates, Projects, Timesheet, Vacation — resolved cleanly to a
same-named `Premium-*` repo on the first try (the naming convention in CLAUDE.md's "Environment
quirks" held with zero exceptions across all 12). 10 of 12 came out byte-identical to upstream
(`git status` clean); `CaseManagement` and `Projects` each had a handful of pre-existing untracked
leftovers (a `.bak` file, two old `theme/` dirs, one old patch file) — same harmless-leftover story
as core.

**Verification done:** PHP 8.2.12 (`php -v`); the 5 Windows/XAMPP-required extensions noted
elsewhere in this doc (`zip`/`gd`/`imap`/`pdo_mysql`/`intl`) were already all enabled on this
machine; `php -l` clean on all 5 bootstrap entrypoints (`index.php`/`include.php`/`process.php`/
`ajax.php`/`cron.php`); hitting `index.php` over HTTP 302-redirects through to `update.php`'s
admin-login gate with a clean AdminLTE-themed render and no PHP errors — exactly the expected
behavior per this file's "Bump `EPESI_VERSION` so `update.php` auto-runs on first load" upgrade
design, since this DB was restored from a pre-migration backup and is still on the old schema.

**Not done yet, deliberately:** logging into `update.php` and running the patch sweep against this
real DB — per this doc's own guidance ("a real mutating operation against the live DB ... be
careful running it outside a disposable environment") that's a one-way action against production
data for the user to trigger attended, not something to run automatically right after a code
merge. Also not done: a logged-in browser walkthrough (no browser-automation tool available this
session — same gap noted in §69).

**How to apply next time:** this merge-in-place / robocopy technique is the general answer for
"convert an existing, already-populated Epesi install into a git-tracked `migration`-branch
checkout without a rename/downtime window" — reusable for the next real client cutover, not just
this one instance.

---

### §72 — `client-instance.example` cutover cleanup: removed the ~264 pre-migration leftovers §71 left in place (2026-08-22)

**Context:** §71's robocopy merge deliberately never deletes anything — it only overwrites/adds, so
every file the old (pre-`migration`) install had that the new tree doesn't track was left behind as
untracked cruft (`git status` showed ~264 such entries after §71: 10 at repo root, the rest under
`modules/` and `vendor/`). §71 judged all of it harmless and left cleanup for later. This entry is
that cleanup, done in two passes at the user's request.

**Pass 1 — 10 root-level files**, removed individually after inspecting each one's actual content
(not just the filename) to confirm none were load-bearing:
- `epesi.407_66615.2026-08-13_14-55-44` / `epesi.407_67944.2025-10-17_17-33-44` — serialized-PHP
  **Softaculous auto-installer** bookkeeping (`softpath`/`softurl`/`softdb`/`softdomain` keys),
  recording two different original cPanel hosting paths this instance was once installed at. Pure
  installer metadata, unread by the app itself.
- `softperms.txt` (23.8k lines) / `softver.txt` (just `1.9.1`) — same Softaculous bookkeeping
  (file-permission manifest + version marker).
- `old.htaccess` — a manually-renamed-aside backup of a prior cPanel-generated `.htaccess`
  (https-redirect + PHP-handler directives); superseded by the new repo's tracked `.htaccess`.
- `codeception.yml` — the empty test-skeleton config; matches §51/§52 ("removed the skeleton") —
  the `migration` branch doesn't ship this file at all.
- `mobile.css` / `mobile.php` — the legacy standalone mobile site. Explicitly listed in
  `deliberate-removals.md` as removed on purpose — this is the "don't silently reintroduce it"
  warning cutting the other way: these were leftover remnants of the *already-removed* feature,
  correctly deleted, not a case of removing something that's still wanted.
- `index.html_` — an old renamed-aside placeholder (likely a disabled directory-index blocker from
  the original host). `install.txt` — one line pointing to `/docs`, superseded/duplicated there.

**Pass 2 — everything else, repo-wide**, via `git clean -fd` (not manual `rm`, once the 10 root
files were already handled individually): safe specifically because `data/*` and `modules/Premium/`
are `.gitignore`d, so `git clean` (which respects `.gitignore` — it only removes untracked-and-
**not**-ignored paths) structurally cannot touch either one, no matter how broad the invocation.
Confirmed no nested git repos existed among the untracked paths (`git clean -n` prints "would skip
... (git repository)" for those, and none appeared) before running for real. One quirk: the first
`git clean -fd` pass left exactly 3 directories behind
(`modules/Libs/PHPExcel/vendor/phpoffice/phpexcel/`, `vendor/codeception/`,
`vendor/ezyang/htmlpurifier/plugins/`) despite none containing a nested `.git` — re-running
`git clean -fd` targeted at just those 3 paths removed them on the second attempt with no error.
Root cause not investigated (didn't recur, low stakes) — **if it recurs, don't assume the leftover
is special; just re-run `git clean -fd` targeted at the specific stragglers.**

**What this cleaned up, by category** (all superseded-by-`migration`, confirmed via
`MIGRATION_NOTES.md`/`AI-shared` cross-references before deleting, not just left/right diffed):
old `admin/` panel files (images + `LangUp.php`/`PhpInfo.php`/`ThemeUp.php`/Patches CSS) replaced by
the new admin UI; `libs/prototype.js` (Prototype.js — see `legacy-js-migration.md`, fully removed
2026-08-06) + `libs/UiUIKit/` + `libs/adodb/` (old bundled copy, now `vendor/adodb/adodb-php`); the
old `modules/CRM/Roundcube/RC/` webmail vendor tree (relocated to `modules/Libs/RoundCube/RC/` — see
§69) + its old `.htaccess`; `modules/Libs/CKEditor/{ckeditor/,ck.js,...}` (superseded by Quill, see
`ckeditor-to-quill-migration.md` — the 2 *inert wrapper files* it deliberately kept were already
part of the tracked tree and untouched by this cleanup); `modules/Libs/{QuickForm,OpenFlashChart,
ScriptAculoUs,PHPExcel/vendor/...}` (old hand-vendored copies, now Composer packages under the
tracked `vendor/`); the empty root `tests/` skeleton; and a long tail of `vendor/<pkg>/...` stragglers
— old dev/test-only packages no longer in the dependency set at all (`behat`, `codeception`,
`phpunit`, `phpspec`, `moneyphp`, `psy/psysh`, etc. — matches §51/§52/§53's dependency-audit trail),
plus extra files inside packages that *are* still tracked but pinned to a different version now
(old `CHANGELOG.md`/`Tests/`/`.travis.yml`/etc. from a prior install of e.g. `nikic/php-parser`,
`symfony/*`, `guzzlehttp/*`, `ezyang/htmlpurifier` that the currently-tracked version doesn't ship).
Also swept: assorted per-module leftover theme/JS/mobile files (`Utils/Attachment`, `Utils/Tray`,
`Utils/GenericBrowser`, `Utils/RecordBrowser` `.orig`/`.jt` backup files, `Base/Lang` flag images no
longer part of the tracked language set, `Base/Search`, `Applets/MonthView`, `CRM/Mail` templates)
and one empty leftover `cgi-bin/` from the original cPanel host (git doesn't list empty untracked
dirs in `git status`, only `git clean`, so it wasn't in §71's ~264 count but was swept here anyway).

**Result:** `git status` on `client-instance.example` is now fully clean against `migration` — zero
untracked entries anywhere in the tree, only the two `AI-shared/*.md` edits (§71 + this entry)
show as modified. `data/` and all 12 `modules/Premium/*` nested repos are exactly as §71 left them.

**How to apply next time:** once a §71-style merge-in-place cutover is done and its leftover list
has been read through category-by-category (as above) and nothing load-bearing is in it, `git clean
-fd` is the right blunt instrument for the bulk of the cleanup — safe by construction as long as
`data/` and any per-installation nested-repo trees (Premium, Custom) are actually `.gitignore`d
already, which they are on this codebase. Don't reach for manual `rm -rf` across hundreds of paths;
let `.gitignore` do the safety filtering.

**Signed off by Jasiek (2026-08-22)**, against the original ask (core → clone of `jtylek/epesi` on
`migration`; every Premium module → clone of its own `jtylek/Premium-<Name>` repo; `data/` untouched;
all leftovers gone): confirmed all four hold. Deliberately still open, by his own call, not an
oversight: manually running `update.php`'s patch sweep against this instance's real DB.

**Non-git installs:** `git clean -fd` only works because this checkout has a `.git` to diff
against — an install upgraded by copying/extracting a new release directly over an old one (no git
anywhere) has no such mechanism and just accumulates these same stale paths release after release.
`modules/Base/patches/20260822_remove_pre_migration_leftover_paths.php` is the generalized,
non-git-dependent version of the code-tree subset of this cleanup (the `libs/adodb`,
`modules/CRM/Roundcube/RC`, CKEditor/OpenFlashChart/QuickForm/ScriptAculoUs vendor leftovers, old
`admin/` panel files, etc.) — a plain list of known-dead paths (each confirmed via git history, not
guessed), existence-checked and `recursive_rmdir()`'d, so it runs safely via `update.php`'s normal
patch sweep on any install and no-ops on ones already clean. Deliberately narrower than the full
§72 sweep: it doesn't touch `vendor/*` stragglers (that's `composer install`'s job, not a patch's)
or the host-specific Softaculous/backup files from Pass 1 above (non-constant names, needed
eyeballing here rather than blind automated deletion).

---

### §73 — Pre-upgrade Premium-module PHP 8 sweep on `client-instance.example`: 8 real fatals found and fixed across 6 modules (2026-08-22)

**Context:** before running `update.php`'s patch sweep against this instance's real DB (per §71/§72),
scanned all 12 `modules/Premium/*` repos for PHP 8 compatibility and reintroduced deliberately-
removed dependencies — the exact job `.claude/skills/fix-old-epesi-module/SKILL.md` (merged in via
the `jasiek` branch, see the two-new-skills discussion this same session) is written for, run
manually against all 12 modules since a skill added to `.claude/skills/` mid-session isn't picked up
by the session that just gained it (see `AI-shared/sharing-skills.md`'s "Known limitation").

**Method:** `rector` (both `rector.php` and `rector-php82.php`, dry-run) + `phpstan analyse` per
module, plus direct `grep`/`git grep --no-index` sweeps for every pattern in
`deliberate-removals.md` and every removed-function name this codebase has been bitten by before
(`create_function`, `each(`, `get_magic_quotes_gpc`, `get_magic_quotes_runtime`, `money_format`,
`convert_cyr_string`, `ezmlm_hash`, `image2wbmp`, `read_exif_data`, `call_user_method`). Sanity-
checked the scan methodology itself first: confirmed PHPStan resolves the `Module`/`RecordBrowser`
base-class hierarchy correctly even when the CLI path is narrowed to one module directory (tested
against `modules/CRM/Contacts`, zero false positives) — so the many real findings below are genuine,
not scope artifacts. Ambiguous findings were verified against actual code context (call-site
analysis, checking whether a calling method has `$this` in scope) and in one case against the live
DB directly (see Timesheet, below) rather than trusted at face value.

**Confirmed fatal, fixed (8 across 6 modules):**
- **Invoice, KnowledgeBase** — both `*Install.php::install()` called
  `Base_LangCommon::install_translations()`, removed from core along with `data/`-based theme/lang
  storage (§ "Theme/lang storage under `data/`" in `deliberate-removals.md` — that entry didn't
  previously name this specific method; added it there too). First line of `install()` in both →
  fatal `Error` on every install/upgrade, uncaught (unlike a patch's own try/catch), risking the
  whole `update.php` request, not just these two modules. Removed both calls.
- **KnowledgeBase** `Category.php`/`Thread.php` — both called `set_quickjump()` inside `install()`,
  the same fatal-on-install pattern already documented twice in `deliberate-removals.md` for other
  Premium modules (SalesOpportunity, ListManager) — this makes KnowledgeBase the third. Removed both
  calls, and added the missing `return $success;`/`return parent::uninstall();` in
  `Category::install()`, `Thread::install()`, `Thread::uninstall()` while in there (all three fell
  through returning `null` instead of a real success/failure value).
- **Payments** `DataTransformCallback.php`/`ProcessCallback.php` — both declare a `private function
  callback_as_string()` (non-static) but call it via `self::callback_as_string(...)` from *other*
  static methods (`register()`/`unregister()`) — identical bug shape to the core `PEAR::isError()`
  fix (§1 at the top of this file): fatal "Non-static method cannot be called statically" the moment
  any payment plugin registers a callback. Both method bodies only touch their argument, never
  `$this`, so declaring them `static` was safe. (Also added a missing `return true;` in
  `DataTransformCallback::register()` — same return-missing shape as KnowledgeBase above.)
- **Payments** `Plugins/AuthorizeNet/Plugin.php` / `Plugins/Paypal/PluginInternal.php` — both built
  the card-expiration month/year `<select>` group via `HTML_QuickForm::createElement()` (static
  syntax), but `createElement()` (`vendor/openpsa/quickform/lib/HTML/QuickForm.php:456`) is a genuine
  instance method that uses `$this->_loadElement()` internally — fatal the moment a user configures
  an AuthorizeNet or Paypal payment method's card-expiration field. Fixed to use the already-in-scope
  `$form` instance instead of the class name (both call sites already had `$form` as a parameter).
- **Vacation** `VacationCommon_0.php` / **CaseManagement** `Matter/MatterCommon_0.php` — both had an
  unguarded `get_magic_quotes_gpc()` call (removed in PHP 8) gating a `stripslashes()` on a
  user-submitted note — fatal "Call to undefined function" on every vacation/ticket note submission.
  The CaseManagement instance was *missed by the phpstan scan* (still unexplained — phpstan flagged
  the identical pattern fine in Vacation) and caught only by the direct grep sweep for this function
  name, confirming the skill's own step 3 "cheap supplementary check" isn't redundant with
  rector/phpstan — run both. Magic quotes have unconditionally returned `false` since PHP 5.4, so the
  guarded `stripslashes()` was already dead logic either way; removed the whole guard in both files
  rather than adding a no-op `function_exists()` wrapper.
- **PrintTemplates** `SectionRecordset.php` — still wired up `$form->addElement('ckeditor', ...)`
  behind a "Use CKEditor" toggle button; nothing registers the `'ckeditor'` QuickForm element type
  anymore (see `ckeditor-to-quill-migration.md`) — fatal the moment a user clicks the toggle. Removed
  the toggle and CKEditor branch entirely, keeping only the already-fully-functional plain-textarea
  path — not even a Quill port, since the removed toggle's own confirm message warned CKEditor could
  mangle the Smarty tags these print templates rely on, and Quill would carry the identical risk.

**Checked and confirmed defused, no fix needed:**
- **Timesheet** `patches/premium_timesheets_add_customer2.php` calls a `Premium_TimesheetCommon::
  get_customer_options()` that doesn't exist anywhere in the class — but rather than guess at
  severity, queried this instance's real `patches` table directly (`mysql` CLI against
  `data/config.php`'s credentials): the patch's identifier hash is already marked applied, and the
  data condition it touches (`premium_timesheet_data_1` rows with empty `f_customer`) has zero
  matching rows. `PatchUtil::apply_new()` skips already-applied patches unconditionally, so this
  specific landmine cannot fire on this instance's upcoming `update.php` run. Left the patch file
  itself untouched (patches are identified by filepath — editing an already-applied one is a silent
  no-op per the Patches convention in `CLAUDE.md`) but worth knowing this file is broken if it's ever
  needed on a *different*, not-yet-upgraded Timesheet install.

**Explicitly not real risks** (verified, not just assumed): the large batch of PHPStan
`property.notFound` findings for dynamic property assignment (`$this->rb = $this->init_module(...)`
and similar, across Accounts/CaseManagement/ExchangeRate/Expenses/Payments/Vacation) is harmless —
confirmed by reading `include/error.php:283` directly: `REPORT_ALL_ERRORS`'s error-reporting level is
explicitly `E_ALL & ~E_DEPRECATED`, and PHP 8.2's dynamic-property notice is exactly `E_DEPRECATED`,
so it's silently suppressed by this codebase's own design, not merely by luck. Also left alone as
genuinely low-priority/pre-existing, unrelated to the migration: `Payments/Plugins/AuthorizeNet/
anet_php_sdk/**` (vendored third-party SDK, out of scope per this folder's own "never patch vendor
code" convention even though it isn't literally under a `vendor/` path); `Payments/Plugins/Paypal/
IpnListener.php`'s undefined `$data` (used inside `empty()`, which never warns on undefined
variables — silently wrong logic, not a crash); `Projects/Tickets/TicketsInstall.php`'s duplicate
array key and a patch file's trailing whitespace after `?>` (both cosmetic); and Rector's
`ArrayToFirstClassCallableRector` suggestions across most modules (pure PHP 8.1+ style
modernization, zero compatibility risk).

**Result:** 12 individual git commits, one per affected Premium module repo (`Invoice`,
`KnowledgeBase`, `Payments`, `Vacation`, `PrintTemplates`, `CaseManagement`), each `php -l`-clean
before committing. Re-ran the full rector+phpstan+grep sweep afterward to confirm: zero remaining
fatal-shaped findings across all 12 modules.

**Pushed to origin (2026-08-22):** all 10 Premium modules with commits — `Invoice`,
`KnowledgeBase`, `Payments`, `Vacation`, `PrintTemplates`, `CaseManagement`, `Expenses`,
`MultipleAddresses`, `Projects`, `Timesheet` — were fetched and confirmed as clean fast-forwards
against their own `jtylek/Premium-<Name>` `master` before pushing, so the fixes above are live on
GitHub, not just local to this checkout: `Premium-CaseManagement` `d6a842f→b4eb52f`,
`Premium-Expenses` `d10d3c7→dbca6dc`, `Premium-Invoice` `7750457→f6746e8`,
`Premium-KnowledgeBase` `a846ed8→fbf2aa5`, `Premium-MultipleAddresses` `1fc4f5d→671b1ce`,
`Premium-Payments` `c1a0aad→73e17c7`, `Premium-PrintTemplates` `0d4101f→419e311`,
`Premium-Projects` `1519683→4e81e6c`, `Premium-Timesheet` `08ce034→4177c37`,
`Premium-Vacation` `edb2011→a72f062`. `Accounts` and `ExchangeRate` had zero findings, so nothing
to push there. The core repo's own `migration`/`jasiek`/`karina` branches were separately fast-
forwarded to the same tip and pushed too (see §74's commit for what's in that history).

**How to apply next time:** this is the concrete worked example for `/fix-old-epesi-module` run at
"all Premium modules" scope rather than one at a time — the same rector+phpstan+grep method scales
fine module-by-module, but cross-checking an ambiguous static-analysis finding against the *actual
DB* (not just the code) before deciding severity, as done for the Timesheet patch, is worth doing
whenever a patch/migration path is in question and the instance is real and reachable.

---

### §74 — Running `update.php`'s patch sweep: CLI over browser, and why `REPORT_ALL_ERRORS` should be off for the run (2026-08-22)

**Context:** after §73's Premium-module sweep, the next step on `client-instance.example` was
actually running `update.php`'s patch sweep against the real (pre-migration-backup) DB. Read
`update.php`'s full source (`EpesiUpdate::run()` and everything it calls) before recommending an
approach, rather than guessing from the login page alone.

**CLI (`php update.php` from the repo root) over the browser, for this and any future
`.git`-backed instance:**
- **No execution-time pressure.** The CLI branch of `update_process()` calls
  `PatchUtil::disable_time_management()` up front, and CLI PHP has no default execution-time limit
  anyway. The browser branch is a `?up=start` → `?up=patches` → `?up=end` redirect chain instead,
  which can hit a web-server timeout mid-sweep and needs manual page reloads to resume.
- **No login friction.** `check_user()`'s CLI branch calls `Base_AclCommon::set_sa_user()` and just
  checks `i_am_sa()` — no session, no admin-level-2 login screen (which the browser path requires).
- **Output streams straight to the terminal in real time** (`cli_msg()` calls at every step),
  pairing naturally with a second window tailing logs (e.g. `/monitor-error-logs`) instead of
  reading a rendered HTML progress page.
- **The network self-update path is a non-issue either way, here.** `net_update_blocked()` returns
  true whenever `.git` exists in the root — true for any `migration`-branch checkout done via §71's
  method — so the "download a signed release zip from ess.epe.si and overwrite files" logic in
  `update.php` never runs in *either* mode on this kind of install. The only thing either mode
  actually does is call `PatchUtil::apply_new()`, which behaves identically regardless of how it's
  invoked — so this isn't a safety tradeoff, purely an ergonomics one.
- Maintenance mode turns on automatically at the start and off at the end in both modes
  (`turn_on_maintenance_mode()`/`MaintenanceMode::turn_off()` in `perform_update_start()`/
  `perform_update_end()`) — expected, not a bug, for a live site.

**`REPORT_ALL_ERRORS` should be turned off (`0`) in `data/config.php` before running the sweep, back
on after, if it's normally left on for dev/debugging (as it was here — see the earlier session note
about turning it on for this exact upgrade).** The reasoning is specific, not just "less noise is
safer":
- `Patch::apply()`'s `try/catch` only catches `PatchException`/`Exception` — **it does not catch
  `Error`** (PHP's separate throwable class for e.g. "call to undefined method"). So the exact class
  of bug §73 hunted down and fixed ahead of time would crash a patch regardless of
  `REPORT_ALL_ERRORS` either way — that setting only ever controls whether `E_WARNING`/`E_NOTICE`
  *also* count as failures, never whether a genuine fatal does. Since §73 already covers the
  fatal-class risk, there's little safety upside left to gain from leaving it on for this specific
  operation.
- With it on, `error.php` sets `error_reporting()` to `E_ALL & ~E_DEPRECATED`, so
  `Patch::error_handler()` (registered via `set_error_handler` for the duration of `include
  $this->file`) converts *any* notice — even something as cosmetically harmless as an old patch
  touching an optional array key — into a caught `PatchException`, marking that one patch
  `STATUS_ERROR`. Because `perform_update_patches()` always calls `PatchUtil::apply_new(true)` —
  `$die_on_error = true`, unconditionally, in both CLI and browser modes — a single such notice
  anywhere in the queue triggers `trigger_error(..., E_USER_ERROR)` and halts the *entire* sweep at
  that point, not just that one patch. Across a decade-plus of patch files spanning a dozen-plus
  modules, hitting at least one such notice is plausible, and it costs a full
  stop-diagnose-fix-rerun cycle for something that usually isn't a real bug.
- **The tradeoff, worth knowing rather than assuming away:** with it off, a notice during a patch is
  silently swallowed — not logged anywhere, not shown — rather than visible-but-non-fatal. Lowering
  `error_reporting()` doesn't just relax a threshold for display purposes; `Patch::error_handler()`
  itself checks `error_reporting() & $errno` and no-ops entirely below that level, so there's no
  fallback visibility. If the sweep still hits a real `STATUS_ERROR` after turning it off, that's a
  genuine signal worth investigating (not a notice that slipped through) — `E_USER_ERROR` from a
  true `Exception`/`PatchException` still halts execution regardless of this setting, since the
  default (non-`REPORT_ALL_ERRORS`) `error_reporting_level` explicitly includes `E_USER_ERROR`.
- Already-applied patches are skipped automatically on any re-run (`PatchesDB::was_applied()`,
  keyed by an md5 of the patch's file path — see §73's Timesheet check for how to query this table
  directly if ever needed) — so a halted run is always safe to just fix-and-rerun, never needs a
  restart from scratch.

**How to apply next time:** for any `.git`-backed instance, prefer `php update.php` (CLI) over the
browser for the same reasons above; they're general, not specific to this one install. Before
running it, check `data/config.php` for `REPORT_ALL_ERRORS` and turn it off for the duration of the
run if it's on, for the specific reason above (notice-vs-fatal, not just "less noise") — turn it
back on afterward if it's the instance's normal dev/debug setting.

---

### §75 — `client-instance.example`: `update.php` run, clean, 47 patches, 1.9.1 → `20260701-rc2` (2026-08-22)

**Result: the §71–§74 preparation paid off — `php update.php` (per §74's CLI recommendation, with
`REPORT_ALL_ERRORS` off per that same entry) ran start to finish with zero errors or timeouts.**

```
WARNING: the database lists modules with no code in this build:
   - Libs_ScriptAculoUs
These are most likely premium/custom modules. Migrate them to this
version together with the core. Their data stays in the database.
Continuing update...
Update from 1.9.1 to 20260701-rc2...
Updated to 20260701-rc2
Done
```

**`data/logs/patches.log` for this run (18:40:34–18:41:07, ~33s):** 47 patches applied, all
`SUCCESS`, zero `ERROR`/`TIMEOUT` — spanning core (`CRM/Contacts`, `Base/Theme`, `Base/Lang`,
`CRM/Roundcube`'s §69 schema-migrate fix, the `utf8mb4` migration — the slowest at ~25s of the
~33s total — etc.) and Premium modules alike (several `Premium/Projects`/`Projects/Tickets`
patches ran clean, good real-world confirmation that §73's fixes didn't just satisfy static
analysis). The very last patch to run, in last position by date, was
`modules/Base/patches/20260822_remove_pre_migration_leftover_paths.php` (§72's addendum, added by
a concurrent session) — also `SUCCESS`, correctly a no-op here since §72's manual `git clean -fd`
had already removed everything on its list.

**The one warning, expected and harmless:** `Libs_ScriptAculoUs` is `orphaned` — the DB has it
recorded as installed, but its code was deliberately removed from core entirely (dependency
dropped, see `deliberate-removals.md`). CLI mode just warns and continues automatically (§74/§59's
`orphaned_modules_gate()` — browser mode would have shown a confirm screen instead). Its data, if
any, is untouched in the DB, not deleted by this update.

**Post-update verification:**
- `SELECT value FROM variables WHERE name='version'` → `20260701-rc2`, matching the deployed code.
- `console.php maintenance:status` → `disabled` — maintenance mode (auto-enabled for the run's
  duration) turned back off automatically at the end, site is live again.

**How to apply next time:** this is the concrete "it worked" confirmation that the §70–§74
preparation sequence (merge-in-place cutover → leftover cleanup → Premium-module PHP 8 sweep →
CLI + `REPORT_ALL_ERRORS`-off for the run) is sufficient groundwork for a real client's `update.php`
to complete cleanly on the first attempt — worth following the same sequence, in the same order,
for the next real cutover rather than treating any one step as optional.

---

### §76 — Repeat cutover on `client-instance-2.example`: re-confirm CLI over browser for `update.php` — don't re-derive the wrong answer from stale context (2026-08-22)

**Context:** a fresh Claude Code session re-ran the §71–§75 cutover process end-to-end on a
second instance, `client-instance-2.example` (separate directory/DB from the original
`client-instance.example` `§71` ran against — same install, different name). Core clone +
all 12 Premium modules populated cleanly, reproducing §71 exactly (264 leftover entries, same
two Premium modules — CaseManagement, Projects — with the same pre-existing leftovers).

**The mistake:** when it came time to run `update.php`'s patch sweep, the session initially
concluded it was blocked — reasoning that the sweep is "browser-gated" (admin login) and that
no browser-automation tool was available this session, citing `environment-gotchas.md`'s older
"no browser-automation tool available" note from a *different* context (a logged-in UI
walkthrough, not the patch sweep itself). This ignored that §74 had already read
`update.php`'s actual source and settled this exact question: **CLI (`php update.php`) is not
a fallback for when browser automation is unavailable — it's the actively preferred method**,
regardless of tooling, because the CLI branch avoids execution-time limits, admin-login
friction, and streams output live (`net_update_blocked()` also makes the two modes behave
identically for a `.git`-backed instance either way — no safety difference, purely ergonomics
per §74). The user caught this and pointed back at the already-recorded decision rather than
letting the session re-derive (and get wrong) an answer that was already settled.

**Result once corrected:** `php update.php` (`/c/xampp82/php/php.exe update.php` on this
Windows box) ran clean — `1.9.1 → 20260701-rc2`, `Done`, matching §75's outcome exactly. One
difference from §75 worth recording: on the original instance §72's *manual* `git clean -fd`
had already removed the pre-migration leftovers before the patch sweep ran, so
`20260822_remove_pre_migration_leftover_paths.php` was a no-op there. On this second instance
no manual cleanup was done first — the patch did real work, logging each removal
(`libs/prototype.js`, `libs/adodb`, `libs/UiUIKit`, old CKEditor/OpenFlashChart/QuickForm/
ScriptAculoUs/PHPExcel bundled copies, `modules/CRM/Roundcube/RC`, old `admin/` files,
`mobile.php`/`mobile.css`, the empty `tests` skeleton) to `data/logs/patches.log` — concrete
confirmation the patch is a full substitute for §72's manual step on a from-scratch repeat, not
just a safety-net for stragglers.

**How to apply next time:** before concluding *any* step of this migration is blocked or needs
a tool that isn't available, grep this file for whether the question was already answered
(here, `§74`/"CLI" would have surfaced immediately). Specifically: **`update.php`'s patch sweep
always runs via CLI (`php update.php` from the repo root) on a `.git`-backed instance — never
attempt or ask for browser automation to drive it**, that was never the blocker it was assumed
to be.

---

### §77 — Full repeat-cutover timing + gotcha log: `client-instance-2.example`, git-init to `update.php` done in ~10 minutes of measured execution (2026-08-22)

**Context:** consolidated record of the full §71-style cutover repeated end-to-end this session
on a second instance, `client-instance-2.example` — requested separately from §76 (which only
covers the CLI-vs-browser correction) because the user wanted one place with total timing and
every gotcha hit along the way, not just the individual step entries.

**Timeline (from git reflog / `data/logs/patches.log` timestamps, all 2026-08-22):**

| Phase | Start | End | Duration |
|---|---|---|---|
| `git init` + `remote add origin` + `fetch` (6 branches) | 19:40:46 | 19:40:56 | ~10s |
| `git checkout migration` — first attempt aborted (see Gotcha 1), retried with `-f` | 19:40:56 | 19:41:59 | ~1m |
| Clone all 12 `jtylek/Premium-<Name>` repos to temp dirs (parallel) + `robocopy /E` merge into `modules/Premium/*` + temp cleanup | ~19:41:59 | 19:43:56 | ~2m |
| Verification pass + user checkpoint (asked whether to also run cleanup/PHP8-sweep/update — user said hold off, then separately confirmed Premium modules were pre-fixed and no manual cleanup needed) | 19:43:56 | 19:49:45 | *(conversation turns, not continuous execution)* |
| `php update.php` (CLI) — 49 patches incl. the leftover-cleanup patch | 19:49:45 | 19:50:22 | ~37s |

**Total measured execution time (git/robocopy/patch operations only): ~9m36s**, spread across
three conversation turns — the gap between Premium-population and running `update.php` was
user think-time/back-and-forth (see Gotchas 2-3 below), not processing time, so it isn't part
of the "how long did the actual work take" figure.

**Gotchas/errors encountered, in order:**

1. **`git checkout -b migration origin/migration` aborted**: "The following untracked working
   tree files would be overwritten by checkout" — expected per §71, since the directory already
   had a full prior install at every path the new repo tracks. Resolved with `git checkout -f`;
   safe here specifically because `data/*` and `modules/Premium/` were never tracked by the core
   repo, so a plain (non-purging) force-checkout can't touch them regardless of `.gitignore`
   state at the time. This is a different (simpler, no-temp-dir) technique than §71's
   robocopy-from-sibling-clone approach — both land in the same result for the core repo; the
   sibling-clone technique remains necessary for `modules/Premium/*` since those aren't a `git
   checkout` target at all (see §71).
2. **Session incorrectly concluded `update.php` was blocked** on missing browser-automation
   tooling, instead of checking whether this exact question had already been answered — it had,
   in §74. Full writeup + correction in §76; not repeated here beyond the pointer.
3. **Robocopy's own exit codes (1, 3) surfaced as "error" banners** from the PowerShell tool
   wrapper (any non-zero `$LASTEXITCODE` is treated as a tool failure) even though 1 = "files
   copied" and 3 = "files copied + extras present" are robocopy's normal informational codes,
   not failures — same point §71 already makes about not trusting `$LASTEXITCODE` at face value,
   now reconfirmed against this specific tool's error-surfacing behavior. Only ≥8 is a real
   robocopy error.
4. **Direct `mysql` CLI verification query was blocked** by this session's permission
   classifier (a bare password on the command line reads as sensitive regardless of intent) —
   worth knowing before relying on §75's exact verification method (`SELECT value FROM
   variables WHERE name='version'` via raw `mysql` CLI) as a repeatable step; fell back to
   `update.php`'s own reported version string (`Updated to 20260701-rc2`) and `console.php
   maintenance:status` (`disabled`) instead, both sufficient confirmation without touching the
   DB directly.

**What was deliberately skipped this run, and confirmed safe in hindsight:** per explicit user
instruction, §73's Premium-module PHP 8 sweep and §72's manual `git clean -fd` leftover cleanup
were both skipped — the former because all 12 Premium modules' PHP 8 fixes are already pushed
to their GitHub repos (a fresh clone comes in fixed), the latter because
`20260822_remove_pre_migration_leftover_paths.php` handles it automatically. Zero `ERROR`
entries in `patches.log` (49/49 `SUCCESS`) confirms skipping the PHP 8 sweep was safe. The
cleanup patch actually did real work this time (unlike §75, where a prior manual `git clean -fd`
had made it a no-op) — logged 22 individual removals, taking the repo from 264 untracked
leftover entries down to 242 (the remaining 242 are the same `vendor/*`-stragglers/etc. category
§72 catalogued as harmless but out of this patch's narrower scope).

**Final state:** `1.9.1 → 20260701-rc2`, `Done`; 49/49 patches `SUCCESS`, 0 `ERROR`/`TIMEOUT`;
maintenance mode auto-disabled at the end; `data/` and all 12 Premium module repos intact
throughout (never touched by anything other than their own robocopy merge step).

**How to apply next time:** budget roughly 10 minutes of actual execution time for this whole
cutover on a machine/network similar to this one (core clone+checkout ~1m, Premium
clone+merge ~2m, `update.php` well under a minute) — the rest of any real session's wall-clock
time is conversation/verification overhead, not the process itself being slow. Read §71-76
before starting a repeat so Gotchas 1-2 above don't recur a third time.

---

### §78 — Rename leftover "TCMS" naming to "Epesi" in the QuickForm array/default renderers (2026-08-22)

**Context:** "TCMS" was Epesi's pre-rebrand product name (see the 2007 copyright header naming
Jasiek in the old `TCMSArraySmarty.php`/`TCMSDefault.php` files) and had survived as the class/file
naming for the two QuickForm renderer classes this migration already touched in §11-12: the
Smarty-array-form renderer (relocated `modules/Libs/QuickForm/Renderer/` → `include/` in
`8cf350707`) and the raw-table fallback renderer used by `Utils_Wizard`/FirstRun. User asked for
a full sweep and rename to "Epesi" everywhere.

**Scope found:** confined entirely to this one QuickForm-renderer subsystem — 58 "TCMS"
occurrences across 18 files (full-repo case-insensitive grep, `modules/Premium/` included via
plain `grep` since Grep silently skips gitignored paths). No DB tables, config keys, or CSS
selectors were named after TCMS — only 2 PHP class names, 2 file names (+1 JS asset), and
comments/docs pointing at them.

**Also found:** `modules/Libs/QuickForm/Renderer/TCMSArray.php` + `TCMSArraySmarty.php` existed
on disk as **untracked** files — the pre-`8cf350707` originals, deleted from git at that
relocation commit but present again outside version control (origin unclear; nothing in the
current code path required them — `QuickForm_0.php` only requires `Renderer/TCMSDefault.php`).
Deleted per user confirmation rather than renamed, since they were dead duplicates of what now
lives at `include/EpesiArray.php`.

**Renamed:**
- Class `HTML_QuickForm_Renderer_TCMSArray` → `HTML_QuickForm_Renderer_EpesiArray`
  (`include/TCMSArray.php` → `include/EpesiArray.php`, `git mv`)
- Class `HTML_QuickForm_Renderer_TCMSDefault` → `HTML_QuickForm_Renderer_EpesiDefault`
  (`modules/Libs/QuickForm/Renderer/TCMSDefault.php` → `Renderer/EpesiDefault.php`, `git mv`)
- `Renderer/TCMSDefault.js` → `Renderer/EpesiDefault.js` (`git mv`; both renderer classes'
  `load_js()` calls updated to match)
- Call sites: `include/EpesiSmartyRenderer.php` (require path + `extends`),
  `modules/Libs/QuickForm/QuickForm_0.php` (require path + `new`)
- `phpstan-stubs/quickform.stub.php` stub class name; `phpstan-baseline.neon` regenerated via
  `vendor/bin/phpstan analyse -c phpstan.neon` rather than hand-edited, since both the class-name
  regex and the `path:` entries needed to change together
- Comment-only references (no functional change): `setup.php`, 3 theme CSS files
  (`modules/Libs/QuickForm/theme/default.css`, `modules/Utils/GenericBrowser/theme_adminltedark/
  default.css`, `modules/FirstRun/theme_adminltedark/default.css`), and the "current-state"
  `AI-shared/` docs (`standalone-entrypoints.md`, `import-wizard-plan.md`, `bug-patterns.md`,
  `adminlte-theme.md`, `TODO.md`)

**Deliberately NOT touched:** this doc's own §11/§12 historical entries — they're a dated record
of what the code was actually named at the time (mid-migration ctor fixes on `TCMSArray.php`/
`TCMSArraySmarty.php`/`TCMSDefault.php`), and rewriting history to the post-rename names would
make those entries inaccurate as a record even though the files they describe no longer exist
under those names.

**How to apply:** pure code/file rename, no stored/seed data involved (not an `*Install.php`
default, DB row, or `data/` file) — ships to existing installs via normal code deployment, no
`patches/` entry needed.

---

### §79 — FIXED (properly this time) — custom QuickForm element types: closed the §12.7/§13 "vendor edit lost on composer update" gap (2026-08-24)

**Symptom:** `CRM_Filters` box fatal on render: `ReflectionClass::__construct(): Argument #1
($objectOrClass) must be of type object|string, array given`, from `_loadElement()` trying to
`new ReflectionClass()` on Epesi's `array($file,$className)` registration pair. Caught live by
`monitor-error-logs` tailing `data/logs/php_errors.log`.

**Root cause:** exactly the failure §12.7/§13 (item 2) predicted. openpsa's `_loadElement()` had
been vendor-patched to accept Epesi's legacy PEAR-style `array($file,$className)` registration
format in addition to its own native plain-classname-string format — but that's a vendor edit,
and `vendor/` isn't git-tracked, so it doesn't survive a `composer install`/`update`. Checked the
live vendor file: the dual-format patch was gone, back to pristine openpsa (string-only). Same
fate had already hit the sibling `registerElementType()` static-method vendor patch (§12.6) —
`modules/Libs/Codepress/CodepressCommon_0.php:16` was still calling it statically, which would
fatal ("Non-static method ... cannot be called statically") the next time Codepress loads.

**Fix — option (b) from §13 item 2, no vendor edit at all:** converted every custom QF type
registration in Epesi's own code from `array($file,$className)` to a `require_once($file)` +
plain classname string, matching what stock/pristine openpsa's `_loadElement()` actually wants
(a string, with the class already loaded — no autoload for Epesi's own classes, per this doc's
top-level note). Touched all 8 registration sites, not just the one that happened to crash first,
since they all shared the same format:
- `include/epesi.php`'s `register_custom_qf_types()` (the central eager-registration function
  from §15.2) — the authoritative list of all 14 custom types
- `modules/Libs/QuickForm/QuickForm_0.php:16-19` (multiselect, autocomplete, automulti, autoselect)
- `modules/Utils/CommonData/CommonDataCommon_0.php` (commondata, commondata_group)
- `modules/Utils/QueryBuilder/QueryBuilder_0.php` (critsvalue)
- `modules/Utils/PopupCalendar/PopupCalendarCommon_0.php` (datepicker, timestamp)
- `modules/Utils/CurrencyField/CurrencyFieldCommon_0.php` (currency)
- `modules/CRM/Contacts/ContactsInstall.php` (commondata FirstRun-timing band-aid, §13 item 4)
- `modules/Libs/Codepress/CodepressCommon_0.php` — also dropped the static `registerElementType()`
  call entirely in favor of writing `$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']` directly (closes
  §12.6/§13 item 1 the same way CKEditor's caller was already closed — CKEditor's own `'ckeditor'`
  registration is gone too, superseded by the Quill editor, per §§2383's note)

Why fix every site instead of just the central `register_custom_qf_types()`: several of these
per-Common-file lines run at module-file-load time, which can happen *after* the central
eager-registration already ran (e.g. loading `Libs_QuickForm` re-executes `QuickForm_0.php`) —
left in the old array format, they'd clobber the working string registration right back to the
broken one. All 8 sites now register the same way, so load order no longer matters.

**Verified:** `php -l` clean on all 8 files; live browser reload of the crashed `crm_filter_contact`
autoselect field (via the dashboard's "Perspective" link → `CRM_Filters` lightbox) renders and
submits correctly, zero console/PHP-log errors.

**How to apply:** pure code fix, no vendor edit, no stored/seed data — ships to existing installs
via normal code deployment, no `patches/` entry needed. Closes §13's "VENDOR EDITS (lost on
composer update)" items 1 and 2 for good; nothing left registers a custom QF type via the array
format, so there's nothing left for a `composer update` to break here.

### §80 — Premium/GanttChart: two more PHP-8-fatal legacy shapes, both silent/easy to miss (2026-08-21)

`modules/Premium/GanttChart` — never swept by any core migration pass (separate, gitignored Premium
repo) — hit two PHP 8-fatal patterns not yet catalogued in §49's removed-function list or
`deliberate-removals.md`, both in `gantt.class.php` (a hand-vendored 2005 charting class bundled
inside the module, not Epesi's own code, but shipped and executed as part of it):

1. **PHP4-style constructor silently stops being a constructor.** `function gantt($definitions)`
   (method name === class name) was PHP 4/5's implicit-constructor convention, removed in PHP 8.0 —
   `new gantt($definitions)` on 8.2 just builds an empty object and runs nothing; every bit of
   drawing logic that used to execute inside that method never runs. **No warning, no fatal — just
   silently broken output** (a blank chart image), which is what makes this shape easy to miss next
   to the rest of §49's removals (those all fatal loudly, so they surface immediately in testing).
   Fix: rename to `__construct`.
2. **Bareword identifiers used as ad hoc string constants, never `define()`'d.** Old code
   (`case END_TO_START:`, `$definitions['dependency_color'][END_TO_START]`, four names total —
   `END_TO_START`/`END_TO_END`/`START_TO_START`/`START_TO_END`) relied on PHP 5/7's fallback
   behavior for an undefined constant: silently coerced to its own name as a string (with an
   `E_WARNING`, easy to miss under normal error reporting). **PHP 8.0 turns this into a fatal
   `Error: Undefined constant`** — the first one hit kills the request outright, no chart renders
   at all. Fix: quote them as the strings they were always functionally standing in for (confirmed
   safe here since the same four names are already used as literal quoted array keys elsewhere in
   the same files, e.g. `$definitions['dependency_color']['END_TO_START']` — the bareword and
   quoted forms were clearly meant to be the same value).

**How this was found, and a related trap:** PHPStan (scoped to just this module, per the
`/fix-old-epesi-module` skill) flagged `img.php`'s `new gantt(...)` as "Instantiated class gantt not
found" — turned out to be a *third*, unrelated issue (`gantt.class.php` opens with a bare `<?` short
tag, not `<?php`) masking PHPStan's ability to discover the class at all for that scoped run, which
in turn hid finding #1 until the short tag was fixed first. A bare `<?` is also a real, separate
portability risk on its own — most `php.ini-production` templates ship `short_open_tag=Off`, which
would make a file opening with `<?` render as literal text instead of executing at all, independent
of anything else in this entry.

**How to apply:** when scanning another never-migrated Premium/Custom module, grep it for
`function <ClassName>(` matching its own class name (PHP4 constructor — rename to `__construct`) and
for bare, unquoted all-caps identifiers used where a string is clearly intended (compare against how
the same name is used elsewhere in the file — if it's ever quoted as a literal in an array key or
comparison, the unquoted uses nearby are the bug). Neither is caught by `php -l`, and the constructor
case isn't always caught by Rector/PHPStan either unless the class is actually resolvable — if a
PHPStan "class not found" finding looks suspicious for a class that's clearly defined right there in
the file, check for a stray short tag before assuming the finding is a false positive.

---

### §81 — `include.php`: PHP-level fallback for hosts where the root `.htaccess` hardening never applies (2026-08-25)

**Context:** `setup.php`'s `check_htaccess()` self-test (see §55) can still legitimately fail even after
§55's `mod_headers` guard fix — e.g. `AllowOverride` too narrow to permit `Options`/`RedirectMatch`/`Header`,
or an nginx/PHP-FPM front-end that ignores `.htaccess` entirely. When that happens the admin sees "Your
hosting is not compatible with default EPESI root .htaccess file" and **no root `.htaccess` gets installed
at all** — so none of `htaccess.txt`'s hardening applies: no `Options -Indexes`, no `.git`/`.svn` blocking,
no `X-Frame-Options`/`X-XSS-Protection` headers, no mod_php `memory_limit` bump.

**Fix (partial — 2 of 4):** added a PHP-level fallback at the very top of `include.php`, right after the
`_VALID_ACCESS` guard, so it runs on every request through the shared bootstrap (`index.php`, `process.php`,
`ajax.php`, `cron.php`, `console.php`):
- `header('X-Frame-Options: SAMEORIGIN')` / `header('X-XSS-Protection: 1; mode=block')`, skipped under CLI
  (`PHP_SAPI !== 'cli'`) since `header()` is a silent no-op there anyway.
- A `memory_limit` floor of 256M, parsed for K/M/G suffixes and left untouched when already higher or
  unlimited (`-1`) — a naive `(int) ini_get('memory_limit')` cast misreads `"1G"` as `1` and would have
  wrongly *downgraded* a 1G host to 256M. Verified standalone via CLI (`64M`→bumped to `256M`, `1G`→left
  alone, `-1`→left alone). `cron.php`'s later unconditional `ini_set('memory_limit', '512M')` (line ~41)
  still overrides this floor same as before.

Verified live against the local XAMPP dev instance (`curl -D -`) — both headers present on the actual
response.

**What's still NOT covered, and can't be at this layer:** `Options -Indexes` (directory listing) and the
`.git`/`.svn` `RedirectMatch` rules act on the request before PHP ever runs — including for static files
PHP never sees (a direct request to `/.git/config` is served straight off disk by the web server). Those
two stay dependent on either the web server's own config (vhost-level `<Directory>`/`<DirectoryMatch>`
block, or a control-panel toggle) or, for VCS metadata specifically, simply never shipping `.git`/`.svn`
into the deployed web root (see `release-packaging-plan.md`). `.htaccess` itself is still preferred when it
works — this fallback exists only for hosts where it's rejected or ignored outright.

**How to apply:** if a future report says the security headers or `memory_limit` aren't taking effect on a
host that also failed `check_htaccess()`, check whether this `include.php` fallback shipped in the version
they're running before assuming it's a new bug — and remember it only covers the two of four `.htaccess`
protections that are reachable from PHP at all.

**Follow-up (same day):** the `check_htaccess()` failure screens in `setup.php` (both the "unable to check"
and "not compatible" messages, ~line 419 and ~line 438) still told the admin to "tweak it yourself" with no
mention that headers/`memory_limit` were already handled — misleading now that they're covered
unconditionally. Added a sentence to both messages saying so, so the admin knows only `Options -Indexes`
and the `.git`/`.svn` blocking still need manual/web-server-level attention. New English string, no
`lang/*.php` translations added (falls back to English until translated, same as other untranslated
strings in this codebase) — no upgrade-gap patch needed since `setup.php` only runs on fresh installs, not
via `update.php`.

**Follow-up 2 (same day) — readability pass, plus a real display bug found along the way:** even with the
above, a first-time installer still got a wall of jargon (`X-Frame-Options`, `memory_limit floor`) followed
immediately by the raw `.htaccess` file dump — not approachable for a non-technical install. While drafting
a plain-language rewrite, found that `setuptheme/message.tpl` had been rendering `{$pre}` with **no
`|escape` modifier**, straight into a `<pre>` block. Browsers parse `<IfModule mod_php7.c>` in unescaped
HTML as an unrecognized custom element — tag hidden, enclosed text kept — so every `<IfModule>`/`</IfModule>`
guard silently vanished from what the admin actually saw on screen (looked like blank double-linebreaks
where each guard used to be), even though the real file on disk always had them. Not a regression from
this session's other changes — pre-existing since `message.tpl` was written; just never noticed until the
"is this needed" thread led to actually reading the template.

Fixed both problems together in one pass:
- `message.tpl`: added `|escape` to `{$pre}` (so the actual `<IfModule>` guards now display correctly, verified via a standalone Smarty render), and wrapped the `pre` block in `<details><summary>` — collapsed by default — gated behind a new optional `pre_collapsed`/`pre_label` param pair (the 3 other `message.tpl` callers in `setup.php` never pass `pre` at all, so this is additive, not a behavior change for them; the one existing `pre`-passing site, `check_htaccess()`, is the only caller updated to opt in).
- `setup.php`: rewrote all three `check_htaccess()` messages in plain language — leads with "this is optional, safe to click Continue," explains in plain terms what's covered automatically vs. what still needs attention, and moved the technical `.htaccess` dump behind the new collapsed `<details>` (via `pre_collapsed=>true`) for the two "hardening isn't applying" cases. The third case (compatible-but-not-writable) keeps the file visible by default since copying it *is* the required action there — no `pre_collapsed`, so it falls through to the template's original always-visible behavior.

**How to apply:** if a future `message.tpl`-style template needs to show a technical/advanced-only block
alongside a plain-language summary, the `pre_collapsed`/`pre_label` pattern here is the reusable shape —
plain-language message first, technical detail opt-in via `<details>`. And more generally: any raw
Apache-style config text (`<IfModule>`, `<Directory>`, etc.) shown through a Smarty `{$var}` without
`|escape` will silently lose its own tags in the rendered page — worth checking any other `pre`/config-dump
template for the same gap.

**Follow-up 3 (same day) — two more `message.tpl` PHP 8 warnings, one caused by this change, one pre-existing:**
Live-tested the "not compatible" screen and got `Warning: Undefined array key "pre_collapsed"` from the
compiled template. Root cause: `setup.php`'s `message.tpl` render call (~line 222) hand-picks fields off
`$check` (`'message' => $check['message']`, `'pre' => $check['pre'] ?? null`, ...) instead of passing
`$check` straight through — Follow-up 1/2 added `pre_collapsed`/`pre_label` to `check_htaccess()`'s *return
array* but never added them to that hand-picked render-call array, so they never reached the template.
Fixed by adding `'pre_collapsed' => $check['pre_collapsed'] ?? false` / `'pre_label' => $check['pre_label']
?? null` alongside the existing `pre` line.

While verifying that fix, found the **same warning class already existed independent of anything in this
thread**: `message.tpl` references `$heading`/`$pre`/`$link_href` via bare `{if $var}`, which Smarty 2
compiles to a raw `$this->_tpl_vars['heading']` array access with no `isset()` guard — a PHP 8 "Undefined
array key" warning for any caller that only assigns `message`. All 3 of `setup.php`'s other `message.tpl`
calls (safe_mode / stale `config.php` / unwritable `DATA_DIR` error screens, ~lines 157–169) do exactly
that and had never been caught, presumably because those specific failure paths are rare enough in
practice that nobody had hit one and looked closely at the output. Fixed centrally in
`setuptheme/SetupSmarty.php`'s `render()`: when `$template === 'message.tpl'`, backfill
`heading`/`pre`/`pre_collapsed`/`pre_label`/`link_href`/`link_text` to `null`/`false` before assigning, so
every current and future bare `render('message.tpl', array('message'=>...))` call is safe by construction
instead of each call site having to remember the full optional-var list.

**How to apply:** this is the same root-cause shape as the `{if $var.optional_key}` warning already
documented in the "Error handling" section of `CLAUDE.md` — Smarty 2 has no implicit `isset()` guard on
`{if $var}`, so *any* template variable a `.tpl` only conditionally references needs either every caller to
assign it (even to `null`/`false`), or a `render()`-level default like the one added here. Worth the same
treatment if another shared template/render-wrapper accumulates optional vars over time.

---

## MERGE CHECKLIST — experiment/composer-deps → main

> **MILESTONE 2026-06-27: entire Core tested locally on PHP 8.2.** All Core modules + Administrator + cron exercised; runtime fixes §23–§41 applied.
> **NOTE:** this checklist captures the 2026-06-27/28 state. The branch has since shipped — released as CalVer `20260701-rc1` (see PHASE 5 STATUS) — and further hardening (§47–§65) is already on `main`. Kept here as history; items below are corrected to their actual outcome rather than left as stale TODOs.

### ✅ Done
- Rector PHP 7→8.2 ladder applied to all own code
- Runtime fixes: Contacts, Companies, Tasks — full CRUD tested, no fatals
- PHP 8 relic fixes committed: login_id guard, TCPDF __DIR__, Meeting addFormRule, checkboxes, attached_to, Flash button, clipboard pattern
- PhoneCall — full CRUD tested, no fatals (§27 watchdog fix applied)
- Meeting — full CRUD tested, no fatals
- User Settings — tested, no fatals
- Calendar/Agenda — tested, no fatals
- Filters/search (critsvalue) — tested across modules, no fatals
- Password recovery — mail-failure now reported instead of silent success (§34)
- Filestorage view/download/get-link — fixed via §20 narrow fix, later superseded by the §36 root-cause fix
- Email/Roundcube — upgraded to RC 1.7.1, send/receive confirmed working (§30)
- Administrator — tested OK: Access restriction (§35), Files (§20; Mail §37a), Common data (§38), RecordBrowser add-field (§39) + Permissions edit (§40) + custom recordset (RAD) create, Currencies, Language & translations. Modules Administration & Store deferred (see below).
- **§22 mcrypt decision** — RESOLVED 2026-06-28: Option A (`phpseclib/mcrypt_compat`) adopted, see §22.
- **§26 timestamp field layout** — FIXED, see §26.
- **§36 Instance() root fix** — ADOPTED as canonical: `include/module_common.php`'s `Instance()`
  keys its singleton storage per-class via `static::class`, confirmed present in the current
  `migration` branch (verified 2026-08-22 while removing the now-redundant
  `PROPOSAL_instance_singleton_fix.md`). This line previously sat under "Still open" pending
  Jasiek's call — corrected here since the code shows the call was made.

### 🔲 Still open
- **Modules Administration & Store** — page opens, but lots to fix; **deferred to the future** (it's effectively a separate application / Telaxus store integration, related to §32/§33 EssClient). Not a migration blocker — decision by Karina 2026-06-27.
- §21.1 off-by-one attachment link — cosmetic
- §21.3 loader/spinner JS — cosmetic
- §21.4 login_id design question (email-copy 1:1 assumption) — code fix applied (§23.1), design intent still to confirm with Jasiek
