# Utils_GenericBrowser grid layout: column sizing and mobile reflow

> **Status:** REFERENCE - how a grid's columns actually get their widths, and how rows reflow
> on narrow viewports. The full development log — measurements, alternatives considered,
> per-screen opt-outs, verification runs — is archived at
> `AI-private/archive/generic-browser-responsive-tables.md`.

The on-screen grid is **not a real `<table>`**. It is CSS table-display `<div>`s —
`Utils_GenericBrowser__thead` / `__tbody` / `__tr` / `__th` / `__td` — emitted by
`Base/Theme/smarty/plugins/function.html_grid_epesi.php`. The wrapper carries an inline
`width:100%; table-layout:fixed`, so it always fills its container exactly and the surrounding
`.table-responsive` (`overflow-x:auto`) never engages. That is why a narrow viewport squeezes
columns instead of scrolling.

Because it is divs, **a `width=` attribute does nothing** — `html_grid_epesi_attrs_to_div()`
rewrites it into an inline `style="width:N%"`. There is no `width` attribute in the live DOM;
JS reading `getAttribute('width')` finds nothing. A test harness that puts `width="10%"` on a
div is not reproducing the real markup.

## Column sizing (desktop)

**All the width maths is client-side JS in exactly one place:**
`modules/Base/Box/theme_adminltedark/default.tpl`, in the `{php}` block, as
`epesiSizeGbActions()` inside the third `eval_js_once(...)`. Nothing under
`modules/Utils/GenericBrowser/` does width maths beyond emitting the initial percentages.

Consequences worth internalising:

- **Theme-scoped.** The legacy `theme/` path has no equivalent — there the server-emitted
  percentages are the final word.
- **Page-wide, not per-module.** The entry point is
  `document.querySelectorAll('.Utils_GenericBrowser')`, so it sizes every grid on the page,
  including ones nested in dashboard applets, Search results and Shoutbox history.
- The name says "Actions" for historical reasons. It sizes every column.
- It runs on initial load, `document.fonts.ready`, jQuery `e:load`, and a 150 ms-debounced
  `window resize`. It does **not** run when a dashboard applet is dragged into a
  different-width column, so widths there stay stale until one of those fires.

Three column kinds are classified **once** into `data-epesi-col-kind` and never re-derived:

| Kind | Declared as | Sized by |
|---|---|---|
| percent | numeric weight (the common case) | weighted split + redistribution |
| absolute | non-numeric CSS length, e.g. `'12em'` | measured content + 6px |
| fixed icon | `Utils_RecordBrowser__favs` / `__watchdog` (`'24px'`) | measured content + 2px, or 0 when hidden at the mobile breakpoint |

**The one-shot classification is load-bearing.** The same pass writes plain `NNpx` back onto
`th.style.width`, so on the next run a percent column would look exactly like an absolute one
and get routed into content measurement — which, for a free-text Note column, means measuring
thousands of px of unwrapped content and locking the column there. `data-epesi-orig-percent`
caches the original percentage for the same reason.

The pipeline: measure the actions column if there is one (a grid without one reserves 0 and
continues) → measure fixed/absolute columns → force-collapse expanded rows (skipped on resize,
or a row the user just expanded gets re-collapsed) → split the remaining width among percent
columns in proportion to their cached percentages → redistribute.

**Redistribution moves surplus to shortfall, smallest-deficit-first.** A column can only ever
donate what its content does not need, which is what keeps grids with meaningful weights safe.
Filling smallest-first rather than proportionally matters: proportional sharing hands nearly
the whole pool to a bottomless free-text column and leaves a genuinely fixable 28px shortfall
still clipping. No shortfall or no surplus anywhere makes the pass a no-op.

### The declared weights are not dead code

They are still the baseline (they decide who has surplus and who is short), the *entire*
answer whenever demand exceeds supply (a narrow container leaves no pool), the only sizing the
legacy theme has, and literal pixels under `absolute_width(true)` — which RecordBrowser sets
for PDF export, where no JS is involved at all.

What *is* effectively dead is the uniform default: RecordBrowser gives every ordinary text
column the same weight, so "proportional" degrades to "every column identical regardless of
content". That is the whole reason redistribution exists.

### Traps, each paid for once already

- **Never measure a live cell's `scrollWidth`.** Under `table-layout:fixed` a body cell's box
  tracks the column's *current* width — including the width this function assigned last run.
  Measure, add a buffer, repeat, and the column grows unboundedly on every resize tick. Clone
  into a detached `table-layout:auto` holder that keeps the `.epesi-gb` /
  `.Utils_GenericBrowser` classes, so the theme's icon and hidden-image rules still apply.
- **Round so the row lands *under* the container, never over.** `Math.floor` for shares,
  `Math.ceil` only for values working against the total, minus a 2px buffer. Chrome and
  Firefox don't round table columns identically, and 1px over triggers `.table-responsive`'s
  scrollbar.
- **CSS must not set `width`/`min-width` `!important`** on these columns — it fights the
  inline value the script writes.
- The whole body is wrapped in `try/catch` deliberately: an uncaught exception in an `e:load`
  observer aborts the entire shared script line it runs in.

## Mobile reflow (the 2-line grid)

At `max-width: 767.98px` each row becomes an N-column CSS Grid instead of letting
`display:table-cell` squeeze every column proportionally. Header and body rows share the
`__tr` class, so one rule keeps them column-aligned.

`theme_adminltedark/default.tpl` computes `mobile_cols = max(1, ceil($visible_count/2))` and
appends `--epesi-gb-mobile-cols` to the wrapper's inline style; `default.css` consumes it in
`grid-template-columns: repeat(var(--epesi-gb-mobile-cols, 2), 1fr)`. Computing it per table
is what lets one generic rule work for grids with any number of columns.

Three details that are easy to get wrong:

- **Force the ancestors to `display:block` first.** A `display:grid` child of a
  `display:table-row-group` parent risks the browser generating an anonymous `table-row`
  wrapper per the CSS table anonymous-box rules, breaking the wrap.
- **Move the row separator from `__td` to `__tr`.** On `__td` it fires on every *physical*
  line, so one logical row reads as two rows with a separator between them.
- **`__th`/`__td` need `width: auto !important`** inside the same block. Their inline
  `style="width:N%"` is sized against the whole row for the desktop layout; on a grid item a
  percentage resolves against its own already-narrow track, compounding down to near-zero and
  clipping headers to one or two characters. Only `!important` beats an inline style.

At `max-width: 991.98px` a separate, older rule collapses the row-actions column into a kebab
menu and hides the favs/watchdog columns. Extend that pattern rather than inventing a new one.
