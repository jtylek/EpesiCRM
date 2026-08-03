# AdminLTE theme(s) status

## RecordBrowser's generic View_entry.tpl: fluid CSS columns (2026-08-03)

See [design-philosophy.md](design-philosophy.md) for why this was originally
computed in PHP at all, and why replacing it with CSS is a continuation of that
principle, not a departure from it.

`Utils_RecordBrowser::view_entry_details()` (`RecordBrowser_0.php`) used to take
a `$cols` param (default 2, optionally overridden per-tab from a page_split
field's own `param` column) and pre-compute which field landed in which of N
columns (`$rows`/`$no_empty`/`$cols_percent`, done in the *template*, not PHP).
This has been removed entirely from the **generic** `View_entry.tpl` in all
three themes (`theme/`, `theme_adminlte/`, `theme_adminltedark/`) — replaced by
a plain CSS multi-column container (`.epesi-rv-fluid { column-width: 420px;
column-gap: 24px; }`) that lets the browser decide how many columns fit the
current width, instead of a fixed PHP-computed count. Fields render as a flat
sequence of rows; `break-inside: avoid` keeps each row intact.

**This does NOT apply to the ~6 other per-table templates** (`CRM_Contacts`'s
`Contact.tpl`, `CRM_Contacts_Photo`'s `Contact.tpl`, `CRM_Mail`'s `mails.tpl`,
`CRM_Meeting`'s and `CRM_PhoneCall`'s `default.tpl`, `Utils_Attachment`'s
`View_entry.tpl`) — a deliberate scope decision, confirmed with the user, since
converting those too would be a much bigger change touching core CRM screens.
They still read `$cols`/`$rows`/`$no_empty` for their own fixed-column table/flex
layout, so `view_entry_details()` still assigns `'cols' => 2` as a **permanent
compatibility shim** purely for their benefit — don't remove it without also
converting (or otherwise updating) all of those templates first.

**Why the generic template needed its own field-row markup, not just new CSS**:
`single_field.tpl` (which builds each field's `$f.full_field` HTML) is *shared*
between the generic template and every one of those other per-table templates.
In the legacy `theme/` (table-based) theme, `single_field.tpl` emits `<tr><td>`
— dropping that directly into a `<div>` (as pure CSS multi-column requires) is
invalid HTML that browsers silently relocate via foster-parenting, breaking
layout. So the legacy generic `View_entry.tpl` now builds its rows directly
from the individual pieces `get_field_display_options()` already returns
(`$f.label`/`.html`/`.error`/`.help`/`.required`/`.advanced`/`.style`/`.element`)
instead of going through `single_field.tpl` at all — `single_field.tpl` itself
is untouched, still serving the other templates exactly as before. The two
AdminLTE-family themes didn't need this workaround: their `single_field.tpl`
already emits `.epesi-rv-row` `<div>`s (no table involved anywhere in that
theme), so `{$f.full_field}` drops straight into the new fluid container as-is.

**Legacy theme CSS note**: most of `.label`/`.data`/`.form_error`/etc in
`theme/View_entry.css` are already unscoped from any table-cell requirement and
so were safe to reuse directly for the new div-based rows; only a handful of
rules that *were* `table.view`/`table.edit`-scoped (background colors, the
automulti edit-mode block, a couple of border tweaks) needed an equivalent
added under `.epesi-rv-fluid.view`/`.epesi-rv-fluid.edit` instead. Also fixed,
proactively, in all three themes: the same `.form_error` positioning bug
documented in `bug-patterns.md` (no `top` set, `max-width:50%`) — it would have
resurfaced the moment these rows went through a flex layout instead of a table
cell.

Two AdminLTE-based themes exist under `modules/*/theme_adminlte/` and
`modules/*/theme_adminltedark/`. Both replace the original `theme/` (legacy,
table-based) look. Themes resolve straight from `modules/` — no build step, no
generated copy (see `Base_ThemeResolver::resolve()`: `theme_<name>` first, falls
back to legacy `theme/`). **Any module without its own `theme_adminlte(dark)/`
override silently falls back to the legacy light table-based theme** — this is
still a large gap, not a bug, for both themes.

## `adminlte` (light) — started 2026-07-26

Working and browser-verified as of early Aug 2026: login, app shell (navbar +
off-canvas sidebar), sidebar menu, GenericBrowser record lists (incl. the ~6
per-table custom templates: Contact ×2, PhoneCall, Mail, Meeting, Attachment),
ActionBar/Launchpad, Dashboard applet chrome, TabbedBrowser, Admin/User-Settings
panels, RecordBrowser view/edit (`View_entry.tpl`), Search (mini + full),
Leightbox popup chrome, module-indicator icons (via the shared
`Base_AdminlteIcons`/`adminlte_icon()` convention — each module opts in with its
own `<Module>Common::adminlte_icon()` static method, same shape as `menu()`/
`user_settings()`).

**Not yet themed / not audited**: individual dashboard applets' own inner
content (Weather, RssFeed, Shoutbox history, Calc, etc. mostly `print()` raw
HTML), `Base_Admin/theme/access_panel.tpl`, QuickForm's raw-table renderer
(`Libs/QuickForm/Renderer/TCMSDefault.php`, used by `Utils_Wizard` — CSS-only
override, not converted to the Smarty array renderer), leightbox popup
*contents* (e.g. CRM_Filters "manage perspectives"), Base_Help's tutorial
overlay. Tooltips are **plain native browser tooltips** (`title="..."` only) —
three separate attempts at a JS-driven (Bootstrap) tooltip component each broke
real functionality in hard-to-diagnose ways (load-order races, orphaned popups,
conflicts with `GenericBrowser`'s own hover-driven `table_overflow_show`); treat
any future JS tooltip attempt here as high-risk and test broadly before calling
it done.

## `adminltedark` — created 2026-08-01

A **full independent fork** of `theme_adminlte/`, not resolver-chained (no
`adminltedark → adminlte` fallback — a module `adminltedark` doesn't cover falls
straight to the legacy theme, same gap as `adminlte` itself). Covers the same
~33 modules `adminlte` covers; module-coverage expansion ("Phase 2") was never
started. Has a live navbar light/dark toggle built on AdminLTE's own
`data-bs-theme-value` color-mode toggler (`adminlte.min.js`'s `Me` class) rather
than a custom implementation — `adminlte` (light) still force-pins
`data-bs-theme="light"` unconditionally and offers no toggle.

As part of this fork, several modules' nested-`<table>` layout was rewritten as
real flexbox/grid (QuickForm's `row.tpl`/`column.tpl`, RecordBrowser's
`View_entry.tpl` + the per-table overrides, the RecordBrowser filter bar) —
landed in the shared `theme_adminlte/` files first, then copied into the dark
fork, so both themes benefit.

## Recurring CSS/JS traps (read before touching either theme)

1. **CSS loads per rendering module.** `modules/X/theme_adminlte/default.css` is
   only fetched when module `X` itself renders — putting a style under the
   wrong module (e.g. sidebar CSS under `Utils/Menu` when `Base_Menu` is what
   actually renders it) produces a silently-unstyled screen. Verify with
   `Epesi::get_csses()` after a render rather than assuming.

2. **Never reuse AdminLTE's own class names on markup it doesn't control** (e.g.
   `.nav-treeview`/`.menu-open`, or its `--lte-sidebar-*` color variables) —
   its own CSS/JS will partially apply and produce silently-broken-looking
   behavior (toggles that flip ARIA state but don't show/hide anything). Use
   fresh `epesi-*`-prefixed class names instead.

3. **A `data-bs-theme` pin only wins if it's on every matching ancestor.**
   AdminLTE ships compound selectors like `[data-bs-theme=dark] .app-sidebar`,
   which match through *any* ancestor carrying the attribute — a pin on one
   wrapper doesn't override a conflicting value further up the tree (e.g.
   `<html>`, which `adminlte.min.js` sets directly from OS `prefers-color-scheme`
   detection). Grep the vendored CSS for the literal `[data-bs-theme=dark]`
   selector before assuming a scoped pin is sufficient.

4. **Fixed-height layout vars (`--epesi-header-height` etc.) must track real
   content, not a guess** — the navbar/ActionBar heights vary with what's
   rendered (widget widths, wrapped text). Both are kept in sync live via a
   `ResizeObserver` in `Base_Box/theme_adminlte/default.tpl`. Never make a CSS
   var that's *written from* an element's measured height also *constrain*
   that same element's `min-height` — creates a one-way ratchet that can only
   grow.

5. **The sidebar has a higher z-index than the navbar's own full-width
   background** — the navbar's *content* (not just its background) must be
   offset by `--epesi-sidebar-width` via `margin-left`, or it renders
   unclickable underneath the sidebar.

6. **Never let a `document.observe("e:load", ...)` handler throw.** This is the
   app-wide "re-init after Epesi's AJAX-patch cycle" convention
   (`Epesi.append_js('Event.fire(document,\'e:load\');Epesi.updateIndicator();')`
   in `include/epesi.js`) — both statements share one script block, Prototype
   doesn't try/catch observers, and an uncaught exception in *any* one observer
   silently aborts `Epesi.updateIndicator()` right after it too (symptom: the
   "Loading..." overlay gets stuck forever, app-wide, with no visible link to
   the actual observer that threw). Any new `"e:load"` handler should wrap its
   body in try/catch and guard on its dependencies actually being loaded
   (load order between independently-`load_js()`'d files is never guaranteed).

7. **Epesi renders almost everything as nested `<table>`s, not divs** — a
   systemic source of width/sizing bugs, not a one-off. A nested `<table>` with
   a percentage width has no resolvable preferred width under auto-layout, so
   sizing becomes unpredictable and content-dependent. `table-layout:fixed` on
   the outer table is a common fix; converting the *inner* element to
   `display:block`/flex (as done for the multiselect dual-listbox widget) is
   usually more robust than fighting auto-layout further. A genuine
   div/flexbox rewrite would eliminate this whole bug class but is out of
   scope for per-screen theming passes — flagged so it isn't rediscovered from
   scratch each time.

8. **AdminLTE's `.card-body.p-0` CSS reaches into any `<table>` inside it**
   (adds `padding-left/right` to first/last-of-type cells at the same
   specificity as a per-column override — needs `!important` to beat).
   Separately, `Utils/GenericBrowser/js/col_resizable.js` strips the `width`
   HTML attribute from header cells at runtime and replaces it with an inline
   pixel style — any later JS that needs a column's *original* declared width
   must cache it early or read a stable class, not the DOM attribute.

9. **Bootstrap utility classes (`shadow-sm`, `border-0`, etc.) set their
   property with `!important`** — remove the utility class from markup rather
   than trying to out-`!important` a custom override targeting the same
   element/property.

10. **A CSS `transform` on any ancestor — even a decorative `:hover` lift —
    becomes the containing block for a `position:fixed` descendant.** A
    JS-positioned fixed dropdown nested inside a hoverable card can get
    violently re-anchored the instant the mouse re-enters the card. Grep
    descendants for `position:fixed` (inline or JS-driven) before adding any
    `transform`/`filter`/`will-change` to an element, anywhere in either theme.

11. **`getBoundingClientRect()` on a `display:none` element returns an
    all-zero rect.** An `onclick="a();b();"` chain where `a()` hides the
    trigger and `b()` measures it (in that order) silently mispositions
    anything JS-positioned. Read the rect before mutating visibility, never
    assume inline-onclick order is layout-safe just because it reads
    left-to-right.

See `MIGRATION_NOTES.md` for the PHP-version-migration side of this codebase;
these theme notes are a separate, still-ongoing effort.
