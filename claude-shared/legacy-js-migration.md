# Legacy JS libraries: inventory and elimination plan

As of 2026-07-30, every page still loads (hard-coded in `index.php`'s `$jses`
array, bypassing Epesi's own `load_js()` module-asset system entirely):

- `libs/prototype.js` (1.7)
- `modules/Libs/ScriptAculoUs/1.8.0/*` (actually v1.7.0 per file header despite
  the directory name) — loaded via `Libs_ScriptAculoUsCommon`, declared as a
  module dependency by ~5 modules (Colorpicker, Leightbox, Dashboard, etc.)
- `libs/jquery-1.11.3.js` + `jquery-migrate-1.2.1.js` +
  `jquery-ui-1.10.1.custom.min.js`

**Critical wiring fact**: `include/epesi.js` calls `jQuery.noConflict()` after
both load, so **`$` is bound to Prototype, `jQuery` is the real jQuery** —
everywhere, on every page. Don't assume `$(...)` is jQuery syntax anywhere in
this codebase.

`MIGRATION_NOTES.md` §9 already flagged this stack as "mostly old JS — do NOT
block PHP 8.2" — deliberately deprioritized as a separate track from the PHP
version migration.

The AdminLTE theme direction (see `adminlte-theme.md`) is already
jQuery/Prototype-free at the chrome level — Bootstrap 5 + AdminLTE 4 load
conditionally only when that theme is active. The legacy stack is what remains
for the widget layer underneath.

## Proposed elimination order (smallest blast radius first)

1. **script.aculo.us** — only 2 files used `Sortable`/`Draggable`/`Droppables`
   (low usage); `Effect.*` used in 7 files, replaceable with CSS transitions.
2. **`Ajax.Request`/`Ajax.Updater`** (27 files) → `jQuery.ajax`, mechanical
   since jQuery is already loaded everywhere.
3. **`Class.create`** (8 files) → plain JS classes.
4. **Remaining `$('id')`/`Element.*` calls** → `jQuery(...)`/vanilla DOM — the
   biggest and riskiest phase; do it module-by-module with manual browser
   testing, since server-built inline JS strings aren't caught by any linter.
5. Remove `jQuery.noConflict()` from `include/epesi.js`, drop
   `libs/prototype.js` and the ScriptAculoUs module from `index.php`'s `$jses`
   and the 5 modules' dependency declarations, delete the files.
6. Stretch: upgrade jQuery 1.11.3 → current, retire jquery-migrate.

## Progress (started 2026-07-30, step 1)

Done (syntax-checked, not yet fully browser-verified — no browser automation
was available in that session):

- Deleted `modules/Utils/Calendar/calendar.js` (dead code — its `load_js()`
  call was already commented out; only Draggable/Sortable/Droppables user).
- `Base_StatusBar/js/main.js`, `Utils_Menu/js/menu.js` (note: `Utils_Menu` has
  **zero live callers** — effectively dead in production, fixed anyway since
  `Tests_Menu` still declares it), `Base_Setup`'s wizard JS, and
  `Utils_Calendar/theme/event_.js`'s `Effect.toggle` all had their
  ScriptAculoUs `Effect.*` calls replaced with vanilla JS/small local helpers.
  (`.clonePosition()`/`$()` in `event_.js` were deliberately left — those are
  Prototype *core*, in scope only once Prototype itself is tackled.)
- The QuickForm autocomplete widget was rewritten from scratch
  (`modules/Libs/QuickForm/FieldTypes/autocomplete/autocomplete.js`, vanilla +
  `jQuery.ajax`), replacing script.aculo.us's `Ajax.Autocompleter`. This one
  file underlies `autocomplete.php`, `autoselect.php`, and `automulti.php`
  (used by Contacts/RecordBrowser/Filters/Planner/Shoutbox/ActivityReport
  search fields) — so fixing it fixed all three field types at once. Confirmed
  via grep that `Ajax.InPlaceEditor`, `Ajax.InPlaceCollectionEditor`,
  `Form.Element.DelayedObserver`, `Autocompleter.Local` have zero remaining
  callers — safe to drop entirely.

**Not yet done**: actually uninstalling the `Libs_ScriptAculoUs` module (it's
DB-tracked via `ModuleManager`; Colorpicker/Leightbox/Dashboard declare it as a
`requires()` dependency even though none of the three call any of its
functions anymore — the dependency is vestigial but removal needs
`ModuleManager::uninstall()`, not a blind directory delete). Steps 2–6 above
have not started.

**Before relying on any file/count above**: this is a live, multi-session
migration — re-verify with a fresh grep rather than trusting these numbers,
especially the "X files use Y" counts.
