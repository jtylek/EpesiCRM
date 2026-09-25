# Epesi on MoonShine

An evaluation of [MoonShine](https://getmoonshine.app) as the UI framework for this port,
and a phased plan for getting there if the answer turns out to be yes.

## Status and honest verdict

**MoonShine is a better fit than Filament for the engine Epesi is building — but not for
the application Epesi is today, and not yet at the panel level.**

Both halves of that matter. The parts of MoonShine that suit Epesi are real and specific
(runtime-declared fields, boot-time module registration, layout freedom). The parts that
don't are also real and specific (two panels is an experimental package, no `filament-shield`
equivalent, 148 of 226 PHP files still import Filament). A migration decided on the first
half alone would be a mistake.

The recommendation at the bottom is therefore **not "migrate"** and **not "don't"** — it is a
sequence that makes the question answerable cheaply, and whose first phase is worth doing
whether or not MoonShine is ever adopted.

## Where the port actually stands

| | Before Phase 1 | Now |
|---|---|---|
| PHP files under `app/` + `modules/` | 224 | 226 |
| …that import `Filament\` | 156 (70%) | **148** (65%) |
| Resources on the RecordBrowser engine (`extends RecordsetResource`) | 1 (`Epesi/Notes`) | **6** |
| Hand-written Filament resources (`extends Resource`) | 11 | **7** |
| Filament panels | 2 | 2 |

**Phase 1 is done for the CRM.** Contacts, Companies, Tasks, Meetings and Phone Calls now
declare `fields()` and nothing else — their `Schemas/`, `Tables/` and per-resource
`ActivitiesRelationManager` classes are deleted, and all five have moved into modules under
`modules/Epesi/CRM`. The five `Form`/`Infolist`/`Table` classes plus five duplicated History
addons (≈1,300 lines) collapsed into five `fields()` declarations.

What remains hand-written is the administrative surface — LoginAudits, Modules, Users,
CommonData, CustomFields, Licences, Products. Those are not recordsets in Epesi's sense
(no custom fields, no ownership ACL, no history tab), so moving them onto the engine is a
weaker case than it was for the CRM.

The number that matters for the MoonShine question is the third row: **the CRM — the bulk of
what a user actually looks at — now renders through `Field`, so its UI framework is decided
in four methods rather than in every resource.**

## What MoonShine is

- Laravel admin panel, MIT licensed, by Danil Shutsky / CutCode.
- **Current version 4.18.1** (July 2026). Note that much of the published documentation still
  targets 3.x; the 3→4 field API changed (see below), so 3.x examples do not transfer
  verbatim.
- **Blade + Alpine.js + Tailwind.** Deliberately no Livewire — interactivity is Alpine plus
  targeted AJAX fragment reloads.
- Core concepts: `ModelResource`, `Field`, `Page` (`IndexPage`/`FormPage`/`DetailPage`),
  `Layout`, `MenuManager`, `Handler`.
- Filament is also MIT. **Licensing is not a differentiator between the two** and should not
  appear in any argument for switching.

## Where MoonShine genuinely fits Epesi better

### 1. Modules register at boot, not at panel construction

This is the strongest technical argument, and it maps onto a constraint already documented in
`CLAUDE.md`:

> A Filament panel is built during provider registration, so a module installed mid-request
> isn't in the panel until the next request.

That constraint exists because Filament assembles a `Panel` object during
`PanelProvider::register()`, and `ModuleRegistry::pluginsFor()` must therefore hand over a
complete plugin list before the application has booted — which is also why the registry reads
a generated cache file instead of querying Eloquent (`app/Support/Modules/ModuleRegistry.php`
documents this at length).

MoonShine registers resources and pages from a service provider's **`boot()`**:

```php
public function boot(CoreContract $core, MenuManagerContract $menu): void
{
    $core->resources([...])->pages([...]);
    $menu->add([MenuItem::make(...)]);
}
```

`boot()` runs after the container is fully populated and the DB connection resolver exists.
For a product whose entire module story is *"install and enable from the GUI with no code
deploy"*, registering later in the lifecycle is not a cosmetic difference — it removes the
reason the cache file and the no-Eloquent-in-`register()` rule exist at all.

Worth being precise: this does not make mid-request installation work either. Providers still
boot once per request. It removes an awkward constraint; it does not deliver a new capability.

### 2. Runtime field catalogs are MoonShine's native shape

Epesi's premise is that a recordset declares `fields()` and the UI is derived — including
fields an administrator adds at runtime through `CustomFieldRegistry`.

MoonShine 4 declares fields as **instance methods returning iterables**, one per page:

```php
protected function fields(): iterable
{
    return [...];   // computed however you like, per request
}
```

A field list assembled from a database catalog is the ordinary case, not a workaround.

Filament can do this too — `RecordsetResource` already does, and its class comment explains
why it works: *"every part of a Filament resource that decides behaviour is a static method,
not a static array."* So this is a difference of grain, not of capability. MoonShine asks for
an instance method where Filament asks for a static one receiving a `Schema`; the former is a
marginally more natural home for per-tenant or per-user field visibility, should that ever be
needed.

Do not overstate this one. It is a mild advantage, not a blocker either way.

### 3. Layout freedom, for a project replacing an AdminLTE UI

MoonShine's layout is a PHP class you own outright — every element including HTML tags is a
component, `$sidebar`/`$topBar`/`$bottomBar` toggle structure, `getSidebarComponent()` and
friends override regions, and pages select their layout via a `$layout` property.

Filament's equivalents are render hooks plus theme CSS. This repo already leans on them:
`MainPanelProvider` injects `compactTableStyles()` and `boxedFieldStyles()` through
`PanelsRenderHook::STYLES_AFTER`. That is a CSS-override strategy, and it is the strategy
Filament intends.

If matching legacy Epesi's shell more closely is a goal, MoonShine starts from a friendlier
place. If it is not a goal, this is worth nothing.

### 4. No Livewire state weight on wide records

Filament forms are Livewire components; the full component state round-trips on interaction.
Epesi records are wide by design — a recordset's declared fields *plus* every administrator-
added custom field, all mass-assignable through `HasCustomFields`. Contacts in legacy Epesi
routinely carried dozens of fields.

MoonShine's Alpine + fragment-AJAX model sends less on each interaction.

**This is a hypothesis, not a measurement.** No profiling has been done on this app. It
should not be cited as a reason to migrate until someone loads a contact form with 60 custom
fields on both stacks and compares. Phase 1 below exists partly to make that measurable.

## Where the case does not hold

### 1. Two panels is an experimental package

Epesi needs exactly what it has: a `main` panel at `path('')` for all three roles, and a
separate `administration` panel gated to `super_admin` via `User::canAccessPanel()`. Multiple
panels is a first-class, mature Filament concept.

In MoonShine it is `moonshine/multi-panels` — **version 0.1.0, released January 2026,
~247 installs, described by its own authors as experimental beta.**

This is the most serious problem in this document. A core structural requirement of the
application would rest on a 0.1.0 package with a three-digit install count and an API its
maintainers say may change. Every other risk here is a cost; this one is a genuine
architectural dependency on immature code.

Mitigations exist — two Laravel route groups with different middleware and layouts, rather
than two "panels" — but that means hand-building what Filament gives for free, and it should
be prototyped before anyone commits.

### 2. No `filament-shield` equivalent

`bezhansalleh/filament-shield` currently generates the role/permission UI and policy
scaffolding over `spatie/laravel-permission`.

`spatie/laravel-permission` itself is framework-level and survives untouched. MoonShine uses
Laravel policies for authorization, so **`app/Policies/*` and `CustomFieldPolicy` port
essentially as-is** — a real saving.

What does not survive is Shield's generated admin UI and its permission-syncing commands. The
community option is `SWEET1S/moonshine-roles-permissions`, which is third-party and far
smaller than Shield. Budget for building the role-management screens by hand.

### 3. `HasOwnershipVisibility` is unaffected — and that is the point

The ownership ACL is an Eloquent global scope. It is framework-agnostic and would not be
touched by any of this.

That is worth stating explicitly because it bounds the problem: **Epesi's genuinely valuable
logic — the ownership scope, the morph-alias registry, the module registry, the legacy
importer, the custom-field registry — is all framework-independent already.** What is
Filament-shaped is the presentation layer. Large, but not the crown jewels.

### 4. Ecosystem asymmetry

Filament has substantially more third-party plugins, more StackOverflow answers, more worked
examples, and more contributors. MoonShine's English documentation is translated and, as
noted, still largely describes 3.x. For a solo or small team this is a real ongoing tax, paid
on every unfamiliar problem.

## Why the engine changes the economics

`modules/Epesi/RecordBrowser/src/Recordset/Field.php` is 840 lines, and it is **Epesi's own
DSL, not a Filament wrapper**. Its declaration surface is framework-neutral:

```php
Field::text('title')->required()->inTable()
Field::relation('contact_id', Contact::class)->inTable()
Field::boolean('pinned')->inTable()
```

Filament appears in exactly four compile methods:

| Method | Line | Produces |
|---|---|---|
| `toFormComponent()` | `Field.php:475` | `Filament\Forms\Components\*` |
| `toInfolistEntry()` | `Field.php:534` | `Filament\Infolists\Components\*` |
| `toTableColumn()` | `Field.php:596` | `Filament\Tables\Columns\*` |
| `toTableFilter()` | `Field.php:667` | `Filament\Tables\Filters\*` |

Plus one resource lookup at `Field.php:784-788` (`Filament::getCurrentPanel()` /
`Filament::getModelResource()`) and the per-field escape
hatches (`formUsing()`, `viewUsing()`, `columnUsing()`, `filterUsing()`), whose closures
return raw framework objects and would each need revisiting.

**For any resource built on the engine, swapping UI framework means rewriting four methods
and the base pages — not the resource.** `FieldType` is a pure enum with no framework
imports at all.

This is the whole argument. It is also why the plan below front-loads engine adoption rather
than MoonShine adoption.

## The plan

### Phase 0 — Prove the blocker first (days)

Do not start with a resource. Start with the thing most likely to kill the project.

1. Scratch Laravel app, `moonshine/moonshine` ^4.18.
2. Stand up two independently-routed, independently-gated admin areas — first with
   `moonshine/multi-panels`, and again without it (two route groups, two layouts).
3. Decide whether either is solid enough to carry `/` and `/administration`.

**Gate: if neither approach is convincing, stop here.** Everything below is wasted otherwise
— and Phase 1, already done, keeps its value either way.

### Phase 1 — Move the CRM onto the engine — **done**

The five CRM resources are on `RecordsetResource`. What the migration actually needed, and
therefore what the `Field` DSL could not say on its own, is the honest MoonShine estimate:

- **Per-field escape hatches** carried everything one-off: `formUsing()` for dependent
  visibility (Phone Calls' other-customer toggle), request-seeded defaults (calendar
  deep-links), `unique(ignoreRecord:)`, and relation queries scoped by
  `Contact::scopeOfCompany()`; `columnUsing()` for joined searchable/sortable columns;
  `viewUsing()` for conditional entries.
- **Two engine additions** were genuinely missing and are now first-class: sections can be
  collapsible/collapsed and carry a description, and the shared History addon gained the
  field-level `changes` column.
- **One pattern needed a helper**: country/zone, where one field is a `Select` or a free-text
  input depending on the chosen country — two components for one field, so
  `App\Support\AddressFields` wraps them in a `Group`. It still returns `Field` objects, so
  custom fields, filters and history keep working.

Nothing needed a new `FieldType`, and nothing needed a subclassed Filament component — the
decision ladder in [filament-fields.md](filament-fields.md) held.

**This phase contained no MoonShine work**, and none of it is wasted if MoonShine is
rejected: it deleted ~1,300 lines, removed five drifted copies of the History addon, and
made the CRM's UI framework a property of four methods.

### Phase 2 — A second backend for `Field` (weeks)

Extract the four compile methods behind an interface — `FieldRenderer`, with
`FilamentFieldRenderer` and `MoonShineFieldRenderer` implementations — selected by config.

Port `Epesi/Notes` first: it is the reference implementation, it is already on the engine, and
it exercises text, long text, relation and boolean fields.

Run both stacks side by side, Filament on `/` and MoonShine on `/ms`, against one database.
This is where the Livewire-payload hypothesis from §4 gets measured rather than asserted.

**Gate:** does a Notes record round-trip — list, filter, search, view, create, edit, custom
fields, history — with no behaviour lost?

### Phase 3 — The rest of the surface

In rough descending order of risk:

1. **Two panels**, per the Phase 0 finding.
2. **Base pages** — `ListRecords`/`ViewRecord`/`CreateRecord`/`EditRecord` become MoonShine
   page classes, carrying the `AI-shared/conventions.md` rules: View before Edit, single
   header action group, Clone as a dedicated action, tab order with History last.
3. **14 addons** — `HasMany` with `creatable()`, placed in detail-page tabs. Preserve the
   convention that a RelationManager is called an *addon*.
4. **Role administration**, hand-built over `spatie/laravel-permission`.
5. **Module plugins** — `NotesPlugin` etc. become service-provider `boot()` registrations;
   `ModuleRegistry::pluginsFor()` returns registration callbacks rather than
   `Filament\Contracts\Plugin` instances.
6. **The 6 custom pages**, including `Epesi/Store`'s Blade view.
7. Layout, styling, `make:epesi-recordset` stub regeneration.

### Phase 4 — Cutover

Delete `FilamentFieldRenderer` and the `filament/*` requires only once nothing references
them. Keep both backends alive through at least one full release cycle.

## Risks

| Risk | Severity | Note |
|---|---|---|
| `moonshine/multi-panels` is 0.1.0 | **High** | Phase 0 exists solely for this |
| Filament escape-hatch closures return raw components | Medium | ~15 across the five CRM resources, each returning a raw Filament object; all need porting |
| Shield's role UI must be rebuilt | Medium | Policies survive; the UI does not |
| MoonShine 4 docs lag at 3.x | Medium | Ongoing friction, worst during Phase 2 |
| Livewire-payload advantage may not be real | Low | Unmeasured; do not cite until Phase 2 |
| No test coverage to migrate against | **High** | Only framework stubs exist today |

That last row deserves emphasis, and Phase 1 did not fix it. The CRM migration was verified
by rendering every list/view/create/edit page against the live database and diffing the
resulting column sets — not by tests, because there are none to speak of and no factories for
the CRM models. **A UI-framework migration with no regression suite is done blind.** Feature
tests over the five CRM resources are a prerequisite for Phase 2, not a nicety, and they are
worth writing regardless of the outcome.

## If the answer is no

Phases 0 and 1 still pay for themselves. Engine adoption is the stated architecture;
consolidating 11 resources onto `fields()` shrinks the codebase, makes custom fields uniform,
and means the next framework question — Filament 6, or whatever follows — is answered by
rewriting four methods.

The most likely honest outcome is: **stay on Filament, finish the engine, and keep the
renderer seam.** That captures most of the portability MoonShine is being considered for,
without betting the panel structure on a 0.1.0 package.

## Sources

- [MoonShine](https://getmoonshine.app) · [GitHub](https://github.com/moonshine-software/moonshine)
- [Package development](https://getmoonshine.app/en/docs/4.x/advanced/package-development)
- [Layout](https://getmoonshine.app/en/docs/4.x/appearance/layout)
- [ModelResource fields](https://getmoonshine.app/en/docs/4.x/model-resource/fields)
- [moonshine/multi-panels](https://packagist.org/packages/moonshine/multi-panels)
- [moonshine-roles-permissions](https://github.com/SWEET1S/moonshine-roles-permissions)
