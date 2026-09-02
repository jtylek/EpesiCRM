# How the sidebar/left menu works

> **Status:** REFERENCE - sidebar/menu internals. Written 2026-08-14.

Notes from a 2026-08-14 investigation into the AdminLTE sidebar menu, done ahead of
planning a menu search/filter feature. Facts as of that date — verify against current
code before relying on line numbers for anything consequential.

## Tree construction (shared by both themes)

- `Base_MenuCommon::get_menus()` (`modules/Base/Menu/MenuCommon_0.php:49`) calls
  `ModuleManager::call_common_methods('menu', false)`, which scans every installed
  module's `<Name>Common` class for a `menu()` method — opt-in by declaration, no
  central registry. Each module's own `menu()` does its own ACL check before
  contributing entries (e.g. `Utils/RecordBrowser/CustomRecordsets/
  CustomRecordsetsCommon_0.php:35`, `Administrator/AdministratorCommon_0.php:58`), so
  the merged tree is already permission-filtered per user by the time it reaches
  rendering — nothing in the DOM needs a second, client-side ACL check.
- The merged result is cached in `$_SESSION` via `Module::static_set_module_variable()`
  (`MenuCommon_0.php:61`) — **editing a module's `menu()` method won't show up until
  re-login**, a recurring "why isn't my menu change showing" trap.
- `Menu_0.php`: `add_menu()` (line 213) merges every module's per-label contribution
  into one tree (label-string matching — no single "owner" file per top-level group,
  see `Dev-Tutorial.md` §7); `sort_menus()` (line 249) applies `__weight__` ordering;
  `body()` (line 261) assembles `$modules_menu` and picks a render path based on
  `Base_ThemeCommon::is_adminlte_family()`.

## Two independent render paths

- **AdminLTE family** (`Menu_0.php:282-291`): sidebar is built straight from
  `$modules_menu` (each top-level group gets its own row, not collapsed under one
  "Menu" root) via `build_menu_html()` (line 71) — a private method that returns a
  literal HTML string, assigned directly to the `menu` template var.
- **Default theme** (else branch, line 293): goes through `Utils_Menu` instead,
  rendered as a hover fly-out widget via the older `build_menu()` (line 148). Separate
  code path entirely — changes to one do not affect the other.
- Template: `modules/Base/Menu/theme/default.tpl` is a one-line `{$menu}` — theme
  agnostic. There is **no** `theme_adminltedark/default.tpl` override for this module;
  `build_menu_html()` pre-builds the whole HTML string in PHP before the template ever
  runs, so any HTML/JS addition to the AdminLTE sidebar menu can be done by editing the
  string `body()` assigns, without touching a template.

## AdminLTE sidebar DOM (`build_menu_html()`, `Menu_0.php:71-146`)

```html
<ul class="nav flex-column epesi-menu">  <!-- epesi-submenu at depth>0 -->
  <li class="nav-item">
    <a href="#" class="nav-link menu-parent collapsed" data-bs-toggle="collapse"
       data-bs-target="#epesi_menu_<md5>" aria-expanded="false" aria-controls="epesi_menu_<md5>"
       helpID="Menu_Accounting">
      <i class="bi bi-folder2 nav-icon"></i><span class="nav-label">Accounting</span>
      <i class="bi bi-chevron-right nav-arrow"></i>
    </a>
    <div class="collapse" id="epesi_menu_<md5>"> <!-- nested <ul> recurses here --> </div>
  </li>
</ul>
```
Leaf items: `<li class="nav-item"><a class="nav-link" href="javascript:..." helpID="...">...<span class="nav-label">Label</span></a></li>`.

Key points:
- **Deliberately not AdminLTE's own `nav-treeview`/`menu-open` classes** — AdminLTE
  hides `.nav-treeview` unless the parent carries `.menu-open`, which would fight the
  Bootstrap 5 `.collapse` mechanism used here (comment at `Menu_0.php:72-75`, also
  called out in `adminlte-theme.md`'s "recurring CSS/JS traps": never reuse AdminLTE's
  own class names on markup it doesn't control).
- Expand/collapse is **pure Bootstrap 5 `data-bs-toggle="collapse"`**, no custom JS.
  Chevron rotation is CSS keyed off `[aria-expanded="true"]`
  (`modules/Base/Menu/theme_adminltedark/default.css:71-73`), not a JS-toggled class.
- Labels are plain escaped text: `htmlspecialchars(_V($k))` (`Menu_0.php:118`) inside
  `<span class="nav-label">` — no separate data attribute carries the untranslated key,
  so anything matching against display text (e.g. a client-side filter) should read
  `.nav-label`'s `textContent`, which is already in the user's own language.
- **The full tree is always in the DOM**, rendered once per shell render — not built
  incrementally via AJAX/click. Submenus are only visually collapsed. This means any
  client-side feature that filters/searches the menu can operate purely on the
  existing DOM; no server round trip is needed.
- Icons resolve through `Base_BootstrapIcons::resolve()` (`modules/Base/Theme/
  bootstrap_icons.php`) — same map used by the ActionBar launchpad icons.

## Shell wiring

`modules/Base/Box/theme_adminltedark/default.tpl` (~line 1022-1078) is the outer shell:
```html
<aside class="app-sidebar shadow" id="epesi_sidebar">
  <div class="sidebar-brand">{$logo}<a class="sidebar-toggle-inline d-lg-none" .../></div>
  <div class="sidebar-wrapper" id="MenuBar">
    {$menu}
    {$help}
  </div>
  <div class="sidebar-footer">...</div>
</aside>
```
`#MenuBar` is Base_Menu's own render target, sitting directly under `.sidebar-brand` —
visually "just under the logo" without needing to touch Box's template at all; content
prepended to the `$menu` string in `Menu_0.php::body()` lands there.

`#MenuBar` is **not** permanent shell chrome — it's part of Box's own output and can be
swapped for a fresh node mid-session (comment at `default.tpl:113-122`). The existing
JS bound to it follows a strict convention worth reusing for any new listener:
- `eval_js()` on **every** render (not `eval_js_once()`), guarded by an idempotency
  marker property on the element (e.g. `bar.__epesiCloseBound`) so a re-bind on a fresh
  node happens exactly once instead of assuming the original node lives forever.
- Example already in place (`default.tpl:127-138`): a delegated click listener on
  `#MenuBar` that closes the mobile off-canvas sidebar on navigation, explicitly
  excluding `a.menu-parent` (submenu toggles — those only expand/collapse, no
  navigation happens).
- `eval_js()` itself (`include/misc.php:93`) is a **global helper**, callable directly
  from module PHP (e.g. inside `Menu_0.php::body()`), not just from `{php}` blocks in
  Smarty templates.

## CSS

`modules/Base/Menu/theme_adminltedark/default.css` is the only stylesheet for this
markup (no separate light-only `theme_adminlte/` variant exists for this or the Box
module — `theme_adminltedark` handles both light and dark via `[data-bs-theme="light"]`
overrides). Everything is scoped `.epesi-adminlte #MenuBar ...`, per the project's CSS
convention: **a module's CSS is loaded only when that module renders**, so sidebar
styling belongs in Base_Menu's own stylesheet, not Box's, even though Box owns the
`#MenuBar` wrapper element.

## Existing search UI (different use case, worth matching visually)

`modules/Base/Search/theme_adminltedark/Search.tpl` + `Search.css` — the navbar
quick-search box (`.epesi-search-mini-group`, `.epesi-search-btn`, icon-prefixed input
+ rounded-pill submit button). This is a **server-submitted form** (`Search_0.php`),
not live client-side filtering, so it can't be reused directly for a live-filter
feature — but its class names/visual language are the closest existing reference for
styling a new sidebar filter input consistently.

## No existing menu search/filter feature

Grepped `AI-shared/` and `MIGRATION_NOTES.md`: nothing planned or in progress for a
sidebar search/filter box as of 2026-08-14. See the sidebar-search section below if a plan
for this is written up and parked rather than implemented immediately.

## The sidebar search box

A client-side filter over the sidebar, living entirely inside `Base_Menu` — it is a good worked
example of the conventions above, so read it before adding anything else to the sidebar.

- **Placement without touching Box.** The search markup is prepended to the string
  `Menu_0.php::body()` assigns to the `menu` Smarty var, so it lands inside `#MenuBar`, above
  the `<ul class="epesi-menu">`. The whole feature stays in the one module that already owns
  this markup.
- **Pure DOM filtering, no round trip** — the entire (already ACL-filtered) tree is in the DOM;
  submenus are only visually collapsed.
- **Matching** is a case-insensitive substring match against each item's own `.nav-label` text,
  which is already the user's translated display text. Matching a folder reveals it expanded
  with all children unfiltered; matching a leaf expands every ancestor to reveal it.
- **Expand/collapse goes through Bootstrap's own API** —
  `bootstrap.Collapse.getOrCreateInstance(el, {toggle:false}).show()` / `.hide()` on the
  ancestor `.collapse` elements, never hand-rolled `style.display`. `bootstrap` is a confirmed
  global here. This keeps the chevron-rotation CSS (keyed off `[aria-expanded="true"]`) in sync
  for free, since Bootstrap updates `aria-expanded`/`.collapsed` itself.
- **Clearing restores, it doesn't collapse everything.** The filter tracks which `.collapse`
  elements *it* opened in a JS `Set`, so clearing leaves anything the user had expanded
  beforehand untouched.
- **Lifecycle** follows the `#MenuBar` convention exactly: `eval_js()` on every render, guarded
  by an idempotency marker property on the element.

This is adminltedark-only. The legacy theme's menu is `Utils_Menu`, a JS-generated hover
fly-out widget rather than a DOM tree — adding search there would be a separate effort.
