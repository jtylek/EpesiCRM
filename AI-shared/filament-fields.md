# Filament field types, and how this port extends them

Filament ships a fixed set of components; this port does **not** consume them directly in
resources. The `Field` DSL (`modules/Epesi/RecordBrowser/src/Recordset/Field.php`) is the
declaration surface, and it compiles down to the components below. This file is the map
between the two: what Filament actually offers, what the DSL exposes, and which of the two
you extend when something is missing.

Versions here are `filament/* v5.7.8`. Component lists are read off `vendor/`, not from
memory — re-check `vendor/filament/forms/src/Components/` if a minor bump lands.

## What Filament provides

**Form fields** — `filament/forms/src/Components/`:

| Group | Components |
| --- | --- |
| Text | `TextInput`, `Textarea`, `RichEditor`, `MarkdownEditor`, `CodeEditor`, `OneTimeCodeInput` |
| Choice | `Select`, `MultiSelect`, `Radio`, `Checkbox`, `CheckboxList`, `Toggle`, `ToggleButtons`, `TagsInput` |
| Date, number, colour | `DatePicker`, `DateTimePicker`, `TimePicker`, `Slider`, `ColorPicker` |
| Relational | `MorphToSelect`, `TableSelect`, `ModalTableSelect`, `RelationshipRepeater` |
| Structured | `Repeater`, `Builder`, `KeyValue` |
| Files | `FileUpload` (on `BaseFileUpload`) |
| Escape hatches | `ViewField`, `LivewireField`, `Hidden`, `Placeholder` |

**Layout** — `filament/schemas/src/Components/`: `Section`, `Fieldset`, `Grid`, `Group`,
`Flex`, `Tabs`, `Wizard`, `FusedGroup`, `Actions`, `Callout`, `Text`, `Html`, `Icon`,
`Image`, `EmbeddedTable`, `EmbeddedSchema`, `EmptyState`, `View`, `Livewire`, `RenderHook`,
`UnorderedList`.

**Infolist entries** (read-only): `TextEntry`, `IconEntry`, `ImageEntry`, `ColorEntry`,
`CodeEntry`, `KeyValueEntry`, `RepeatableEntry`, `ViewEntry`.

**Table columns**: `TextColumn`, `IconColumn`, `ImageColumn`, `ColorColumn`, `BadgeColumn`,
`TagsColumn`, `SelectColumn`, `TextInputColumn`, `ToggleColumn`, `CheckboxColumn`,
`ViewColumn`, `ColumnGroup`.

Note the asymmetry the DSL has to absorb: a form field, a view entry and a table column are
three unrelated class hierarchies. A single declared field is one thing to the user and up to
four objects to Filament (form, entry, column, filter).

## How Filament components are extended

Four levels, cheapest first:

1. **`ViewField` / `ViewEntry` / `ViewColumn`** — point an existing component at your own
   Blade view. No new class; state binding and validation stay Filament's.
2. **Subclass `Field`** — `class SignaturePad extends Field` with a `$view`.
   `Filament\Forms\Components\Field` is a plain class composed of traits (`CanBeValidated`,
   `HasHelperText`, `HasHint`, state casts), so a subclass inherits labels, validation and
   Livewire state for free. Same shape for `extends TextEntry` or `extends Column`.
3. **Subclass a concrete component** — `class MoneyInput extends TextInput` presetting prefix,
   mask and numeric rules. The usual way to make a house style reusable.
4. **Macros** — every component descends from `ViewComponent`, which is `Macroable`, so
   `TextInput::macro('phone', ...)` adds a fluent method globally from a service provider.

Nothing in `app/` or `modules/` currently does any of these. That is the point of the next
section.

## What the DSL exposes instead

`FieldType` (`Recordset/FieldType.php`) is a 16-case enum — `Text`, `LongText`, `Integer`,
`Decimal`, `Boolean`, `Date`, `DateTime`, `Time`, `Select`, `Multiselect`, `CommonData`,
`Relation`, `Relations`, `Email`, `Url`, `Phone` — deliberately smaller than Filament's list and
smaller than legacy Epesi's field types. Each case has a static constructor on `Field`
(`Field::text()`, `Field::relation($name, Model::class)`, …), and `Field` fans it out through
four `build*` methods:

| Method | Produces | Example mapping |
| --- | --- | --- |
| `toFormComponent()` | form field | `Boolean` → `Toggle`; `LongText` → `Textarea` rows 6; `Phone` → `TextInput->tel()` |
| `toInfolistEntry()` | view entry | `Boolean` → `IconEntry->boolean()`; `Select` → `TextEntry->badge()`; `Url`, `Email` → linked badge (an address links to `RecordExtensions::emailLink()`'s URL — Mail's compose page — or `mailto:`) |
| `toTableColumn()` | table column | `Boolean` → `IconColumn->boolean()`; `LongText` → `TextColumn->limit(60)` |
| `toTableFilter()` | filter | `Boolean` → `TernaryFilter`; `Select` → `SelectFilter`; date/number → a from/until range `Filter` |

Two consequences worth internalising:

- **`FieldType` values are stored data.** `custom_fields.type` holds the enum's string value
  for every administrator-added field, so renaming a case is a migration, not a refactor.
- **The enum is the framework-neutral part.** A `FieldType` says what a field *means*; only
  the `build*` methods know Filament exists. That separation is what keeps the MoonShine
  question answerable — see [Epesi-Moonshine-Laravel.md](Epesi-Moonshine-Laravel.md).

## Which one to extend

Decide in this order:

1. **A one-off tweak on one resource** — use the per-field escape hatches. `formUsing()`,
   `viewUsing()`, `columnUsing()` and `filterUsing()` each take a closure receiving the built
   component and the `Field`, and return a replacement. No new type, nothing stored, nothing
   for other resources to learn.
2. **A new meaning that recurs across recordsets** — add a `FieldType` case, a `Field` static
   constructor, and an arm in each of the four `build*` matches. Add the migration/backfill
   thinking that stored `custom_fields.type` values imply, and decide whether an administrator
   may pick it in the custom-field form.
3. **A genuinely new input widget** — only then subclass a Filament component (level 2 or 3
   above), and still surface it through a `FieldType` arm rather than letting resources
   reference the class directly.

The failure mode to avoid is reaching for step 3 when step 1 was the answer: a subclassed
component referenced straight from a resource bypasses the DSL, so it gets no custom-field
support, no filter, and no history rendering, and it re-couples that resource to Filament.

`commondata` has since been built (see [Common-data.md](Common-data.md)); `file` is still
noted in `FieldType`'s docblock as deliberately deferred, waiting on a disk convention. That
is not an oversight — don't add it ad hoc.

## Legacy parity

Where each legacy Epesi field type lands. Legacy references are paths inside the legacy
epesiCRM checkout; the canonical type list is `get_default_QFfield_callback()`
(`modules/Utils/RecordBrowser/RecordBrowserCommon_0.php`), cross-checked against
`actual_db_type()` in the same file and the two administrator pickers in `RecordBrowser_0.php`.
Seventeen stored types, plus a registry that lets modules define more.

### Done, one for one

`text` → `Text`, `long text` → `LongText`, `integer` → `Integer`, `float` → `Decimal`,
`date` → `Date`, `timestamp` → `DateTime`, `time` → `Time`, `checkbox` → `Boolean`,
`page_split` → `Field::section()`, `hidden` → `->onlyInTable()`.

These are strictly less code than legacy: `QFfield_integer` is a text element plus a regex
rule, where the DSL emits `TextInput->integer()`.

### Already ahead of legacy

`email`, `crm_company`, `crm_contact` and `crm_company_contact` are not real legacy types.
They are rows in `recordbrowser_datatype`, rewritten at install time into `text` or `select`
plus a `QFfield_callback`/`display_callback` pair — `CRM_ContactsCommon::email_datatype()`
sets `type = 'text'`, `param = '128'` and attaches two callbacks. Web address and phone are
plain `text` with a display callback. The port promotes all of them to first-class
`FieldType` cases with no callback machinery.

Legacy's `RecordPicker`/`automulti`/`autoselect` — a module with its own JS, swapped in above
`$options_limit` records — is replaced by `->searchable()->preload()` and `ModalTableSelect`.

### Ported, minus one hard half

`decode_select_param()` shows legacy `select`/`multiselect` carrying three unrelated meanings:
a commondata tree reference (`__COMMON__`), a link to one recordset, and a link to *several
recordsets at once* — the last stored as `tab/id` tokens (`__company/49__contact/54__`, which
`LegacyValue` already decodes on import).

Meaning two is `Relation`/`Relations` and is done. Meaning three has no Filament equivalent:
`MorphToSelect` picks one record polymorphically, but "many, across several recordsets" needs
a custom field over `morphToMany` plus the morph-alias registry. It is not an edge case —
Tasks' Customers field uses it — and it is the highest-risk outstanding item.

`crits_callback` (per-record option filtering) maps onto `Field::crits()` for a relation
field, or `->options(closure)` for a plain select; `adv_params_callback` is still a manual
rewrite. Use `crits()` rather than handing Filament's `->relationship(modifyQueryUsing:)` the
same filter by hand. The raw modifier also narrows what the field loads and saves, so a linked
record that no longer matches (an employee who has left) drops out of the form while staying
linked, and nobody can remove it. `crits()` keeps the record's current links on the form,
shown and removable, but holds the offered options and search results to the crits, so a
removed link isn't offered back.

### Needs building

| Legacy type | What is actually missing | Size |
| --- | --- | --- |
| `commondata` | **Built** — `modules/Epesi/CommonData` plus `FieldType::CommonData`. It was a store problem rather than a widget one; see [Common-data.md](Common-data.md). | Done |
| `currency` | No Filament component exists. Stored as one `C(128)` string `amount__currencycode`, with per-currency precision and a per-user default currency. `Decimal` silently drops the code. Needs a currencies table, a user setting, a schema decision, and a `FusedGroup` or subclassed field. | Medium |
| `file` | The widget is free (`FileUpload`), and the store now exists: `App\Services\FileStorage` (see [architecture.md](architecture.md#file-storage)), which the Notes addon's upload field already writes to. Legacy holds `Utils_FileStorage` ids encoded `__id__id__`, with an action handler and inline previews. What is left is the field type and the import mapping. | Small |
| `autonumber` | Trivial as an accessor plus `TextEntry`. One decision: legacy *stores* the formatted value and rewrites every row when the param changes (`format_autonumber_str_all_records`); deriving it instead removes that maintenance path. | Small |
| `calculated` | Legacy renders a formula as static HTML with DOM hooks for live recalculation. An Eloquent accessor is the right answer. Administrator-*defined* formulas would need an expression evaluator and a reactive Livewire component — out of scope. | Skip the admin half |

Suggested order for what is left: `autonumber`, then `file`, `currency`, and multi-recordset
relations last.

### The registry gap

Legacy has `register_datatype($type, $module, $func)`, backed by the `recordbrowser_datatype`
table — a runtime, module-extensible type registry. It is how Contacts adds `email` without
touching RecordBrowser.

`FieldType` is a PHP enum, and enums are closed. A module under `modules/<Vendor>/<Name>/`
therefore cannot add a field type at all; it can only reach for the per-field closures. That
is an asymmetry with legacy worth deciding on deliberately, because the module system exists
so third parties ship features without a code deploy. Making `FieldType` a string-keyed
registry instead is far cheaper to decide now than after more cases land — its values are
stored in `custom_fields.type`, so every later change is a data migration.
