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
  line is too narrow (the theme's `compact-tables.css`). The same goes for any table given a
  `->heading()`, such as the Watched records and Shoutbox pages.
- **Every addon has a column selector.** Each of its columns is toggleable and shown by
  default (`AppServiceProvider::giveEveryAddonAColumnSelector()`), so a new addon needs
  nothing. `->toggleable(isToggledHiddenByDefault: true)` starts a column hidden;
  `->toggleable(false)` keeps one out of the selector.
  The Record History popup is an exception: all columns are always visible, with no
  column selector or redundant table heading beneath the modal title.

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
- **Tab order on a View page**: real addons first, then any additional static tabs
  (`getAdditionalContentTabs()`; none at present). Record Info and History aren't tabs — they
  sit behind a kebab (an icon-only dropdown) as the very last header action, after Edit/Clone
  and whatever a resource or module adds, and open as a modal when picked; a resource with no
  real addons at all (e.g. Administration's ViewUser) shows no tab strip. The default-active
  tab is always the first real addon. Deep-link to Record Info/History with
  `?action=record-info` / `?action=history` (Filament's own
  `InteractsWithActions::$defaultAction`) rather than `?tab=`.
- **Every addon tab shows how many records it lists**, "0" included (gray; a count is in the
  accent color), so the strip says which addons are worth opening. `ViewRecord::withCountBadge()`
  counts the addon's relationship, so a new addon needs nothing and follows the same row
  visibility as its table. An addon whose table narrows the relationship further overrides
  `getBadge()` (Reminders shows only the reminders you may see). Record Info/History, behind
  the kebab, show no count.
  Any Create/Delete/Associate... run inside an addon makes the page recount
  (`addon-changed`, see `RecordBrowserServiceProvider`).

## Page header

Every page, in every panel, reads the same at the top: its sidebar icon and name as the first
breadcrumb, header actions on the same line, and no large `<h1>` title (Filament's default).
A resource page reads "(icon) Contacts > List", and a standalone page reads "(icon) Calendar".
A record's View/Edit page reads "(icon) Contacts > View", without the record's title between
the two: the page shows the title in its first row anyway.

- **Resource pages** get this by extending RecordBrowser's `ListRecords`/`ViewRecord`/
  `EditRecord`/`CreateRecord`. A page that extends Filament's own classes has to mix in
  `HasResourceIconBreadcrumb` and `HidesPageHeading` itself.
- **Every other page** (a `Filament\Pages\Page` subclass, including the Dashboard, which is
  `App\Filament\Pages\Dashboard` rather than Filament's own class) mixes in
  `HasPageIconBreadcrumb` and `HidesPageHeading`.

`tests/Feature/PageHeaderTest` checks every page registered in any panel, module pages
included, and fails for any page that is missing one of these traits.

## "My records" button on lists

Every RecordBrowser-based List page (core, CRM and any module recordset, e.g. Tickets or
Projects) shows a quick-filter button in the toolbar, so a user sees just the records of
interest without opening the filter panel and setting it by hand.

- **Records select.** "My records" (the default) / "All records", a select in the same style as
  All / Favorites / Recent. "My records" sets the **Employees** filter to the logged-in user (Notes
  has no Employees field, so it sets **Edited by** instead) and never touches the status filter.
- **Active / Inactive select.** A recordset with a closed/canceled status (Tasks, Phone Calls,
  Meetings, likewise Tickets, Projects) also gets an Active (not closed or canceled, the default) /
  Inactive (closed or canceled only) select (`ListRecords::setStatusMode()`). If the user sets the
  status filter some other way, the select shows "All". E-mails and Notes get no such select.
  "My records" keeps its state when it resets the other filters.
- **Toolbar order.** The records select, the Active / Inactive select, the All / Favorites / Recent select,
  then the search box.
- **Reset.** Clicking the button again resets the filters and the search (it toggles between
  "mine" and no filter) rather than stacking on top of whatever is already set. A list with no
  Employees (or Edited by) filter, such as Contacts and Companies, gets "My records" as a
  toggle filter instead: the records the user created or has changed (`created_by`, or any
  activity-log entry with the user as causer). `ListRecords::table()` adds that filter.
- **Default after login.** The first time a list is opened in a session (a fresh login), it
  opens with this filter already applied (`ListRecords::applyDefaultMyRecords()`). Later
  visits keep whatever filters the user left, including none.
- **Engine-level, not per resource.** It belongs in RecordBrowser's `ListRecords`, driven by
  what the table's filters offer (an `employees`/`employee`/`edited_by` filter, and a status
  filter with the `StatusField::NOT_CLOSED` option), so a new recordset gets it with no code of
  its own. Covered by `tests/Feature/MyRecordsButtonTest`.

## Edit/Create pages and modals

- One action group, not Filament's default split between header and form-bottom actions.
  Edit: Save + Cancel + Delete in the header ("Cancel" relabels the View action rather than
  using browser-history cancel); redirects to View on save. Create: Save + Cancel in the
  header. There is no "Create & create another" anywhere — pages and modals both switch it off.
- A header Save/Create action needs `formId('form')` — without it, moving these buttons into
  the header leaves them outside the actual `<form>` element and clicking does nothing.
- **Modals follow the same rule.** An action modal's footer actions (Create/Save, Cancel,
  Close…) sit on the heading's line instead of under the form, and there is no corner close
  button (Cancel does that job). Create/Edit modals style their buttons as the pages do:
  Save green with a check (the label is Save on Create too), Cancel with an X.
  This is a global default (`AppServiceProvider::putModalActionsOnHeadingLine()`), so a new
  modal needs nothing. Confirmation dialogs (`requiresConfirmation()`), slide-overs, small
  centred modals and modals with no footer keep Filament's own layout.
- **Clone** is a dedicated header action on View pages, not Filament's own `ReplicateAction`:
  it warns and clones immediately on confirm (no pre-fill edit modal), then redirects to the
  new record's Edit page. Unique/identity columns that would collide (email, linked user,
  etc.) are blanked on the clone; `created_by` always attributes to whoever cloned it.

## Click 2 Fill

RecordsetResource forms include a collapsible Click 2 Fill panel on Create and Edit,
including action forms using the resource schema. Its toggle sits in the page header
beside the Save/Cancel actions. Paste text, scan it into words
(commas and whitespace separate them), select words in the desired order, then click
an editable text field to replace its value. Selection numbers show the order;
filling clears the selection while retaining the source words for the next field.
Text, email, phone, URL inputs and plain textareas participate, including custom
fields and collection items. Select controls, rich text editors and read-only fields
do not. Values follow the ordinary Livewire validation and Save flow; pasted text is
kept only in the current form's browser state. The implementation follows legacy
`Utils/RecordBrowser/click2fill.js` without its global state or HTML interpolation.

## Record Info

Every View page has a Record Info action (Record ID, Updated At/By, Created At/By, Deleted At
for trashed records), opened as a modal from the kebab at the end of the header actions row, and
built from a shared entries list rather than duplicated per resource. "Updated By"/"Created By"
show the linked CRM contact's name when one exists, falling back to the raw account name
otherwise.

## Custom fields

Every model opts in with a `HasCustomFields` concern, and administrator-added fields then
reach its forms, tables, infolists and history through the same code path a shipped field
takes. A recordset built on `RecordsetResource` gets this from `fields()` and needs nothing
else; the concern is also what lets a model that is *not* yet on the engine still carry
custom fields. See [Epesi-custom-fields.md](Epesi-custom-fields.md#part-2--custom-fields) for
how an administrator adds, edits and removes one from Administration → Fields.

## General code style

- No comments explaining *what* code does — only *why*, for a genuinely non-obvious
  constraint or workaround.
- Don't add abstractions, error handling, or config flags for scenarios that can't occur in
  this app; match the scope of a change to what was actually asked for.
