# Utils_GenericBrowser mobile/responsive table (planned, not started)

**Status as of 2026-08-10: research + plan agreed, zero code changed.** Triggered by
`CRM_LoginAudit` being unreadable on narrow/mobile viewports (7 columns squeezed into
one line). Decision: fix it **generically for every `Utils_GenericBrowser`/
`Utils_RecordBrowser` list**, not just Login Audit — do this work on its own
`mobile-gb` branch (see [[karina-branch-sync]]-style branch discipline further down),
not mixed into `jasiek`/`karina` mainline work.

## Why it squeezes instead of scrolling

The on-screen grid was converted from a real `<table>` to CSS table-display `<div>`s a
while back (see this file's own table-conversion entries above, and
`function.html_grid_epesi.php`'s header comment) — markup is
`Utils_GenericBrowser__thead/__tbody/__tr/__th/__td` divs styled with
`display:table/table-row-group/table-row/table-cell`
(`theme_adminltedark/default.css:181-217`). The wrapper carries an inline
`width:100%;table-layout:fixed` (`theme_adminltedark/default.tpl:70`), so it always
fills its container exactly — it never overflows, so the surrounding
`.table-responsive` (`overflow-x:auto`) never actually engages. Column widths are
relative weights (e.g. Login Audit's `LoginAudit_0.php:87-94`: 20/15/15/10/10/25/15),
not pixels, converted to percentages of that fixed 100% (`GenericBrowser_0.php:833-856`)
— so on a narrow viewport every column just shrinks proportionally instead of the row
scrolling sideways or wrapping.

## Key files

- `modules/Utils/GenericBrowser/GenericBrowser_0.php` — column width→percent math
  (~833-856); `set_prefix()`/`set_postfix()` (~1072-1078) wrap the rendered grid in
  arbitrary HTML, the one built-in per-instance scoping hook (used by callers like
  `LoginAudit_0.php` before the grid is emitted).
- `modules/Utils/GenericBrowser/theme_adminltedark/default.tpl` — `.table-responsive`
  wrapper + `{html_grid_epesi}` call (~68-73); `theme/default.tpl` is the legacy-theme
  twin. (The light `adminlte` theme is gone — see [[adminlte-theme]] — so
  `theme_adminltedark` is the only AdminLTE-family copy to touch.)
- `modules/Base/Theme/smarty/plugins/function.html_grid_epesi.php` — the actual div
  emitter; header row and every body row both get class `Utils_GenericBrowser__tr`
  (header cells `__th`, body cells `__td`), which is what makes a shared
  header/body-row CSS rule keep them visually aligned.
- `theme_adminltedark/default.css:883-934` — existing precedent for a breakpoint-driven
  layout change: at `max-width:991.98px` the row-actions column collapses into a kebab
  menu and the favs/watchdog star/eye columns hide outright. Login Audit has neither
  column, so this rule doesn't touch it today, but it's the pattern to extend rather
  than invent something new.

## Planned mechanism

Turn each row (header included, since header/body share the `__tr` class) into an
N-column CSS Grid at a mobile breakpoint, instead of letting `display:table-cell`
squeeze every column proportionally:

```css
@media (max-width: 767.98px) {
  .epesi-gb .Utils_GenericBrowser,
  .epesi-gb .Utils_GenericBrowser__thead,
  .epesi-gb .Utils_GenericBrowser__tbody {
    display: block; /* escape the table layout algorithm cleanly first */
  }
  .epesi-gb .Utils_GenericBrowser__tr {
    display: grid;
    grid-template-columns: repeat(var(--epesi-gb-mobile-cols, 2), 1fr);
  }
}
```

Forcing the ancestors to `display:block` before making `__tr` a grid matters: a
`display:grid` child of a `display:table-row-group` parent otherwise risks the browser
generating an anonymous `table-row` wrapper around it per the CSS table anonymous-box
rules, which would break the intended wrapping.

Because this is going **generic** (not scoped to one module via `set_prefix()`), column
count varies per table, so the number of columns-per-line can't be hardcoded — it needs
to be `ceil(column_count/2)`, computed server-side in `GenericBrowser_0.php` where the
column array is already built, and emitted as a CSS custom property
(`--epesi-gb-mobile-cols`) on the wrapper. That single generic rule then gives every
`Utils_GenericBrowser`/`Utils_RecordBrowser` table a 2-line mobile layout regardless of
how many columns it has, with header and body rows staying column-aligned since both
use the same rule and the same computed value.

## Alternatives considered

- **Stacked `label: value` cards** (`::before{content:attr(data-label)}` per cell) —
  most self-explanatory, standard responsive-table pattern, but gives N lines per row
  (7 for Login Audit) not 2, and needs a real change to
  `function.html_grid_epesi.php`/`GenericBrowser_0.php` to emit a `data-label` per cell
  (nothing carries the column label onto the body `<td>` today).
- **Let `.table-responsive` actually scroll** — drop the `width:100%;table-layout:fixed`
  inline style (or add a `min-width`) at the mobile breakpoint so the existing
  `overflow-x:auto` engages and columns keep natural width. Smallest possible diff, but
  doesn't meet the "read every value without scrolling" goal.
- **Hide low-priority columns on mobile** — reuse the existing 991.98px
  favs/watchdog-hide precedent to drop less-important columns entirely on narrow
  screens. Lowest risk, matches prior art, but hides data rather than reflowing it.
- **JS responsive-table plugin** — rejected outright: unnecessary new frontend
  dependency for a problem CSS already solves, and this codebase deliberately avoids
  adding to the legacy Prototype/jQuery stack (see [[legacy-js-migration]]).

## Regression surface to retest once implemented (generic = touches every list screen)

- The 991.98px actions-kebab collapse and favs/watchdog column hide
  (`default.css:883-934`) — need to confirm the two breakpoints/rules don't fight each
  other on tables that have both row actions *and* need the 2-line mobile split.
  Do not confuse `Utils_RecordBrowser__watchdog` here with `[[gb-actions-menu-flyout-direction]]`'s
  isCoreAction path-scoping — different mechanism, same file/breakpoint neighborhood.
- `GenericBrowser`'s `expandable`/row-drag (jQuery UI sortable) features, if any table
  using them also opts into the mobile split.
- The just-fixed ajax-tooltip cell-overflow hover preview (`82d577c2`, `929b699a`,
  `7ce240b8`) — it measures cell boxes; changing a cell's display mode at the breakpoint
  needs re-verifying the hover preview still triggers correctly.
