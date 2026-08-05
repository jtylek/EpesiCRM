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
