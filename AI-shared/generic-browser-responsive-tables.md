# Utils_GenericBrowser grid layout: column sizing and mobile reflow

> **Status:** DONE - both halves are shipped and live. Part 1 (column sizing) shipped as
> 8dec01fcc on 2026-08-31 and was extended the same day to grids without an actions column.
> Part 2 (mobile reflow) merged as e6a18586f; the CSS is live in
> `GenericBrowser/theme_adminltedark/default.css`. Filename kept as-is so existing links
> keep resolving, though the file now covers more than responsive tables.

Two related but independent mechanisms decide how a `Utils_GenericBrowser` grid lays out.
They live in different files and fire under different conditions, and confusing them wastes
time - so both are documented here rather than in two docs that drift apart:

- **[Part 1: column sizing](#part-1-column-sizing-desktop-widths)** - JS in
  `Base/Box/theme_adminltedark/default.tpl` that converts every column to px against its
  container and moves unused width to columns short of their content. Runs at all widths.
- **[Part 2: mobile reflow](#part-2-mobile-reflow-the-2-line-grid)** - CSS in
  `Utils/GenericBrowser/theme_adminltedark/default.css` that turns each row into a 2-line
  CSS Grid below 767.98px, plus the per-screen opt-outs that accumulated around it.

Both are deliberately **generic**: they apply to every `Utils_GenericBrowser` /
`Utils_RecordBrowser` list, not one module. That reaches RecordBrowser Browse mode too,
since `RecordBrowser_0.php` renders its grid via a genuine child
`Utils_GenericBrowser::module_name()` instance (`RecordBrowser_0.php:298` etc.) going
through this same GenericBrowser theme - RecordBrowser's own
`theme_adminltedark/Browsing_records.tpl` renders only the surrounding page chrome
(filters/tabs), not the grid, and GenericBrowser's `theme_adminltedark/` has exactly one
template/CSS pair, so there is no per-caller override to miss.

---

# Part 1: column sizing (desktop widths)

## Where the code is

**All of it is client-side JS, and it lives in exactly one place:**
`modules/Base/Box/theme_adminltedark/default.tpl`, in the `{php}` block, as the
`epesiSizeGbActions()` function inside the third `eval_js_once(...)` call. Nothing in
`modules/Utils/GenericBrowser/` does width math beyond emitting the initial percentages.

Consequences worth internalising before touching anything:

- It is **theme-scoped**. The legacy `theme/` (non-AdminLTE) rendering path has no
  equivalent - there, the server-emitted percentages are the final word.
- It is **page-wide**, not per-module: the entry point is
  `document.querySelectorAll('.Utils_GenericBrowser')`. Every grid on the page is sized,
  including grids nested in dashboard applets (`#dashboard .epesi-applet-body .epesi-gb.card`,
  `Base/Dashboard/theme_adminltedark/default.css`), Search results, Shoutbox history, and
  RecordBrowser's own Browse grid.
- The function name says "Actions" for historical reasons only. It sizes every column.

It runs on: initial load, `document.fonts.ready`, jQuery `e:load` (Epesi's "AJAX content
patch finished" event - paging/sorting/filtering replaces a table's rows wholesale), and a
150ms-debounced `window resize`. It does **not** run when a dashboard applet is dragged into
a different-width column, so widths there stay stale until one of the above fires.

## The server side: what the JS starts from

`GenericBrowser_0.php` (~line 969 onward) turns each column's declared `'width' => N` weight
into `width="<intval(100*N/all_width)>%"`, and
`Base/Theme/smarty/plugins/function.html_grid_epesi.php`'s `html_grid_epesi_attrs_to_div()`
rewrites that presentation attribute into an inline `style="width:N%"` on the
`<div role="columnheader">` - because the grid is CSS-table-display divs, not a real
`<table>`, and a `width=` attribute on a div does nothing at all.

**So there is no `width` attribute in the live DOM.** JS that reads
`th.getAttribute('width')` finds nothing and falls through to `th.style.width`. Any test
harness that puts `width="10%"` on a div is not reproducing the real markup.

Three column kinds exist, and the JS classifies each one **once** into
`data-epesi-col-kind`, never re-deriving it:

| Kind | Declared as | Sized by |
|---|---|---|
| percent | numeric weight (the common case) | weighted split + redistribution below |
| absolute | non-numeric CSS length, e.g. `'12em'` (Utils_Attachment's `edited_on`) | measured content + 6px |
| fixed icon | `Utils_RecordBrowser__favs` / `__watchdog` (`'24px'`) | measured content + 2px, or 0 when the mobile breakpoint has hidden it |

The one-shot classification is load-bearing: this same pass writes plain `NNpx` back onto
`th.style.width`, so on the *next* run a percent column would look exactly like an absolute
one and get mis-routed into content measurement - which for a free-text Note column means
measuring thousands of px of unwrapped content and locking the column there. Same reason
`data-epesi-orig-percent` caches the original percentage.

## The pipeline, in order

1. **Actions column** (`.Utils_GenericBrowser__actions`), if the grid has one: measure the
   widest actions cell, reserve `+8`. **A grid without one reserves 0 and continues.**
2. **Fixed/absolute columns**: measure each column's own content, set px, add to
   `fixedWidth`.
3. **Force-collapse** any `div.expandable.expanded` taller than one line (guarded by
   `forceCollapse`, false on resize - without the guard a row the user just expanded gets
   re-collapsed a moment later).
4. **Weighted split**: `availableWidth = containerWidth - actionsWidth - fixedWidth - 2`,
   divided among percent columns in proportion to their cached original percentages. This is
   `basePx`.
5. **Content-aware redistribution** (the 2026-08-31 change), below.

### Redistribution: surplus moves to shortfall, smallest-first

`columnNaturalWidth(table, idx)` measures a column's widest content by cloning **every** cell
of that column into ONE detached, `table-layout:auto` throwaway table and reading
`scrollWidth` - one forced layout per column rather than one per cell, which matters because
this runs on load, on fonts.ready, on e:load and on every debounced resize. Then:

- A column whose `basePx` exceeds what its content needs contributes the difference to a
  `pool`. **A column can only ever donate what it does not need** - that is what keeps this
  safe on grids whose weights are meaningful (a Note at 90 against a date at 12 has no
  surplus, so nothing is taken from it).
- Columns short of their content are filled **smallest-deficit-first** (water-filling), not
  proportionally. Proportional sharing hands nearly the whole pool to a bottomless free-text
  column and leaves a genuinely fixable 28px shortfall still clipping.
- Donors then give back the amount actually taken, in proportion to their spare.
- No shortfall anywhere, or no surplus anywhere, means the pass is a **no-op** and the layout
  is byte-for-byte the pre-2026-08-31 weighted split.

## Are the declared weights dead code now? No.

Asked directly, and worth recording because the answer is not obvious. The weights are still:

- **the baseline** - `basePx` is what decides which columns have surplus and which are short,
  so changing a weight changes both the starting point and the donor/receiver roles;
- **the entire answer whenever demand exceeds supply** - a narrow container (a dashboard
  applet, typically) leaves no surplus, `pool` is 0, and the result is the plain proportional
  split;
- **the only sizing the legacy `theme/` path has** (no JS there at all);
- **literal pixels under `absolute_width(true)`** - `RecordBrowser_0.php:936` sets it for PDF
  export, where the declared numbers are used as-is and no JS is involved.

What *is* effectively dead is the uniform default: RecordBrowser gives every ordinary text
column the same weight (all seven of Contacts: Browse arrive as 14), so "proportional" there
degraded to "every column identical regardless of content" - which is the whole reason the
redistribution pass exists.

## The 2026-08-31 gate removal

The pass used to open with `if(!headerCell)return;` - no `.Utils_GenericBrowser__actions`
header meant the entire table was skipped, even though nothing in the width math needs an
actions column to exist. A grid only gets one from `add_action()` or, via
`GenericBrowser_0.php`'s `$expand_action_only` fallback, from `set_expandable(true)`.

Grids calling neither, and therefore silently excluded until now: **Administrator > Login
Audit** (`CRM/LoginAudit`), Shoutbox, Search results, Contacts > Activities, Cron, Reports,
Fax, Base/Lang Administrator. All of them kept the raw proportional split - which for Login
Audit meant a Duration column sitting on 100px it never used while User Name clipped.

A second `if(maxWidth<=0)return;` (actions header present but no measurable action cells - an
empty grid) abandoned the table the same way; it now keeps whatever width the column already
carries and sizes the rest.

Measured on a Login-Audit-shaped grid at a 1100px container:

| | column widths |
|---|---|
| before | 100 / 100 / 150 / 150 / 100 / 100 / 249 / 150 |
| after | 108 / 152 / 145 / 145 / 73 / 103 / 209 / 164 |

An actions-bearing grid measures byte-identical before and after.

## Traps (all of these were paid for once already)

- **Never measure a live cell's `scrollWidth`.** `col_resizable.js` forces
  `table-layout:fixed`, so a body cell's box tracks the COLUMN's current width - including
  the width this very function assigned last run. Measure, add a buffer, repeat, and the
  column grows unboundedly on every resize tick (reproduced as ~10px per call with no actual
  resize). Always clone into a detached `table-layout:auto` holder that keeps the `.epesi-gb`
  / `.Utils_GenericBrowser` classes, so the theme's icon and hidden-image rules still apply.
- **Round so the row lands UNDER the container, never over.** `Math.floor` for shares,
  `Math.ceil` only for values working *against* the total (a fixed column's own footprint, a
  donor's give-back), minus a 2px buffer. Chrome and Firefox do not round table columns
  identically and 1px over is enough to trigger `.table-responsive`'s scrollbar.
- **CSS must not set `width`/`min-width` `!important`** on these columns - it fights the
  inline value this script writes, the same way it used to fight the PHP-computed one.
- The whole body is wrapped in `try/catch` deliberately: an uncaught exception in any one
  `e:load` observer aborts the entire shared script line it runs in, including the StatusBar
  call right after it.

## Verifying a change to this code without app credentials

The emitted JS can be reconstructed and tested standalone, which is much faster than a
login-and-click-through pass and catches the whole class of "does this table get sized at
all" questions:

1. Slice the lines between `eval_js_once(` and its closing `);` out of the template, strip
   the trailing `.`, and eval it as a PHP expression - it is pure string-literal
   concatenation, so this is safe and exact.
2. `node --check` the result. Catches unbalanced braces in the concatenated string, which
   `php -l` on the template cannot see. (`php -l` still matters, but the template is a Smarty
   file - extract the `{php}...{/php}` block to a temp file first.)
3. Build a `file://` harness page: inline
   `modules/Utils/GenericBrowser/theme_adminltedark/default.css`, hand-write grid markup
   matching `html_grid_epesi_attrs_to_div()`'s real output (inline `style="width:N%"`, NOT a
   `width=` attribute - see above), stub `window.jQuery` to a no-op `.on()`, and append the
   reconstructed JS.
4. Drive it with `chromium.launch({ executablePath: <the ms-playwright chromium under
   AppData/Local> })` and read back `getBoundingClientRect().width` per header, plus
   `wrapper.scrollWidth > wrapper.clientWidth` for the scrollbar regression, and a count of
   cells where `firstChild.scrollWidth > clientWidth` for clipping.
5. Run the same harness against the pre-change JS from `git show HEAD:<file>` for a true A/B,
   and once more with an actions column to prove the existing path is untouched.

A caveat on the harness: it is not a substitute for the real page for anything involving the
surrounding chrome (card padding, sidebar width, the real actions-column content), and small
absolute discrepancies against the live app are expected there. It is authoritative for
*relative* questions - does this grid get sized at all, did the actions path change, which
column gained and which donated.

See [environment-gotchas.md](environment-gotchas.md) for the browser/Playwright specifics on
this machine, and its standing rule: credentials for the real app are asked for in-session,
never written into a git-tracked doc.

---

# Part 2: mobile reflow (the 2-line grid)

Triggered by `CRM_LoginAudit` being unreadable on narrow/mobile viewports (7 columns
squeezed onto one line). Sections below are in the order they were written, so later ones
correct earlier ones; the per-screen opt-outs near the end are the most-edited part.

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

**Superseded for the AdminLTE theme (2026-08-31):** those percentages are now only the
*starting point* - see Part 1 above, which re-sizes every column to px against the real
container and moves unused width to columns short of their content. The paragraph above
still describes the legacy `theme/` path exactly, and the CSS-level reason
`.table-responsive` never engages.

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
  `mobile_cols = max(1, ceil($visible_count/2))` (see 2026-08-11 fix below for what
  counts as "visible") and assigns it as a template var; the
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

## Utils_Attachment Notes/Journal addon: opted out of the 2-line grid entirely (2026-08-10)

Reported after visual verification: the Notes addon (`Utils_Attachment::body()`, embedded as
a tab on Contacts/Companies/etc. — Journal is the same widget, different `$crits`/caption,
see `theme_adminltedark/default.css`'s own `data-rb-tab="utils_attachment"` comment) rendered
fine before today's change and shouldn't have been swept up in the generic fix — its `note`
column is a wide `Utils_RecordBrowser__tallpreview` text column (see this file's "expandable
cells" entry in `default.css`), not a fixed value that benefits from an even N-way grid split.
This was flagged as a risk in advance, see "Regression surface to retest" above.

Unlike the kebab-only opt-outs above, this table opts out of the *entire*
`max-width:767.98px` block, reverting to the pre-fix proportional-width table-cell layout.
Same `set_prefix()`/`set_postfix()` wrapping technique as `view_edit_history()`'s
`.epesi-rb-changes-history` (`RecordBrowser_0.php:2462-2464`), but `Attachment_0.php` calls
`show_data()` directly rather than going through `body()`, so it has no `$gb` of its own to
call `set_prefix()` on — added a small public hook, `RecordBrowser_0.php`'s
`set_data_gb($gb)`, reusing the existing private `$data_gb` property `show_data()` already
checks (`if ($this->data_gb!==null) $gb = $this->data_gb;`) for RecordBrowser's own full-page
Browse mode (`body()`/`show_filters()`). `Attachment_0.php::body()` now pre-builds a
`Utils_GenericBrowser` child itself, wraps it in `<div class="epesi-attachment-notes">`, and
injects it via that setter before calling `show_data()`.

`theme_adminltedark/default.css`'s mobile 2-line block gained a `:not(:has(.epesi-attachment-notes))`
clause on every selector, scoped to the ancestor `.epesi-gb` card — simplest option since the
marker div sits *inside* the grid wrapper those rules target, not on it.

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

## Bug found in real visual verification: empty trailing cells on every row (2026-08-11)

Reported via screenshot (Companies Browse mode, Ticket list) on a real phone-width
viewport: every row - header and data alike - left 1-2 grid cells visibly empty at the
end of its second physical line, and on Companies the header's second line read
"Account Manager, ⋮" instead of the expected City/Company Name/Phone/Group/Account
Manager/actions split evenly across two lines. This is the "Known limitation" flagged
in `default.css`'s own comment when the feature was first implemented (2026-08-10) -
turned out to be a real, visible defect, not just an imbalance.

Root cause: `--epesi-gb-mobile-cols` (`default.tpl`) was computed from `count($cols)` -
every column RecordBrowser hands to `{html_grid_epesi}`, including its own
favourite/watchdog columns (`RecordBrowser_0.php`'s `fixed_columns_class`,
`RecordBrowser_0.php:459`/`464` - tagged via `attrs="class=Utils_RecordBrowser__favs"` /
`__watchdog`). But `default.css`'s own pre-existing `@media (max-width: 991.98px)` rule
(`default.css:1000-1015`) already `display:none`s exactly those two columns' `__th`/`__td`
at this width (991.98px covers 767.98px too) - and a `display:none` grid item is removed
from CSS Grid's auto-placement flow entirely, not just visually hidden in place. So the
*actual* number of items landing in the `repeat(var(--epesi-gb-mobile-cols), 1fr)` grid
was 1-2 less than what `--epesi-gb-mobile-cols` (computed from the pre-hide total) assumed,
leaving that many trailing cells on the row's second line empty. Confirmed algebraically:
for a table with favs+watchdog+5 data columns+actions = 8 total columns,
`mobile_cols = ceil(8/2) = 4`, but only 6 columns actually render at this width, so line 2
(items 5-6: Account Manager, actions) filled only 2 of its 4 slots.

Fix: `default.tpl`'s `{php}` block now counts only columns whose `attrs` string does
*not* match `Utils_RecordBrowser__favs`/`__watchdog` before computing
`ceil(.../2)` - i.e. it counts what will actually be visible at this breakpoint, the
same two columns the 991.98px rule already excludes. The actions column is deliberately
*not* excluded from the count - unlike favs/watchdog, its `__th`/`__td` box stays in
grid flow at this width (only its inner icons collapse into a kebab via a separate,
unrelated mechanism - see "---- mobile actions menu ----" in `default.css`), so it
still needs a grid slot. `default.css`'s stale "Known limitation" comment was rewritten
to describe the fix instead. Not yet re-verified in a browser after this change.

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
