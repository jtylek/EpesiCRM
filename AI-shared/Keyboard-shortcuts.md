# Keyboard shortcuts

Epesi is keyboard-first: `/` jumps straight to any page by name, once you're on a List page it
can be browsed — searched, its tabs switched, its rows moved through, opened or added to —
without a mouse, and a Create or Edit page can be saved or left the same way. Every shortcut
below is a single key with no modifier held, except two that use Ctrl/Cmd (Save and Cancel —
see below), and is silently ignored while a field already has focus, so it never
interferes with typing, or while a modal is open — the same guard on all but one of them
(Ctrl/Cmd+E, noted below). Most are scoped to a List, Create or Edit page, noted below.

## Reference

| Key | Where | Does |
| --- | --- | --- |
| `/` | anywhere | Opens the quick switcher — jump to any page by name |
| `Backspace` | anywhere | Goes back a page |
| `A` / `F` / `R` | a List page | Switches to the All / Favorites / Recent tab, whichever the resource has |
| `M` | a List page | Switches the records select between "My records" and "All records", where the list has one |
| `I` | a List page | Cycles the status select through "Active", "Inactive" and "All", where the list has a status |
| `S` | a List page | Focuses the search field |
| `N` | a List page | Opens the "New …" page, if the resource offers one |
| `↑` / `↓` | a List page | Moves the highlighted row |
| `PageUp` / `PageDown` | a List page | Turns the table's page |
| `Enter` | a List page, a row highlighted | Opens the highlighted row |
| `Space` | a List page, a row highlighted | Previews the highlighted row in place, if it has one |
| `Enter` | a List page, search field focused | Runs the search and lands on its first row |
| `Ctrl`/`Cmd`+`S` | a Create or Edit page | Saves |
| `Ctrl`/`Cmd`+`E` | a Create or Edit page | Cancels — discards the changes and leaves |

## Quick switcher ("/")

Pressing `/` anywhere in the main panel (unless a field already has focus, or a modal is
already open) opens a centered "Jump to…" modal: type a few letters of a sidebar item's
label, arrow through the matches, Enter navigates. The list stays empty until the first letter
is typed rather than opening on every sidebar item, which stops fitting on the screen once
enough modules are installed.
`resources/views/filament/components/command-palette.blade.php`, wired in through
`MainPanelProvider`'s `BODY_END` render hook. The item list is `Filament::getNavigation()`
flattened across every group, so it needs nothing when a resource, page or navigation group
(see [Navigation-groups.md](Navigation-groups.md)) is added — the same list the sidebar itself
renders from. Roundcube's Mailbox page bridges the same shortcut in from inside its iframe
(`modules/Epesi/Roundcube/resources/views/mailbox.blade.php`): a keydown inside a same-origin
frame never reaches the parent window on its own, so the bridge listens on the frame's own
`contentWindow` and forwards `/` as the same `open-modal` event.

## Browsing a List page

Once "/" (or anything else) has landed you on a recordset's List page,
`modules/Epesi/RecordBrowser/resources/views/list-keyboard-nav.blade.php` takes over — wired
in through `RecordBrowserServiceProvider`'s `TOOLBAR_START` render hook on every List page
(unconditionally, unlike the tabs below, so even a resource with only the "All" mode gets
search and row browsing).

- **A / F / R** jump straight to the All / Favorites / Recent tab (`BrowseMode`, see
  ListRecords) when the resource has it — only the modes that actually exist get a key, which
  is why the mnemonic lines up with `BrowseMode`'s own labels for free.
- **M** switches My records / All records (`ListRecords::toggleMyRecords()`); only
  bound when the list has that select (`getMyRecordsButton()` is not null).
- **I** cycles Active / Inactive / All (`ListRecords::toggleStatusMode()`); only bound when the
  list has a status filter (`getInactiveToggle()` is not null).
- **S** focuses the table's own search field.
- **N** opens the resource's "New …" page — the same link the header's own button is, found
  by its URL (`a[href$='/create']`) rather than its label, so it needs no resource-specific
  wiring and works whatever language that label is in. Silently does nothing where a role
  can't create the resource, since there's then no such link to find. Ctrl/Cmd+N was briefly
  tried, to match Cancel's modifier below, but Ctrl+N is a reserved browser shortcut (opens a
  new window) that a page can't intercept — plain N is the only option here, unlike Cancel's
  Ctrl/Cmd+E below, which has no such reservation.
- **↑ / ↓** move a highlighted row the same way a mouse hovering it does (`epesi-row-active`,
  the same background `.fi-ta-row.fi-clickable:hover` already gets — not Filament's own
  `.fi-selected`, which means a bulk-select checkbox is ticked). Every row gets a highlight,
  not just `.fi-clickable` ones — Notes (`Epesi\Attachments\AttachmentResource`) sets
  `->recordUrl(null)` deliberately, since its row expands a preview in place instead of
  navigating (`preview()`). **Enter** opens the highlighted row — the same place a click on
  it goes (`AppServiceProvider::preferViewOverEditOnRecordClick()`'s `recordUrl`), falling
  back on a row with no such link to the same thing **Space** always does: clicking whichever
  of the row's own `x-on:click` targets is currently visible (Notes' expand-in-place toggle) —
  a no-op on a row that has none.
- **PageUp / PageDown** just turn the table's page (clicking Filament's own pagination
  buttons); the highlight starts over on the next ↑/↓ once that page has loaded, rather than
  chasing the same record across pages.
- **Enter inside the search field** lands on the first result row the same way ↓ would, once
  the search has actually gone through — no Tab needed to get from typing a search to
  browsing its rows. Tab from the search field also no longer stops on the filter and
  column-manager triggers beside it (they stay clickable, just out of the tab order), so the
  very next Tab reaches a row.
- Filament's search field forces its debounced search early on Enter without cancelling the
  debounce's own timer — typing at a normal "type it, hit Enter" pace, not just fast typing,
  can still leave that timer pending when Enter lands, and the table update firing twice close
  together corrupts its own row-selection state (Alpine errors, a table that goes empty). The
  fix catches Enter on `window` in the capture phase — which runs before the search field's
  own `keyup` listener regardless of attach order, unlike trying to attach a second listener
  on the field itself and race it to go first — and stops that keyup from reaching the field
  at all, so only the debounce's single request ever happens.
- Guarded the same way "/" is: never while typing into a field (the search field's own Enter
  handling is the one exception above), and never while any modal — ours or Filament's own —
  is open over the table (`.fi-modal.fi-modal-open`).

## Creating or editing a record

`modules/Epesi/RecordBrowser/resources/views/form-keyboard-shortcuts.blade.php`, wired in
through `RecordBrowserServiceProvider`'s `PAGE_END` render hook on every Create and Edit page.

- **Ctrl/Cmd+S saves**, on both pages, with nothing of Epesi's own: it's Filament's own default
  `keyBindings` on the Save action
  (`vendor/filament/filament/src/Resources/Pages/{Create,Edit}Record.php`). "mod" is
  mousetrap's (the library behind Filament's own `keyBindings`) cross-platform alias for
  Ctrl on Windows/Linux and Cmd on a Mac.
- **Ctrl/Cmd+E cancels** — discards the form and leaves, the same place clicking Cancel already
  goes (back, on Create, if there's a referrer to go back to, else the panel's home; the
  record's own View page, on Edit). Found the same way N finds "New…" on a List page: the
  header action right after Save is always Cancel on both pages (`CreateRecord.php`,
  `EditRecord.php`'s `getHeaderActions()`), clicked directly rather than this needing to know
  which of the two behaviours applies. Two other keys were tried first and rejected: plain
  Escape, because a browser exits full screen on a bare Escape
  (`resources/views/filament/components/fullscreen-toggle.blade.php`) and won't let a page
  `preventDefault()` that away; and Ctrl/Cmd+Escape, because Ctrl+Escape opens the Windows
  Start menu and Alt+Escape cycles open windows — both handled by the OS before the page ever
  sees them. Ctrl/Cmd+E has no such reservation: Chrome's own Ctrl+E just focuses the address
  bar, and unlike closing a tab or opening a window, a page is free to `preventDefault()` that
  away.
- Ctrl/Cmd+E is the one shortcut in this file — in the app — that fires even while a field has
  focus: the point is to leave the form from wherever you're typing in it, the same way
  Ctrl/Cmd+S saves from wherever you're typing (below). It still won't fire while a modal is
  open.
- **A Create page focuses its first field on load** — the first visible `input`, `textarea` or
  `select` inside the page's `<form id="form">` — so typing can start right away with no click
  first. Edit doesn't: there's usually one specific field to change rather than one to start
  typing into.

## Backspace

Goes back one page, anywhere in the main panel — the keyboard shortcut for the topbar's own
Back button (`window.history.back()`, `history-navigation.blade.php`). Guarded like every
other single-key shortcut here: never while typing (Backspace's usual job there) or while a
modal is open.

## Adding another shortcut

Every shortcut above follows the same shape: an `x-on:keydown.window` (one shared handler per
page type — `list-keyboard-nav.blade.php`, `form-keyboard-shortcuts.blade.php`) that returns
early when `event.target.tagName` is `INPUT`/`TEXTAREA`/`SELECT`, `event.target.
isContentEditable`, or `document.querySelector('.fi-modal.fi-modal-open')` finds an open
modal, then `event.preventDefault()`s before acting. Ctrl/Cmd+E is the one exception, and only
because a modifier combo has nothing to step on inside a field: see "Creating or editing a
record" above before dropping the field-focus guard on a new one. A new shortcut belongs in
whichever existing file already
owns that part of the page (the topbar's own component, or a List/Create/Edit page's
keyboard-shortcuts partial) rather than a new file, unless it's genuinely a new part of the
page.
