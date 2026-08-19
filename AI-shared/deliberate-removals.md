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

**Gap found 2026-08-18:** the sweep only covered the tracked repo — `modules/Premium/`
is a separately-licensed, gitignored tree (each premium module its own git repo, see
main `CLAUDE.md`) and was never touched, so it can still contain leftover
`Utils_RecordBrowserCommon::set_quickjump()` calls in `*Install.php` files. One was
hit live in `modules/Premium/SalesOpportunity/SalesOpportunityInstall.php:38` —
fatal `Call to undefined method` on install/upgrade via the Store admin screen — and
fixed by deleting the call (`install_new_recordset()` is idempotent, so re-running
the install after the fix is safe, no leftover partial state to clean up). If another
Premium module fails to install/upgrade with the same "undefined method
`set_quickjump`" error, this is why — grep `modules/Premium/` for `set_quickjump` and
delete the call the same way, don't try to re-add the method.

**Second hit 2026-08-19:** same call found and fixed in
`modules/Premium/ListManager/ListManagerInstall.php:34` (`Premium_CampaignManager`
depends on `Premium/ListManager` via `requires()`, so it was blocked from installing
too until this was fixed). `modules/Premium/CampaignManager` itself has no
`set_quickjump` call. Still not swept: any other Premium repos not yet cloned into
this checkout — check for this on every fresh `modules/Premium/*` clone, not just
these two.

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

## Contacts "Birth Date" field disabled (2026-08-14)

The `type=>'date'` "Birth Date" field on `CRM_Contacts`
([modules/CRM/Contacts/ContactsInstall.php:71](../modules/CRM/Contacts/ContactsInstall.php))
was disabled via the admin field-management UI (a per-instance `RecordBrowser`
config flag, not a code change — the field definition itself is untouched) at
explicit user request, after repeated "Invalid date - clearing" reports
traced to browser/password-manager autofill on this specific field — see the
"browser autofill, not stale JS" entry in `bug-patterns.md` for the full
investigation. Two real code fixes were shipped for the underlying mechanism
(`autocomplete="off"` on the field, then a datepicker.js fallback that
reparses/reformats an unambiguous ISO `YYYY-MM-DD` autofill value instead of
rejecting it) and both are still in place — but re-enabling the field and
retesting still reproduced the alert with the user's real (Chrome-profile-
synced) autofill data, meaning whatever Chrome is actually inserting there
isn't the plain ISO shape the reformat fallback handles. Rather than keep
chasing unknown real-world autofill formats, the user decided disabling the
field is not worth continuing to fight.

**Also removed on this instance**: the `Applets/Birthdays` dashboard applet
(reads Contacts' Birth Date to list upcoming birthdays — pointless with the
field disabled) was uninstalled via Administration: Modules Administration &
Store, and dropped from the `[CRM installation]` preset in
`modules/FirstRun/distros.ini` (the first-run wizard's fresh-install module
bundle, `modules/FirstRun/FirstRun_0.php:69`) so future fresh installs don't
pull it in either.

**How to apply**: if a future request wants Birth Date back, re-enabling it
needs a warning that this exact autofill-triggered alert may return for
users whose browser/password-manager autofills it — the underlying datepicker
fixes reduce but don't eliminate the risk. Don't silently re-enable it (or
reinstall the Birthdays applet / re-add it to distros.ini) as a "fix" for an
unrelated complaint; confirm with the user first, per this file's header.

## `Libs/CKEditor` — 2 wrapper files kept, not fully removed (2026-08-11)

CKEditor was replaced app-wide by `Libs/Quill` (see `ckeditor-to-quill-migration.md`
for the full migration). Almost everything was deleted: the ~250-file vendored
`ckeditor/` tree, `ckeditor.php` (the QuickForm element class), `ck.js`, `onsubmit.js`.
Nothing registers the `'ckeditor'` QuickForm element type anymore, and the two modules
that used to depend on it (`Utils_Attachment`, `Base_Dashboard`) had their `requires()`
swapped to `Libs_QuillInstall` via patches.

**But `modules/Libs/CKEditor/CKEditorInstall.php` and `CKEditorCommon_0.php` (stripped
to an empty documented shell) are still there — on purpose, not a leftover.**
`ModuleManager::uninstall()` needs the target's `*Install.php` to stay loadable (it
calls the class's own `uninstall()` hook) and refuses to run if anything still
`requires()` it. Auto-uninstalling `Libs_CKEditor` from a patch was judged more risk
than the disk space is worth, so the module stays installed-and-harmless rather than
actually uninstalled.

**How to apply**: if asked to finish the cleanup / "why does this dead-looking module
still exist" / delete `modules/Libs/CKEditor` entirely — don't, without first writing
and testing a real uninstall patch (or otherwise confirming `ModuleManager::uninstall()`
won't refuse). Two small inert files are the accepted tradeoff here, not a bug.

## `Libs/OpenFlashChart` → `Libs/ChartJS` (2026-08-18)

`Libs_OpenFlashChart` rendered charts via a literal Flash `<object>`/`<embed>`
pointing at a vendored `.swf`. Not just legacy — **completely non-functional in
every browser since ~2021** (Adobe killed Flash Player Dec 2020), yet it was
still the live, only-reachable chart mechanism: `Utils_RecordBrowser_Reports`
puts a "Charts" button in the ActionBar on every report
(`Reports_0.php::body()`), and `make_charts()`/`draw_chart()`/
`draw_category_chart()`/`draw_summary_chart()` all built on it. Anyone clicking
that button got a blank box for ~5 years with nobody noticing/filing it.

Replaced with a new `Libs/ChartJS` module (mirrors `Libs/Quill`'s shape - vendored
single-file UMD build, `modules/Libs/ChartJS/4.5.1/chart.umd.min.js`, MIT
licensed, no build step) rendering real `<canvas>` charts. `Libs_ChartJS` is a
plain `Module` (like `Libs_OpenFlashChart` was, not a QuickForm element like
Quill) with a small setter API (`set_type`/`set_title`/`set_labels`/
`add_dataset`/`set_y_max`/`set_width`/`set_height`) that the 4 Reports methods
above call the same way they called the old OFC classes. Chart init uses the
same `e:load`-driven lifecycle pattern as `Libs/Quill`'s `qu.js` (see that
file's own comment for the AJAX-push rationale) - `cj.js`'s version is simpler
since a report chart has no in-progress edit state to preserve across a
destroy/recreate, unlike an editable field.

**Same uninstall-safety tradeoff as the CKEditor entry above**:
`modules/Libs/OpenFlashChart/OpenFlashChartInstall.php` is kept completely
unchanged and `OpenFlashChart_0.php` stripped to an empty shell, rather than
deleting the module outright - `ModuleManager::uninstall()` needs
`*Install.php` loadable and refuses if anything still `requires()` it, and
auto-uninstalling from a patch was judged more risk than the disk space is
worth. The vendored `2-lug/` (`open-flash-chart.swf`) tree and `data.php` (the
Flash movie's own data-fetch endpoint - `Libs_ChartJS` doesn't need this at
all, chart data goes straight into the page as inline JSON) are deleted.
`Utils_RecordBrowser_ReportsInstall::requires()` now lists `Libs_ChartJSInstall`
instead, with a `20260818_swap_openflashchart_dependency_for_chartjs.php` patch
calling `ModuleManager::install('Libs/ChartJS')` for existing installs. The
`modules/Tests/OpenFlashChart` demo module (unlike `Tests/Codepress`, which
still demos a working feature) was deleted outright - no value in a demo of
functionality that no longer exists.

**How to apply**: if asked to finish the `Libs/OpenFlashChart` cleanup / delete
it entirely - same answer as CKEditor: don't, without first writing and testing
a real uninstall patch.

## Setup wizard tooltip/JS component attempts — see `adminlte-theme.md`

(Not a removal of a feature per se, but three separate JS-tooltip-component
attempts were each tried and reverted back to plain native tooltips — see the
"Not yet themed" section there before trying a fourth.)
