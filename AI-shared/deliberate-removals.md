# Deliberate removals

> **Status:** REFERENCE - features removed on purpose. Do not silently reintroduce.

Features that are *missing on purpose*, at explicit user request — not oversights,
regressions, or gaps to silently fill back in. If a request implies re-adding one of
these, surface the tension and confirm first rather than just doing it.

See [load-bearing-oddities.md](load-bearing-oddities.md) for the converse case: code and
config that is *present* on purpose despite looking like cruft, and breaks when tidied up.

Some of these (Quick Jump, Theme installation) are recurring reintroduction bugs
specifically in `modules/Premium/`/`modules/Custom/` — separate, gitignored, never-swept
repos (see `CLAUDE.md`). The `/fix-old-epesi-module` skill
(`.claude/skills/fix-old-epesi-module/SKILL.md`) scans a given old module for exactly
this shape of issue, plus general PHP 8.x compatibility, and fixes them.

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

**Follow-up (2026-08-21):** `install_default_theme()`/`uninstall_default_theme()` are kept as
no-ops in core (`Base_ThemeCommon`) purely so old call sites don't fatal, not as something new
code should still call. `modules/Premium/GeneralContractor` (a separate, gitignored repo — not
swept by the 2026-07-31 core removal) had 9 `*Install.php` files still calling
`Base_ThemeCommon::install_default_theme($this->get_type())` at the top of `install()`; removed
all 9, no replacement needed (themes already resolve straight from `modules/` without it). If
another Premium module's install fatals or just carries this dead call, same fix applies — delete
the line, don't look for a replacement API. `console/Develop/CreateModuleCommand.php`'s scaffold
already generates a clean `install()` with no theme-install call, so newly-created modules aren't
at risk of reintroducing this — checked 2026-08-21, still true.

**Follow-up (2026-08-22):** unlike `install_default_theme()` above, `Base_LangCommon::
install_translations()` was **not** kept as a core no-op — it's gone entirely, so a leftover call
fatals with "Call to undefined static method" instead of silently doing nothing. Found live in
`modules/Premium/Invoice/InvoiceInstall.php` and `modules/Premium/KnowledgeBase/
KnowledgeBaseInstall.php`, both as the first line of `install()` — meaning every install/upgrade of
either module fataled immediately, before anything else in `install()` ran (see
`MIGRATION_NOTES.md` §73 for the full pre-`update.php` Premium sweep this was found in). Fixed by
deleting both calls, same treatment as `install_default_theme()` — translations are shipped via
each module's own `lang/<code>.php` now, no install-time registration step needed. If another
Premium module's install fatals on this specific method name, same fix applies.

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

**Update 2026-09-01**: `modules/Applets/Birthdays` itself was deleted outright (not just
uninstalled) as part of the wider legacy-applets code removal below — re-adding Birthdays
now needs restoring the module from git history, not just reinstalling it via Setup.

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
`modules/Tests/OpenFlashChart` demo module was deleted outright - no value in
a demo of functionality that no longer exists. (`Tests/Codepress` was cited
here as the contrasting case at the time - it was later removed too, see the
`Libs/Codepress` entry below.)

**How to apply**: if asked to finish the `Libs/OpenFlashChart` cleanup / delete
it entirely - same answer as CKEditor: don't, without first writing and testing
a real uninstall patch.

## `Libs/Codepress` + `Tests/Codepress` — removed entirely (2026-09-01)

`Libs_Codepress` vendored the CodePress 0.9.6 syntax-highlighting JS editor
(`modules/Libs/Codepress/0.9.6/`) as a custom QuickForm element type
(`'codepress'`, registered in `include/epesi.php`'s `register_custom_qf_types()`).
Its only caller anywhere in the tracked tree was `Tests_Codepress`, a demo
module rendering a CodePress-editable textarea pre-filled with its own source
— no real feature depended on it (unlike `Libs/OpenFlashChart` above, which
was still wired into live Reports code when it was replaced).

Unlike the CKEditor/OpenFlashChart entries above, **this one was deleted
outright, `*Install.php` included** — both modules' `install()`/`uninstall()`
were always just `return true;` with no schema of their own, so there was
nothing for `ModuleManager::uninstall()` to protect and no orphan-row risk
beyond a bare tracking row (same reasoning as the `Libs/ScriptAculoUs`
removal — see "Orphaned `modules` DB row after upgrade" below). Cleanup patch:
`modules/Base/patches/20260901_remove_orphaned_codepress_module_rows.php`
(deletes both rows, guarded by an existence + code-gone check, same pattern
as ScriptAculoUs's own patch).

**How to apply**: if asked to add syntax-highlighted code editing to a form
again, don't resurrect this — it's a 2008-era unmaintained library with no
Prism/CodeMirror-style active alternative vendored yet; treat it as a fresh
"pick and vendor a library" decision, not a revert.

## Setup wizard tooltip/JS component attempts — see `adminlte-theme.md`

(Not a removal of a feature per se, but three separate JS-tooltip-component
attempts were each tried and reverted back to plain native tooltips — see the
"Not yet themed" section there before trying a fourth.)

## Entire `modules/Develop/` tree removed (2026-08-21)

All five `Develop_*` modules — `MiscUtils`, `ModuleCreator`, `ModuleEditor`,
`TableBrowserCreator`, `Translations` — were deleted outright, at explicit user
request, as no-longer-needed 2006–2008-era developer tooling now that an
AI-assisted session reads/writes code and the DB directly instead of a human
using in-app scaffolding/debugging widgets. **If a future request wants any of
this back, it needs to be rebuilt fresh** (or use the modern equivalents noted
below) — don't restore these from git history and re-wire them in.

Confirmed zero cross-references anywhere else in the tracked codebase before
deleting each one (no other module's `requires()` named any of them, no
`Develop_*`/`Develop/*` string hits outside their own directories except a
stale doc-comment example in `Utils_Wizard`'s adminltedark stepper template,
fixed in the same pass — see below). None were installed in this dev
instance's DB either (`console.php module:uninstall` reported "not installed"
for all five), so no live uninstall step ran for any of them.

**Per-module reasoning** (user's own words, recorded verbatim where given):

- **MiscUtils** — a global `p($x)` var-dump helper plus a bundled copy of
  Kint (a pre-Xdebug/VarDumper-era PHP debug library), for a developer to
  sprinkle into code while debugging. Superseded by an AI assistant reading
  code/logs/DB state directly. This is also what was breaking PHPStan
  earlier the same day — see the `phpstan.neon`/Kint entries in
  `environment-gotchas.md` for that side story; the `excludePaths` workaround
  added there is now moot and was removed again once the module itself was
  deleted.
- **ModuleCreator** — "the web wizard predates it and does nothing the CLI
  command (or an AI assistant writing the files directly) doesn't already
  cover. We already have in Administrator control panel Custom Recordset."
  I.e. `console.php dev:module:create` (added during the PHP 8.2 migration,
  `console/Develop/CreateModuleCommand.php`) already covers the same
  boilerplate-file scaffolding this wizard did, and the Administrator
  control panel's Custom Recordset feature already covers ad hoc
  table/recordset creation without needing a whole new module — the two
  together made this wizard fully redundant.
- **TableBrowserCreator** — the oldest of the five (2006), admin-gated,
  scaffolded a RecordBrowser-backed module + its DB tables. Same redundancy
  as ModuleCreator: Custom Recordset covers the table-creation need, CLI/AI
  covers the module-file scaffolding.
- **ModuleEditor** — an in-browser file manager *and* code editor (Codepress
  widget) that could create/browse/edit arbitrary files from the app root
  through the web UI, with no ACL check of its own (relied on Epesi's normal
  per-user grant model, so not open by default, but a real arbitrary-file-write
  surface if ever granted to anyone). Redundant now that code is edited via
  IDE/AI-assisted terminal access, and worth removing on security-hardening
  grounds alone, independent of the tooling argument.
- **Translations** — "Translations are handled now by special Epesi
  instance." This was never a per-instance utility — it was the old
  **public/community translation-contribution tracker** tied to Telaxus's
  original open-source project (`develop_trans_contribs`/
  `develop_trans_users` DB tables, `svn_config_example.php`,
  `receive_translation.php` — an SVN-based pipeline for accepting
  crowd-sourced translations), unrelated to the per-instance admin
  "Translate" screen (`Base_Lang`) that's still active and untouched by this
  removal. That community-translation role now lives on a separate, dedicated
  Epesi instance instead.

**Known residual gap**: unlike the CKEditor/OpenFlashChart removals above,
no uninstall patch was written for any of these five. For `MiscUtils`,
`ModuleCreator`, `ModuleEditor`, and `TableBrowserCreator` this is harmless —
none of their `*Install.php::install()` methods created any DB schema, so
there's nothing to leak. `Translations` did create the two tables named
above; on any install where this module was ever actually installed (not
this one), those tables become orphaned rather than dropped — same
"auto-uninstall patch judged more risk than the disk space is worth" call
already made for `Libs/OpenFlashChart` above. Confirmed this is not a
crash risk either way: `ModuleManager::check_is_module_available()`
(`include/module_manager.php:1098`) already handles a module whose directory
has disappeared while still marked installed — it flags the DB row
`MODULE_NOT_FOUND` and unregisters it for that request, no fatal error - so
the module system itself tolerates exactly this kind of removal without a
patch, even though the orphaned tables specifically don't get cleaned up.

**Related cleanup**: `modules/Utils/Wizard/theme_adminltedark/default.tpl`'s
nested-wizard-step grouping logic existed specifically to support
ModuleCreator's dynamic per-table sub-steps — it's now dead-but-harmless (no
remaining `Utils_Wizard` consumer produces nested captions), left in place as
reusable infrastructure for FirstRun/`Premium/Import` rather than ripped out,
per the same "don't refactor shared infrastructure beyond what's asked"
judgment used elsewhere in this file. Its stale comment referencing
`Develop/ModuleCreator` was updated in the same pass so it doesn't send a
future reader looking for a module that no longer exists.

## Orphaned `modules` DB row after upgrade: usually tolerated, but not a blanket rule

`Libs/ScriptAculoUs` (removed 2026-07-30, commit `255a5256b` — a legacy
Prototype.js-era animation/autocomplete JS library, replaced by vanilla CSS
transitions) is gone from the tracked tree entirely, same as `Libs/CKEditor`'s
vendored editor and the `Develop_*` modules above. Running `update.php` on
`client-instance.example` (see `MIGRATION_NOTES.md` §75) surfaced it via the
§59 `orphaned_modules_gate()` warning: the `modules` table still had a row
(`name='Libs_ScriptAculoUs', state=2`) — `state=2` is
`ModuleManager::MODULE_NOT_FOUND`, meaning `check_is_module_available()` had
already self-quarantined it for the request with no fatal error, exactly as
designed.

**Why this one got an actual cleanup patch, unlike CKEditor/OpenFlashChart/
`Develop_*` above:** those three were left alone specifically because a real
uninstall risked more than it fixed — `ModuleManager::uninstall()` needs the
target's `*Install.php` to still exist and loadable (CKEditor/OpenFlashChart
deliberately kept theirs as empty shells for exactly this reason), or the
module genuinely had real schema/tables that a slapdash cleanup could get
wrong (the `Develop/Translations` case). `Libs_ScriptAculoUs` has neither
problem: its code is gone completely (nothing to keep an empty shell for —
there's no `*Install.php` left to worry about breaking), and checking the
pre-removal source (`git show 255a5256b^:modules/Libs/ScriptAculoUs/
ScriptAculoUsInstall.php`) confirms `install()`/`uninstall()` were always
just `return true;` — it never created a single table or row beyond the
`modules` tracking entry itself. With zero schema risk and no class to keep
loadable, there was nothing left for the general "leave it" precedent above
to actually be protecting against, so a plain
`DELETE FROM modules WHERE name='Libs_ScriptAculoUs'` — shipped as
`modules/Base/patches/20260822_remove_orphaned_scriptaculous_module_row.php`
— was the right, low-risk call here specifically.

**How to apply next time:** an orphaned `modules` row is *not* an automatic
"always leave it" — check the pre-removal `*Install.php` (via `git show
<removal-commit>^:<path>`) for what `install()` actually did before deciding.
No schema ever created (like this one) → a plain `DELETE FROM modules WHERE
name=...` patch is safe and worth shipping. Real schema, or an `*Install.php`
that might still need to stay loadable → leave it alone, same as CKEditor/
OpenFlashChart/`Develop_*` above.

## `FORCE_CACHE_COMMON_FILES` common-class bundle (removed 2026-08-31)

Every module's `*Common_0.php` used to be concatenatable into one cached file,
`temp/data/cache/common.php`, so `ModuleManager::load_modules()` could do one
`require_once` instead of ~71. The flag defaulted off since inception; the same day it
was turned on for fresh installs (`5e3ed0378`, part of `optimization-plan-opus.md` item
2.5), then reverted (`df9a0cf82`) once actually measured, then the whole mechanism was
cut. **If a future performance pass proposes reintroducing "bundle the Common classes",
read this entry and the measurement below first — it has already been tried twice and
measured once.**

**Why it existed.** `optimization-plan-opus.md` §A3 framed `load_modules()`'s per-request
cost as "~71 `require`s + `file_exists()` calls", and the bundle turns that into one. That
framing implied an order-of-magnitude win.

**What it actually measured.** Web SAPI, opcache on, 95 modules, local disk:
`load_modules()` cost ~13.5 ms bundled vs. ~17 ms unbundled — **~3.5 ms per request**,
about 1% of a 245 ms page render. Under CLI, where opcache is off, it measured **worse**
than nothing (~83 ms bundled vs. ~79 ms unbundled). The reason generalizes past this
codebase: without opcache, the compiler does identical work whether the code arrives as
71 files or 1 — only the file-open overhead is saved, and that is small on local disk.
With opcache, compilation is already cached either way, so what remains is the same small
overhead. **The bundle can only ever save `stat`/open cost, never compilation cost** — the
"71 requires become 1" framing invites exactly the opposite assumption, and it was the
plan's own author who made it.

**What it cost, against that ~3.5 ms:**

1. **Silently stale code, no warning.** The bundle is used whenever `file_exists()` is
   true, with no timestamp/hash check — so an edit to any `Common_0.php` had zero effect
   until an explicit `cache:rebuild`. Confirmed live on this machine 3 times (2026-08-13,
   2026-08-25, and implicitly every time the flag was on) — see `bug-patterns.md`'s
   "turning a config constant into a runtime flag" entry and `environment-gotchas.md`'s
   former `FORCE_CACHE_COMMON_FILES` section for the incidents.
2. **A duplicate `use` across two Common files was an instant fatal for the whole app,**
   because every module's Common class became one PHP compilation unit. `CRM_Calendar`
   and `Premium_PasswordManager` both `use Symfony\Component\HttpFoundation\Request` —
   installing that Premium module would have fataled the entire application under the
   bundle. Never hit in practice only because that module was never installed here.
3. **A Common class loaded outside the module system (a raw `require_once`, bypassing
   `include_common()`) got re-declared by the bundle — `E_COMPILE_ERROR`, the whole
   request down.** This is what actually happened: `modules/Libs/RoundCube/RC/config/
   config.inc.php` raw-requires `MailCommon_0.php` because that bootstrap deliberately
   skips the module system, and the webmail client stopped loading within an hour of the
   flag going on (`5e3ed0378` → broke it; `e257b7a93` shipped a same-day fix keyed on
   `get_declared_classes()` instead of the guard's own bookkeeping — moot now that the
   bundle itself is gone, but the fix commit is worth reading for the mechanism). The
   visible symptom, a page of literal `?>?>?>…`, was a second bug layered on the first:
   `create_common_cache()` force-appended a `?>` separator, but 128 files already ended
   with their own, and two close tags in a row leave everything between them in HTML
   mode — 85 stray `?>` were sitting in every generated bundle, invisible until the
   compile error stopped the output buffer from being discarded normally.

**What was removed.** `ModuleManager::load_modules()`'s bundle branch, `create_common_cache()`,
`any_common_already_declared()`, `$individually_loaded_commons` and its bookkeeping write,
the `FORCE_CACHE_COMMON_FILES` and `CACHE_COMMON_FILES` (the latter was dead — defined,
never read, since before this investigation) constants and their `include/config.php`
defaults, every `create_common_cache()` call site (`console/CacheRebuildCommand.php`,
`console/RebuildAllCommand.php`, `admin/modules/ClearCache.php`, two install/upgrade/
patch call sites), and the config-checklist row in `admin/modules/ConfigInfo.php`.
`console.php cache:rebuild` and admin "Clear Cache" still exist and still do something
real — they call `Cache::clear()`, which is unrelated general-purpose caching (menu
lookups, `check_common_methods()`, language merges) that this removal does not touch.

**How to apply if this is proposed again.** Don't re-derive the "71 requires → 1" framing
from a fresh reading of `load_modules()` — it is the exact framing that led here twice.
Measure first, under the web SAPI with opcache on (CLI figures are meaningless — opcache
is off there and inflates every file-loading number 4-5x). If a case for it appears on a
network filesystem — the one scenario not measured here, where `stat`/open overhead is
genuinely large — treat that as a fresh proposal with fresh numbers from that environment,
not a reason to restore this code unmeasured.

## Multi-tenant hosting (host → `DATA_DIR`/DB mapping) removed (2026-09-01)

Epesi could once serve several tenants from one codebase: `include/data_dir.php`
`include_once`d an optional, untracked, repo-root `map.php` defining a `$virtual_hosts`
array (host string/regex → data-dir name, `false` for "forbidden", or a redirect spec).
Whichever `DATA_DIR` it picked determined which `<DATA_DIR>/config.php` got loaded next
in `include/config.php` — and that file is where `DATABASE_HOST/USER/PASSWORD/NAME` live,
so "different data dirs" meant "different databases" for free, routed purely off the
incoming `Host` header. No `map.php` ever shipped in this repo — confirmed absent, and
`phpstan-baseline.neon` had carried a standing suppression for exactly that missing
`include_once()` since before this investigation, i.e. static analysis had been tolerating
it as permanently gone. Removed at explicit user request as no-longer-used.

**What was removed:**
- `include/data_dir.php` — collapsed from the full `map.php`/`$virtual_hosts` matching
  loop (regex host matching, redirect-spec handling, `die('Forbidden')`, a CLI
  `readline()` prompt for the install URL, and a `hosting/`-directory redirect fallback)
  down to `if (!defined('DATA_DIR')) define('DATA_DIR', 'data');`. Every consumer
  (`ModuleManager::get_data_dir()`, `TEMP_DIR`, `include/backups.php`, patches, etc.) only
  ever reads the `DATA_DIR` constant as an opaque string, so nothing downstream changed.
- The CLI-only `DATA_DIR` override knobs that existed solely so one crontab/console
  invocation could target a different tenant per call (HTTP requests have a `Host` header
  to resolve from; CLI scripts don't): `console.php`'s `--data-dir` option (and the now-
  unused `ArgvInput`/`InputOption` imports), `update.php`'s "any bare CLI arg that isn't
  `-f`/`-b` is the data dir" parsing (CLI usage is now `update.php [-f] [-b]`, was
  `update.php <data_dir> [-f] [-b]`), and `cron.php`/`monitoring.php`'s `$argv[1]` checks.
- The `phpstan-baseline.neon` suppression for `map.php` not existing (`include/data_dir.php`).
- The "multi-tenant installs sharing one codebase" framing in `include/config.php`'s
  `TEMP_DIR` comment — the `temp/<DATA_DIR>` nesting itself was kept (still legitimately
  useful for excluding regenerable cache from data backups in one shot), just reworded
  since the multi-tenant justification no longer applies.

**No patch shipped** — this is bootstrap logic, not stored/seed data or an `*Install.php`
change, so there's nothing for existing installs to migrate; the code change alone reaches
every install on next deploy.

**How to apply.** If a future request wants multi-host/multi-tenant routing back, it needs
to be designed and built fresh — don't restore `map.php`/`$virtual_hosts` handling from git
history, and don't reintroduce a CLI `--data-dir`-style override without a concrete reason
one is needed again (there was no other use for it once per-tenant routing was gone).

## Legacy `Applets/{Birthdays,Calc,Google,Host,Weather}` deleted outright (2026-09-01)

At explicit user request, five of the nine `modules/Applets/*` dashboard applets were
deleted from the tree entirely — not just uninstalled — so they no longer appear in either
Advanced or Simple Setup on any install, fresh or existing: `Birthdays` (see the update to
the entry above), `Calc` (calculator), `Google` (a bare Google search box), `Host` (prints
the server's IP/hostname), `Weather`. `Clock`, `MonthView`, `Note`, and `RssFeed` were kept
— **MonthView was initially deleted in the same pass, then explicitly restored** after the
user caught the mistake; if MonthView ever looks like a target for this same cleanup again,
confirm first, this is not an oversight.

**Zero schema risk**: all five removed applets' `install()` only ever called
`Base_ThemeCommon::install_default_theme()` (theme registration, no `CREATE TABLE`, no
`Variable::set()`), confirmed by reading each `*Install.php` before deleting — same
"nothing to leak" case `Libs_ScriptAculoUs` documents above. On this dev instance, four of
the five (`Calc`, `Google`, `Host`, `Weather`) were already not installed; `MonthView` (kept)
was Active and untouched. **No uninstall patch shipped** for any of the five, on the same
"tolerated orphaned row" reasoning as the `Develop/*` removal above — a stray `modules`
table row on another install that happened to have one of these installed self-quarantines
via `MODULE_NOT_FOUND` with no fatal error, and there's no schema for a cleanup patch to
drop anyway.

**Also removed**: their `Applets/*` lines in `modules/FirstRun/distros.ini`'s `[CRM
installation]` preset (including a pre-existing duplicate `Applets/Calc` line, unrelated to
this cleanup, removed in the same edit) so a fresh install's first-run wizard doesn't try to
install a module that no longer exists.

**Confirmed zero cross-references** in the tracked tree (`git grep`) and in the gitignored
`modules/Premium/`/`modules/Custom/` trees (plain `grep`, per `CLAUDE.md`'s Premium-sweep
note) before deleting — nothing `requires()`'d any of the five. The one real coupling found,
`CRM_Calendar`/`Utils_Calendar`'s `scope=mine` query param and the `jump_to_date`/
`switch_to_tab`/`open_add` deep-link handling in `CRM_Calendar::body()`, turned out to be
**MonthView's**, not one of the five actually removed — moot once MonthView was restored,
left untouched.

**How to apply.** If a future request wants any of these five back, they need to be
restored from git history (`git log --diff-filter=D -- modules/Applets/<Name>`) — don't
recreate them from scratch, the originals are simple but not trivial to rebuild faithfully
(e.g. `Weather`'s RSS-based fetch/cache in `refresh.php`/`rsslib.php`).

## "Additional applets" / "Error reporting" / "Web Notifications" folded into Epesi Core, no longer separately toggleable (2026-09-01)

Companion change to the removal above, same request. `Applets_Clock`, `Applets_Note`,
`Applets_RssFeed`, `Applets_MonthView`, `Base_Error`, and `Base_Notify` all used to declare
their own `'option'=>...` key in `simple_setup()` (`Setup_0.php`), making each its own row
under Epesi Core's "Optional" dropdown on the Simple Setup screen — see
`Simple-setup-ESS.md` for how that grouping works. All six were changed to the plain
`return __('Epesi Core');` shape ~40 other always-bundled core modules already use (no
`option` key at all), which merges them into the *same* `$packages['Epesi Core']` bucket as
everything else — see `Base_Setup::simple_setup()`'s `foreach ($structure as $s)` loop,
keyed on `$s['package'].($s['option']?'|'.$s['option']:'')`. **Net effect**: none of these
six can be individually installed/uninstalled from Simple Setup anymore — only as part of
uninstalling all of Epesi Core, which is already blocked (`'core'=>1` on `Base_SetupInstall`
covers the whole merged bucket). The "Optional" toggle itself disappears from the Epesi Core
card entirely once `$package.options` is empty (`theme_adminltedark/default.tpl`'s
`{if !empty($package.options)}` guard) — verified live via screenshot, matches the CRM
package's own still-populated Optional dropdown (Account Manager, Contact Photo, etc.,
unrelated and untouched) staying exactly as before.

**One collision avoided**: `Base_NotifyInstall::simple_setup()` used to also return
`'version'=>self::version` (its own `'2.0'`) — harmless while it had its own dedicated
`'Epesi Core|Web Notifications'` packages-array key, but `$packages[$key]['version']` is
set unconditionally whenever `isset($s['version'])`, with **no "first/identity wins" guard**
(unlike `readme_id`, which explicitly has one — see `Simple-setup-ESS.md`). Once merged into
the shared `'Epesi Core'` key, that assignment would have raced `Base_SetupInstall`'s own
`'version'=>EPESI_REVISION` (the real CalVer shown on the card) depending on module scan
order, potentially showing "2.0" instead of the real product version. Dropped the `version`
key when removing `option` — none of the other ~40 plain-string `Epesi Core` modules set
their own version either, so this just brings Notify in line with that convention.

Each of the six modules' `README.md` ("...listed under its Optional list as **X**") was
also updated to stop describing a UI element that no longer exists for them.

**How to apply.** If a future request wants any of these six to be independently
installable/uninstallable again, restore the `'option'=>__('...')` key in that module's
`simple_setup()` (and, for `Base_Notify` specifically, re-add `'version'=>self::version` —
safe again once it's back to its own dedicated packages key). Don't do this silently as a
side effect of an unrelated Setup-screen change; confirm first, per this file's header.
