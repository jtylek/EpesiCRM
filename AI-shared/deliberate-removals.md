# Deliberate removals

Features that are *missing on purpose*, at explicit user request — not oversights,
regressions, or gaps to silently fill back in. If a request implies re-adding one of
these, surface the tension and confirm first rather than just doing it.

## A-Z "quick jump" letter selection (removed 2026-07-27)

The letter-selection popover in `GenericBrowser`/`RecordBrowser` list screens
was removed codebase-wide (not just from the adminlte theme) after review.
Touched: `Utils_GenericBrowser` (`quickjump`/`quickjump_to` vars, WHERE-clause
building, `letter_links` assignment, `theme/`+`theme_adminlte/` popover markup),
`Utils_RecordBrowser` (`disable_quickjump()`, the `quickjump` disabled-flag/
column lookup, the `"~`-prefixed search-crit encoding used only for this —
**not** the same-looking marker used elsewhere for unrelated OR-grouped search
crits), `RecordBrowserCommon_0.php::set_quickjump()`, the `Recordset` wrapper,
and the `quickjump` column in `RecordBrowserInstall.php`'s `CREATE TABLE` (fresh
installs only — the column is left inert in already-installed tables, no
migration was run to drop it).

## Login Audit purge/maintenance (removed 2026-07-28)

`CRM_LoginAudit`'s `purge_log()` method and its "Maintenance" ActionBar button
were deleted entirely. **The access/login audit log is meant to be permanent —
no purge capability at all, manual or automatic.** If a future request is about
the audit table growing large or wanting cleanup, don't reintroduce a purge
feature — flag the tension and confirm with the user first. Browsing/filtering
the log and the dashboard "last login" applet are untouched.

## GenericBrowser search-box autofocus (removed 2026-07-28)

The unconditional `.focus()` call on the keyword-search field (fired on render,
app-wide, shared PHP not theme-scoped) was removed — it was popping the mobile
on-screen keyboard immediately on page load, before the user did anything. If a
future request wants autofocus back in some form (e.g. desktop-only, or a
keyboard shortcut), that needs new conditional logic — don't just restore the
unconditional call.

## Legacy mobile subsystem (removed 2026-07-28)

The entire pre-AdminLTE mobile UI/device-detection system was deleted, at
explicit request, once the `adminlte` theme was confirmed to render well on
mobile in its own right. **AdminLTE is now the mobile experience for every
device** — there is no separate mobile codepath to route to.

Removed: `mobile.php`/`mobile.css` (repo root), `libs/UiUIKit/` (~80 files),
`index.php`'s `IPHONE`/`detect_mobile_device()` branching (the `IPHONE`
constant itself and its *other*, unrelated callers — tap-to-call links,
calendar tweaks — were kept), `include/misc.php::detect_mobile_device()`, and
every module's own `mobile_menu()`/`mobile_*` dispatcher method (Calendar,
Contacts, Meeting, PhoneCall, Tasks, Tray, Login). `MOBILE_DEVICE`
(`include/config.php`) still exists as a define but is now permanently `0` —
nothing sets it to 1 anymore, so the handful of remaining
`if (MOBILE_DEVICE)` guards elsewhere in the codebase are dead-but-harmless,
left alone as low-value cleanup.

If a future request wants device-specific behavior again, it needs to be built
fresh (most likely as adminlte CSS/JS), not restored from this removed system.

## Theme/lang storage under `data/` (removed 2026-07-31)

`data/Base_Theme/` and `data/Base_Lang/` are no longer used to store theme or
language files. This includes deleting the **custom theme zip-upload admin
feature outright** (`Base_Theme_Administrator::upload_template()` and the whole
epesi.org theme-repo browse/download/install/delete/update flow) — only the
theme-picker `<select>` + Save remains. Also dropped: `install_default_theme()`,
`uninstall_default_theme()`, `themeup()`, `create_cache()`,
`Base_ThemeResolver::resolve()`'s old `data/Base_Theme/templates/<theme>/`
priority-1 lookup, and `Base_ThemeCommon::get_template_dir()`.

**Why:** the flattened `data/` copy had drifted from source and made every
template edit require a manual "Theme update" run — themes are served straight
from `modules/` now with zero build step (see `theme-served-from-modules`
context folded into this file and `adminlte-theme.md`). Never suggest running a
theme rebuild, and if asked "why doesn't theme upload work anymore" — it was
deliberately removed, not broken.

**Follow-up (2026-08-12):** `data/Base_Lang/` came back, narrowly, for one
thing only — per-instance custom translation overrides. Shipped translations
still live at `modules/<Module>/lang/<code>.php` (unchanged, still theme/lang-
from-`modules/` as above). But the per-instance override a user creates via
the admin Translate screen now writes to
`data/Base_Lang/custom/<module>/<code>.php` instead of
`modules/<Module>/lang/<code>_custom.php` — the user's call that instance
data (even one gitignored file per module) shouldn't be written into
modules/ at all. See `Base_LangCommon::append_custom()`/`build_merge()` and
patch `20260812_move_custom_translations_to_data.php`, which migrates any
already-written `modules/*/lang/*_custom.php` files on upgrade. This does
*not* revive theme storage under `data/` — that stays fully removed.

## Setup wizard tooltip/JS component attempts — see `adminlte-theme.md`

(Not a removal of a feature per se, but three separate JS-tooltip-component
attempts were each tried and reverted back to plain native tooltips — see the
"Not yet themed" section there before trying a fourth.)
