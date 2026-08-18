# Premium/Import guided wizard — plan

Planned and approved 2026-08-18. Extends into a shared `Utils_Wizard` AdminLTE template used by both
`Premium/Import` and `FirstRun`.

**Status: implemented and verified live** (upload → CSV parse → destination → mapping → fixed values →
duplicate handling → review → import → done, full round trip, records landed correctly in Contacts). Two
real bugs only surfaced by actually driving it in a browser, worth remembering:

1. **`Premium_Import_Temp_Worksheet::set_recordset()` unconditionally deletes and recreates the mapping** -
   harmless for the standalone `select_recordset()` screen (visited once, then navigated away from via
   `set_back_location()`), but the wizard re-submits its pre-filled "Destination" step every time the user
   goes Prev→Next, or resumes after a full page reload - silently wiping out all completed mapping work each
   time. Fixed in the shared `recordset_apply()` helper: no-op if the submitted recordset equals the
   worksheet's current one. Check any other `Utils_Wizard`-driven step for the same "resubmitting unchanged
   data destroys state" shape before assuming a first-page-of-a-multi-step-form is safe to re-visit.
2. **`Utils_GenericBrowser` (like `Utils_Wizard` itself) renders via a `<span id="...">` placeholder that a
   *separate* pass of `Epesi::process()` patches with the real content** (`include/module.php`'s
   `get_html_of_module()`, ~line 1126: a non-`is_inline_display()` module returns the placeholder only, and
   registers its real HTML into `Epesi::$content[$path]['value']` for that separate pass to pick up). This
   works one level deep (`Premium_Import` → `Utils_GenericBrowser`, the existing `import_preview()` screen)
   but is broken two levels deep (`Premium_Import` → `Utils_Wizard`'s `callback_page()` →
   `Utils_GenericBrowser`): the outer `Utils_Wizard` captures the inner placeholder as inert text via its own
   output buffering, and the real content never gets patched in - `display_module()` still reports success
   (`true`), the row/column counts going in are correct, but nothing visible comes out. Confirmed live by
   dumping `display_module()`'s return value and row counts right next to the empty space where the table
   should render. Fixed by giving the wizard's "Review & import" step its own plain, self-contained HTML
   table (`wizard_review_table()`) instead of routing through `Utils_GenericBrowser` - no placeholder, no
   patch step, works at any nesting depth. `Utils_TabbedBrowser` (used by the "Match values" step,
   `common_data_body()`) is architecturally the same shape and hasn't been exercised by any test data yet -
   check this first if that step ever renders empty.

Also found and fixed opportunistically: `Utils_Wizard`'s own `curr_page`/`history` (stored via
`get_module_variable`) don't survive a full page reload (a fresh top-level page load gets a fresh
module-variable scope) - without a fix, reloading mid-wizard always restarted at step 1. Fixed by computing
an initial `$start_page` from the worksheet's actual state (recordset set? mapping valid? etc.) before
constructing the `Utils_Wizard` instance - only affects the *very first* render of a fresh instance, normal
Prev/Next navigation is unaffected.

**Known minor issue, not yet fixed:** the action bar on the final "Done" screen still shows leftover buttons
("Autodetect columns", "Duplicate checking setup", "Find duplicates", "Select all", "Select none") from
earlier steps. Cause: `Base_ActionBarCommon::add()` accumulates globally per-request, and
`worksheet_wizard()` unconditionally re-runs every step's field-building code every request (the same
pattern `FirstRun` itself uses) - including the `Base_ActionBarCommon::add()` calls inside steps that aren't
the active one. Cosmetic only (clicking a stale button just harmlessly reloads), not chased further this
session.

**Not yet exercised by live testing:** the multi-sheet-XLSX "choose worksheet" picker, the chunked
parse/import progress screens for a file large enough to hit `Import/FileProcessingLimit`/`Import/ImportLimit`,
the "Match values" step (needs a commondata field actually mapped via a file column, not manual), and
`Develop/ModuleCreator`'s nested (`level=1`) captions rendering through the new shared stepper template.

## Context

`modules/Premium/Import` (a separately-licensed, gitignored submodule — its own nested git repo at
`modules/Premium/Import/`, remote `jtylek/Premium-Import`) exposed its whole import flow as two flat
`Utils_GenericBrowser` tables (`files()` = uploaded-file queue, `worksheets()` = parsed-worksheet queue),
each row driven by up to 12 tiny icon actions that silently enable/disable based on hidden business-rule
state (`Premium_Import_Temp_Worksheet::get_status()`). Some steps (date format, duplicate checking) were
only reachable via a text link buried in a table cell or a secondary action-bar button on *another* screen.
No sense of "step N of M," no plain-language help, raw batching internals ("processing will be performed in
parts with up to %d rows... change Import/ImportLimit in commondata") leaking into a confirm dialog. The
underlying import engine (recordset targeting, column mapping, manual fields, common-data value mapping,
date-format detection, duplicate checking, chunked parse/import) is solid and unchanged by this work — only
navigation/presentation become linear and guided.

## Key finding: `Utils_Wizard` already exists and is production-used

`modules/Utils/Wizard/Wizard_0.php` (`Utils_Wizard`) is a real multi-step-form engine — validation-gated
`next_page()`, back/forward history, named/conditional pages via `delete_page()`, arbitrary step content via
`callback_page()` (output buffering captures whatever a callback echoes, not just QuickForm fields) — and
already drives `modules/FirstRun/FirstRun_0.php` (EPESI's in-app first-run/setup wizard: welcome → admin
user → mail settings → SMTP → warning → done) and `modules/Develop/ModuleCreator/ModuleCreator_0.php` (a dev
tool; the only real consumer using nested `set_caption($caption, 1)` sub-steps, for its dynamic per-table
definition pages).

`Utils_Wizard`'s own step-caption sidebar template (`modules/Utils/Wizard/theme/default.tpl`) is a bare
`<ul>`/`<li>` list, only bolding the currently-active caption — no AdminLTE styling, no done/upcoming
distinction. `FirstRun` worked around this by re-skinning that raw output with scoped CSS in its own
`theme_adminltedark/default.css` (`#FirstRun ul`/`#FirstRun ul li` rules) rather than fixing the template
itself.

**Template resolution is theme-aware per file, already codebase-wide** — confirmed via
`modules/Base/Theme/resolver.php:60-69` (`Base_ThemeResolver::resolve_uncached`): for the active
`adminltedark` theme it checks `theme_adminltedark/<file>` first, falling back to `theme/<file>` only if
that path isn't readable. `ThemeCommon_0.php`'s `display_smarty()` (~line 293-310) and `get_template_file()`
(~line 375-384) both route through this same resolver — so `.tpl` overrides work exactly like the `.css`
overrides already used throughout the app (48+ modules already ship a real `theme_adminltedark/*.tpl`, e.g.
`modules/Base/Box/theme_adminltedark/default.tpl`, `modules/Utils/RecordBrowser/theme_adminltedark/
Browsing_records.tpl`). Nothing about Import or FirstRun's rendering path is special-cased — dropping a
`theme_adminltedark/default.tpl` next to `Utils/Wizard`'s existing `theme/default.tpl` is picked up
automatically, for **every** consumer of `Utils_Wizard`, with zero code changes in FirstRun or ModuleCreator.

**Decision: build one real shared AdminLTE stepper template for `Utils_Wizard` itself**, instead of each
consumer re-skinning the bare `<ul>` with its own CSS hack:
- `modules/Utils/Wizard/theme_adminltedark/default.tpl` — horizontal step indicator (numbered circles +
  labels + connector lines) for flat (level-0) caption sequences — the case both `FirstRun` and the new
  Import wizard use. Per-step state (done / active / upcoming) is computed directly in Smarty by comparing
  each caption's position to `$active_caption_key`'s position within the `$captions` array (both already
  passed in by `Wizard_0::body()`) — no CSS `:has()`/sibling-selector tricks needed, since the template can
  just emit the right class per `<li>` outright.
  - `ModuleCreator`'s nested (`level=1`) sub-steps are the one other real consumer of the level mechanism;
    handled by rendering any level>0 captions as a small indented sub-list under their level-0 parent
    (not folded into the horizontal circle row) rather than building a fully general nested-stepper widget —
    proportionate to it being a rarely-used dev tool, not over-engineered for a case nothing else needs.
- `modules/Utils/Wizard/theme_adminltedark/default.css` — the actual step-circle/connector/label styling,
  plus the `[data-bs-theme="light"]` override block (matching the convention in every other
  `theme_adminltedark/default.css` in this codebase, e.g. `modules/FirstRun/theme_adminltedark/default.css`
  lines 152-179).
- Cleanup: remove `modules/FirstRun/theme_adminltedark/default.css`'s now-dead `#FirstRun ul`/
  `#FirstRun ul li` rules (verified they only ever targeted the Wizard's own bare list — FirstRun's outer
  shell template, `theme_adminltedark/default.tpl`, has no `<ul>` of its own, it just embeds `{$wizard}`
  verbatim). While in that file: verify whether the step-focus JS selector
  (`#FirstRun table#quickform input...`) still matches anything post the 2026-08-04 `<table>`→`<div>`
  QuickForm-renderer conversion documented earlier in that same file's own comment — if `#quickform` is now
  a `<div>`, not a `<table>`, that selector is dead and per-step autofocus has silently stopped working;
  fix opportunistically since it's directly adjacent, not a separate investigation.
- This supersedes the original plan's idea of Import re-skinning the bare `<ul>` itself the same way FirstRun
  did — Import's wizard now gets a real stepper for free via the shared template, same as FirstRun does.

## Premium/Import wizard design

### Entry point & routing
`admin()` (`Import_0.php:34`) routes to a new `import_wizard()` instead of `files()`:
- No worksheets yet → Step 1 = upload.
- Exactly one incomplete (`!is_imported()`) worksheet → resume it directly, jumping to the step implied by
  its current `get_status()` (that method already *is* the linear step order, just re-surfaced as UI).
- Multiple incomplete worksheets (e.g. a multi-sheet XLSX fans out into one worksheet per sheet via
  `Premium_Import_File_File::create_items()`) → a small "Continue an import" picker (name, file, plain
  status, Continue/Discard) before entering the per-worksheet wizard.
- "Advanced: manage all files & worksheets" action-bar link → today's `files()` screen, unchanged (bulk
  reprocessing, stuck-item cleanup, CSV delimiter fixes stay available); reverse link added there too.

### Step sequence (one `Utils_Wizard` instance per worksheet)
Reuses existing screen bodies almost verbatim, each split into a shared `_body($worksheet, $form|null)`
helper plus two thin callers (the existing standalone method, kept for the Advanced/icon-driven path; a new
wizard-step method driving navigation via `$wizard->next_page()`). Optional steps skipped via
`$wizard->delete_page($name)` using the *same* checks the current icon on/off logic already uses:

1. Upload — `file_upload()` (`Import_0.php:78`).
2. Choose worksheet (only if the file has >1 sheet) — new, thin.
3. Parsing (auto-advancing progress, see below).
4. Choose destination (was "recordset") — `select_recordset()` (`:396`).
5. Map columns — `mapping_editor()` (`:445`), including "Autodetect columns".
6. Fixed values (was "manual fields") — `manual_fields()` (`:597`); skipped when
   `!$mapping->has_manual_fields()`.
7. Match values (was "common data") — `set_common_data()`/`common_field()` (`:1144`/`:1173`,
   `Utils_TabbedBrowser`-based); skipped when `!$worksheet->has_common_data_to_mapping()`.
8. Date format — `date_format()` (`:1243`); skipped when `!$mapping->has_date_fileds()`.
9. Duplicate handling — `duplicate_checking()` (`:663`); safe to leave optional (no configuration → nothing
   flagged duplicate → every valid row stays selected, current default behavior).
10. Review & import — `import_preview()` (`:982`); one "Start import" CTA replaces the current split UI
    (row-checkbox "Add to queue" + separate action-bar "Import queue" button), queuing and beginning import
    immediately.
11. Importing (auto-advancing progress) → Done: counts, "View imported records", "Import another sheet",
    "Start a new import".

### Chunked parse/import (real technical constraint, not just UX)
Row-limited batching is deliberate (`Premium_Import_File_Queue::process()` /
`Premium_Import_Temp_Worksheet::import()` cap work per call via `Import/FileProcessingLimit` /
`Import/ImportLimit` commondata, avoiding request timeouts on large files) — kept, not collapsed. The
Parsing/Importing steps call `process()`/`import()` once per request and report progress; a small AJAX poll
(new `ajax_progress.php`, modeled on the existing `ajax_select.php`) repeatedly invokes the next chunk and,
once done, programmatically submits the wizard's own hidden `button_next` — no more raw confirm-dialog
batching text, no repeated manual "still processing" clicks for large files.

### Wording
New step copy only (backend class/method/table names untouched): "recordset" → "destination", "manual
fields" → "fixed values", "common data" → "match values", one short explanatory line per step. The raw
`Import/ImportLimit`-in-commondata confirm text is dropped entirely — chunking is now invisible.

## Files touched
- `modules/Utils/Wizard/theme_adminltedark/default.tpl`, `default.css` — new, shared stepper (benefits
  `FirstRun` and `Develop/ModuleCreator` too, no changes needed in either).
- `modules/FirstRun/theme_adminltedark/default.css` — remove now-dead `ul`/`li` rules; opportunistic
  `#quickform` selector fix if confirmed stale.
- `modules/Premium/Import/Import_0.php` — `import_wizard()` entry, worksheet picker, per-step wrappers,
  `_body` extraction from the six reused screens, Advanced/Guided cross-links.
- `modules/Premium/Import/ajax_progress.php` — new, modeled on `ajax_select.php`.
- `modules/Premium/Import/js/` — new small poll/auto-advance script alongside existing `js/ajax_select.js`.
- `modules/Premium/Import/theme_adminltedark/default.css` — Import-specific layout only (no stepper CSS —
  that's now shared); existing row-icon glyph rules untouched (still used by the unchanged Advanced screens).
- `modules/Premium/Import/lang/en.php` — new `__()` strings; other languages can lag (existing
  fallback-to-literal convention).
- No changes to `Utils_Wizard`'s PHP logic, `FirstRun_0.php`, or `ModuleCreator_0.php` — template-only.

## Verification
- `C:\xampp82\php\php.exe -l` on every new/changed PHP file.
- Launch the app; drive the Import wizard end-to-end: small single-sheet CSV; multi-sheet XLSX (worksheet
  picker + "import another sheet" loop); a file large enough to force multi-request chunked parsing/import;
  an unmapped-required-field mapping (validation blocks Next); mappings touching commondata/date/manual
  fields (each optional step appears only when relevant); resuming mid-wizard after navigating away;
  imported rows landing correctly in the destination RecordBrowser tab.
- Confirm `files()`/`worksheets()` (Advanced) still work exactly as today.
- Re-verify `FirstRun` (the actual EPESI first-run wizard) still renders correctly end-to-end with the new
  shared stepper template — this is a shared-module change, regression risk is real, not hypothetical.
- Screenshot/click through both the Import wizard and FirstRun in AdminLTE light and dark mode via
  Playwright MCP tools — this module has had light/dark parity bugs before.
