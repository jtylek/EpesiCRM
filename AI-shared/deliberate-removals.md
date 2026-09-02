# Deliberate removals

> **Status:** REFERENCE - features that are missing *on purpose*. Do not silently reintroduce.
> If a request implies re-adding one of these, surface the tension and confirm first. Full
> per-removal write-ups (what was touched, which commit, which incident) are archived at
> `AI-private/archive/deliberate-removals.md`.

See [load-bearing-oddities.md](load-bearing-oddities.md) for the converse: code that is
*present* on purpose despite looking like cruft.

Several of these recur as reintroduction bugs in `modules/Premium/` and `modules/Custom/` —
separate, gitignored, never-swept repos. The `/fix-old-epesi-module` skill scans an old module
for exactly this shape.

## Removed APIs a legacy module may still call

Delete the call; **don't look for a replacement API.** All three are unnecessary now.

| Call | Behaviour today | Fix |
|---|---|---|
| `Utils_RecordBrowserCommon::set_quickjump()` | fatal — undefined method | delete the line |
| `Base_ThemeCommon::install_default_theme()` / `uninstall_default_theme()` | kept as core no-ops so old call sites don't fatal | delete the line |
| `Base_LangCommon::install_translations()` | fatal — gone entirely | delete the line |

A fatal in `install()` is the usual symptom, since these sit at the top of it — the module
then fails to install or upgrade with nothing else having run. `install_new_recordset()` is
idempotent, so re-running the install after the fix is safe.

## The removals themselves

**A-Z "quick jump" letter selection.** Removed codebase-wide from `GenericBrowser`/
`RecordBrowser`. The `quickjump` column is left inert in already-installed tables; no
migration drops it.

**Login Audit purge/maintenance.** `purge_log()` and its ActionBar button are gone. **The
access/login audit log is meant to be permanent — no purge capability at all.** If a request
is about the table growing large, flag the tension rather than reintroducing a purge.

**GenericBrowser search-box autofocus.** The unconditional `.focus()` popped the mobile
keyboard on every page load. Restoring it in any form needs new conditional logic, not the old
unconditional call.

**The entire legacy mobile subsystem.** `mobile.php`, `libs/UiUIKit/`, device detection, and
every module's `mobile_menu()`/`mobile_*` dispatcher. **AdminLTE is the mobile experience for
every device** — there is no separate mobile codepath. `MOBILE_DEVICE` still exists as a
define but is permanently `0`. Device-specific behaviour has to be built fresh, as theme
CSS/JS.

**Theme and language storage under `data/`, and theme upload.** Themes are served straight
from `modules/` with no build step — never suggest a theme rebuild. The custom-theme zip-upload
admin feature and the epesi.org theme-repo browse/download/install flow were deleted outright;
only the theme-picker `<select>` remains. **One narrow exception:** per-instance custom
translation overrides came back, writing to `data/Base_Lang/custom/<module>/<code>.php` — never
into `modules/`. Shipped translations still live at `modules/<M>/lang/<code>.php`.

**Contacts "Birth Date"** is disabled per-instance (an admin field-management flag, not a code
change) after repeated "Invalid date - clearing" reports traced to browser autofill. Two real
fixes shipped and are still in place, but re-enabling still reproduced it. Re-enabling needs a
warning that the alert may return.

**`modules/Develop/`** — all five 2006–2008-era in-app developer tools deleted outright. If
any of it is wanted back it must be rebuilt fresh, not restored from git history.

**Multi-tenant hosting.** The optional `map.php` / `$virtual_hosts` host→`DATA_DIR` routing is
gone; `include/data_dir.php` is now just `define('DATA_DIR', 'data')`. The CLI overrides that
existed only to target a tenant per invocation went with it — `console.php --data-dir`,
`update.php <data_dir>`, and `cron.php`/`monitoring.php`'s `$argv[1]`. The `temp/<DATA_DIR>`
nesting was kept (still useful for excluding regenerable cache from backups).

**Five legacy dashboard applets** — `Birthdays`, `Calc`, `Google`, `Host`, `Weather` — deleted
from the tree, and dropped from `modules/FirstRun/distros.ini`. `Clock`, `MonthView`, `Note`
and `RssFeed` were kept; **MonthView was deleted by mistake in the same pass and explicitly
restored**, so it is not a target for the same cleanup.

**"Additional applets" / "Error reporting" / "Web Notifications" are no longer separately
toggleable.** Six modules dropped their `'option'` key from `simple_setup()` and merged into
the plain `Epesi Core` package bucket. Restoring independent install/uninstall means restoring
that key — and for `Base_Notify`, its `'version'` key too, which is only safe again once it
has its own dedicated packages key (`$packages[$key]['version']` has no first-wins guard, so a
merged module setting it races the real product version).

**Seven thin `modules/Tests/*` demo modules** — `Attachment`, `Comment`, `Image`, `Lang`,
`Menu`, `Search`, `TabbedBrowser`. Each was one `init_module()` plus a couple of setters, and
two demonstrated hooks with no consumer left. **What was kept is the point**, because it is the
closest thing the repo has to executable API documentation that cannot drift:

- `Tests/RecordBrowser` — every `RBO_Field_*` type, plus field-level ACL.
- `Tests/Bugtrack` — smallest complete business module, and the only side-by-side of the old
  array-based `install_new_recordset()` and the new OO `RBO_Recordset` API.
- `Tests/Callbacks` — the only end-to-end demo of the request/navigation model
  (`create_callback_href`, `is_back()`, `create_back_href($n)`, `Base_Box::push_main()`, and
  the return-`true`/`false` "render instead of" vs. "fall through" semantics). No prose covers
  this.
- `Tests/QuickForm` — the element-type catalogue.
- `Tests/Tooltip` — the only `_1` multi-version module, kept to demonstrate `version()`'s
  array-length semantics.
- `Tests/{Report,Calendar,Wizard,SharedUniqueHref,Leightbox,Colorpicker,GenericBrowser}` —
  each the **sole** worked example of its API.

If asked to trim further, the test is whether the candidate is the last remaining caller of the
API it demonstrates — not its line count.

## Replaced libraries: two kept as empty shells, on purpose

`Libs/CKEditor` (→ [Quill](ckeditor-to-quill-migration.md)) and `Libs/OpenFlashChart`
(→ `Libs/ChartJS`, real `<canvas>` charts; the Flash version had rendered a blank box in every
browser since 2021 while still being the only chart mechanism Reports offered) both had
everything deleted **except** `<Name>Install.php` and a stripped `<Name>Common_0.php` shell.

That is deliberate: `ModuleManager::uninstall()` needs the target's `*Install.php` loadable —
it calls the class's own `uninstall()` hook — and refuses while anything still `requires()` it.
Auto-uninstalling from a patch was judged more risk than the disk space. **Don't "finish the
cleanup"** without first writing and testing a real uninstall patch.

`Libs/Codepress` (+ `Tests/Codepress`) *was* deleted outright, including its `*Install.php`,
because both modules' `install()`/`uninstall()` were always `return true;` — no schema to
protect. Syntax-highlighted code editing, if wanted again, is a fresh "pick and vendor a
library" decision, not a revert.

## When an orphaned `modules` row needs a cleanup patch

An orphaned row is **not** an automatic "always leave it". Check the pre-removal `*Install.php`
(`git show <removal-commit>^:<path>`) for what `install()` actually did:

- **Never created schema** → a guarded `DELETE FROM modules WHERE name=...` patch is safe and
  worth shipping.
- **Real schema, or an `*Install.php` that must stay loadable** → leave it. A stray row
  self-quarantines as `MODULE_NOT_FOUND` (`state=2`) with no fatal error, by design.

## Don't propose bundling the Common classes again

`FORCE_CACHE_COMMON_FILES` concatenated every `*Common_0.php` into one cached file so
`load_modules()` could do one `require_once` instead of ~71. It was tried twice and measured
once, then removed entirely.

**Measured:** ~3.5 ms per request on the web SAPI with opcache on — about 1% of a 245 ms page
render — and *worse* than nothing under CLI. The reason generalizes: without opcache the
compiler does identical work whether the code arrives as 71 files or 1, and with opcache
compilation is already cached either way. **The bundle can only ever save `stat`/open cost,
never compilation cost.** The "71 requires become 1" framing invites the opposite assumption,
and is what led here both times.

**What it cost against that 3.5 ms:** silently stale code with no timestamp check (an edit to
any `Common_0.php` did nothing until an explicit `cache:rebuild`); a duplicate `use` across two
Common files became an instant fatal for the whole app, since they were now one compilation
unit; and a Common class loaded by a raw `require_once` outside the module system got
re-declared — `E_COMPILE_ERROR`, whole request down.

If it is proposed again: measure first, under the web SAPI with opcache on. CLI figures are
meaningless. A network filesystem is the one untested scenario where `stat` cost might be real
— treat that as a fresh proposal with fresh numbers, not a reason to restore the code.

`console.php cache:rebuild` and admin "Clear Cache" still exist and still do real work — they
call `Cache::clear()`, which is unrelated general-purpose caching.
