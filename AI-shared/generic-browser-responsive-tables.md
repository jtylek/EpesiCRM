# Utils_GenericBrowser mobile/responsive table (implemented on `mobile-gb`, unverified)

**Status as of 2026-08-10: implemented on the `mobile-gb` branch, not yet visually
verified in a browser, not merged to `jasiek`/`karina`.** Triggered by `CRM_LoginAudit`
being unreadable on narrow/mobile viewports (7 columns squeezed into one line).
Decision: fix it **generically for every `Utils_GenericBrowser`/`Utils_RecordBrowser`
list**, not just Login Audit — confirmed this reaches RecordBrowser's Browse mode too,
since `RecordBrowser_0.php` renders its grid via a genuine child
`Utils_GenericBrowser::module_name()` instance (`RecordBrowser_0.php:298` etc.), which
goes through this same GenericBrowser theme — RecordBrowser's own
`theme_adminltedark/Browsing_records.tpl` only renders the surrounding page chrome
(filters/tabs), not the grid itself, and GenericBrowser's `theme_adminltedark/` has
exactly one template/CSS pair (no per-caller override to miss). Do this work on its
own `mobile-gb` branch (see [[mobile-gb-branch]]), not mixed into `jasiek`/`karina`
mainline work.

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

## Implemented mechanism

Each row (header included, since header/body share the `__tr` class) becomes an
N-column CSS Grid at a mobile breakpoint, instead of letting `display:table-cell`
squeeze every column proportionally. Two files changed, both in
`modules/Utils/GenericBrowser/theme_adminltedark/`:

- **`default.tpl`** — the existing `{php}` block that builds `cols` now also computes
  `mobile_cols = max(1, ceil(count($cols)/2))` and assigns it as a template var; the
  `table_attr` capture appends `--epesi-gb-mobile-cols:{$mobile_cols};` to the
  wrapper's inline `style=`. Done in the `.tpl` (not `GenericBrowser_0.php`) since it's
  purely a presentation concern of this theme and keeps the change to one file per
  theme rather than touching the shared PHP class.
- **`default.css`** — new `@media (max-width: 767.98px)` block, placed right after the
  existing 991.98px kebab/favs-watchdog-hide block:
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
    .epesi-gb .Utils_GenericBrowser__tbody > .Utils_GenericBrowser__tr {
      border-bottom: 1px solid #343a40; /* row separator moved from __td to __tr */
    }
    .epesi-gb .Utils_GenericBrowser__td {
      border-bottom: none;
    }
  }
  ```

Forcing the ancestors to `display:block` before making `__tr` a grid matters: a
`display:grid` child of a `display:table-row-group` parent otherwise risks the browser
generating an anonymous `table-row` wrapper around it per the CSS table anonymous-box
rules, which would break the intended wrapping. Moving the row-separator border from
each `__td` (existing rule, fires on every physical line) to the `__tr` itself means a
logical row's 2 physical lines read as one row with one separator below it, not two
rows with a separator between them.

Because this is generic (not scoped to one module), column count varies per table, so
the number of columns-per-line can't be hardcoded — `ceil(column_count/2)` computed
per-table in the `.tpl` and exposed via `--epesi-gb-mobile-cols` is what lets this one
CSS rule work for every table regardless of how many columns it has, with header and
body rows staying column-aligned since both use the same rule and the same computed
value.

**Not done, deliberately:** no change to `GenericBrowser_0.php`, `theme/default.tpl`
(legacy non-AdminLTE theme, out of scope — every other polish pass this session has
been `adminltedark`-only), or `function.html_grid_epesi.php`.

## Bug found in first-round visual verification: headers clipped to 1-2 characters

Every `__th` (and any `__td` on an `absolute_width` table) carries its own inline
`style="width:N%"` set by `GenericBrowser_0.php:856` — sized as N% of the *whole row*,
for the desktop table-cell layout. A percentage `width` on a CSS grid item resolves
against that item's own grid area (already just one 1fr track), not the row as a
whole — so the same N% reapplied on top of an already-narrow track compounded down to
near-zero width, and `overflow:hidden;text-overflow:ellipsis` clipped the label to
whatever tiny sliver was left (1-2 characters). Fixed by adding
`.epesi-gb .Utils_GenericBrowser__th, .epesi-gb .Utils_GenericBrowser__td { width: auto
!important; }` inside the same `max-width:767.98px` block — `!important` is required
because only that can beat an inline `style=`, and `width:auto` lets the item fall back
to the grid's own default stretch-to-fill-the-track sizing.

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

## Dashboard applets: kebab collapse turned off (2026-08-10)

Once rows wrap into the 2-line grid, the actions column is just one of
`--epesi-gb-mobile-cols` slots — on a phone-width Dashboard applet card that's plenty
of room for its icons, so collapsing them into a kebab (the existing 991.98px rule)
just hides actions that already fit; confirmed by screenshot (Karina's Tickets,
Phonecalls applets). Fixed by reusing the exact opt-out pattern
`.epesi-admin-panel`/`.epesi-watchdog-applet` already established
(`theme_adminltedark/default.css` ~1000-1025): added the same 2-rule override scoped to
`.epesi-applet-body` — Base_Dashboard's own generic wrapper around *every* applet's
content (`Base_Dashboard/theme_adminltedark/default.tpl:37`), regardless of which
module it is, unlike Watchdog's module-specific wrapper. `.epesi-watchdog-applet`'s own
rule is now a redundant subset of this one (a Watchdog applet is also inside
`.epesi-applet-body`) — left in place rather than removed, harmless.

**Deliberately scoped to applets only, not GenericBrowser generally** — a full-page
RecordBrowser Browse-mode table (not inside a Dashboard applet) still gets the kebab
collapse. Per explicit request, this is scoped for now — revisit if it should extend
to Browse mode too.

**Follow-up: missed a second, independent kebab mechanism (2026-08-10).** Confirmed by
a follow-up screenshot (Jasiek's Tickets applet) that a "⋮" kebab was still showing
even with the fix above live — not stale cache after all. `ensureToggles()`
(`Base_Box/theme_adminltedark/default.tpl`) actually runs *two* separate collapsing
mechanisms: the responsive one fixed above (`.epesi-gb-actions-toggle`, mobile-width
only), and a second, **always-on regardless of viewport width** "More actions" toggle
(`.epesi-gb-more-toggle`/`.epesi-gb-actions-extra`) that groups any action not in
`isCoreAction()`'s fixed list (view/edit/delete/info/print/restore/active-on/
active-off/move-up-down/move-up/move-down/history/history_inactive/plus_gray/
minus_gray/expand/collapse) — e.g. Premium Projects/Tickets' own per-row actions
beyond view/edit/info. `.epesi-watchdog-applet` already had an override for *both*
mechanisms (`theme_adminltedark/default.css` ~1058-1069); my original `.epesi-applet-body`
fix only copied the first pair. Added the matching second pair
(`.epesi-applet-body .epesi-gb-actions-extra`/`.epesi-gb-more-toggle`) right after it —
now `.epesi-watchdog-applet`'s rules are a genuinely redundant subset of
`.epesi-applet-body`'s for both mechanisms, not just one.

## Regression surface to retest before merging (generic = touches every list screen)

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

## Record Browser "Changes History" tab: single-action kebab opted out (2026-08-10)

Reported from a mobile-portrait screenshot: the Changes History tab's per-row action set
is just one entry (`view`, label "View", jumping to the matching point in "Record
historical view" — `RecordBrowser_0.php:2503`/`2517`) but the 991.98px kebab collapse
still applied, so viewing a single change cost an extra tap to open the kebab first.
Same opt-out shape as `.epesi-admin-panel`/`.epesi-watchdog-applet`/`.epesi-applet-body`
above, scoped narrowly instead of generically: `RecordBrowser_0.php`'s
`view_edit_history()` now wraps that `Utils_GenericBrowser` instance's grid via
`set_prefix()`/`set_postfix()` in a `<div class="epesi-rb-changes-history">`, and
`theme_adminltedark/default.css` adds the matching `.epesi-gb-actions-icons` (show) /
`.epesi-gb-actions-toggle` (hide) pair, unscoped to any media query so it wins at every
width. Only the responsive-kebab pair was needed, not the always-on "More actions"
pair — `view` is in `isCoreAction()`'s fixed list, so that second mechanism never
applied here to begin with.
