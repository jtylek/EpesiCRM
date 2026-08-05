# TODO

Follow-up work items intentionally deferred during AI-assisted sessions — distinct
from [known-todos.md](known-todos.md) (which audits `TODO`/`FIXME`/`XXX` markers
already present in Epesi's own code). Entries here are decisions made to ship a real,
working fix now and come back to a known limitation later — usually because the
current dev install can't exercise the condition that would need testing. Keep
entries dated; move a resolved entry to "Done" (or delete it) with a note on how/where
it was fixed, rather than leaving it lingering in "Open".

## Open

- **2026-08-05 — Mobile multiselect checklist doesn't degrade for large option
  counts.** `modules/Libs/QuickForm/FieldTypes/multiselect/multiselect.js`'s
  `epesi_ms_build_checklist()` (paired with `.epesi-ms-checklist` in
  `modules/Libs/QuickForm/theme_adminltedark/default.css`) replaces the native
  two-`<select multiple>` widget with a flat checkbox list below this theme's
  767.98px mobile breakpoint — iOS Safari and Android Chrome (confirmed on both)
  never render `<select multiple>` as an inline listbox under touch regardless of
  the `size` attribute; the native compact "N selected" summary control still opens
  a working picker on tap, but shows nothing inline and doesn't match this widget's
  own selection UI at all.

  The checklist renders **every** option unconditionally, with no virtualization or
  paging — fine for the option counts this dev install actually has (a handful of
  employees/customers), but a multiselect field backed by a large recordset
  (hundreds+ rows) would render a checklist that's effectively unusable to scroll
  through on a phone.

  **Fix direction**: past some option-count threshold, the mobile fallback should
  switch to an autoselect/search-as-you-type UI (`modules/Libs/QuickForm/FieldTypes/
  autocomplete/autocomplete.php` — already used elsewhere in the app, e.g. Activity
  Report's User field) instead of rendering the full checklist.

  **Not implemented**: this dev install doesn't have enough records in any
  multiselect-backed recordset to meaningfully pick/test a threshold or the
  switchover UX. Needs a large dataset (real or seeded) before implementing.

- **2026-08-05 — `.automulti td.search` (the "Advanced Selection" zoom-icon
  link) added blind, never visually verified.** Fixed the same bug shape as
  the "timestamp fields" entry in `bug-patterns.md` (`Base_ThemeResolver`
  picks exactly one `theme_adminltedark/default.css` per module, no cascading
  with the legacy `theme/default.css` — the legacy `.automulti` layout rules
  were silently dropped under adminltedark, leaving the search box + Remove
  button row sized by min-content instead of the `.data` cell's real width;
  reported against CRM_PhoneCall's Employees field). Ported `.automulti` into
  `modules/Libs/QuickForm/theme_adminltedark/default.css` as a flex layout
  (same technique as `#multiselect` in the same file) and re-styled the
  Remove button as a Bootstrap rounded-pill.

  `RecordBrowserCommon_0.php` only swaps in that zoom-icon link
  (`set_search_button()`) for a `'multiselect'` field once its linked
  recordset exceeds `Utils_RecordBrowserCommon::$options_limit` (50 rows) —
  this dev install has no multiselect-backed recordset that large, so the new
  `.automulti td.search` rule (small fixed-width flex item, margin-left gap)
  was written from reading `automulti.php`'s markup, not seen rendered.
  Everything else in this fix *was* verified in Chrome (Playwright) at both a
  1400px desktop width and a 390px mobile width, light and dark theme.

  **Fix direction if this turns out wrong**: seed 51+ rows into any table a
  `'multiselect'` field points at, open that field's edit form, and check the
  zoom icon sits at a sane size next to the Remove button.

- **2026-08-05 — other `Libs/QuickForm/theme/default.css` selectors not yet
  audited for the same shadowing gap.** Diffing top-level selectors between
  `theme/default.css` and `theme_adminltedark/default.css` turns up several
  more present only in the legacy file: `#quickform`, `.autoselect_search_tip`,
  `.checkbox_off`/`.checkbox_on`, `.element_automulti`, `.element_button`,
  `.form_error`, `.quickform-row`, `.radio_off`/`.radio_on`. Not chased down
  this pass — `.element_automulti` in particular looks like dead CSS for a
  class only the old `Renderer/TCMSDefault.php` renderer emits (not the
  renderer QuickForm_0.php's own `column.tpl`/`row.tpl` actually use under
  adminltedark), but the rest weren't checked either way. Worth a grep-each-
  selector pass before assuming any of them either matters or is safe to
  ignore.
