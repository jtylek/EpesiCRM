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

### 11.4 Epesi's own QuickForm extensions need PHP4→PHP8 constructor fixes for openpsa (IN PROGRESS)
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

- **STILL TO FIX** (same family, found via grep — these have name == own class, so they're REAL php4
  ctors that DO initialize, need rename to __construct + fix internal parent call to parent::__construct):
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
- Remaining blocker: the 6 extension constructors above. Once fixed, FirstRun should render → login → live app.
- Open flags: "Modules dir writable: No" (yellow on system-check; may matter for module install — chmod
  modules/ if needed). Old libs/adodb/ + old modules/Libs/QuickForm/3.2.14-php7/ now unused → cleanup
  candidates AFTER proving they're dead (grep whole codebase first, same discipline as before).
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

### 12.6 openpsa — registerElementType() called statically (CKEditor, Codepress)
modules/Libs/CKEditor/CKEditorCommon_0.php:18 and modules/Libs/Codepress/CodepressCommon_0.php:16 call
`HTML_Quickform::registerElementType(...)` statically; openpsa declares it non-static → fatal.
The method only writes to $GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES'] (no $this), so made it `static` in
vendor (QuickForm.php:296). This unblocked module installation (modules then installed successfully).
⚠️ VENDOR EDIT (lost on composer update). FIX-TWICE plan (user's decision): move the change OUT of
vendor by rewriting the 2 Epesi calls to write the global directly, like Epesi does everywhere else:
  $GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['ckeditor'] = 'HTML_Quickform_ckeditor';  // + codepress
Then revert the vendor `static` edit. (Not yet done — verify-first approach: confirmed static works,
trad-off change pending.)

### 12.7 openpsa — custom element type registration format mismatch (IN PROGRESS, current blocker)
Epesi registers ~8 custom element types as ARRAY: 
  $GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['commondata'] = array('file.php', 'ClassName');
(commondata, commondata_group, datepicker, timestamp, critsvalue, currency, multiselect, autocomplete,
automulti, autoselect). openpsa expects a STRING (classname only) and instantiates via ReflectionClass
(autoload, no file include). Patched openpsa `_loadElement` (QuickForm.php:477) to accept BOTH formats:
if array → require_once($reg[0]) then use $reg[1]; else use string. (Vendor edit — fix-twice candidate.)
BUT current blocker is EARLIER: `isTypeRegistered()` (QuickForm.php:1128) checks the global and throws
at line 476 BEFORE reaching the format handler — meaning 'commondata' isn't

---

## 13. QUICK-FIXES TO RESOLVE PROPERLY (fix-twice checklist)

All of these got Epesi running on PHP 8.2 but are temporary. Each needs a permanent solution.

### VENDOR EDITS (lost on `composer update` — highest priority to relocate)
1. **openpsa registerElementType() made static** — vendor/openpsa/quickform/lib/HTML/QuickForm.php:296
   Proper fix: revert vendor; rewrite the 2 Epesi callers (CKEditor CKEditorCommon_0.php:18,
   Codepress CodepressCommon_0.php:16) to write $GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES'] directly.

2. **openpsa _loadElement() dual-format patch** — vendor/openpsa/quickform/lib/HTML/QuickForm.php:477
   Added array-format handling (Epesi uses array('file','Class'); openpsa expects string).
   Proper fix: either fork openpsa, or convert all Epesi type registrations to openpsa's string format
   + ensure those classes are autoloadable.

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
   Proper fix: eager-register ALL custom element types before FirstRun/module install, OR ensure
   custom-type modules load before dependent modules. (include_common didn't work — module not
   installed yet at that point.)

5. **Core PHP8 fixes** (these are legit, keep — not band-aids): error.php:207 ($errcontext=null),
   get_magic_quotes_gpc→false in 5 files, magicquotes.php, QuickForm extension __construct fixes (7 files),
   renderHidden signatures, TCMSArray finishForm/renderHtml.

### SMARTY (replace, don't patch — Smarty 5 is PHP 8-native)
6. create_function + each() patched in Smarty_Compiler.class.php (265, 566). Replace whole Smarty later.

### ADODB / OPENPSA (drop-ins — keep, but clean up)
7. Old libs/adodb/ and old modules/Libs/QuickForm/3.2.14-php7/ now UNUSED → delete AFTER proving dead
   (grep whole codebase). Keeping them risks more include_path stale-loads (see #3).

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