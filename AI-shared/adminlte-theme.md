# AdminLTE theme(s) status

## `adminlte` (light) removed entirely — `adminltedark` is now the only AdminLTE-family theme (2026-08-04)

Per explicit direction: stop spending time fixing/debugging the light-only
`adminlte` theme and focus solely on `adminltedark` going forward. All 34
`modules/*/theme_adminlte/` directories in the main repo (75 files) were
deleted outright, not deprecated — same "delete, don't leave inert" approach
as the [[quickjump-removed]]/[[legacy-mobile-removed]]-style removals
elsewhere in this codebase. `modules/Premium/Projects/Tickets/theme_adminlte`
was deliberately left untouched — Premium is a separate, separately-licensed
git repo (see this file's own README), out of scope without a separate
decision there.

Everything below this entry that still describes `adminlte` (light) — the
now-removed `## adminlte (light)` section, and any path example naming
`theme_adminlte` in the traps list further down — is historical, describing
work against files that no longer exist. Left in place rather than deleted
outright: the underlying lessons (CSS-per-rendering-module, class-name
collisions, `data-bs-theme` pin scoping, etc.) still apply verbatim to
`adminltedark`, just substitute `theme_adminltedark` for `theme_adminlte` in
any path mentioned.

**`Base_ThemeCommon::is_adminlte_family()`** (`modules/Base/Theme/ThemeCommon_0.php`)
was narrowed from `array('adminlte', 'adminltedark')` to just
`array('adminltedark')` rather than removed — every one of its ~17 call sites
across the codebase is a legitimate "is this the Bootstrap/AdminLTE-based
rendering path" check that still needs to hold for `adminltedark`, and the
abstraction costs nothing to keep for a hypothetical future family member.

**Two live hardcoded-path bugs found and fixed *before* deleting the
directories** (would otherwise have 404'd for `adminltedark` too, since
neither branches by which family member is active):
`Utils_Tooltip/TooltipCommon_0.php`'s `ajax_open_tag_attrs()` and the
module-load `load_js()` at the bottom of that file both hardcoded
`theme_adminlte/tooltip.js`; `Utils_PopupCalendar/PopupCalendarCommon_0.php`'s
`create_href()` hardcoded `theme_adminlte/main2.js`. Both `theme_adminltedark`
copies were confirmed byte-identical (`diff`, zero output) before repointing,
so this was a latent bug with no behavior change today — it only would have
started 404ing once `theme_adminlte/` was gone.

**Two more hardcoded `<link>`s, in the *permanently*-AdminLTE-styled admin/
setup/login chrome** (`admin/templates/layout.tpl`, `include/templates/
login_page.tpl` — independent of the user-selected app theme, see
`MIGRATION_NOTES.md` and [[admin-tools-adminlte]]/[[update-check-adminlte-split]]):
both had a comment explicitly stating intent — "reused as-is (not copied) so
this shell stays in sync with the real app's own AdminLTE shell/login screen"
— pointing at `Base_Box`'s / `Base_User_Login`'s `theme_adminlte/default.css`.
Repointed to their `theme_adminltedark/default.css` equivalents instead of
forking a static copy, honoring that same stated intent now that the real
app's shell/login screen *is* `adminltedark`. Confirmed safe: both
`theme_adminltedark/default.css` files already carry a full
`[data-bs-theme="light"]` override layer (CSS custom properties redefined
under that selector, auto-generated, see `gen_light_override.js` mentioned in
several `theme_adminltedark/*.css` file headers) — these two templates already
pin `data-bs-theme="light"` on their wrapper, so they render via that override
layer, not the dark defaults.

**Migration patch**: `modules/Base/Theme/patches/20260804_remove_adminlte_theme.php`
flips any existing install's `default_theme` variable from `'adminlte'` to
`'adminltedark'` if still set that way — this dev DB's own `default_theme` was
already `'adminltedark'` (set by `ThemeInstall.php` since before this pass),
and there's no per-user theme override anywhere in the codebase (`default_theme`
is the only place a theme name is ever stored), so this patch only matters for
some other, real existing install where an admin had explicitly picked the
light theme.

**Not done as part of this pass** (flag before assuming it's covered): the
stale `theme_adminlte`-path comments left throughout the codebase (mostly
"see Base_Box/theme_adminlte/default.css" style cross-references explaining
*why* some other file's value was chosen) were not scrubbed — cosmetic
staleness only, the values themselves are still correct. `Base_AdminlteIcons`
(`modules/Base/Theme/adminlte_icons.php`) and every module's own
`adminlte_icon()` static method were **not** touched — that's shared,
family-wide icon-resolution infrastructure `adminltedark` depends on, named
after the framework/family, not the removed theme variant.

## Legacy `theme/` converted to div-only layout (2026-08-04)

The legacy `theme/` (old default, pre-AdminLTE, table-based) is now fully
div/flexbox/CSS-Grid-based, matching what `theme_adminlte(dark)/` already did
per-screen. Scope: all of `theme/` across every module, plus
`admin/templates/`, `setuptheme/`, `include/templates/`, and root `theme/`
(the boot splash). Point 7 below ("Epesi renders almost everything as nested
`<table>`s") is resolved for `theme/` as a result — the whole bug class it
described no longer applies there.

**New Smarty plugin**: `Base_Theme/smarty/plugins/function.html_grid_epesi.php`
replaces `html_table_epesi` for GenericBrowser's core list grid in the legacy
theme (same `loop`/`cols`/`row_attrs`/`table_attr` param contract, emits
`role="table"`/`row`/`columnheader`/`cell` div markup instead of `<table>`).
`function.html_table.php` and `function.html_scrolled_table_epesi.php` (+ its
companion JS) were confirmed to have zero remaining callers and were deleted
outright rather than converted.

**Dropped feature**: GenericBrowser's column-resize (jQuery `colResizable`,
`js/col_resizable.js`) was removed — the vendored plugin hard-requires a real
`<table>` and no div-compatible equivalent was available. Sort/filter/search
are unaffected. `GenericBrowser_0.php`'s `$resizable_columns`/
`set_resizable_columns()` API surface is left in place but now inert.

**PDF/email carve-outs — these 4 templates deliberately still emit
`<table>`, do not "fix" them**:
- `Utils/GenericBrowser/theme/pdf.tpl` — still calls `{html_table_epesi}`.
- `Utils/RecordBrowser/Reports/theme/pdf_row.tpl`
- `CRM/Calendar/Event/theme/pdf_version.tpl`
- `Utils/RecordBrowser/theme/RecordPrint.tpl` — found *during this pass*, not
  in the original inventory. Confirmed via `RecordPrinter.php` →
  `Base_Print_Printer` → `Base_Print_Document_PDF::add_content()` →
  `Libs_TCPDFCommon::writeHTML()`: this exact template's HTML is fed straight
  into TCPDF, which only reliably renders `<table>`-based layout, not
  flex/grid. Verified PDF export still works end-to-end post-conversion
  (`Base_Print/Handle.php` response: `content-type: application/pdf`, 94KB,
  valid).
`Utils/RecordBrowser/theme/changes_list_email.tpl` (an inline-styled **email
body**, not PDF) was left as `<table>` for the same reason — email client CSS
support is closer to TCPDF's than to a browser's. `Base_Theme/smarty/debug.tpl`
(Smarty's own vendored debug console, see `MIGRATION_NOTES.md` §17 on why
Smarty 2 itself is never touched) was also deliberately left alone — not a
carve-out for rendering reasons, just out of scope as vendored code.

**Files missed by the original inventory, found via a post-hoc full-repo
grep sweep and converted in the same pass**: `Utils/LeightboxPrompt/theme/
form.tpl`, `CRM/Followup/theme/leightbox.tpl`, `Utils/FileStorage/theme/
download.tpl`. If a future sweep of this codebase turns up more legacy
`<table>` usage outside `modules/Premium/` (out of scope, separately
licensed/gitignored), treat it the same way, using whichever of these 5
recipes fits — don't assume this pass's inventory was exhaustive:
1. **Label/value row** (`single_field.tpl`): `<tr><td class="label">`/
   `<td class="data">` → `<div class="epesi-rv-row"><div class="label">`/
   `<div class="data">`, tag swap only. Row is `display:flex`.
2. **Generic field-list form** (QuickForm's `column.tpl`): CSS Grid
   (`grid-template-columns: minmax(120px,max-content) minmax(0,1fr)`), label/
   data divs emitted flat — grid auto-placement pairs them, no row wrapper.
3. **Repeating group hand-wrapped into a new `<tr>` every N items**
   (`RecordBrowser/Filters/theme/elements.tpl`): delete the modulo/counter
   bookkeeping, replace with `display:flex; flex-wrap:wrap` on the container.
4. **Genuine 2D data grid** (GenericBrowser's list, calendar month/week/day
   grids, Settings matrix): CSS Grid with explicit `grid-template-columns`
   (computed via `{$x|@count}` when column count is dynamic), plus
   `role="table"`/`"row"`/`"columnheader"`/`"cell"` ARIA attributes to
   restore the semantics real `<table>` markup gave for free. For a row that
   needs to stay hoverable/addressable as a group, wrap it in a
   `display:contents` div with `role="row"` — its cells still lay out
   directly against the grid's own column tracks.
5. **`rowspan`-dependent layout**: prefer native CSS Grid `grid-row:span N`
   over a flex-sibling-stacking workaround — simpler and, per the
   CRM_Meeting bug above, less error-prone. If flex-sibling-stacking is used
   anyway (make the rowspanning cell a flex sibling of a second flex item
   that internally stacks the rows that used to share the rowspanned cell),
   triple-check the closing `</div>` for that second flex item lands *after*
   every row meant to be inside it, and verify live in a browser, not just
   by reading the template.

**A real bug found and fixed during this pass** (not present before this
conversion touched the file, and not caused by the div conversion mechanics
themselves — a nesting mistake made while doing it): `CRM/Meeting/theme/
default.tpl`'s rowspan-replacement wrapper column (`flex: 1 1 0; min-width:
0`, standing in for the original `<td rowspan="3">`'s sibling) was closed one
`</div>` too early, leaving the multiselects/longfields/alert sections as
*siblings* of that wrapper instead of children stacked inside it. Since those
sections have `flex-basis:auto` (auto-sized to content) and the wrapper had
`flex-basis:0` (grows only from `flex-grow`), the auto-sized siblings claimed
essentially all the row's width first, leaving the wrapper — and everything
inside it, including Title/Permission/Employees/etc — collapsed to ~0px
(visually: field values wrapped one character per line). Caught by live
browser verification, not by grep or lint — this class of bug (a flex/grid
container is syntactically valid HTML and produces no console error) is
invisible to static checks. `CRM/Calendar/Event/theme/default.tpl` uses the
same rowspan-replacement pattern and was re-checked by hand against this
exact failure mode — nesting confirmed correct there.

**Also found and fixed: a stale-CSS-selector regression from the GenericBrowser
grid conversion itself.** `Utils_GenericBrowser`'s core list grid moving from
`<table>`/`<thead>`/`<tr>`/`<th>`/`<td>` to `.Utils_GenericBrowser__thead`/
`__tr`/`__th`/`__td` div classes broke any *other* module's CSS that still
targeted the old tag-based selectors for a GenericBrowser widget embedded
outside GenericBrowser's own theme files. Found in `Utils/FrontPage/theme/
default.css` (a whole duplicated block, `.contents table > thead > tr > th`
etc.), `Base/Dashboard/theme/default.css` (`.Utils_GenericBrowser th`), and
`Utils/RecordBrowser/Reports/theme/default.css` (`table.Utils_GenericBrowser
tr:hover`) — all fixed to the current class-based selectors. This class of
bug doesn't show up in a `<table` grep (it's CSS, not HTML) — if
`Utils_GenericBrowser`'s own markup ever changes again, grep for
`Utils_GenericBrowser.*\b(tr|td|th|thead|tbody)\b` and `table\.Utils_GenericBrowser`
across all `*.css` first, not just the files being actively edited.

**Pre-existing bugs surfaced by browser-testing this pass (not caused by it,
left as found — report/decide separately, don't silently fix mid-conversion)**:
- `Base_ActionBar/theme/default.tpl`'s `{if $i.icon_url}` undefined-array-key
  warning — this one **was** fixed, as a low-risk drive-by, since it was in a
  file already being edited for the conversion itself.
- The shared confirm-modal (`Module::create_confirm_href()`/
  `window.epesi_confirm()`) renders unstyled/visible inline off-AdminLTE,
  because Bootstrap's `.modal{display:none}` CSS isn't loaded for the legacy
  theme. Still unfixed.
- `{foreach item=n from=$new}` (a per-record "what's new" tooltip loop) is
  unguarded by `isset()` in **22 View_entry-family templates** across both
  the legacy and AdminLTE-family themes (`View_entry.tpl`, `Contact.tpl` ×2,
  `mails.tpl` ×2, `PhoneCall`'s and `Meeting`'s `default.tpl`,
  `Attachment/View_entry.tpl`, all three themes) — pre-existing in the
  original table markup, carried forward verbatim, not introduced by this
  pass. Triggers `E_WARNING: Undefined array key "new"` under
  `REPORT_ALL_ERRORS` on `view`/`edit` actions where `$new` isn't assigned.
  Still unfixed — systemic enough (22 call sites) to warrant its own pass
  rather than a drive-by fix.
- Similarly-shaped unguarded-array-access warnings exist elsewhere in
  `CRM_Meeting`'s own template (`$event_info.start_date` compared without an
  `isset()` guard, hit by all-day/"Company Holiday"-type records) and in
  `RecordBrowser/theme/RecordPrint.tpl` (`$no_access`) — same "pre-existing,
  verified against the original baseline via git, not this pass's doing"
  story, left alone for the same reason.

**Verification performed**: `php -l` on every touched PHP file; a full-repo
grep sweep for `<table|<tr|<td|<th` across the entire in-scope tree (clean
except the carve-outs above and this file's own explanatory comments); live
Playwright testing of ~15 screens in the legacy theme (top bar/ActionBar
chrome, Dashboard applets, GenericBrowser list view, RecordBrowser
View_entry — Contact/PhoneCall/Task/Meeting incl. recurring and
timeless/all-day variants, PopupCalendar widget incl. year/month drill-down,
Calendar month/week/day/year views, a Leightbox popup) plus the PDF export
pipeline — all confirmed working, zero console errors.

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

One AdminLTE-based theme exists, under `modules/*/theme_adminltedark/`
(replaces the original `theme/` (legacy, table-based) look; the light-only
`adminlte` sibling was removed 2026-08-04, see the dated entry at the top of
this file). Themes resolve straight from `modules/` — no build step, no
generated copy (see `Base_ThemeResolver::resolve()`: `theme_<name>` first,
falls back to legacy `theme/`). **Any module without its own
`theme_adminltedark/` override silently falls back to the legacy light
table-based theme** — this is still a large gap, not a bug.

## `adminlte` (light) — started 2026-07-26, removed 2026-08-04

Superseded by `adminltedark` below, then deleted outright (see the dated entry
at the top of this file). Before removal it had reached working/
browser-verified coverage of: login, app shell, sidebar menu, GenericBrowser
record lists, ActionBar/Launchpad, Dashboard applet chrome, TabbedBrowser,
Admin/User-Settings panels, RecordBrowser view/edit, Search, Leightbox popup
chrome, module-indicator icons — all since carried forward by `adminltedark`,
which covers the same module set (see below). Kept here only as a pointer for
anyone wondering what happened to it; no per-screen detail preserved since
none of it maps to an existing file any more.

## `adminltedark` — created 2026-08-01, sole AdminLTE-family theme since 2026-08-04

Was a **full independent fork** of `theme_adminlte/` (not resolver-chained —
a module it doesn't cover falls straight to the legacy theme, not to a
now-nonexistent `adminlte`). Covers the same ~34 modules `adminlte` used to;
module-coverage expansion ("Phase 2") was never started and is now the only
remaining path for growing AdminLTE-family coverage. Has a live navbar
light/dark toggle built on AdminLTE's own `data-bs-theme-value` color-mode
toggler (`adminlte.min.js`'s `Me` class) rather than a custom implementation.

**Not yet themed / not audited** (inherited from `adminlte`, never closed):
individual dashboard applets' own inner content (Weather, RssFeed, Shoutbox
history, Calc, etc. mostly `print()` raw HTML), `Base_Admin/theme/
access_panel.tpl`, QuickForm's raw-table renderer (`Libs/QuickForm/Renderer/
TCMSDefault.php`, used by `Utils_Wizard` — its `_headerTemplate`/
`_elementTemplate`/`_formTemplate`/`_requiredNoteTemplate` raw strings were
converted from `<tr>/<td>` to `<div>` as part of the 2026-08-04 legacy-theme
div conversion, since the class is theme-agnostic and shared by every theme;
still a CSS-only reskin, not converted to the Smarty array renderer),
leightbox popup *contents* (e.g. CRM_Filters "manage perspectives"),
Base_Help's tutorial overlay.

**Tooltips** (`Utils_Tooltip`, 2026-08 restyle): grey/black to match the
sidebar (`#DEE2E6`/`#000`, hardcoded not themed — see
`Base/Box/theme_adminltedark/default.css`'s sidebar rule for why). Native
tooltips (`title="..."`, the pre-2026-08 mechanism) can't be recolored by CSS
at all, so recoloring forced a real rendering change. Two things were tried
and rejected before landing on the current approach:
1. A JS-driven Bootstrap tooltip *component*, three separate times, well
   before this restyle — broke real functionality in hard-to-diagnose ways
   each time (load-order races, orphaned popups, conflicts with
   `GenericBrowser`'s own hover-driven `table_overflow_show`). Treat any
   future JS tooltip *component* attempt here as high-risk.
2. A pure CSS `::after` popup (`[data-tooltip]:hover::after`, no JS at all) —
   seemed safest given (1), but had to be dropped too: it's clipped by any
   ancestor with `overflow:hidden`, and that turned out to include plain
   Bootstrap `.card` containers (rounded corners on dashboard applets, admin
   panels, etc.), not just `RecordBrowser`/`GenericBrowser` table cells'
   ellipsis truncation — i.e. most of the app, not an edge case. Confirmed by
   hovering a dashboard applet's own "Remove" icon in a live browser: computed
   `content`/colors were correct, but invisible, clipped by `.card`.

**Ajax tooltip responses are now cached client-side, shared by `tooltip_id`
across every element on the page (2026-08-09).** Previously
`epesi_tooltip_ajax_load()` fetched fresh from `Utils/Tooltip/req.php` on
every element's *first* hover, even when several elements resolved to the
exact same `tooltip_id` (`ajax_open_tag_attrs()`'s
`md5(serialize($tooltip_settings))`) — e.g. a RecordBrowser list where the
same Customer/Contact is linked from several rows (`CRM_ContactsCommon`'s
`company_get_tooltip()`/`contact_get_tooltip()`, or the generic
`create_default_record_tooltip_ajax()` path any other module's record-hover
tooltip goes through) fired one redundant round-trip per row. Now a
page-scoped `epesi_tooltip_ajax_cache` (keyed by `tooltip_id`) is checked
first, and an in-flight `epesi_tooltip_ajax_pending` map lets a second
element hovered while the first's request is still in flight piggyback on
that same response instead of firing its own. Safe to share because two
elements only ever land on the same `tooltip_id` when their
callback+args+`safe_html` serialized identically — i.e. they'd have fetched
byte-identical content anyway.
Caught one regression while landing this: the cache-hit branch first called
`epesi_tooltip_ajax_apply()` (the "update an already-open popup" helper,
gated on `epesi_tooltip_current_el === el`) directly — but nothing had
called `epesi_tooltip_show_popup()` yet to make that true for a fresh cache
hit, so every hover *after* the first one on a shared `tooltip_id` silently
showed nothing at all. Fixed by having the cache-hit branch call
`epesi_tooltip_show_popup()` itself, same as the synchronous
`open_tag_attrs()` path (`epesi_tooltip_show()`) already does.
**Deliberately client-side only, not server-side, for now**: the same
per-`tooltip_id` sharing idea was considered for `req.php` too (there's a
real cross-request cache abstraction already in `include/cache.php`'s
`Cache` class), but `contact_get_tooltip()`/`company_get_tooltip()`'s
`Utils_RecordBrowserCommon::get_access(..., 'view', $record)` ACL check and
every tooltip's `__()` translation calls are both evaluated against
*whoever's currently hovering* at fetch time — neither is part of the
`tooltip_id` hash, so a naive shared server cache could leak an
access-gated tooltip to a user without view access, or serve the wrong
language. Would need the cache key to also fold in the user's effective
permission context and language to be safe, which cuts into the cross-user
hit-rate benefit — not implemented.

What's actually live now: both `open_tag_attrs()` ("help" tooltips, e.g.
action icons) and `ajax_open_tag_attrs()` (RecordBrowser "show in tooltip"
record-hover tooltips) render the same plain `.epesi-tooltip-popup` div,
appended to `<body>` on a bare `onmouseenter`
(`Utils/Tooltip/theme_adminltedark/tooltip.js`) — the same body-append escape
`table_overflow_show` itself already uses for overflowing cell content, not a
component with its own event-delegation lifecycle, so still a fundamentally
different risk profile from (1). Known consequence: since this popup and
`table_overflow_show`'s own overflow-preview box can both trigger off
hovering the same `<td>` (the tooltip span is nested inside the cell
`table_overflow_show`'s `onmouseover` is bound to), a cell whose content is
both truncated *and* tooltip-enabled can show both popups at once. Cosmetic
(nothing errors or breaks), not chased further yet.

As part of the original fork, several modules' nested-`<table>` layout was
rewritten as real flexbox/grid (QuickForm's `row.tpl`/`column.tpl`,
RecordBrowser's `View_entry.tpl` + the per-table overrides, the RecordBrowser
filter bar) — landed in `theme_adminlte/` first, then copied into the dark
fork; that provenance is now purely historical since only the dark fork
remains.

**Touch devices: hold-to-preview collided with the native link context menu
(2026-08-10).** The popup above is wired purely via `onmouseenter` — no
touch handling anywhere. On a phone this meant a quick tap on a
tooltip-bearing `<a>` just navigated before the popup could register, while a
long-press synthesized `mouseenter` around the same hold duration the browser
uses to decide "show the native context menu" (Open in New Tab/Copy Link/...)
— so holding the link showed both the popup *and* the native menu at once.
Neither `open_tag_attrs()` nor `ajax_open_tag_attrs()`'s own
`if(MOBILE_DEVICE) return '';` guard helps here — `MOBILE_DEVICE` has been
permanently `0` since `detect_mobile_device()` was deleted (see
[[deliberate-removals]]'s "Legacy mobile system" entry), so every tooltip
renders fully hover-wired even on a phone; that entry calls the remaining
`MOBILE_DEVICE` checks app-wide "dead-but-harmless," which undersells this one
specifically — its deadness is what let this bug happen.

Fixed client-side only, in `tooltip.js`: `epesi_tooltip_mobile_enhance()` runs
on `window` `load` and on `e:load` (idempotent via an
`epesi-tooltip-mobile-done` marker class), and on any `(hover:none)` device,
every `[data-epesi-tooltip="1"]` element that sits inside a real `<a href>`
gets a small `.epesi-tooltip-mobile-trigger` button inserted right after that
link (a sibling, not a child - nesting `<button>` inside `<a>` is invalid
HTML) wired to the *same* `onmouseenter` handler the server already rendered
(captured by reference before being cleared, not reimplemented). Tapping the
button shows the popup without navigating; tapping the link itself navigates
immediately like any ordinary link, since the link's own `onmouseenter` is
nulled out so the browser's native hold gesture no longer triggers the popup
at all. A deferred (`setTimeout`) document-level capture-phase click listener
dismisses the popup on the next tap elsewhere, since `mouseleave` (the
existing dismiss path, still used for real mouse hover) generally never fires
on touch.

## Leightbox popups: fixed grey/black sidebar chrome convention (2026-08-08/09)

Per explicit direction, Leightbox-based popups are being moved, one at a time
as they come up, to the **same fixed grey/black scheme as the app-sidebar**
(`#dee2e6` background, `#000` text — `Base_Box/theme_adminltedark/
default.css`'s sidebar rule, "stays this same color in both light and dark
mode, per request") instead of each popup's own `var(--epesi-*)`-themed dark
chrome. This is not a global default yet — it's applied popup-by-popup on
request, not swept across every Leightbox in the app — but any *new* Leightbox
popup, or one that comes up for other styling work, should default to this
scheme rather than the themed one unless told otherwise. Already converted:
`Libs_Leightbox`'s own header (`#Leightbox_header`) and the Watchdog "what
changed" popup (`#tooltip_leightbox_mode`) — both in `Libs/Leightbox/
theme_adminltedark/default.css`; `CRM_Followup`'s Follow-up popup and
`Premium_Projects_Tickets`' "Change Status" popup (same `_followups_leightbox`
id suffix/plumbing) — `CRM/Followup/theme_adminltedark/leightbox.css` and
`Premium/Projects/Tickets/theme_adminltedark/status_leightbox.tpl`;
`Utils_LeightboxPrompt`'s generic chooser grid (`CRM_Calendar`'s "New Event",
`CRM_Mail`, `Utils_Messenger`, `Base_Lang_Administrator` all reuse it) —
`Utils/LeightboxPrompt/theme_adminltedark/leightbox.css`. See the Tooltips
entry above for the same scheme applied to `Utils_Tooltip`'s hover popup.

**The trap this convention creates: native `<select>`/`<textarea>` text goes
invisible.** Forcing a popup to always-light chrome doesn't stop the *browser*
from still treating the page as dark-themed — `Base_ThemeCommon`'s dark-mode
toggle sets `colorScheme='dark'` on `<html>` (see `ThemeCommon_0.php`), and
browsers use that (not this popup's own CSS) to pick native form-control
colors for anything left unstyled. A `<select>`/`<textarea>` with an explicit
white `background-color` but no explicit `color` renders white-on-white for
both the closed control's own text *and* its native dropdown option list —
`background-color: #fff` alone is not enough. Fix, needed on every such field:
```css
color: #000;
color-scheme: light;
```
`color-scheme: light` is the part that fixes the native dropdown popup
specifically (the option list is browser-native chrome, not something CSS can
otherwise restyle) — `color` alone can fix the closed box but isn't reliably
enough for the open list. Found and fixed in both `CRM_Followup`'s and
`Premium_Projects_Tickets`' popups (same underlying bug, hit independently in
each). See the matching `bug-patterns.md` entry for the full symptom writeup —
check any *other* fixed-light-chrome popup containing a `<select>`/`<textarea>`
for the same gap before assuming only these two had it.

## Recurring CSS/JS traps (read before touching the theme)

1. **CSS loads per rendering module.** `modules/X/theme_adminltedark/default.css` is
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
   `ResizeObserver` in `Base_Box/theme_adminltedark/default.tpl`. Never make a CSS
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

7. **Epesi used to render almost everything as nested `<table>`s, not divs** —
   a systemic source of width/sizing bugs, not a one-off. **Resolved for the
   legacy `theme/` as of 2026-08-04** (see the dated entry at the top of this
   file) — that whole theme is now div/flexbox/CSS-Grid-based, the same as
   `theme_adminlte(dark)/` already was. A nested `<table>` with a percentage
   width has no resolvable preferred width under auto-layout, so sizing
   becomes unpredictable and content-dependent; the flex/grid equivalents
   don't have that failure mode, but introduce a different one instead — see
   the CRM_Meeting div-nesting bug in that same entry: a flex/grid container
   closed one level too shallow produces no HTML validation error and no
   console warning, only a silently-collapsed column, so live browser
   verification (not grep/lint) is what actually catches it.

8. **AdminLTE's `.card-body.p-0` CSS reaches into any `<table>` inside it**
   (adds `padding-left/right` to first/last-of-type cells at the same
   specificity as a per-column override — needs `!important` to beat).
   (`Utils/GenericBrowser/js/col_resizable.js`, previously noted here for
   stripping the `width` HTML attribute from header cells at runtime, was
   deleted 2026-08-04 along with the column-resize feature itself — see the
   dated entry at the top of this file.)

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
