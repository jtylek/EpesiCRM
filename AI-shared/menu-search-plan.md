# Sidebar menu search/filter — plan

Planned and approved 2026-08-14; implemented and verified in the same session. Builds
directly on [how-menu-works.md](how-menu-works.md) — read that first for the
file:line detail behind every claim below.

**Status: implemented** (`modules/Base/Menu/Menu_0.php`,
`modules/Base/Menu/theme_adminltedark/default.css`). Three real bugs surfaced only by
actually driving it in a browser, worth remembering:
1. `#MenuBar`'s direct child is a generated AJAX-patch span
   (`<span id="/Base_Box|0|2content">`), not `ul.epesi-menu` — a `:scope >` selector
   assuming the latter was a direct child never matched anything. Fixed by
   `menuBar.querySelector('ul.epesi-menu')` (unscoped search for the tree root) before
   taking its direct-child `<li>`s.
2. `bootstrap.Collapse.getOrCreateInstance(el).show()/.hide()` silently no-ops while a
   transition on that same element is already in flight (`Collapse._isTransitioning`)
   — a debounced per-keystroke filter naturally fires open/close on the same nested
   folder in quick succession and hit this, leaving a filter-opened folder stuck
   closed after Escape. Fixed by manipulating `.show`/`aria-expanded`/`.collapsed`
   directly instead of going through the Collapse instance's animated API — also
   better UX for a per-keystroke filter than a slide animation firing every keystroke.
3. The `.epesi-menu-hidden { display: none; }` CSS rule was described in this plan and
   in code comments but never actually written into `default.css` — the JS was
   correctly toggling the class the whole time, it just did nothing visually.
   `document.styleSheets` + a direct `getComputedStyle` check on a hidden-by-class
   item caught it (`display: list-item`, not `none`).

## Context

With many modules installed the AdminLTE sidebar tree gets deep enough that finding a
specific module (e.g. Accounting → Assets → Service Tickets) means manually opening
folders to hunt for it. The ask: a search box just under the logo (top of `#MenuBar`)
that narrows the tree as the user types, expanding whatever ancestor folders are
needed to reveal a match.

## Scope

- **AdminLTE (`adminltedark`) sidebar only.** It's the only actively maintained
  AdminLTE-family theme (`adminlte-theme.md`: light `adminlte` was deleted outright,
  "stop spending time" on anything but `adminltedark`). The legacy default theme's
  menu is `Utils_Menu`, a completely different JS-generated hover fly-out widget, not
  a DOM tree — adding search there is a separate, unrelated effort and is out of scope
  here.
- **Pure client-side filtering of the existing DOM.** `build_menu_html()` renders the
  *entire* already-ACL-filtered tree into the DOM on every shell render; submenus are
  only visually collapsed via Bootstrap's `.collapse`. No AJAX/server round trip is
  needed or wanted.

## Design decisions

1. **Placement** — prepend the search box's HTML to the string `Menu_0.php::body()`
   assigns to the `menu` Smarty var, so it lands inside `#MenuBar`, above the
   `<ul class="epesi-menu">`. `#MenuBar` sits directly under `.sidebar-brand` in
   `Base_Box`'s shell (`theme_adminltedark/default.tpl` ~line 1022-1047), so this
   reproduces the requested placement (just under the logo) without touching Box's
   template — the whole feature stays inside the one module that already owns this
   markup.
2. **Matching** — case-insensitive substring match against each item's own
   `.nav-label` text (already the user's translated display text; no separate raw-key
   data attribute exists). Matches on both leaf items and folder labels: typing
   "Accounting" reveals the whole Accounting folder, expanded, with all its children
   shown unfiltered (cascading open through nested folders too); typing "Service
   Tickets" matches the leaf and expands every ancestor (Accounting, then Assets) to
   reveal it.
3. **Expand mechanism** — use Bootstrap's own Collapse JS API
   (`bootstrap.Collapse.getOrCreateInstance(el,{toggle:false}).show()`/`.hide()`) on
   ancestor `.collapse` elements, not hand-rolled `style.display` toggling. `bootstrap`
   is already a confirmed global in this codebase (`include/module.php:618-625,660`
   uses `bootstrap.Modal.getOrCreateInstance(...)`). This keeps the existing
   chevron-rotation CSS (`[aria-expanded="true"] .nav-arrow`, `default.css:71-73`) in
   sync for free — Bootstrap 5's Collapse plugin auto-updates `aria-expanded`/
   `.collapsed` on every trigger referencing the target via `.show()`/`.hide()`.
4. **Clear behavior** — track which `.collapse` elements the filter itself opened (a
   JS `Set` keyed by element id, closed over the bound listener). Clearing the box
   (via a clear button or Escape) collapses only those, leaving anything the user had
   manually expanded *before* searching untouched, and un-hides every `li.nav-item`.
5. **JS lifecycle** — follow the existing `#MenuBar` convention exactly
   (`Box/theme_adminltedark/default.tpl:127-138`): call `eval_js()` on *every* render,
   not `eval_js_once()`, guarded by an idempotency marker property (e.g.
   `input.__epesiFilterBound`) so a mid-session shell swap re-binds cleanly instead of
   double-binding or going stale. `eval_js()` is a global helper
   (`include/misc.php:93`), callable directly from `Menu_0.php::body()`, not just from
   Smarty `{php}` blocks.
6. **No patch file** — this is pure code, no schema/seed/data change, so it reaches
   existing installs through normal deployment (CLAUDE.md's upgrade-gap discipline
   only applies to stored/seed data changes).

## Files changed

### `modules/Base/Menu/Menu_0.php`
- New private method building the search box markup (icon-prefixed input + clear
  button + a "no matching items" message div), translated via `__()`
  (`modules/Base/Lang/LangCommon_0.php:473`), escaped the same way the existing
  `_V($k)` label output is (`htmlspecialchars(..., ENT_QUOTES)`).
- In `body()`'s AdminLTE branch: prepend this HTML to the `build_menu_html()` output
  before `$theme->assign('menu', ...)`, and add one `eval_js()` call with the filter
  script, gated to the same branch.

### `modules/Base/Menu/theme_adminltedark/default.css`
- `.epesi-adminlte #MenuBar .epesi-menu-search` rules sized to sit flush under
  `.sidebar-brand`, visually consistent with `Base_Search`'s
  `.epesi-search-mini-group`/`.epesi-search-btn` (icon-prefixed input, rounded), plus
  a `[data-bs-theme="light"]` override block matching this file's existing convention.
- `.epesi-adminlte #MenuBar li.nav-item.epesi-menu-hidden { display: none; }` — a
  toggled class, not inline styles, so state stays inspectable/overridable.
- A small `.epesi-menu-no-results` message style.

## JS filter logic (inside the `eval_js()` string)

- Bind `input` (debounced ~120ms) on `.epesi-menu-search-input`.
- Recursive walk from each top-level `li.nav-item` (`#MenuBar > ul.epesi-menu >
  li.nav-item`): a li's own label is read via `:scope > a.nav-link > .nav-label`
  (skips descendant labels); `visible = selfMatch || any-child-visible`; a `forced`
  flag propagates down once an ancestor matched, so an ancestor match cascades
  "show everything, unfiltered" through the whole subtree, matching decision #2.
  `hr.menu-split` dividers fall out of the same logic for free (no label, no
  children → hidden whenever a query is active).
- Any folder li that ends up visible via a match (its own or a descendant's) gets its
  `.collapse` opened via the Bootstrap API; newly-opened ones (weren't already
  `.show`) get their id recorded into the `openedByFilter` Set.
- Empty query resets: unhide every `li.nav-item`, `.hide()` every collapse recorded in
  `openedByFilter`, clear the Set, hide the no-results message and clear button.
- Clear button appears only while the input is non-empty; Escape does the same as
  clicking it.

## Verification

No automated test suite exists for this app (CLAUDE.md) — verify by running it:

1. `/c/xampp82/php/php.exe -l modules/Base/Menu/Menu_0.php`
2. Launch the app, log in, confirm the AdminLTE dark theme is active.
3. Type "Service Tickets" → Accounting and Assets auto-expand, Service Tickets becomes
   visible, unrelated groups hide.
4. Type a folder name ("Accounting") → it shows expanded with all children visible
   (cascading), unrelated top-level groups hidden.
5. Clear via the X button and via Escape:
   - after a fresh page load (everything collapsed) → tree returns fully collapsed
   - after manually expanding an unrelated folder *before* searching → that folder
     stays open after clearing (only filter-opened folders should close)
6. Resize to a mobile viewport (off-canvas sidebar): confirm the existing
   close-sidebar-on-nav-click listener (`default.tpl:127-138`) still ignores clicks
   inside the search input and still fires on real nav-link clicks; confirm the search
   box itself is usable in the off-canvas view.
7. Toggle light/dark mode — confirm the search box renders correctly in both.
8. Trigger a shell re-render mid-session (theme toggle, or navigating somewhere that
   re-renders Box) and confirm the search box still works afterward — proves the
   idempotency-marker rebind is working rather than double-binding or going stale.
