# Priority List

Each user's short, ordered list of what they work on next: the tasks, meetings and phone calls
they mean to do before anything else. It holds at most **10** records. The cap is the point:
putting something on the list means choosing, and a full list asks you to finish or drop
something first ("Your priority list is full! Do some work first").

## Getting Things Done

The module covers the "next actions" part of GTD:

| GTD | Here |
|---|---|
| Organize | **Add to priority list** on the task, meeting or phone call you will do next |
| Next actions | The list itself: up to 10 records, in the order you will do them |
| Engage | Work from the top; drag a row to change the order as priorities shift |
| Done | **Done** (✓) closes the record, and so takes it off the list |
| Not now | **Take off the list** (✕) keeps the record but frees the place |
| Review | The applet on the dashboard, whose heading shows how full the list is (`3/10`) |

## What the user sees

- **A flag on the View page** of every task, meeting and phone call: "Add to priority list",
  or "On priority list" once it is there (its tooltip gives the place on the list). With a full
  list it refuses and says so. It is a flag, not a star: the star beside it is Favorite.
- **A dashboard applet**, "Priority list (3/10)", the only place the list is shown: there is
  no page or menu item for it. Each row shows:
  - a grip (☰) to drag it by: every row can be dragged at any time, with no reorder mode to
    switch on;
  - its place, and the record's title, which opens the record;
  - the record's type and status, and its due date, red once passed;
  - **Done** (✓), which asks first, then sets the record's status to Closed. It is offered only
    for a record whose status is still open and which the user may edit;
  - **Take off the list** (✕), which leaves the record as it is.

Everything on the list is a record from one of the recordsets: the list has no items of its
own, and nothing is added to it except with the flag.

The applet has no settings: the list is never longer than 10, so it is always shown whole.

## Rules

- **One list per user.** Nobody else sees or changes it, managers included.
- **Only what you can see.** A record that someone else makes private, or that is trashed, drops
  off your list. It takes no place either: adding a record first removes such hidden entries,
  so the list never holds more than 10, even if one of them becomes visible again later.
- **Finished work leaves.** A record whose status becomes Closed or Canceled, by anyone and from
  anywhere, or which is deleted, leaves every list it is on. Restoring a trashed record does not
  put it back.
- **Order.** A new entry goes last. Reordering writes 1, 2, 3… for the rows of your own list
  only.

To change the cap, change `PriorityList::LIMIT`.

## Administration → Priority list types

Every RecordsetResource in the main panel — every recordset a module ships, core or not — is a
*candidate* type with no code of its own: `PriorityList::candidateTypes()` finds it by scanning
the panel's registered resources and reverse-mapping each one to its morph alias. A candidate
starts **off** unless it is one of the three built in (task, meeting, phone call), which start on,
same as always. A super_admin turns any other one on or off from Administration → Priority list
types (`src/Filament/Pages/PriorityListManagement.php`), persisted in the single-row
`epesi_priority_list_settings` table (`PriorityListSetting`) — installing a module is enough for
its recordset to appear there; nobody edits a service provider to add it.

Turning a type off empties it out of `entries()` — not just future additions: a record already on
someone's list stops showing (and counting toward their 10) until it is turned back on.

This replaces what used to be the only way to add a type: task, meeting and phone call still get
their due date from `PriorityList::enableFor()` (see below), but that call is no longer what makes
a type usable — it only decides whether it starts on and what due date it shows.

## Adding a record type by hand

A type still needs code for two things beyond appearing on the admin page:

- **A due date beside the record**, red once passed: `PriorityList::enableFor()` from the module's
  service provider `boot()`, purely a polish — the type is a candidate either way.

  ```php
  PriorityList::enableFor(
      'invoice',                                         // morph alias
      due: fn (Invoice $invoice) => $invoice->due_on,    // optional, shown beside the record
      allDay: fn (Invoice $invoice): bool => true,       // optional: a date with no time of day
  );
  ```

  A type registered this way also starts **on** by default (like task/meeting/phone call), rather
  than needing that first admin visit.

- **The Done button**, which needs a `status` attribute cast to `App\Enums\RecordStatus`
  specifically, to know which value means "closed" to write. A type whose status is some other
  enum (Project's `ProjectStatus`, Ticket's `TicketStatus`) has no Done button, only add/remove —
  but still leaves every list on its own once its status enum's own `finished(): array` says it's
  done (`PriorityList::wire()` reads that generically, not just `RecordStatus`).

Every type also needs a resource in the main panel with a View page, for the title, type label
and link — which is exactly what makes it a candidate in the first place.

The flag appears on the covered types' View pages through
`RecordExtensions::headerActions()`, which every RecordBrowser View page reads.

## Files

| File | What it is |
|---|---|
| `src/PriorityList.php` | The API: `add()`, `remove()`, `move()`, `entries()`, `complete()`, `enableFor()`, `candidateTypes()`, `isEnabled()`/`setEnabled()`, the cap |
| `src/PriorityListServiceProvider.php` | Registers tasks, meetings and phone calls' due dates, the flag, and the listeners that take finished records off every list |
| `src/Filament/Widgets/PriorityListWidget.php` | The dashboard applet (implements the dashboard's `Applet` contract): the entries, and drag, Done and Take off the list, each checked against your own list |
| `resources/views/widget.blade.php` | The list itself, dragged with Livewire's `wire:sort` rather than a Filament table, whose rows only drag in a reorder mode that hides ✓ and ✕ |
| `src/Filament/Pages/PriorityListManagement.php` | Administration → Priority list types: every candidate, and its on/off toggle |
| `src/Models/Entry.php` | One record on one user's list |
| `src/Models/PriorityListSetting.php` | Single-row settings: which types an administrator turned on or off |
| `database/migrations/` | The `epesi_priority_list_entries` and `epesi_priority_list_settings` tables |
| `lang/pl.json` | Polish |

Tests: `tests/Feature/Modules/PriorityListTest.php` (the API and the flag; discovery and the
admin toggle use Company, so they need no Premium module) and
`tests/Feature/Modules/PriorityListManagementTest.php` (the admin page itself). The Project/Ticket
side of this — a real discovered-but-off type, and the generic auto-forget against a non-
`RecordStatus` enum — is `modules/Premium/ProjectsTickets/tests/PriorityListTest.php`, run with
`php artisan test modules/Premium/ProjectsTickets/tests`, since that module is a separate,
privately licensed repo this one's own test suite can't depend on.
