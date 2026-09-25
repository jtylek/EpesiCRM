# The CRM port

How Epesi's five CRM recordsets are built in this app: where they live, what declaring one
actually involves, what the `Field` DSL could not express on its own, and the traps that come
with models living in modules rather than `app/`.

Read [architecture.md](architecture.md) for the engine and module system in general, and
[filament-fields.md](filament-fields.md) for the `Field` → Filament mapping. This file is the
worked case.

## The five modules

All under `modules/Epesi/CRM/`, all `"core": true`, all `panels: ["main"]`, all
`requires: ["epesi/recordbrowser"]`.

| Module | id | Model | Fields | Addons | Default sort |
|---|---|---|---|---|---|
| `Contacts` | `epesi/crm-contacts` | `Contact` | 28 | Tasks, Phone Calls, Meetings, History | — |
| `Companies` | `epesi/crm-companies` | `Company` | 16 | Contacts, Tasks, Phone Calls, Meetings, History | — |
| `Tasks` | `epesi/crm-tasks` | `Task` | 10 | History | `deadline` |
| `Meetings` | `epesi/crm-meetings` | `Meeting` | 11 | History | `date` |
| `PhoneCalls` | `epesi/crm-phonecalls` | `PhoneCall` | 12 | History | `called_at` desc |

Field counts include administrator-added custom fields, so they move.

`CRM` is a **plain grouping directory** — no `module.json`, no namespace, nothing owns it.
`ModuleManifest` allows a module path of two segments or more precisely so first-party
features can group the way legacy Epesi's `modules/` tree did. Nothing else about a nested
module is special: it registers, packages to a zip and installs exactly like `Epesi/Notes`.

## What a CRM module owns

Each module holds its **model, policy, Filament resource (with pages, addons and any
schemas), and calendar event provider** where it has one.

The core app deliberately keeps:

- **Shared vocabulary** — `App\Enums\RecordPermission`, `RecordStatus`, `RecordPriority`.
  These cross all five modules and `HasOwnershipVisibility` in the engine depends on
  `RecordPermission`, so they are not any one module's property.
- **Migrations.** Several span more than one CRM entity (the `task_employee`,
  `meeting_customer`, `task_customer_company` pivots join two modules' tables), so there is no
  single owner for them. They have also already run everywhere; moving applied migration files
  buys nothing.
- **The legacy importers** (`App\Services\LegacyImport`). A developer-run cutover tool, not a
  module feature.
- **The Calendar page and `CalendarRegistry`.** The page is core; the *events* are not — each
  of Meetings, Tasks and Phone Calls registers its own `CalendarEventProvider` from its module
  service provider's `boot()`. Nothing core-side contributes events any more, which is the
  arrangement `CalendarRegistry`'s docblock always described.

## Declaring a recordset

A resource extends `RecordsetResource`, sets `$model`, and declares `fields()`. There is no
form class, no infolist class, no table class, and no History relation manager. `Epesi/Notes`
is the smallest example; `Contacts` is the largest.

One `Field` fans out to as many as four Filament objects — form component, infolist entry,
table column, table filter — and three flags decide which:

- `->inForm(false)` / `->inView(false)` — drop it from the form or the View page.
- `->inTable()` — show it as a column without the user turning it on.
- `->notInTable()` — keep it off the list *and* out of the column chooser. The default sits
  between the two: offered in the chooser, hidden until asked for.
- `->onlyInTable()` — shorthand for a column and nothing else.
- `->tooltipFrom('description')` — on hover, the list column shows another attribute of the record
  (cut at 500 characters). Tasks' and Meetings' title and Phone Calls' subject use it.

### One list drives two orders

`fields()` order determines both the form layout and the table column order. Those two want
different things, so pick an order that serves both rather than optimising either. Tasks is
the worked example: `timeless` sits immediately before `deadline` because the form needs it
there (it is a live toggle the deadline reads), and the visible column order still comes out
as title, deadline, status — matching what the hand-written table had.

Where the two genuinely conflict, the form order wins and the list reorders; Companies' Group
column moved for exactly this reason, which was judged not worth an override.

## What the DSL could not say

27 escape hatches across the five recordsets: 16 `formUsing()`, 6 `columnUsing()`,
5 `viewUsing()`, and **zero `filterUsing()`**. That distribution is the useful signal — the
generated filters were right every time; forms needed the most help.

What they are actually doing:

- **Dependent visibility.** Phone Calls' `other_customer` toggle is `->live()`, and
  `other_customer_name` / `contact_id` / `company_id` show or hide against it — on the form
  via `formUsing()`, and on the View page via `viewUsing()`.
- **Request-seeded defaults.** Meetings' `date`/`time`, Tasks' `deadline`/`timeless` and Phone
  Calls' `called_at` read `request()->query(...)` so the Calendar page can deep-link into a
  pre-filled Create form.
- **Validation the DSL has no verb for.** `->unique(ignoreRecord: true)` on Contact and
  Company email, and on Contact's linked user.
- **Scoped relation queries.** Contact's `user_id` picker excludes logins already claimed by
  another contact. The Employees picker on Tasks, Meetings and Phone Calls started here too,
  narrowed to the acting user's own company's staff via `Contact::scopeOfCompany()` (Epesi's
  `employees_crits()`). It has since become the DSL's `->crits()`, because the hand-written
  `modifyQueryUsing` also hid employees who had left from the form without unlinking them.
- **Joined searchable columns.** The engine renders a relation column from the related
  resource's record title, which is often an accessor (`Contact::full_name`) with no SQL
  equivalent, so sorting and searching default off. Where a real joined column exists —
  Contacts' `company.company_name`, Phone Calls' `contact.full_name` — `columnUsing()`
  restores them.
- **Conditional formatting.** Tasks' deadline renders date-only when `timeless`, and turns
  `danger` when overdue and not closed.

Nothing needed a new `FieldType`, and nothing needed a subclassed Filament component. The
decision ladder in [filament-fields.md](filament-fields.md) held for the whole port.

## What the port added to the engine

Three gaps were real and recurring rather than one-offs, so they became engine features
rather than per-resource workarounds:

- **Section options.** `->section('Address', collapsible: true, collapsed: true)` and
  `->section('Login', description: '…')`. The first field to name a section decides how it
  renders, so the options are stated once.
- **`App\Support\AddressFields`.** The address block, declared three times (Contact billing,
  Contact home, Company). Only Zone is hard: it is a `Select` when the chosen country has a
  known state list and a free-text input when it does not — two components for one field, so
  they are wrapped in a `Group` that still occupies one cell of the two-column grid. It
  returns `Field` objects, so custom fields, filters and history keep working.
- **A field-level `changes` column on the shared History addon.** Five per-resource copies of
  `ActivitiesRelationManager` had drifted into two different versions; the richer one (which
  renders `field: old → new`, the `<tab>_edit_history_data` half of Epesi's history) is now
  the only one, in the engine.

## Traps

Most of these come from models living in a module rather than `app/`.

- **Models no longer share a namespace.** In `App\Models` they referenced each other
  unqualified (`belongsTo(Company::class)`); split across `Epesi\Modules\CRM\*\Models` those
  references silently resolve to a class in the *referring* model's namespace and fail at
  runtime. Every cross-model reference now needs an explicit `use`. This bites files that did
  not move too — `App\Models\User::contact()` was the last one found.
- **Policy auto-discovery does not apply.** Laravel maps `App\Models\X` to `App\Policies\XPolicy`;
  a module's model matches neither half. Each module binds its own policy with
  `Gate::policy()` in `boot()`.
- **The PSR-4 prefixes must be in `config('modules.core_namespaces')`.** The core app names
  these models directly (`User::contact()`, the importers) and `AppServiceProvider` touches
  them during `register()`, before the `modules` table has been consulted. Waiting for the
  registry would be too late.
- **Morph aliases are what make the move safe.** `activity_log`, `model_has_roles` and
  `custom_fields.model_type` store `contact`, not a class name, so changing namespace orphans
  nothing. Each CRM module registers its own alias from `register()`; `AppServiceProvider`
  still calls `enforceMorphMap()`, which merges.
- **A `Field` means a real column.** An accessor is not a field. Meeting's `starts_at`
  (date + time combined) is rendered by giving `date` a `viewUsing()` that reads the accessor,
  not by declaring `starts_at`. `recordset:check` enforces this and will catch it.
- **`columnUsing()` returning a *fresh* column discards what the engine set**, including
  `toggleable(isToggledHiddenByDefault:)`. Prefer modifying the component you are handed;
  if you must build a new one, re-apply the toggle state explicitly.
- **`composer dump-autoload` after moving classes.** The optimised classmap pins old paths and
  the failure surfaces as a confusing "class not found" for a class you can see on disk.

## Verifying a recordset

There is no meaningful automated test coverage, so verification is manual and worth doing in
this order:

1. **`php artisan recordset:check --strict`** — every declared field maps to a real column.
   Cheap, and catches the accessor-as-field mistake.
2. **Render every page** — list, create, view and edit for each recordset, against real data.
   Route registration alone proves nothing; the schemas are only built when a page renders.
3. **Diff the visible column set** against what the resource had before. Build each field's
   `toTableColumn()` and check `isToggledHiddenByDefault()`. A column that is present but
   silently promoted from hidden to visible will not show up as an error anywhere else — this
   is how the `columnUsing()` trap above was found.

Feature tests over the five CRM resources are the obvious next thing; there are currently no
factories for these models, which is the first blocker.

## What is deliberately not on the engine

The administrative surface — LoginAudits, Modules, Users, CommonData, CustomFields, Licences,
Products — remains hand-written Filament. These are not recordsets in Epesi's sense: no custom
fields, no ownership ACL, no History tab, no administrator-added columns. Putting them on
`RecordsetResource` would buy the uniformity and cost the fit.

## RegionalSettings: a non-recordset module that reuses the port's fields

`modules/Epesi/RegionalSettings` (port of legacy's `Base_RegionalSettings`) is not a
`"core": true` module and not built on `RecordsetResource` — there is nothing to list. Every
user edits only their own row (`RegionalSetting::current()`, a `firstOrCreate` keyed on
`Auth::id()`), so there is no ownership ACL, no policy and no History addon; the module is a
single `Filament\Pages\Page` plus a one-row-per-user model
(`epesi_regional_settings`: `timezone`, `date_format`, `time_format`, `country`, `state`).

It sits in its own `user-settings` panel (`App\Providers\Filament\UserSettingsPanelProvider`),
not `main` or `administration` — reached from a "Settings" item in the main panel's user menu
rather than the CRM sidebar, since it holds a personal preference, not CRM data or an admin
tool. `RegionalSettingsPlugin::register()` just does `$panel->pages([RegionalSettings::class])`,
so which panel picks it up is entirely `module.json`'s `panels` key — the plugin code itself
doesn't know or care. A module that lists more than one panel gets its plugin registered into
each, so its plugin must pick by `$panel->getId()` or everything lands everywhere: `Epesi/Mail`
lists `main` and `user-settings`, and puts only its Mail accounts resource in the latter. Links
to such a resource from the other panel need an explicit `getUrl(..., panel: 'user-settings')`.

Two things carry over directly from the CRM port rather than being reinvented:

- **Date/time formats are PHP `date()` tokens (`Y-m-d`, `g:i A`), not legacy's `strftime()`
  ones.** `strftime()` was removed in PHP 8.1, so there was no parity target to preserve.
- **Country → State is the same two-components-for-one-field `Group` as
  `App\Support\AddressFields::zone()`**: a `Select` from `Zones::forCountry()` when the chosen
  country has a known state list, a free-text input otherwise, both named `state` and toggled
  by `visible()`.

**Trap:** `firstOrCreate()`'s second argument must spell out every column that needs a real
default, even ones the migration already defaults at the schema level — the returned model
reflects only what it was given and is never re-read from the row, so a column left to the
database default comes back `null` in memory. First cut of `RegionalSetting::current()` passed
only `timezone`; `date_format`/`time_format` showed as blank placeholders on a brand-new row
until the other two defaults were added explicitly.
