# Features and APIs that are gone on purpose

Things that are missing **deliberately**. Do not silently put them back. If a request
implies re-adding one, say so and confirm first.

These recur as reintroduction bugs in module trees that no codebase-wide sweep ever
reached, because they are gitignored and separately distributed. The
`/fix-old-epesi-module` skill scans an old module for exactly this shape.

## Removed APIs a legacy module may still call

**Delete the call. Don't look for a replacement** — all three are unnecessary now.

| Call | Behaviour today | Fix |
|---|---|---|
| `Utils_RecordBrowserCommon::set_quickjump()` | fatal — undefined method | delete the line |
| `Base_ThemeCommon::install_default_theme()` / `uninstall_default_theme()` | kept as core no-ops so old call sites don't fatal | delete the line |
| `Base_LangCommon::install_translations()` | fatal — gone entirely | delete the line |

A fatal in `install()` is the usual symptom, since these sit at the top of it — the module
then fails to install or upgrade with nothing else having run. `install_new_recordset()` is
idempotent, so re-running the install after the fix is safe.

## There is no separate mobile codepath

The entire legacy mobile subsystem is gone: `mobile.php`, `libs/UiUIKit/`, device
detection, and every module's `mobile_menu()`/`mobile_*` dispatcher. **AdminLTE is the
mobile experience for every device.** `MOBILE_DEVICE` still exists as a define but is
permanently `0`.

Device-specific behaviour has to be built fresh, as theme CSS/JS — not by restoring a
parallel render path.

## There is no theme build step, and no theme upload

Themes are served straight from `modules/` — never suggest a theme rebuild. The
custom-theme zip-upload admin feature and the theme-repo browse/download/install flow were
deleted outright; only the theme-picker `<select>` remains.

Theme and language storage under `data/` went with it, with **one narrow exception**:
per-instance custom translation overrides, which write to
`data/Base_Lang/custom/<module>/<code>.php` — **never** into `modules/`. Shipped
translations still live at `modules/<M>/lang/<code>.php`.

## A-Z "quick jump" letter selection

Removed codebase-wide from `GenericBrowser`/`RecordBrowser`. The `quickjump` column is left
inert in already-installed tables; no migration drops it.

## The login audit log is permanent by design

`purge_log()` and its ActionBar button are gone, and **there is no purge capability at
all** — that is the intent, not an oversight. If a request is about the table growing
large, flag the tension rather than reintroducing a purge.

This is the opposite end of the same instinct as lazy delete (see
[design-philosophy.md](design-philosophy.md)): most tables soft-delete so nothing is ever
destroyed; this one keeps everything for the same reason.

## GenericBrowser search-box autofocus

The unconditional `.focus()` popped the mobile keyboard on every page load. Restoring it in
any form needs new conditional logic, not the old unconditional call.

## The in-app developer tools (`modules/Develop/`)

All five deleted outright. If any of it is wanted back it must be rebuilt fresh, not
restored from git history.

## Multi-tenant hosting

The optional `map.php` / `$virtual_hosts` host→`DATA_DIR` routing is gone;
`include/data_dir.php` is now just `define('DATA_DIR', 'data')`. The CLI overrides that
existed only to target a tenant per invocation went with it — `console.php --data-dir`,
`update.php <data_dir>`, and `cron.php`/`monitoring.php`'s `$argv[1]`. The `temp/<DATA_DIR>`
nesting was kept, since it is still useful for excluding regenerable cache from backups.

## Two replaced libraries are kept as empty shells

`Libs/CKEditor` (replaced by Quill) and `Libs/OpenFlashChart` (replaced by `Libs/ChartJS`)
both had everything deleted **except** `<Name>Install.php` and a stripped
`<Name>Common_0.php` shell.

That is deliberate: `ModuleManager::uninstall()` needs the target's `*Install.php` loadable
— it calls the class's own `uninstall()` hook — and refuses while anything still
`requires()` it. **Don't "finish the cleanup"** without first writing and testing a real
uninstall patch.

`Libs/Codepress` *was* deleted outright, including its `*Install.php`, because its
`install()`/`uninstall()` were always `return true;` — no schema to protect.

## Don't propose bundling the Common classes again

`FORCE_CACHE_COMMON_FILES` concatenated every `*Common_0.php` into one cached file so
`load_modules()` could do one `require_once` instead of ~71. It was tried twice and
measured once, then removed entirely.

**Measured:** ~3.5 ms per request on the web SAPI with opcache on — about 1% of a 245 ms
page render — and *worse* than nothing under CLI. The reason generalizes: without opcache
the compiler does identical work whether the code arrives as 71 files or 1, and with
opcache compilation is already cached either way. **The bundle can only ever save
`stat`/open cost, never compilation cost.** The "71 requires become 1" framing invites the
opposite assumption, and is what led here both times.

**What it cost against that 3.5 ms:** silently stale code with no timestamp check; a
duplicate `use` across two Common files becoming an instant fatal for the whole app, since
they were now one compilation unit; and a Common class loaded by a raw `require_once`
outside the module system getting re-declared — `E_COMPILE_ERROR`, whole request down.

If it is proposed again: measure first, under the web SAPI with opcache on. CLI figures are
meaningless.

`console.php cache:rebuild` and admin "Clear Cache" still exist and still do real work —
they call `Cache::clear()`, which is unrelated general-purpose caching.

## One security convention that belongs here

**Gate a genuinely sensitive admin or maintenance surface with
`Base_AclCommon::get_admin_level()`** — it queries the DB and depends on nothing else.
`i_am_admin()`/`i_am_sa()` are fine for ordinary in-app authorization.
