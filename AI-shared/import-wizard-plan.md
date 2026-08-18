# Premium/Import guided wizard — plan

Planned and approved 2026-08-18. Extends into a shared `Utils_Wizard` AdminLTE template used by both
`Premium/Import` and `FirstRun`.

**Status: implemented and verified live** (2026-08-18). `Premium/Import` commits so far: `cf24218..ede708f`
on the module's own nested repo (`jtylek/Premium-Import`, pushed - includes the "Match values" fix (bug #3),
the sticky-callback-hijacking fix (bug #4, `7ac63c0`), the Permission-column case-sensitivity fix (bug #5,
`ede708f`), and an AdminLTE layout polish pass over the wizard's own screens, also `ede708f`); the shared
`Utils_Wizard` template plus `FirstRun` cleanup: `87e727bf..77ae10fc` on `migration` (`jtylek/epesi`, pushed).
Full round trip verified live four times (upload → CSV parse → destination → mapping → fixed values →
[match values] → duplicate handling → review → import → done), records landed correctly in Contacts each
time, including with an actual commondata (Match values) field mapped via a real file column.
Five real bugs only surfaced by actually driving it in a browser, worth remembering:

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
   patch step, works at any nesting depth.
3. **Confirmed the same shape hits `Utils_TabbedBrowser` too** - flagged as a risk in the first pass of this
   doc, then actually verified live: the "Match values" step (`common_data_body()`, `Utils_TabbedBrowser`
   with one tab per commondata column) rendered as just the "Common data setup" heading and Prev/Next, the
   entire tab area empty, same silent-success shape as bug #2. Fixed the same way: `match_values_fields()`/
   `match_values_apply()` build one flat `Libs_QuickForm` covering every commondata column's unique values
   (field names prefixed `<column_id>__<index>` to stay unique across columns, since they no longer get one
   form per tab) instead of routing through `Utils_TabbedBrowser`. The standalone `set_common_data()` screen
   keeps using `common_data_body()`/`Utils_TabbedBrowser`/`common_field()` unchanged (one level deep, works
   fine) - only the wizard path was rewired. **General lesson for this codebase: any child module normally
   reached via `display_module()` (`Utils_GenericBrowser`, `Utils_TabbedBrowser`, and presumably others with
   the same shape) is unsafe to nest two `display_module()` calls deep - verify empty-looking output by
   checking `display_module()`'s return value before assuming the underlying data/logic is what's wrong.**
4. **Reciprocal `create_callback_href()` cross-links between two screens silently hijack every *other*
   in-page action on both.** Reported by the user as "Clear worksheet list does not work now." Root cause,
   confirmed by reading `include/module.php`'s callback dispatch (`get_html_of_module()` ~line 1081) plus
   `create_unique_key()` (~line 1304) and `get_module_variable_or_unique_href_variable()` (~line 297):
   `create_callback_href($func)` calls `set_callback()`, which **re-registers that callback at the *end* of
   `__callbacks__` every time the link is merely *rendered*, not just when clicked** - and once a callback's
   own href has been clicked at least once, its "active" flag is *persisted* (`set_module_variable`) and
   stays true forever unless the callback itself returns falsy. The dispatch loop checks callbacks in
   `array_reverse()` order (most-recently-registered first) and stops at the first one whose flag is true.
   I had added an `import_wizard`-targeted "Back to guided import" button to both `worksheets()` and
   `files()`'s action bars. Since `import_wizard` had already been activated once (via `admin()`'s own
   `call_callback_href()` on the original menu click) and always returns truthy, merely *rendering*
   `worksheets()` (which draws that button) re-registered `import_wizard` as more recent than `worksheets`'s
   own already-active callback - so the *next* request against that screen (any unique-href action,
   including "Clear worksheet list", almost certainly also "Delete worksheet"/"Set delimiter"/etc.) got
   silently redirected into re-rendering the wizard instead, with `worksheets()`'s own action code never
   running at all. 100% reproducible; confirmed by direct DB inspection (`premium_import_temp_worksheet`/
   `premium_import_file_queue` row counts genuinely unchanged after clicking "Clear worksheet list" and
   confirming the dialog, then unchanged again after removing ref-staleness as a possible confound). Fixed by
   simply removing both added links - `worksheets()`/`files()` already had a working "Back" button
   (`create_back_href()`, which does *not* register/re-prioritize anything, just pops the history stack), so
   the cross-link was redundant as well as actively harmful. Also verified the *wizard's own* forward-only
   "Advanced: manage all files & worksheets" link (`import_wizard()` → `files`, one direction only now) does
   **not** carry the same risk for the wizard's own "Next" progression - tested live: enter wizard → visit
   Advanced → Back → Next still correctly advances. **General lesson: in this framework, only use
   `create_callback_href` for one-way/forward navigation (matching the codebase's own existing pattern,
   e.g. `files()` → `worksheets()` → deeper screens); never point one screen's callback-href back at a
   screen that can also navigate forward to it, even for something as innocuous-seeming as a "back to X"
   convenience button - use `create_back_href()` for that instead.**
5. **`is_permission_column()`'s field-name check was case-sensitive against the wrong case, so it never
   matched anything.** Reported by the user as the "Map columns" step still showing a required, unmapped
   "Permission" field (screenshot: red asterisk + "This field is required" sitting right above the select).
   `is_permission_column()` compared `$column->get_name() == 'permission'` (lowercase) - but `get_name()`
   (`Premium_Import_Mapping_SystemColumn::get_name()`, fed from `Utils_RecordBrowserCommon::init()`'s `name`
   key, which is the RecordBrowser field's raw stored `field` value, not a separate machine key) is literally
   `"Permission"`, capitalized - confirmed by querying `contact_field`/`company_field` directly
   (`field='Permission', caption=NULL`), and by `CRM_Contacts`/`CRM_Companies`'s own `Install.php` using
   `_M('Permission')` verbatim. The mismatch meant this column was never recognized as the auto-forced system
   column it's meant to be, so it leaked into every step that's supposed to silently skip it (Map columns,
   Fixed values, Duplicate handling) instead of being force-set to Public. Fixed with a case-insensitive
   `strcasecmp()` - single-point fix since every other call site already routes through this same helper.
   **General lesson: `SystemColumn::get_name()`/`get_col_data()`'s `'name'` key is a display label
   (caption-or-field-name), not a stable machine identifier - don't assume exact-case string equality against
   it without checking the actual stored value first.**

Also found and fixed opportunistically: `Utils_Wizard`'s own `curr_page`/`history` (stored via
`get_module_variable`) don't survive a full page reload (a fresh top-level page load gets a fresh
module-variable scope) - without a fix, reloading mid-wizard always restarted at step 1. Fixed by computing
an initial `$start_page` from the worksheet's actual state (recordset set? mapping valid? etc.) before
constructing the `Utils_Wizard` instance - only affects the *very first* render of a fresh instance, normal
Prev/Next navigation is unaffected. Side effect, not fixed: `history` itself still resets to empty on that
same reload even though `$start_page` correctly restores `curr_page` - so the first render after a resume is
missing its "Prev" button (`Wizard_0::next_page()` only adds Prev when `history` is non-empty) until at
least one more step transition happens. Cosmetic (the Advanced link and browser back both still work),
noted for whoever picks this up next.

**Known minor issue, not yet fixed:** the action bar on the final "Done" screen still shows leftover buttons
("Autodetect columns", "Duplicate checking setup", "Find duplicates", "Select all", "Select none") from
earlier steps. Cause: `Base_ActionBarCommon::add()` accumulates globally per-request, and
`worksheet_wizard()` unconditionally re-runs every step's field-building code every request (the same
pattern `FirstRun` itself uses) - including the `Base_ActionBarCommon::add()` calls inside steps that aren't
the active one. Cosmetic only (clicking a stale button just harmlessly reloads), not chased further this
session.

**AdminLTE layout polish pass (2026-08-18, `ede708f`):** the wizard's screens render as plain
`HTML_QuickForm_Renderer_TCMSDefault` markup (see `modules/Libs/QuickForm/Renderer/TCMSDefault.php`), which
has no AdminLTE styling of its own - each field/button was rendering as its own full-width block with no
gap, plain unstyled buttons, and multi-field steps (Map columns, Match values) stacking every
label/selector pair vertically instead of side by side. Fixed with CSS scoped to a new
`.epesi-import-wizard` wrapper (`worksheet_wizard()` now wraps its `display_module($wizard, ...)` call in
this div) so the fix doesn't leak into every other `TCMSDefault`-rendered form on the page - same
unscoped-`#quickform`/`.quickform-row` constraint `FirstRun`'s own theme fix already worked around, see that
module's `theme_adminltedark/default.css`. Also introduced an `.epesi-import-mapping` marker div
(`mapping_fields()`/`match_values_fields()` bracket their field rows in it via a `'html'` pseudo-element,
which `TCMSDefault` renders with no wrapping row of its own) driving a CSS-Grid label/selector two-column
layout, reused by both steps. All in `modules/Premium/Import/theme_adminltedark/default.css` +
`Import_0.php`.

**Not yet exercised by live testing:** the multi-sheet-XLSX "choose worksheet" picker, the chunked
parse/import progress screens for a file large enough to hit `Import/FileProcessingLimit`/`Import/ImportLimit`,
and `Develop/ModuleCreator`'s nested (`level=1`) captions rendering through the new shared stepper template.
("Match values" *is* now exercised - see bug #3 above.)

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
