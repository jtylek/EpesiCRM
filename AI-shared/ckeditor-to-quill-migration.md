# CKEditor → Quill migration (planned, not started)

**Status as of 2026-08-07: planning only, zero code changed.** Reason for the swap:
license (CKEditor 4 here is GPL/LGPL/MPL tri-license, not MIT; Quill is MIT) and general
retirement of an old dependency. **Do this on its own branch, not mixed into other work.**

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

## Progress

None yet — plan only, as of 2026-08-07. Do this work on a dedicated branch.
