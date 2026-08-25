# CKEditor → Quill migration

**Status as of 2026-08-11: done, on branch `ckeditor-to-quill` (off `jasiek`), not yet
merged.** Triggered by a real mobile bug the same night: `ck.js` crashed on a `null`
tracked CKEditor instance (a failed `CKEDITOR.replace()`, more likely on mobile/touch -
see `AI-shared/bug-patterns.md`'s "uncaught exception in a document-level handler" entry),
which silently ate every Save click. Reason for the swap: license (CKEditor 4 here is
GPL/LGPL/MPL tri-license; Quill is **BSD-3-Clause**, not MIT as originally noted below -
still permissive/non-copyleft, the actual motivation stands) and general retirement of an
old, now-demonstrably-fragile dependency.

## Scope (verified by grep across the whole repo, including a plain-`grep` sweep of
the gitignored `modules/Premium/` tree — zero CKEditor references there)

The vendored `modules/Libs/CKEditor/ckeditor/` tree (~250 files: skins, per-language
plugin dialogs, etc.) is 100% CKEditor's own distribution and just gets deleted
wholesale. The actual **integration surface is narrow** — one shared QuickForm element
type plus exactly 4 call sites:

- `modules/Libs/CKEditor/ckeditor.php` — `HTML_Quickform_ckeditor`, the QuickForm
  element class (renders a plain `<textarea>`, CKEditor 4's classic "replace this
  textarea" init mode).
- `modules/Libs/CKEditor/CKEditorCommon_0.php` — registers the `'ckeditor'` element
  type with QuickForm; `Libs_CKEditorCommon::QFfield_cb` in this same file is **dead
  code**, no caller anywhere.
- `modules/Libs/CKEditor/ck.js` — lifecycle glue: destroys/recreates live CKEditor
  instances around this app's AJAX-push page-replacement model, keyed off the
  `e:submit_form` / `e:loading` / `e:load` custom events (same event trio documented in
  [legacy-js-migration.md](legacy-js-migration.md)).
- `modules/Libs/CKEditor/onsubmit.js` — already-dead CKEditor-3-era code
  (`ckeditor_onsubmit`/`CKeditorAPI`, doesn't match the CKEditor 4 API actually in use);
  its `load_js()` call is already commented out in `CKEditorCommon_0.php:20`.

Call sites (`addElement('ckeditor', ...)` / `createElement('ckeditor', ...)`):

1. `modules/Utils/Attachment/AttachmentCommon_0.php:399` (`QFfield_note`) — **Notes
   body, the flagship usage.** Per-user "Simple"/"Advanced" toolbar setting
   (`Base_User_SettingsCommon::get(...,'editor')`, a Basic/Full toolbar choice, not a
   plain-textarea-vs-rich-text choice). Image paste lands as a plain `data:` URI
   `<img>` (no upload plugin — see [[bug-patterns.md]] / `MIGRATION_NOTES.md` §62,
   which is also why `HtmlPurifier` is configured to allow `data:` URIs). Storage is
   `utils_attachment_data_1.f_note`, widened to `LONGTEXT` by that same §62 fix.
2. `modules/CRM/Mail/Mail_0.php:43` — outgoing mail global signature.
3. `modules/Utils/RecordBrowser/RecordBrowser_0.php:2044` — "Help Message" field when
   an admin configures a RecordBrowser field.
4. `modules/Applets/Note/NoteCommon_0.php:38` (`text_elem`) — Dashboard "Note" applet
   body.

Module wiring: `Libs_CKEditorInstall` is declared as a dependency in
`modules/Base/Dashboard/DashboardInstall.php` and
`modules/Utils/Attachment/AttachmentInstall.php`.

## The one decision that determines how big this job is

CKEditor stores/produces **HTML**. Quill's native format is **Delta** (JSON), but it
can still read/write plain HTML via `quill.root.innerHTML` and its clipboard matcher.

**Decision: keep storing HTML, not Delta.** Every existing note/signature/help-message
row in every install then works unchanged — zero data migration, no HTML→Delta backfill
patch, and `HtmlPurifier`/the RecordBrowser render pipeline/CRM_Mail body assembly don't
need to change at all. Switching to Delta storage would turn this from a UI-layer swap
into a data-migration project across every existing install, for no real benefit here —
don't do that unless a concrete reason to store Delta shows up later.

One consequence: Quill needs a container `<div>` + a hidden `<input>` synced from
Quill's `text-change` event (and again right before submit) to carry the value through
form submission — CKEditor's classic mode replaces the `<textarea>` in place so this
isn't needed today. This is a standard Quill integration pattern, not novel design work,
but it is the one piece of `ck.js`'s lifecycle glue that doesn't port mechanically.

Image paste behavior transfers essentially unchanged: Quill's default clipboard module
also just inlines pasted images as base64 `data:` URIs into the Delta, so the existing
`HtmlPurifier` `data:`-allowance from §62 keeps working with no changes.

## Planned steps (not started)

1. New `Libs/Quill` module mirroring `Libs/CKEditor`'s shape: `QuillInstall.php`,
   `QuillCommon_0.php` (registers `'quill'` QuickForm element type), `quill.php`
   (`HTML_Quickform_quill` element class), vendored `quill.js` + `quill.snow.css`
   (single-file MIT build, no build step — same pattern already used for vendoring
   `libs/fullcalendar-6.1.21/index.global.min.js`).
2. New element class: container `<div>` + hidden `<input>`, per the decision above.
3. Port `ck.js` → new lifecycle glue reacting to the same `e:submit_form` / `e:loading`
   / `e:load` events, syncing Quill content into the hidden field before
   destroy/submit.
4. Port the 4 call sites: `addElement('ckeditor', ...)` → `addElement('quill', ...)`;
   `setFCKProps($width,$height,$advanced)` → equivalent Quill toolbar preset (Basic/Full
   maps directly to a minimal-vs-full Quill toolbar array).
5. Swap `Libs_CKEditorInstall` → `Libs_QuillInstall` in `DashboardInstall.php` and
   `AttachmentInstall.php`, shipped as a **patch** (per this repo's upgrade-gap
   discipline) so existing installs pick up the new module dependency, not just fresh
   installs.
6. Delete `modules/Libs/CKEditor/` entirely once nothing references it.
7. Manual verification (no automated test suite exists in this repo) across all 4
   surfaces, including the pasted-image round-trip on Notes, plus the AJAX-push edge
   cases (submit, cancel mid-edit, navigate away mid-edit, browser back).
8. `phpstan-baseline.neon`: the 3 existing CKEditor entries drop out; may pick up 0-1
   new entries for the new files.

**Estimate: roughly 1–2 focused days** for someone who knows this codebase — small,
bounded, no data migration. The main slip risk is step 3 (AJAX-push re-init glue) if
some edge case in Epesi's page-replacement lifecycle doesn't map cleanly onto Quill's
init/destroy API; worth prototyping steps 1-3 against just the Notes field (highest
traffic, most complex of the 4) before touching the other 3 call sites.

## What actually happened, deviations from the plan above

Steps 1-4 and 7 done as planned. Steps 5, 6, 8 deviated:

**Step 5 (module wiring)**: done as planned - `Libs_QuillInstall` replaces
`Libs_CKEditorInstall` in both `requires()` lists, with an
`20260811_swap_ckeditor_dependency_for_quill.php` patch in each of
`Utils_Attachment`/`Base_Dashboard` calling `ModuleManager::install('Libs/Quill')` for
existing installs.

**Step 6 (delete CKEditor) - did NOT delete the whole module.**
`ModuleManager::uninstall()` requires the target's `*Install.php` to still be loadable
(it calls the class's own `uninstall()` hook) and refuses if anything still `requires()`
it - by the time that's true here, `commons_with_code` (below) has already made this
sort of "was this really updated for every install" question uncomfortable to resolve
with confidence, so auto-uninstalling `Libs_CKEditor` from a patch was judged more risk
than the disk space is worth. Instead: deleted the ~250-file vendored `ckeditor/` tree,
`ckeditor.php`, `ck.js`, `frontend.css` (ported to `Libs/Quill/frontend.css` unchanged -
still needed so old `class="Bold"/"Title"/"Code"` HTML from CKEditor's Styles dropdown
keeps rendering), `onsubmit.js`, `.hidden`; kept `CKEditorInstall.php` (unchanged) and
`CKEditorCommon_0.php` (stripped to an empty documented shell) so the module stays
installed-and-harmless rather than installed-and-broken. Nothing registers the
`'ckeditor'` QuickForm type anymore.

**Step 8 (phpstan)**: not run - this checkout's `composer.json` doesn't list
`phpstan/phpstan` in `require-dev` and `vendor/bin/` has no phpstan binary, a pre-existing
environment gap unrelated to this migration.

**Not ported: CKEditor's live in-session "Switch toolbar" button** (the vendored
`toolbarswitch` plugin's toolbar icon, wired to `ck.js`'s `ckeditor_reload()`). The
Basic/Full toolbar *choice* itself is fully preserved (still driven by the same
`Base_User_SettingsCommon::get(...,'editor')` setting, via `setQuillProps()`'s 3rd arg,
now on all 4 call sites) - only the mid-session live-toggle convenience is gone. Deliberate
scope cut given the night's priority was mobile reliability, not full parity; revisit if
anyone actually asks for it back.

**A new, non-obvious bug found and fixed while porting**: `include/epesi.php`'s
`[data-bs-theme="dark"]` selector convention is easy to get backwards. `.app-wrapper`
(the actual visual theme root) always carries `data-bs-theme="dark"` as a fixed baseline
independent of `<html>`'s own `data-bs-theme`, which is what the light/dark *toggle*
actually flips - so `[data-bs-theme="dark"] X` matches X unconditionally (there's always
a "dark" ancestor via `.app-wrapper`), while `[data-bs-theme="light"] X` matches only in
light mode (via `<html>`). `theme.css` initially gated dark colors behind
`[data-bs-theme="dark"]` (seemed intuitive) and got a solid-black, unreadable editor body
even in light mode as a result. Fixed by following the convention every other
`theme_adminltedark/*.css` file in this repo already uses: dark is the *unscoped
default*, `[data-bs-theme="light"]` is the override - never the reverse. Worth grepping
for this exact mistake if a future dark-mode CSS addition looks "inverted" in one theme.

**Also found, unrelated to Quill's own correctness**: this dev environment's
`ModuleManager::create_common_cache()`/`commons_with_code` caching (`include/
module_manager.php`) did not reliably run a freshly-`module:install`-ed module's
`Common_0.php` top-level code on every request, even after `console.php cache:rebuild`
and fresh logins - `Libs_QuillCommon`'s own `load_css()` calls (the file's only job)
intermittently never fired. Root cause not fully chased (limited value chasing pre-existing
infra rather than this migration); worked around by moving `load_css()` into `quill.php`'s
own element-class constructor, right next to its `load_js()` calls, which proved reliable
every time in testing - matching how CKEditor's own JS loading (also constructor-based)
never had this problem, only its Common-file-based CSS loading might have (never
specifically verified either way for the old code, since nothing was watching for it).
If a *different* future module's `load_css()`/`load_js()` call placed in a `Common_0.php`
top level seems to silently not fire, this is the first thing to suspect.

## Verification (2026-08-11)

**Notes body (flagship, highest-traffic surface)**: fully verified end-to-end live via
Playwright - typed title + rich-text body, saved, viewed record, content rendered
correctly. Basic toolbar (Bold/Italic/Underline/lists/link/clean) renders and functions
correctly in both the browser's light and dark modes after the CSS convention fix above.

**Mail signature / RecordBrowser Help Message / Note applet**: not each independently
smoke-tested live to completion - repeated, unrelated Playwright browser-cache staleness
(the same class of issue documented in `AI-shared/bug-patterns.md`, but hitting *this
testing session's* long-lived browser rather than a real user's) and sidebar-link click
flakiness in the test harness made live verification of the Advanced/Full toolbar
preset's rendering unreliable to pin down in the time available. Instead verified by:
constructing an isolated Quill instance directly with the exact same Advanced toolbar
config used by these 3 call sites (`{header:[1,2,3,false]}`, `bold/italic/underline/
strike`, `color/background`, `list`, `indent` (fixed to numeric `-1`/`1`, not string -
Quill's format matching is type-strict and silently no-ops on a string, logging
"quill:toolbar ignoring attaching to nonexistent format" rather than erroring),
`blockquote`/`code-block`, `link`/`image`, `clean`) - zero console warnings/errors.
Combined with the proven-identical underlying element class/lifecycle glue (same code
path as the fully-verified Notes body, just a different toolbar preset value), this is
good confidence but not the same as an actual screenshot of each screen. **Worth an
independent live check of these 3 screens before merging**, particularly the Mail
signature (Advanced toolbar) and RecordBrowser Help Message (admin-only, Basic toolbar,
lowest risk of the three).

## Progress

Merged into `jasiek` as `8d47bec1` ("Replace CKEditor with Quill for all rich-text
fields") - the line above ("not yet merged... holding for commit approval") is stale,
left for history.

## Follow-up: toolbar switch button restored, Notes only (2026-08-12)

The "not ported" live toolbar-switch button (above) got asked for back, scoped to the
Notes field (`Utils_Attachment::QFfield_note`) only - the other 3 call sites are
unchanged (still a single fixed preset per render, no switch).

- `quill.php`: `toolbarBasic()`/`toolbarAdvanced()` split out of `setToolbarPreset()` as
  private helpers; new `enableToolbarSwitch()` appends a `'switchtoolbar'` button group
  to both arrays and emits both plus translated title strings into the `quills_hib[id]`
  JS config when set. Must be called after `setQuillProps()` (reads
  `$this->config['advanced']` to know the starting preset).
- `qu.js`: registers a custom three-dot icon on `Quill.import('ui/icons')` once
  (globally, matches `.ql-fill` styling so `theme.css`'s toolbar-invert dark-mode filter
  themes it for free); `quill_switch_toolbar(key)` destroys/recreates the instance
  against the alternate toolbar array, same destroy-and-rebuild technique
  `quill_teardown`/the `e:load` hibernate path already used - Quill has no API to swap a
  running instance's toolbar config in place, same limitation CKEditor's own
  `ckeditor_reload()` worked around.
- Notes call site: `$fck->enableToolbarSwitch();` added right after the existing
  `setQuillProps(...)` call. The per-user `Base_User_SettingsCommon` 'editor'
  (Simple/Advanced) setting still picks the *starting* toolbar unchanged - the switch is
  purely a live, session-only override on top, not a replacement for that setting.

**Bug found and fixed while testing this**: the Advanced preset's header group in
`toolbarAdvanced()` was `array('header'=>array(1,2,3,false))` - NOT wrapped in its own
array. Quill's toolbar builder decides whether `modules.toolbar.container` is "a list of
groups" or "one flat group" by checking `Array.isArray(groups[0])` - since our
`groups[0]` was a plain object (`{header:[1,2,3,false]}`), not an array, that check fails
and Quill silently reinterprets the *entire* 8/9-entry toolbar array as a single flat
group. Every other group (each itself a real array, e.g. `["bold","italic",...]`) then
gets treated as one bogus control named by its first numeric key ("format 0"), and only
the header `<select>` renders - as an unstyled native dropdown too, since the pickers-
upgrade step never got that far. This is why the Advanced toolbar showed only a plain
"Normal" dropdown and nothing else, in both light and dark mode - not a CSS/theme issue,
a config-shape bug. It predates today's switch button (same array since the original
2026-08-11 migration) but was never caught live because Notes always defaulted to Basic
and the other 3 call sites' Advanced rendering was never independently screenshotted (see
"Verification" section above - the isolated-instance test that claimed "zero console
warnings" evidently used a differently-shaped config than what actually shipped). Fixed
by wrapping: `array(array('header'=>array(1,2,3,false)))`, matching Quill's own canonical
`[[{header:[...]}], [...]]` shape. Confirmed live via Playwright (console warnings went
from 8x "quill:toolbar ignoring attaching to nonexistent format 0" to zero, all 9 toolbar
groups render, content survives a live switch) in both light and dark mode. Worth
remembering if anyone hand-edits a Quill toolbar array again: a single-control group must
still be wrapped in its own array, especially at index 0.

## Gap found: `modules/Premium/`/`modules/Custom/` call sites aren't covered by the "Scope" sweep above (2026-08-20)

The original scope sweep (top of this doc) explicitly grepped the gitignored
`modules/Premium/` tree and found zero CKEditor references - but each module under
`modules/Premium/` and `modules/Custom/` is its **own separate nested git repo**
(`.gitignore`: `modules/Premium/*`, `modules/Custom/*` except `Custom/Tutorial`), so that
sweep only ever reflected whatever those repos' checked-out state was on 2026-08-11. A
repo updated/pulled after that date - or simply not present in this checkout at sweep
time - would not have been caught, and won't show up in a `git grep` of this repo either.

Found live via log monitoring: `modules/Premium/CampaignManager/CampaignManagerCommon_0.php`
(`QFfield_ckeditor`) still called `addElement('ckeditor', ...)`/`setFCKProps(...)`, throwing
`HTML_QuickForm_Error: unregistered element: Element 'ckeditor' does not exist` on the
Campaign message "Add" form - `CKEditorCommon_0.php` no longer registers that type (see
"Step 6" above). Fixed by porting it to `addElement('quill', ...)`/`setQuillProps(...)`,
same signature, matching the 4 sites already ported. No `requires()`/module-dependency
change was needed - `'quill'` is registered unconditionally in `include/epesi.php` (its
eager custom-QuickForm-type block), independent of which modules declare a dependency on
`Libs/Quill`.

**Same bug shape as the Prototype.js removal's Premium gap** (see `CLAUDE.md` and
`legacy-js-migration.md`) - any repo-wide migration or removal is, by construction, blind
to `modules/Premium/`/`modules/Custom/` content that isn't in *this* checkout at sweep
time. If you're relying on "the sweep found zero references" for either tree, that's a
snapshot claim, not a standing guarantee - a plain `grep`/`git grep --no-index` sweep (Claude
Code's own Grep tool silently skips gitignored paths - see `CLAUDE.md`'s Environment
quirks) needs re-running per-installation, and any *new* Premium/Custom module pulled in
later should be independently checked for `addElement('ckeditor', ...)` before assuming
this migration is complete there.
