# CommonData

The design for this port's reference-data engine — the replacement for legacy Epesi's
`Utils_CommonData`, which backs the `commondata` field type and every shared lookup list
(`CRM/Priority`, `Countries/US/PA`, `CRM/Mail/Security`).

The module exists: `modules/Epesi/CommonData` holds the table, the model, the facade and the
administration screen, `php artisan import:legacy commondata` brings the legacy tree across,
and `FieldType::CommonData` wires it into the `Field` DSL. All five build steps below are
done; what remains is moving further lists onto it, one at a time. Legacy references are
paths inside the legacy epesiCRM checkout.

## Where it lives

A core module, `modules/Epesi/CommonData/`, with a manifest shaped like RecordBrowser's:
`"core": true`, `"panels": ["administration"]`.

Core, because once a recordset field points at an array, disabling the module breaks every
form that renders it. Separate from RecordBrowser, because legacy uses commondata well outside
record fields — `Base_Notify/Timeout`, `CRM/Mail/Security` — and this port will too.

## Schema

Legacy is a plain adjacency list (`Utils_CommonDataInstall`):
`utils_commondata_tree(id, parent_id DEFAULT -1, akey C(64), value X, readonly I1, position I4)`
with `UNIQUE(parent_id, akey)`.

```php
Schema::create('common_data', function (Blueprint $table) {
    $table->id();
    $table->foreignId('parent_id')->nullable()->constrained('common_data')->cascadeOnDelete();
    $table->string('key', 64);
    $table->text('value')->nullable();
    $table->string('path', 512);        // 'Countries/US/PA'
    $table->boolean('readonly')->default(false);
    $table->integer('position')->default(0);
    $table->timestamps();

    $table->unique('path');
    $table->index(['parent_id', 'position']);
});
```

The one deliberate departure from legacy is the materialised `path` column. It earns its place
three times:

- **`UNIQUE(parent_id, key)` does not work with a nullable parent.** MySQL treats NULLs as
  distinct in a unique index, so two root-level `Countries` arrays would insert happily.
  Legacy avoids this with its `parent_id = -1` sentinel; a unique `path` is the cleaner fix and
  lets `parent_id` stay honestly nullable.
- **Lookup by name becomes one query.** Legacy's `get_id($name)` walks the path segment by
  segment, one `SELECT` per level, memoised in statics. `where('path', $path)` is one index hit.
- **Subtree work becomes trivial** — `path LIKE 'Countries/US/%'`.

The cost is that a rename or move must rewrite descendants' paths: one
`UPDATE ... SET path = CONCAT(?, SUBSTRING(path, ?))` in the model's `saved` hook. Keys cannot
contain `/` — legacy enforces that in `new_array()` and `rename_key()`, and so must this — so
paths stay unambiguous. `cascadeOnDelete` replaces legacy's hand-rolled recursive
`remove_by_id()`.

## API

A `CommonData` facade over a repository, keeping the legacy names so ported code reads the
same way:

| Legacy | Port |
| --- | --- |
| `get_array($name, $order)` | `CommonData::array(string $path, string $order = 'value'): array` |
| `get_translated_array()` | the same method — translated by default, `::raw()` when not |
| `get_translated_tree($col)` | `CommonData::tree(string $path): array`, flattened `parent/child` keys |
| `get_value($name)` | `CommonData::value(string $path): ?string` |
| `set_value()` | `CommonData::set(string $path, ?string $value)` |
| `new_array()` / `extend_array()` | `CommonData::seed(string $path, array $items, bool $overwrite = false, bool $readonly = false)` |
| `remove()` | `CommonData::remove(string $path)` |

**Translation.** Legacy pipes every value through `_V()` (Base_Lang). `__($value)` is the exact
analogue: an untranslated string returns itself, which is already legacy's fallback. Translate
by default; the administration editor is the one caller that wants the raw value.

**Caching.** Legacy loads the entire tree into statics on first touch. Cache per path instead:
`Countries/US` with its counties is already several hundred rows, and the dominant read is one
array at a time.

Invalidation cannot be targeted, though — a cached `tree()` under any ancestor may contain the
node that just changed, and those entries cannot be enumerated from the node. Every cache key
therefore carries a version number that `CommonDataNode`'s own model events bump, retiring the
lot at once. Entries under a retired version are never read again and expire on the cache
store's own schedule. (The configured store is `database`, which has no tag support, so tags
were not an option.)

## Administration UI

Legacy's screen (`Utils_CommonData::browse()`) is a one-level `GenericBrowser`: Position, Key,
Value; row actions View (drill in), Edit and Delete, the last two hidden when the node is
`readonly`; drag handles wired up by `sort_nodes.js`; and action-bar buttons Add array, Reset
Order By Key, Back.

That maps onto stock Filament with no custom Livewire. A `CommonDataResource`, registered by a
`CommonDataPlugin` shaped like `RecordBrowserPlugin`, with a single list page scoped by a query
string:

- **Drill-down.** `ListCommonData` reads `?path=Countries/US` and scopes
  `->where('parent_id', $node?->id)`. The Key column links deeper through
  `->url(fn ($record) => static::getUrl('index', ['path' => $record->path]))`, which keeps the
  view-before-edit rule in [conventions.md](conventions.md).
- **Breadcrumbs built from the path segments**, each linking to its own level. This replaces
  legacy's Back button and is strictly better — legacy only steps back one level.
- **Reordering.** `->reorderable('position')` gives Filament's own drag handles, scoped to the
  current parent by the query that is already there. One line replaces `sort_nodes.js` together
  with `change_node_position()`'s manual position shuffling.
- **Create and edit in modals.** The form is Key plus Value. Key is validated with a
  no-slash rule and must be unique within its parent — legacy's `check_key` and `check_key2`.
- **Readonly.** Hide Edit and Delete, and enforce the rule in `CommonDataNode` itself — not in
  the policy. The administration panel is super_admin-only and filament-shield is configured
  with `define_via_gate` and `intercept_gate: 'before'`, so a policy check never fires for the
  one role that can reach this screen; a guard there would be decorative. In the model it also
  covers seeders, tinker and the importer. `CommonDataNode::withoutReadonlyProtection()` is the
  deliberate way past it, and is what `seed()` uses to update the arrays a module owns.
  `CommonDataPolicy` still carries the readonly check for any future role granted panel access.
- **Delete.** The foreign key cascade handles descendants, but the confirmation should say how
  many go with it. Legacy removes the subtree silently.

Filament tree plugins were considered and rejected: single-maintainer packages sitting on the
administration UI, when core Filament already covers both the drill-down and the dragging.

`ListCommonData` mixes in `HasResourceIconBreadcrumb` and `HidesPageHeading` directly rather
than extending RecordBrowser's shared base page. Those two concerns live in the core app, so
nothing is lost; extending the base would make this module require RecordBrowser, and that
dependency becomes circular as soon as `FieldType::CommonData` reads reference data from here.

## Wiring into the Field DSL

Add `FieldType::CommonData = 'commondata'`. The enum value matches legacy's string, so imported
`custom_fields.type` rows need no translation.

```php
Field::commonData('group', 'Contacts_Groups')
Field::commonData('state', 'Countries::country')   // dependent
```

The `::` segments in legacy's `array_id` name *other fields* whose values build the path —
`display_commondata()` walks them to assemble `Countries/<country>/<state>`. Filament does
cascading selects natively, so `qf.js` and `HTML_QuickForm_commondata`'s chained-select
rendering both disappear:

```php
Select::make('state')
    ->options(fn (Get $get) => CommonData::array('Countries/'.$get('country')))
```

with the parent field `->live()` and `->afterStateUpdated(fn (Set $set) => $set('state', null))`.
`Get` and `Set` come from `Filament\Schemas\Components\Utilities`.

The infolist entry ports `display_commondata` directly. Multiselect over commondata (contacts'
`Contacts_Groups`) becomes `Select->multiple()`; `LegacyValue` already decodes the
`__office__field__` storage encoding. `CustomFieldForm` gains an Array picker, shown when the
chosen type is commondata and listing the root paths.

## Import

Module-owned arrays are seeded with `CommonData::seed(..., readonly: true)` — the port of
`new_array($name, $array, true, true)`.

Legacy rows come from `utils_commondata_tree`, mapping `parent_id = -1` to `NULL` and computing
`path`, preserving `readonly` and `position`.

**Match on `path`, not `legacy_id`.** The importer's usual guard — upsert by `legacy_id`, refuse
to run when non-imported rows exist — misfires here, because module-seeded readonly arrays
(`CRM/Priority`, `CRM/Mail/Security`) are legitimately non-imported rows that exist before any
import runs. `path` is the natural key, stable across both systems, and a seeded `CRM/Priority`
merges with the legacy one rather than colliding. `CommonDataImporter` records this as a
deliberate deviation so it does not read as an oversight.

That has a consequence for `import:legacy`'s fixture guard, which refuses to run when any
migrated table holds rows without a `legacy_id`. Reference data neither carries a `legacy_id`
nor depends on legacy ids lining up with local ones, so demo rows in `companies` are no reason
to refuse it — importing reference data into a working database is a normal thing to want. The
command keeps an `ALIGNMENT_EXEMPT` list for tabs in that position; every other tab is still
guarded, and `import:legacy all` still checks everything.

Two details the legacy data forces:

- **Decode HTML entities.** Epesi runs keys and values through `htmlspecialchars()` on the way
  in, so a quote is stored as `&quot;`. Blade escapes on output here, so importing the encoded
  form would double-encode it and show the entity to the user.
- **Import under `withoutReadonlyProtection()`.** Most of what comes across is readonly, so a
  second run would otherwise be refused by the model's own guard.

## Build order

1. Migration, model, facade, cache observer. **Done.**
2. `seed()` — **done**; moving this port's currently-hardcoded CRM lists onto it is not, and is
   a behaviour change to existing resources rather than new plumbing.
3. The Filament resource: drill-down, reordering, modals, policy. **Done.**
4. `FieldType::CommonData` and the DSL additions. **Done.**
5. The importer. **Done** — `php artisan import:legacy commondata`.

Steps 1 to 3 are independently useful.

## What still uses hardcoded lists

Contacts' and Companies' Group fields moved onto `Contacts_Groups`/`Companies_Groups`, and
their enums are gone. Deliberately left as enums:

- **`RecordPermission`.** It is load-bearing: `HasOwnershipVisibility` scopes on
  `RecordPermission::Private->value` and every CRM policy compares against
  `RecordPermission::Public`. Moving it would turn the row-level ACL into magic-number
  comparisons and hand an administrator a screen where renaming "Private" changes who can see
  what. Epesi made Access a commondata array because it had no enums; that is not a reason to
  copy it.
- **`RecordStatus` and `RecordPriority`.** Inert in logic, but both implement Filament's
  `HasColor`, which drives badge colours in tables and infolists and feeds `CalendarColor`.
  CommonData stores a key and a label and has nowhere to put a colour, so moving them today
  trades colour-coded statuses for administrator-editable ones. If that parity matters, add a
  colour column to `common_data` first.
- **`Countries`/`Zones`** (`app/Support`). Blocked on a coding decision, not on effort: legacy
  country codes are not ISO in places — Ireland is `IR` (ISO `IR` is Iran), South Korea `KS`,
  North Korea `NK` — while this port and the data already in `contacts.country` use ISO.
  Legacy also has zones for only four countries (US, CA, PL, RO), so Australia's list would
  disappear, and its Canadian codes differ (`LB`/`YK` against ISO `NL`/`YT`). Either adopt
  legacy's coding and migrate stored values, or correct the imported keys to ISO. Step 4 is what unblocks the `__COMMON__` third of every
legacy `select` field — see the legacy parity section of
[filament-fields.md](filament-fields.md).
