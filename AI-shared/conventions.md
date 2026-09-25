# Conventions

Applied to every resource in this app, first-party or module-provided — follow these rather
than Filament's own defaults when building a new resource.

## Terminology: "addon"

A Filament `RelationManager` tab is called an **addon** in conversation and in docs/comments
— matching Epesi's own term for a tab of related data attached to a record. `RelationManager`
stays the actual class name; "addon" is the concept name.

## Addon tables

- **One header row.** An addon table's search box, filters and column selector sit on its
  heading's line, not on a toolbar row below it; they wrap under the heading only when the
  line is too narrow (`HasCompactTableStyles`). The same goes for any table given a
  `->heading()`, such as the Watched records and Shoutbox pages.
- **Every addon has a column selector.** Each of its columns is toggleable and shown by
  default (`AppServiceProvider::giveEveryAddonAColumnSelector()`), so a new addon needs
  nothing. `->toggleable(isToggledHiddenByDefault: true)` starts a column hidden;
  `->toggleable(false)` keeps one out of the selector.

## Linked records

A record shown inside another one — a relation field, a task's employees, what a note is
attached to or an e-mail is linked to — is a badge with a link icon after it, opening that
record's View page, in lists, addons and View pages alike. E-mail and web addresses read the
same. The engine does it for every relation field; anything built by hand uses
`Epesi\Modules\RecordBrowser\Filament\LinkedRecords` (`badges()` for a set of records,
`style()` when the state and link are already worked out). A record with no View page to
open keeps the badge but not the icon.

## Navigation

- **The sidebar is alphabetical**, by each item's translated label, within every group — the
  Dashboard stays first. `App\Filament\Navigation\AlphabeticalNavigationManager` does this for
  every panel, so a new resource or page needs no `$navigationSort`; only a negative one (the
  Dashboard's -2) still counts, pinning an item above the list.
- **View before Edit, everywhere.** Clicking a table row opens the record's View page, not
  Edit — including on vendor/plugin resources that only register an Edit action by default.
- **Tab order on a View page**: real addons first, then any additional static tabs (e.g. a
  Login tab), then Record Info, then **History always last**. The default-active tab is the
  first real addon if one exists, otherwise History — never Record Info or another static
  tab.

## Page header

Every page, in every panel, reads the same at the top: its sidebar icon and name as the first
breadcrumb, header actions on the same line, and no large `<h1>` title (Filament's default).
A resource page reads "(icon) Contacts > List", and a standalone page reads "(icon) Calendar".

- **Resource pages** get this by extending RecordBrowser's `ListRecords`/`ViewRecord`/
  `EditRecord`/`CreateRecord`. A page that extends Filament's own classes has to mix in
  `HasResourceIconBreadcrumb` and `HidesPageHeading` itself.
- **Every other page** (a `Filament\Pages\Page` subclass, including the Dashboard, which is
  `App\Filament\Pages\Dashboard` rather than Filament's own class) mixes in
  `HasPageIconBreadcrumb` and `HidesPageHeading`.

`tests/Feature/PageHeaderTest` checks every page registered in any panel, module pages
included, and fails for any page that is missing one of these traits.

## Edit/Create pages and modals

- One action group, not Filament's default split between header and form-bottom actions.
  Edit: Save + Cancel + Delete in the header ("Cancel" relabels the View action rather than
  using browser-history cancel); redirects to View on save. Create: Create + "Create & create
  another" + Cancel in the header.
- A header Save/Create action needs `formId('form')` — without it, moving these buttons into
  the header leaves them outside the actual `<form>` element and clicking does nothing.
- **Modals follow the same rule.** An action modal's footer actions (Create/Save, Cancel,
  Close…) sit on the heading's line instead of under the form, and there is no corner close
  button (Cancel does that job). Create/Edit modals style their buttons as the pages do:
  Create/Save green with a check, "Create & create another" with a plus, Cancel with an X.
  This is a global default (`AppServiceProvider::putModalActionsOnHeadingLine()`), so a new
  modal needs nothing. Confirmation dialogs (`requiresConfirmation()`), slide-overs, small
  centred modals and modals with no footer keep Filament's own layout.
- **Clone** is a dedicated header action on View pages, not Filament's own `ReplicateAction`:
  it warns and clones immediately on confirm (no pre-fill edit modal), then redirects to the
  new record's Edit page. Unique/identity columns that would collide (email, linked user,
  etc.) are blanked on the clone; `created_by` always attributes to whoever cloned it.

## Record Info

Every View page has a Record Info tab (Record ID, Updated At/By, Created At/By, Deleted At
for trashed records) built from a shared entries list rather than duplicated per resource.
"Updated By"/"Created By" show the linked CRM contact's name when one exists, falling back to
the raw account name otherwise.

## Custom fields

Every model opts in with a `HasCustomFields` concern, and administrator-added fields then
reach its forms, tables, infolists and history through the same code path a shipped field
takes. A recordset built on `RecordsetResource` gets this from `fields()` and needs nothing
else; the concern is also what lets a model that is *not* yet on the engine still carry
custom fields.

## General code style

- No comments explaining *what* code does — only *why*, for a genuinely non-obvious
  constraint or workaround.
- Don't add abstractions, error handling, or config flags for scenarios that can't occur in
  this app; match the scope of a change to what was actually asked for.
