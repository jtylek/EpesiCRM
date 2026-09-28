# Fields: the `Field` DSL, custom fields and collections

Everything about a recordset's fields, in one place:

- [Part 1](#part-1--the-field-dsl-and-filament): how a module declares fields with the `Field`
  DSL, how the DSL turns each one into Filament components, and which of the two to extend when a
  type is missing.
- [Part 2](#part-2--custom-fields): how an administrator adds a field from Administration →
  Fields, and how it is stored.
- [Part 3](#part-3--legacy-field-types-against-the-port): legacy Epesi's field types against the
  port's: what maps one for one, what is only partly there, and what is missing.
- [Part 4](#part-4--collections-and-related-records): the design for what a record can have
  none or many of. Addresses, phone numbers, e-mail addresses and online accounts become
  collections. Notes, e-mails, tasks and anything else that links to a record appear as related
  records.
- The [implementation plan](#implementation-plan) for what isn't built yet, with its progress.

## In short

- **A recordset declares its fields; the engine builds the screens.** `Field::text('city')`,
  `Field::relation('company_id', Company::class)` and so on. From each declaration the engine
  builds the form input, the View page entry, the list column and the filter. A resource never
  uses Filament components directly, except through the per-field escape hatches.
- **An administrator's field is a real column.** Administration → Fields adds a field to any
  recordset from the browser. It becomes a column on the recordset's own table (`cf_<id>`) and
  behaves like a shipped field everywhere, History included.
- **Against legacy Epesi, two types are missing:** `currency` and `calculated`. A select over
  several named recordsets is one field per target, and a select over any recordset is "Link to
  any record" (`Related`). Administration → Fields can add every type, links to records and
  shared lists included.
- **The new direction: collections and related records.** A record keeps as its own fields only
  what it has exactly one of. Anything it can have none or many of takes one of two forms:
  - a **collection**: a set of fields repeated as often as needed and owned by the record, such
    as its addresses, phone numbers, e-mail addresses and online accounts;
  - a **related record**: one that exists on its own and links to this one, such as a note, an
    e-mail, a task or a meeting, or any record a module adds.

  A contact can then start with just a name and fill in over time.
- **Eleven steps.** Steps 1–5 are done: step 4 finished Administration → Fields, and step 5
  built collections and moved addresses into them. Steps 6–9 add phone numbers, online accounts
  and e-mail addresses as collections, and the related-record tabs. Steps 10–11 add `calculated`
  and `currency`.

## Part 1 — The `Field` DSL and Filament

Filament ships a fixed set of components, and the resources here don't use them directly. The
`Field` DSL (`modules/Epesi/RecordBrowser/src/Recordset/Field.php`) is where fields are
declared, and it compiles each field into Filament components. This part maps the two: what the
DSL exposes, what Filament offers, and which of the two to extend when something is missing.

**DSL** stands for *domain-specific language*: a small vocabulary made for one job, as opposed to
a general-purpose programming language. Here it is the set of `Field` methods a recordset uses to
describe its fields:

```php
Field::text('last_name')->required()->maxLength(64)->inTable(),
Field::relation('company_id', Company::class)->label('Company')->filterable(),
Field::longText('memo')->section('Details'),
```

It is still plain PHP, but it reads as a description of the field in Epesi's own terms: text,
required, shown in the list, filterable, in the Details section. It doesn't say how to build it.
The engine turns each description into the actual Filament form input, View page entry, list
column and filter. A DSL written inside a general-purpose language like this is called an
*internal* or *embedded* DSL.

### What the DSL exposes

`FieldType` (`Recordset/FieldType.php`) is a 20-case enum: `Text`, `LongText`, `Integer`,
`Decimal`, `Boolean`, `Date`, `DateTime`, `Time`, `Select`, `Multiselect`, `CommonData`,
`Relation`, `Relations`, `Email`, `Url`, `Phone`, `Autonumber`, `File`, `Related` and
`Collection`. It is
deliberately smaller than Filament's list and smaller than legacy Epesi's set of types. Each case
has a static constructor on `Field` (`Field::text()`, `Field::relation($name, Model::class)`,
…), and `Field` fans it out through four `build*` methods:

| Method | Produces | Example mapping |
| --- | --- | --- |
| `toFormComponent()` | form field | `Boolean` → `Toggle`; `LongText` → `Textarea` rows 6; `Phone` → `TextInput->tel()` |
| `toInfolistEntry()` | view entry | `Boolean` → `IconEntry->boolean()`; `Select` → `TextEntry->badge()`; `Url`, `Email` → linked badge (an address links to `RecordExtensions::emailLink()`'s URL — Mail's compose page — or `mailto:`) |
| `toTableColumn()` | table column | `Boolean` → `IconColumn->boolean()`; `LongText` → `TextColumn->limit(60)` |
| `toTableFilter()` | filter | `Boolean` → `TernaryFilter`; `Select` → `SelectFilter`; date/number → a from/until range `Filter` |

Two consequences worth internalising:

- **`FieldType` values are stored data.** `custom_fields.type` holds the enum's string value for
  every administrator-added field, so renaming a case is a migration, not a refactor.
- **The enum is the framework-neutral part.** A `FieldType` says what a field *means*; only the
  `build*` methods know Filament exists. That separation is what keeps the MoonShine question
  answerable — see [Epesi-Moonshine-Laravel.md](Epesi-Moonshine-Laravel.md).

### What Filament provides

Versions here are `filament/* v5.7.8`. The component lists are read off `vendor/`; re-check
`vendor/filament/forms/src/Components/` when a minor version lands.

**Form fields**, in `filament/forms/src/Components/`:

| Group | Components |
| --- | --- |
| Text | `TextInput`, `Textarea`, `RichEditor`, `MarkdownEditor`, `CodeEditor`, `OneTimeCodeInput` |
| Choice | `Select`, `MultiSelect`, `Radio`, `Checkbox`, `CheckboxList`, `Toggle`, `ToggleButtons`, `TagsInput` |
| Date, number, colour | `DatePicker`, `DateTimePicker`, `TimePicker`, `Slider`, `ColorPicker` |
| Relational | `MorphToSelect`, `TableSelect`, `ModalTableSelect`, `RelationshipRepeater` |
| Structured | `Repeater`, `Builder`, `KeyValue` |
| Files | `FileUpload` (on `BaseFileUpload`) |
| Escape hatches | `ViewField`, `LivewireField`, `Hidden`, `Placeholder` |

**Layout**, in `filament/schemas/src/Components/`: `Section`, `Fieldset`, `Grid`, `Group`,
`Flex`, `Tabs`, `Wizard`, `FusedGroup`, `Actions`, `Callout`, `Text`, `Html`, `Icon`, `Image`,
`EmbeddedTable`, `EmbeddedSchema`, `EmptyState`, `View`, `Livewire`, `RenderHook`,
`UnorderedList`.

**Infolist entries** (read-only): `TextEntry`, `IconEntry`, `ImageEntry`, `ColorEntry`,
`CodeEntry`, `KeyValueEntry`, `RepeatableEntry`, `ViewEntry`.

**Table columns**: `TextColumn`, `IconColumn`, `ImageColumn`, `ColorColumn`, `BadgeColumn`,
`TagsColumn`, `SelectColumn`, `TextInputColumn`, `ToggleColumn`, `CheckboxColumn`, `ViewColumn`,
`ColumnGroup`.

The DSL has to absorb an asymmetry here. A form field, a view entry and a table column are three
unrelated class hierarchies, so one declared field is one thing to the user and up to four
objects to Filament (form, entry, column, filter).

### How Filament components are extended

Four levels, cheapest first:

1. **`ViewField` / `ViewEntry` / `ViewColumn`.** Point an existing component at your own Blade
   view. No new class, and state binding and validation stay Filament's.
2. **Subclass `Field`.** For example `class SignaturePad extends Field` with a `$view`.
   `Filament\Forms\Components\Field` is a plain class composed of traits (`CanBeValidated`,
   `HasHelperText`, `HasHint`, state casts), so a subclass inherits labels, validation and
   Livewire state for free. The same shape works for `extends Entry` or `extends Column`.
3. **Subclass a concrete component.** For example `class MoneyInput extends TextInput`, presetting
   prefix, mask and numeric rules. The usual way to make a house style reusable.
4. **Macros.** Every component descends from `ViewComponent`, which is `Macroable`, so
   `TextInput::macro('phone', ...)` adds a fluent method globally from a service provider.

One class here uses level 2. RecordBrowser's `SwitchEntry` (`Filament/Infolists`) extends
`Entry` to show a yes/no value as a disabled switch, because Filament has no switch entry.
Nothing uses levels 3 or 4.

### Which one to extend

Decide in this order:

1. **A one-off tweak on one resource**: use the per-field escape hatches. `formUsing()`,
   `viewUsing()`, `columnUsing()` and `filterUsing()` each take a closure that receives the built
   component and the `Field`, and returns a replacement. No new type, nothing stored, nothing for
   other resources to learn.
2. **A new meaning that recurs across recordsets**: add a `FieldType` case, a `Field` static
   constructor and an arm in each of the four `build*` matches (the full list is under
   [What every new type touches](#what-every-new-type-touches)). Think through the migration or
   backfill that the stored `custom_fields.type` values imply, and decide whether an administrator
   may pick the type in the custom-field form.
3. **A genuinely new input widget**: only then subclass a Filament component (level 2 or 3
   above). Still surface it through a `FieldType` arm rather than letting resources reference
   the class directly.

The failure mode to avoid is reaching for step 3 when step 1 was the answer. A subclassed
component referenced straight from a resource bypasses the DSL. It then gets no custom-field
support, no filter and no History rendering, and it ties that resource to Filament again.

## Part 2 — Custom fields

Administrator-added fields on any recordset: the port of legacy Epesi's custom fields
(`Utils_RecordBrowserCommon::new_record_field()`, Administration → Records Browser). A
`super_admin` adds a field to an existing recordset from the browser, with no code, no deploy and
no hand-written migration. It behaves exactly like a field the module shipped with: it appears
on the form, the View page, the list, the filters and the column chooser, and its changes show in
the record's History tab.

### A real column, not a blob

An administrator's field is a genuine column on the model's own table (`companies.cf_1`, named
after the id of its `custom_fields` row). It is added and dropped with real DDL
(`Epesi\Modules\RecordBrowser\CustomFields\CustomFieldSchema`). That was a deliberate choice
over a single JSON column or an EAV side table (see the class docblock): sorting, filtering,
export and any raw SQL or reporting see it exactly as they see a shipped column, with no
special-casing in any of them. `custom_fields` is the source of truth, and the schema is always
derived from it. That is what makes [Repair columns](#repair-columns) possible.

**DDL** stands for *Data Definition Language*: the SQL statements that change a database's
structure rather than its data.

- **DDL** creates, changes or removes tables, columns and indexes: `CREATE TABLE`,
  `ALTER TABLE`, `DROP TABLE`, `CREATE INDEX`.
- Its counterpart, **DML** (*Data Manipulation Language*), works on the rows inside those tables:
  `SELECT`, `INSERT`, `UPDATE`, `DELETE`.

Adding a custom field runs DDL such as `ALTER TABLE contacts ADD COLUMN cf_12 VARCHAR(255) NULL`,
and Drop column runs `ALTER TABLE contacts DROP COLUMN cf_12`. When this document says a field
or a collection "needs no DDL", it means adding it changes no table's structure, only rows: a
"Link to any record" field's links are rows of the existing `epesi_recordbrowser_links` table.
MySQL can't undo DDL inside a transaction, so a failed DDL statement can leave a half-built table
behind.

A model opts in with one trait, `Epesi\Modules\RecordBrowser\Models\Concerns\HasCustomFields`,
and that is the whole integration. The trait makes the field mass-assignable, so a Filament form
saves it with no extra code. `spatie/laravel-activitylog`'s `logFillable()` reads the same
fillable list, so the field's edits land in History automatically. The trait also brings in
`HasFileFields`, `HasRecordLinks` and `HasCollections`, which the `File`, `Related` and
`Collection` types need. The five CRM
recordsets (Companies, Contacts, Tasks, Meetings, Phone Calls) use it, and `make:epesi-recordset`
scaffolds it into a new recordset by default.

### Administration → Fields

`Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\CustomFieldResource`, gated to
`super_admin` like the rest of the Administration panel. It lists every custom field on every
recordset, grouped by recordset by default, with a **Recordset** filter and column to narrow it
down.

#### Adding a field

**New field** opens a form:

- **Recordset** and **Type**, picked once and locked after the field is created. Moving a field
  to another table, or changing its type, would leave its data behind or fail to convert it
  (see [Editing a field](#editing-a-field)).
- **Label**, what users see. Typing it fills in **Name** (a lowercase slug) automatically until
  the administrator edits Name by hand. Code, imports and History keys use Name, so it can't be
  renamed afterwards the way Label can.
- The type's own settings appear once a type is picked:
  - **Maximum length** for text-like types;
  - **Decimal places** for Decimal;
  - **Prefix**, **Digits** and **Pad character** for Automatic number;
  - **Minutes interval** for Time and Date and time;
  - **Several files** and **Maximum file size (MB)** for File;
  - **Recordsets** for Link to any record;
  - **Links to** (the target recordset, locked once created) for Link to one record and Link to
    many records;
  - **List** (a shared list from Administration → Common Data) and **Several values** (locked
    once created) for Shared list;
  - **Collection** (the collection type, such as Address, locked once created) for Collection;
  - **Choices** (stored value → shown label) for Select and Multiple select.

  On a collection type's own items (the Address recordset), **Type** offers only what an item
  can hold (`FieldType::fitsCollectionItem()`): no links, files, automatic numbers or
  collections.
- **Where it appears**:
  - **Group**: a heading that gathers the fields sharing it into their own card (legacy's
    `page_split`);
  - **Position**: lower numbers first;
  - toggles for **On the add/edit form**, **On the record view**, **As a list column** (off still
    offers it in the column chooser), **As a list filter**, **Required**, **Included in
    exports** and **Active**.

Saving runs the `ALTER TABLE` immediately. If it fails (for example, the per-recordset field
limit is hit), nothing is saved, so no half-built definition is left behind.

**Included in exports** is stored but not wired to anything yet: the app has no export feature to
consult it. It's there for when one is built, and toggling it today has no visible effect.

#### Editing a field

Recordset and Type stay locked on the edit form. Everything else can be changed freely: Label,
Name, Group, Position, Required, the four `show_in_*`/filterable toggles, Active and Help text.
None of it touches the database column.

This screen doesn't offer changing a field's type at all. If a type change reached the model
layer programmatically, `CustomFieldSchema::assertChangeAllowed()` would allow some widening
conversions (Text → Long text, Integer → Decimal, and similar) and refuse anything that loses
data (Date → Integer, Long text → a short Text). From the admin screen, the only way to change a
field's type is to add a new field and migrate the data by hand.

#### Removing a field

There is one destructive action: the **Drop column** icon (a red trash icon in the row, or a
labelled button on the View page). It drops the column *and every value stored in it, on every
record*, then deletes the definition. It asks for confirmation and names the exact
`table.column` it is about to remove. There is no undo. For a Link to any record or Link to many
records field, which has no column, it deletes the field's links instead, and for a Collection
field its items.

To hide a field without losing data, turn its **Active** toggle off instead. That is reversible,
and the field's column and data stay exactly as they were. There is deliberately no action that
deletes only the definition. It would leave an orphaned column holding data that only SQL could
recover, and "Active off" already covers hiding, just as Drop column covers destroying.

#### Repair columns

The wrench button in the list's header. It walks every field definition, and for any field whose
table exists but lacks the field's column, it adds the column back (`CustomFieldSchema::sync()`).
It never touches existing columns or their data; it only fills in what's missing.

Two situations call for it:

- A column was lost some other way than Drop column (a manual `ALTER TABLE`, a restore that
  didn't include the schema change) while its `custom_fields` row survived.
- Field definitions were copied into a fresh install's `custom_fields` table directly rather
  than through the admin form. This is how their columns get created there.

It reports what it created, or that every field already had its column. `customfields:sync` is
the same thing on the command line.

### Limits and available types

A recordset can carry at most 64 administrator-added fields
(`CustomFieldSchema::MAX_FIELDS_PER_MODEL`). That is well under InnoDB's own row-width limits,
and high enough that reaching it means rethinking the data model rather than hitting a real
constraint.

Every type is offered from the GUI (`FieldType::isAdministratorDefinable()` withholds none,
and stays as the place to withhold one a form can't offer yet). How the less obvious ones are
stored:

- **File**: the column holds StoredFile ids, and a file taken off the field is released (see
  [`file`](#file)).
- **Link to any record**: no column; its links are rows of the shared link table (see
  [Selects across several recordsets](#selects-across-several-recordsets)).
- **Link to one record** (`Relation`): the column (`cf_12`) holds the linked record's key, with
  no database foreign key, so uninstalling the target recordset's module doesn't break the
  table; a key whose record has gone shows as `#id`. `HasCustomFields` registers its
  `belongsTo` with `Model::resolveRelationUsing()` under a name of its own (`cf12`,
  `CustomFieldRegistry::relationshipName()`), and the engine's usual relation select, badges
  and filter work on it unchanged.
- **Link to many records** (`Relations`): no column and no pivot. Its relationship (`cf12`) is a
  `morphToMany` over the shared link table, held to the field and its target with
  `withPivotValue()`, so its rows are the same kind "Link to any record" keeps.
- **Shared list** (`CommonData`): the column is a string holding one key, or JSON holding
  several.
- **Collection**: no column; its items are rows of the collection type's table, kept apart from
  the recordset's own collection of that type by `field` (`cf_12`). See
  [Collections](#collections).

A link field whose target recordset is gone (its module uninstalled) gets no relationship and no
field on the screens (`CustomFieldRegistry::relationsFor()`/`toField()`); its data stays. So does
a Collection field whose type is gone.

For everything else legacy's add-field screen offered that this one doesn't, see
[Administration → Fields against legacy's add-field screen](#administration--fields-against-legacys-add-field-screen).

### Files

| File | What it is |
| --- | --- |
| `modules/Epesi/RecordBrowser/database/migrations/2026_09_07_110000_create_epesi_recordbrowser_custom_fields_table.php` | The `custom_fields` table |
| `modules/Epesi/RecordBrowser/src/Models/CustomField.php` | One field definition. Its model events run the DDL on create/update and refresh the registry on save/delete |
| `modules/Epesi/RecordBrowser/src/CustomFields/CustomFieldSchema.php` | The DDL: add, change, drop, sync, and the widening-conversion and per-recordset-limit rules |
| `modules/Epesi/RecordBrowser/src/CustomFields/CustomFieldRegistry.php` | Which recordsets can carry custom fields (derived from the morph map), the active definitions, cached to `bootstrap/cache/epesi-custom-fields.php`, and the relationships link fields need (`relationsFor()`) |
| `modules/Epesi/RecordBrowser/src/Models/Concerns/HasCustomFields.php` | The one-line opt-in trait a model adds; it registers the link fields' relationships and brings `HasFileFields`, `HasRecordLinks` and `HasCollections` along |
| `modules/Epesi/RecordBrowser/src/Filament/Resources/CustomFields/` | Administration → Fields: the resource, form, infolist, table and the drop-column action |
| `modules/Epesi/RecordBrowser/src/Console/CustomFieldsSyncCommand.php` (`customfields:sync`) | The command-line form of Repair columns |

[conventions.md](conventions.md#custom-fields) has the one-paragraph version, for a resource
author wondering what `HasCustomFields` buys them.

## Part 3 — Legacy field types against the port

This part compares every field type in legacy Epesi's RecordBrowser with the port's `FieldType`
enum and `Field` DSL. It covers two cases: a module declaring fields in code, and an
administrator adding a field under Administration → Fields.

### Where the legacy list comes from

Legacy references are paths inside the legacy epesiCRM checkout. The stored types are the cases of
`actual_db_type()` in `modules/Utils/RecordBrowser/RecordBrowserCommon_0.php`. They were
cross-checked against these:

- `get_default_QFfield_callback()` in the same file;
- the two administrator pickers in `RecordBrowser_0.php`, one on the field list and one on the
  add-field form.

That makes seventeen types. Each recordset's `<tab>_field` table also has a `foreign index` row,
but that is the internal `id` row every recordset gets on install, not a field type.

### Type by type

| Legacy type | Port | Status |
| --- | --- | --- |
| `text` (length in `param`) | `Text`, with `->maxLength()` | Done |
| `long text` | `LongText` | Done |
| `integer` | `Integer` | Done |
| `float` | `Decimal` | Done |
| `checkbox` | `Boolean` | Done |
| `date` | `Date` | Done |
| `timestamp` | `DateTime`, with `->minutesStep()` | Done |
| `time` | `Time`, with `->minutesStep()` | Done |
| `commondata`, and a `multiselect` on `__COMMON__` | `CommonData` (`multiple: true` for several values) | Done |
| `select` / `multiselect` on one recordset | `Relation` / `Relations` | Done |
| `select` / `multiselect` on several named recordsets (`contact,company`) | One `Relation` / `Relations` per target | Done, as separate fields; see [below](#selects-across-several-recordsets) |
| `select` / `multiselect` on any recordset (`__RECORDSETS__`) | `Related` (`Field::related()`) | Done; see [below](#selects-across-several-recordsets) |
| `autonumber` | `Autonumber` | Done |
| `page_split` | `Field::section()` | Done |
| `hidden` | `->onlyInTable()`, or `inForm(false)->inView(false)->notInTable()` for a field shown nowhere | Done |
| `file` | `File` (`Field::file()`) | Done; see [below](#file) |
| `currency` | — | **Missing** |
| `calculated` | — | **Missing** |

The port's own `Select` / `Multiselect` take a fixed list of choices from an enum or an array.
Legacy has no such type: its `select` always points at a recordset or a commondata list.

These types need less code than in legacy. `QFfield_integer` is a text element plus a regex rule,
where the DSL emits `TextInput->integer()`.

`Autonumber` is derived from the record's key rather than stored (`Field::formatAutonumber()`),
so it has no column, and changing its format needs no migration or backfill. Legacy stores the
formatted value instead, and rewrites every row when the format changes
(`format_autonumber_str_all_records`). `CustomFieldSchema` skips it in `add()`, `change()` and
`sync()` for the same reason.

The minute interval (`->minutesStep()`, the `minutes_step` param) reaches both of Filament's
pickers: `minutesStep()` steps the JavaScript picker's minute input and becomes `step` (in
seconds) on the native one. A native input with a step rejects a stored value off the step, so a
record imported at 10:07 won't save until its time is changed. That's why the CRM recordsets
don't set one, although legacy's Meeting Time used 5 minutes.

### `file`

`Field::file($name, $multiple = true, $maxSizeMb = 50)`. Files go into the shared file storage
(`App\Services\FileStorage`, see [architecture.md](architecture.md#file-storage)), and the
field's column is JSON holding their StoredFile ids.

- **The cast.** `Epesi\Modules\RecordBrowser\Files\StoredFileIds` keeps the ids as strings, as
  FileUpload's tamper check (`preventFilePathTampering()`) wants them, and stores "no files" as
  null, not `[]`. It's also how the engine tells a file column from any other JSON column.
  `Field::cast()` returns it for an administrator's field. A module casts its own file column to
  it, and `recordset:check` reports a File field whose column isn't cast that way.
- **The trait.** `HasFileFields` (which `HasCustomFields` uses) finds the file columns by that
  cast and does two things:
  - A file taken off a field is deleted, and `FileStorage` releases the content once nothing
    uses it. The same happens to every file of a record deleted for good. A soft-deleted record
    keeps its files, so Restore brings them back.
  - Its `tapActivity()` writes `file_names` (id => name) into the History entry, so a file taken
    off is still named there. A model with its own `tapActivity()` calls `tapFileNames()` from
    it.

  The deletion runs on `saved`, not `updated`. LogsActivity logs on `updated`, and which of two
  traits' `updated` listeners runs first depends on the order the model lists them in, so the
  file could otherwise be gone before its name was logged.
- **The form.** A `FileUpload` configured like the Notes addon's: it saves uploads straight into
  `FileStorage`, and describes only files the record holds.
- **Serving.** `records/{type}/{id}/files/{field}/{file}` (`epesi.records.file`,
  `RecordFileController`). It finds the record through its model's own query, so the ownership
  scope turns a record the user can't see into a 404. It checks the `view` policy (no policy
  counts as allowed, as in Filament), and serves only a file that column holds. With `?preview=1`
  a previewable type (`StoredFile::isPreviewable()`) comes back inline; anything else downloads.
- **View and list.** The same file pills as Notes and the Mail archive (`FileChip`). A list cell
  shows the first two and "+N".
- **Filter.** Has files, yes or no.
- **History.** Files added and taken off, by name.
- **Administration → Fields.** Offered, with **Several files** and **Maximum file size (MB)**.

Not built yet:

- **Import mapping.** Legacy stores `Utils_FileStorage` ids as `__1__2__`. The Attachments
  importer converts them for notes; no other importer has a file field yet.
- **One upload setup shared with Notes.** `AttachmentResource` still configures its own
  `FileUpload` the same way.

### Selects across several recordsets

`decode_select_param()` shows that legacy's `select`/`multiselect` can point at four things:

- a commondata list (`__COMMON__`);
- one recordset;
- several named recordsets (`contact,company`);
- any recordset at all (`__RECORDSETS__`).

The last two store `tab/id` tokens (`__company/49__contact/54__`), which `LegacyValue` decodes on
import. The first two are `CommonData` and `Relation`/`Relations`.

**Several named recordsets.** The port splits these into one field per target. Tasks and
Meetings have Contacts (`customers`) and Companies (`customerCompanies`), and Phone Calls has
`contact_id` and `company_id`. It works, but the two show as separate fields on the form, the
View page and the list.

**Any recordset** is `Field::related($name, ?array $recordsets = null)`, "Link to any record":
the Related field on Tasks, Meetings and Phone Calls. It links to records that exist on their own
(see [Part 4](#two-kinds-of-more-than-one)). Mail's Related and Notes' "Attached to" still use
their own link tables (`epesi_mail_links`, `epesi_attachment_links`); step 9 moves them onto
this one.

- **What it offers.** `LinkableRecordsets`: every model in the morph map with a View page in the
  main panel, so a module's recordset is offered as soon as it's installed. `$recordsets` (model
  classes or morph aliases) narrows that, as legacy's per-field list (`task_related`) did.
- **Storage.** One shared table, `epesi_recordbrowser_links` (`RecordLink`), with columns
  `source_type`, `source_id`, `field`, `target_type` and `target_id`. Both ends are morph
  aliases, and there are no foreign keys. A unique key covers all five columns, and an index
  covers the target. Because one table serves every field, a field an administrator adds needs
  no DDL: `FieldType::hasColumn()` is false for it, so `CustomFieldSchema` skips it, and dropping
  the field deletes its links.
- **The trait.** `HasRecordLinks` (which `HasCustomFields` uses) has `recordLinks()`,
  `linkedRecords($field)` and `syncRecordLinks($field, $tokens, $recordsets)`. A linked record
  is always loaded through its own model's query, so the ownership scope hides one the user can't
  see. Saving leaves such a link alone, since it was never on their form. A token naming a record
  they can't see, or a recordset the field doesn't offer, is ignored. A record deleted for good
  takes its links with it; a soft-deleted one keeps them.
- **The form.** One searchable multiple select over every offered recordset. It searches the
  columns each resource searches globally (plain columns only), as the Notes "Attached to" picker
  does. Each value is a token, `alias:id` ("company:12"), labelled "Company: Acme Ltd". It is
  `dehydrated(false)`, and `saveRelationshipsUsing()` syncs the links after the record is saved.
- **View and list.** `LinkedRecords` badges, each opening its record's View page. The list loads
  a page's links when its first row asks: one query for the links and one per recordset they
  point at, not two per row.
- **Filter.** By recordset.
- **History.** Nothing, as for a `Relations` field: `LogsActivity` doesn't track link tables.
- **Import.** The Tasks, Meetings and Phone Calls importers read `f_related` through
  `Importer::importRelated()`. `LegacyRecordRefs` turns a legacy `tab/id` into a token, using the
  tab each importer reads and the model it writes. A reference to a recordset that isn't ported,
  or to a record not imported yet, is skipped and reported in the import summary. It comes in on
  a later run once its target exists.
- **Administration → Fields.** Offered, with a **Recordsets** setting (empty offers them all).
- **Checks.** `recordset:check` reports a Related field on a model without `HasRecordLinks`.

Not built yet: offering only some records of a recordset. Legacy's `related_crits` could in
principle filter records as well as recordsets, though the CRM's never did.

Per-record option filtering (`crits_callback`) maps onto `Field::crits()` for a relation field,
or onto `->options(closure)` for a plain select. `adv_params_callback` is still a manual rewrite.

Use `crits()` rather than passing the same filter to Filament's
`->relationship(modifyQueryUsing:)` by hand. The raw modifier also narrows what the field loads
and saves. A linked record that no longer matches, such as an employee who has left, then drops
out of the form while staying linked, and nobody can remove it. `crits()` keeps the record's
current links on the form, shown and removable, but limits the offered options and search results
to the crits, so a removed link isn't offered back.

### Ahead of legacy

`email`, `crm_company`, `crm_contact` and `crm_company_contact` aren't real legacy types. They
are rows in `recordbrowser_datatype`, which legacy rewrites at install time into `text` or
`select` plus a `QFfield_callback`/`display_callback` pair. For example,
`CRM_ContactsCommon::email_datatype()` sets `type = 'text'` and `param = '128'`, and attaches two
callbacks. Web address and phone are plain `text` with a display callback. The port makes all of
them first-class `FieldType` cases, with no callback machinery.

Legacy's `RecordPicker`/`automulti`/`autoselect` is a module with its own JS, swapped in once a
recordset passes `$options_limit` records. The port replaces it with `->searchable()->preload()`
and `ModalTableSelect`.

### What is missing

#### `currency`

**In legacy**, the value is one `C(128)` string, `amount__currencyid`. The id points into the
currency list kept by `Utils/CurrencyField`, which also sets each currency's precision and decimal
sign, and each user has a default currency.

**In the port**, `Decimal` has a number of decimal places and nothing else: no currency, no
symbol, and no list of currencies anywhere in the repository. A legacy currency value imported
into a `Decimal` loses its currency.

None of the CRM recordsets use it, but any module that records money (prices, totals, rates)
needs it. No Filament component covers it. It needs:

- a currencies table;
- a per-user default currency;
- a decision on how to store it (an amount column plus a currency column sort and add up;
  legacy's encoded string doesn't);
- a form component, either a `FusedGroup` of amount and currency or a subclassed field.

[Step 11](#step-11-currency) builds it.

#### `calculated`

**In legacy**, the value comes from a display callback, and optionally a form callback. The field
may or may not have a column: its `param` holds the column type, and an empty `param` means no
column. Legacy core uses it for:

- Contacts' Username, Set Password, Confirm Password and Admin, the contact's login account. The
  port covers these with the contact's `user_id` link and the Users screen.
- Mail's message count on a thread, and its attachment list.

An administrator could add one too, by typing PHP into the field form.

**In the port**, there is no type. The workaround is an Eloquent accessor plus a field kept off the
form (`inForm(false)`). It shows on the View page and in the list, but it doesn't hold up:

- `recordset:check` reports the field as a missing column.
- A scalar-typed field is sortable by default, and a text-like one is searchable, so the list
  queries a column that doesn't exist unless the module turns both off.

A code-only field fixes this: no column, never on the form, and not sortable, searchable or
filterable unless the module supplies the query. `recordset:check` would skip it, the way it skips
`Autonumber`. [Step 10](#step-10--calculated) makes it a modifier rather than a type.

Skip the administrator half. Formulas defined in the browser would need an expression evaluator
and a reactive Livewire component.

### Administration → Fields against legacy's add-field screen

Legacy's add-field form offers autonumber, currency, checkbox, date, time, timestamp, integer,
float, text, long text, select and file. Its select can draw from a recordset or a CommonData
list, with one value or several. Administration → Fields offers the types listed under
[Limits and available types](#limits-and-available-types). Date and time and Time take legacy's
minute interval (1, 2, 5, 10, 15, 20 or 30 minutes, or full hours), and Automatic number its pad
character. A select from a recordset is Link to one record or Link to many records, a select
from several recordsets is Link to any record, and a select from a CommonData list is Shared
list, with one value or several.

What an administrator could do in legacy and can't here:

| Legacy | Port |
| --- | --- |
| Currency, Calculated | The types don't exist (see above). |
| Display callback, form callback and field template, typed as PHP | Not reproduced. The port's escape hatches (`formUsing()`, `viewUsing()`, `columnUsing()`, `filterUsing()`) exist only in code. |

The port adds something legacy's form didn't have: Select and Multiple select take a fixed list
of choices (stored value → label) typed straight into the form.

### The registry gap

Legacy has `register_datatype($type, $module, $func)`, backed by the `recordbrowser_datatype`
table. It is a runtime type registry that modules can extend, and it's how Contacts adds `email`
without touching RecordBrowser.

`FieldType` is a PHP enum, and enums are closed. A module under `modules/<Vendor>/<Name>/`
therefore can't add a field type at all; all it can use is the per-field closures. That differs
from legacy, and it's worth deciding deliberately: the module system exists so that third parties
can ship features without a code deploy.

Collections (Part 4) take part of the pressure off: a module *can* add a collection type (a set
of fields with a table of its own) without touching the enum.

The enum's values are stored in `custom_fields.type`, so renaming one is a data migration.
Replacing the enum with a string-keyed registry that keeps the same values isn't; the cost of that
switch is the code that names `FieldType::` cases. The plan below therefore adds new types as enum
cases, and leaves the registry as a decision of its own.

## Part 4 — Collections and related records

### The problem

Before collections, a record held a fixed number of each kind of contact detail, each in columns
of its own. Addresses have since become a collection ([step 5](#step-5-done)); phone numbers,
e-mail addresses and web addresses are still columns:

- A contact has one e-mail address, a work, mobile and home phone and a fax, and had two
  addresses: `address_1` … `zone`, and the same six again prefixed `home_`. A company has one
  e-mail, one phone, one fax, and had one address.
- A third address had nowhere to go, and a second mobile number still hasn't. Each extra slot is
  a migration and another set of columns. `AddressFields::block()` took a column prefix only so
  that Contact could declare its address twice.
- Where more than one is needed, it is bolted on elsewhere. Extra e-mail addresses live in Mail's
  own table (`epesi_mail_addresses`) behind an "E-mail addresses" tab, so every consumer looks in
  two places. `ContactMatcher` runs three queries (contacts, companies, extra addresses), and the
  Roundcube address book is a UNION of four SELECTs. Extra postal addresses would need another
  recordset with another tab, and every consumer (a shipping form, a mail merge) would have to
  enumerate that too.
- A consumer that needs "a phone number" must know the column names. The Phone Calls importer
  maps legacy's phone choice 1, 2 and 3 onto `mobile_phone`, `work_phone` and `home_phone`.

The records that link to a contact have the same kind of problem. Each tab on a contact's View page
(Tasks, Phone Calls, Meetings) is a hand-written relation manager, and each is written again for
Companies. That makes seven of them across the two recordsets, counting the Contacts tab on a
company, each with its own columns. A recordset added by a module (an invoice, a ticket) gets no
tab on a contact unless someone writes one more.

### Two kinds of "more than one"

| | Collection | Related record |
| --- | --- | --- |
| Examples | addresses, phone numbers, e-mail addresses, online accounts | notes, e-mails, tasks, meetings, phone calls, any module's records |
| Exists on its own | No: it is part of the record that owns it | Yes: its own list, View page, permissions and History |
| Belongs to | exactly one owner | links to one record or many |
| Deleted | with its owner | never because a record it links to is deleted |
| Who can see it | whoever can see the owner | its own ownership scope |
| History | on the owner's History tab | on its own History tab |
| Edited | on the owner's form | on its own form |
| Stored | a table per collection type, keyed by owner | its own table, with a `Relation`, `Relations` or `Related` field pointing at the record |
| Shown on the owner | as a field: repeated blocks on the form, a list on the View page | as a tab listing the records |

The test for which one something is: would anyone open it on a page of its own, or want it
visible to different people than its owner? For a phone number the answer is no; for a meeting
it is yes.

`epesi_recordbrowser_links`, the `Related` field's table, is the related-record mechanism, and
collections don't use it. Its rows link two records that exist independently, and a link has no
fields of its own.

### Collections

#### What a collection is

A **collection type** is a named set of fields, declared with the same `Field` DSL as a
recordset, on a model of its own (`Epesi\Modules\RecordBrowser\Models\Address`):

```php
class Address extends CollectionItem
{
    protected $table = 'epesi_recordbrowser_addresses';

    public static function fields(): array
    {
        return [
            Field::text('address_1')->maxLength(64),
            Field::text('address_2')->maxLength(64),
            Field::text('city')->required()->maxLength(64)->inTable()->filterable(),
            Field::text('postal_code')->maxLength(64),
            AddressFields::country()->required()->filterable()->searchable(false),
            AddressFields::zone()->searchable(false),
        ];
    }

    /** The CommonData list its items' kinds come from. */
    public static function kinds(): string
    {
        return 'Address_Kinds';
    }

    /** One line, for the View page, History and pickers: "Main St 1, 00-001 Warsaw, Poland". */
    public function summary(): string { /* … */ }
}
```

A recordset declares a collection with one field:

```php
Field::collection('addresses', Address::class)->inTable(),
Field::collection('phones', PhoneNumber::class)->inTable(),
Field::collection('emails', EmailAddress::class)->inTable(),
```

This is `FieldType::Collection` (`'collection'`). It has no column on the owner's table
(`hasColumn()` is false) and holds several values (`isMultiple()` is true).

RecordBrowser ships four types, next to the `Email` and `Phone` field types, so any module's
recordset can use them without depending on Contacts:

| Type | Its fields | Kinds (a CommonData list) |
| --- | --- | --- |
| `Address` | Address 1, Address 2, City, Postal code, Country, Zone | Business, Home, Billing, Shipping, Other |
| `PhoneNumber` | Number, Messengers | Work, Mobile, Home, Fax, Other |
| `EmailAddress` | Address | Work, Private, Other |
| `OnlineAccount` | Handle | Website, LinkedIn, Telegram, Microsoft Teams, Facebook, X, Instagram, Other |

A module adds a type of its own in the same way (bank accounts, for example): a model extending
`CollectionItem`, a migration for its table and a morph alias.

#### Storage

Each collection type has one table, with every field as a real column. That is the same choice
custom fields make ([a real column, not a blob](#a-real-column-not-a-blob)), for the same
reasons: a city sorts and filters, an e-mail address is found through an index, and raw SQL
sees all of it.

Every collection table starts with the same columns:

| Column | Holds |
| --- | --- |
| `owner_type`, `owner_id` | The owning record, as a morph alias and id. No foreign key, as on the link table. |
| `field` | The name of the owner's collection field. A record can then hold two collections of one type (a company's Addresses and an administrator-added "Delivery points"), and a collection an administrator adds needs no DDL. |
| `kind` | A key into the type's CommonData list (Business, Home, Mobile …), nullable. |
| `position` | The item's place in the field's order. |
| `created_at`, `updated_at` | Nullable, so MySQL adds no `ON UPDATE CURRENT_TIMESTAMP`. |

The type's own columns follow, then an index over `owner_type, owner_id, field, position`. Name
that index explicitly: the generated name
(`epesi_recordbrowser_addresses_owner_type_owner_id_field_position_index`) runs past MySQL's
64-character limit.

The type-specific columns:

- **`EmailAddress`**: `value`, stored lower-cased and trimmed (as `MailAddress` does today), with
  a unique index: an address belongs to one record (see [the form](#the-form)).
- **`PhoneNumber`**: `value` as typed, plus `digits`, which holds the digits alone and is
  indexed. That lets "600100200" find "600 100 200", and an incoming number be matched to its
  owner. It also has `messengers`, which is JSON: the keys of the `Phone_Messengers` list whose
  apps reach this number.
- **`Address`**: `address_1`, `address_2`, `city`, `postal_code`, `country` (an ISO code) and
  `zone`.
- **`OnlineAccount`**: `value`, the handle. For this type the kind is the service and is
  required, since the service decides what the handle links to.

A phone number's kind and messengers answer different questions, so they are separate fields:

- **The kind** says which line a number is. Each number has exactly one.
- **The messengers** say which apps reach it. A number can have none, one or several.

A WhatsApp number is a phone number, so it is ticked on the number rather than entered again.
A mobile on WhatsApp and Signal is one item: kind Mobile, messengers WhatsApp and Signal.
An account identified by a username (a Telegram `@name`, a Teams account) has no number to tick
it on, so it is an online account instead.

An online account's handle is whatever identifies it with its service: a username (`@ann` or
`ann`), an address (Teams), or a full URL pasted from the browser. Its link comes from the
service:

| Service | Link |
| --- | --- |
| Website | the handle itself, with `https://` added when it has no scheme |
| LinkedIn | `https://www.linkedin.com/in/<handle>` |
| Telegram | `https://t.me/<handle>`, without the `@` |
| Microsoft Teams | `https://teams.microsoft.com/l/chat/0/0?users=<handle>` |
| Facebook, X, Instagram | `https://www.facebook.com/<handle>`, `https://x.com/<handle>`, `https://www.instagram.com/<handle>` |

A handle that is already a URL links to itself, whatever the service. These patterns live in
code, since CommonData holds only a key and a label. A service an administrator adds (Discord,
say) shows its handle as plain text unless the handle is a URL.

#### Kind and order

Each item has a kind, taken from the type's CommonData list, so an administrator can add
"Warehouse" or "Pager" without code. The kind replaces the column prefix: Contact's `home_`
address becomes an Address item of kind Home.

**The first item is the primary one**, and the first item of a kind is that kind's default: the
first Shipping address is where things ship to. There is no "primary" flag. The form's items are
reorderable, and dragging one to the top makes it primary. A flag would be a second fact that had
to agree with the order, and the order alone can't contradict itself.

#### The trait: `HasCollections`

- **One relation per collection field.** It is registered with `resolveRelationUsing()` from the
  recordset's fields. `$contact->emails` is a `morphMany` over the type's table, fixed to
  `field = 'emails'` and ordered by `position`, so `$contact->emails->first()?->value` is the
  primary address. Filament's dot notation (`emails.value`) works on it in search.
  `$contact->collection('emails')` is the same relation by the field's name.
- **Which fields.** `CollectionFields` reads them off the model's resource in any panel
  (`resolvedFields()`, the administrator's included), once per model per process, so importers
  and the setup wizard reach them without a page. It is forgotten with the custom-field
  registry.
- **One way to write.** `syncCollection($field, array $items)` updates items by id, creates the
  new ones, deletes those missing and renumbers `position` in the order given. Only an id of
  this field's own items is honoured, and only the item's values are fillable, never its owner.
  An item with nothing but a kind is left out. It also writes the History entry (see
  [History and Watchdog](#history-and-watchdog)) and touches the owner's `updated_at`. The form,
  the importers, the customer portal and the setup wizard all save through it.
- **Deleting.** Force-deleting the owner deletes its items. A soft delete keeps them, so Restore
  brings them back, as `HasRecordLinks` does with links.
- **Where it comes from.** `HasCustomFields` uses it, as it uses `HasRecordLinks`, so every
  recordset that takes custom fields can take collections.

#### The form

The form shows a `Repeater` of the type's fields plus Kind, one card per item, reorderable, with
"Add address" (or "Add phone number") beneath. It is wired the way the `Related` field's select
is: `dehydrated(false)`, filled from the relation when the form loads, and saved by
`saveRelationshipsUsing()` calling `syncCollection()` after the record is saved. Don't use
Filament's `Repeater::relationship()`: it saves the rows itself, and so would bypass the History
entry and the item events Mail listens to. Each card keeps its item's id in a hidden field. The
save hands `syncCollection()` the cards' raw state, which the form has validated: a card's own
`getState()` drops a key two of its inputs share, such as Zone's select and text box.

A field inside a repeater item reads its siblings relative to the item, so Zone's
`$get('country')` sees its own address's country. `AddressFields` has lost its prefix
parameter, and Contact's two address blocks are one field.

Validation:

- An item is checked against its type's fields: an Address needs City and Country. Not
  Address 1: many an address brought over from legacy Epesi is a city and a postal code alone,
  and requiring a street would block every edit of such a record.
- An e-mail address must be valid, and unique across contacts and companies: an address belongs
  to one record, so mail from it files to one record. The unique index on the e-mail table
  enforces it, so an importer or Mail can't add a duplicate either. It spans every record using
  the type, so a module's recordset with e-mail addresses joins the same rule. The form's message
  names the record that already has the address. That record may be a deleted one, which keeps
  its addresses until it is deleted for good, so the message says so, and the user can restore
  that record or delete it for good.
- The collection field itself can be `->required()` (at least one item) or `->maxItems(1)` (a
  meeting's single location).

#### View page, list, search and filters

- **View page.** One line per item: the kind as a badge, then the summary, then any
  administrator's field that holds something ("Gate code: 4412"). An e-mail address links
  through `RecordExtensions::emailLink()`, as the `Email` type does. A phone number shows an icon
  per messenger, and each icon opens the app on that number (`https://wa.me/48600100200`,
  `https://signal.me/#p/+48600100200`, `viber://chat?number=%2B48600100200`,
  `https://t.me/+48600100200`). The link patterns for the four messengers the list starts with
  live in code, since CommonData holds only a key and a label. A messenger an administrator adds
  shows as a badge without a link.
- **List.** `->inTable()` shows the primary item: the e-mail address, the phone number, or the
  City of the first address (the field its type marks `inTable()`, which also names the column).
  `->columnsForKinds(['work',
  'mobile'])` shows the first item of each named kind as a column of its own instead, which keeps
  the Work phone and Mobile phone columns the Contacts list has now. A page's items load in one
  query per collection field, as the Related field's links do.
- **Sorting** uses a subquery on the first item (`sortable(query:)`). **Searching** matches any
  item, so a contact is found by their second e-mail address.
- **Global search.** `getGloballySearchableAttributes()` adds each collection's searchable fields
  as dot paths: `emails.value`, `phones.digits`, `addresses.city`.
- **Filters.** `->filterable()` gives one filter of several inputs: has any (yes or no), which
  kinds, and the type's own filterable fields, such as Country (a choice) and City (contains) for
  addresses. Each input names its field, since Filament gives such a filter no heading.

#### History and Watchdog

`LogsActivity` sees only the owner's own columns, so `syncCollection()` writes the History entry
itself. A save that changes a collection writes one `updated` entry on the owner, holding the old
and new items as one-line summaries (`CollectionItem::historyLine()`: kind, summary and any
administrator's field). The History tab shows the items taken off struck through and those
added after them, "Addresses: − Business: Main St 1, Warsaw + Home: Long St 2, Kraków"; a change
of order alone says "Reordered". A save that leaves the collection unchanged logs nothing.
Watchdog notifies on every activity row created, so anyone watching the contact hears about a
new phone number with no extra code.

A save that changes the record's own fields and a collection makes one entry, not two. Filament
saves the collections after creating the record, but before updating it, so whichever comes
second joins the entry the first made (`History\SaveActivity`): a collection change joins the
record's `created` or `updated` entry, and the record's own changes join a collection's entry
(`History\MergeIntoCollectionEntry`, a LogsActivity pipe). It goes by the model instance being
saved, not the record's id, so the next save starts an entry of its own.

History already recorded against the old columns (`work_phone`, `home_city`) stays readable. A
key whose field no longer exists falls back to its headline.

#### Visibility

An item has no visibility of its own. It is shown only through its owner, which the ownership
scope has already filtered, and saving it counts as updating the owner (the owner's `update`
policy). Code that finds an owner through an item, such as Mail's matcher or the address book,
decides visibility as it does today.

#### Custom fields and administrators

The item models use `HasCustomFields` and are in the morph map, so Administration → Fields offers
Address, Phone number, E-mail address and Online account as recordsets. A field added there ("Gate code") becomes
a column on the collection's table, and every address everywhere shows it: on contacts, on
companies, and on any module's recordset that uses the type.

An administrator can also add a collection to a recordset: `FieldType::Collection` is offered in
Administration → Fields, with a **Collection** setting that picks the type. It needs no DDL,
because items are keyed by `field`.

Defining a new collection type from the browser isn't part of this design. It needs a table
created at run time, the same machinery an administrator-created recordset would need.

#### What moves where

| Today | As a collection item |
| --- | --- |
| Contact `email` | E-mail address, kind Work, first |
| Mail's `epesi_mail_addresses` (for contacts and companies) | E-mail addresses of kind Other, after the first |
| Contact `work_phone`, `mobile_phone`, `home_phone`, `fax` | Phone numbers of kinds Work, Mobile, Home and Fax |
| Company `phone`, `fax` | Phone numbers of kinds Work and Fax |
| Contact and company `web_address` | Online account of kind Website |
| Contact's address and `home_` address | Addresses of kinds Business and Home (moved in step 5) |
| Company's address | Address of kind Business (moved in step 5) |

An address moved only if it had a street, a city or a postal code. Legacy Epesi filled Country and
Zone in on every new record from the user's regional settings, so a typical legacy install has
most of its contacts' home addresses holding nothing else, and moving those would give each such
contact an "address" that is just a country.

Consumers that change with them:

- **Mail.**
  - `ContactMatcher` becomes one query on the e-mail table, and finds at most one record per
    address.
  - The "E-mail addresses" tab and `MailAddress` go, because the collection is edited on the
    contact's own form.
  - `relinkExisting()` runs when an e-mail item is saved. Mail listens to the item model's
    `saved` event, so RecordBrowser knows nothing about Mail.
  - `Records::emailsOf()`, the compose page's suggestions, `MailArchiver` and
    `LinkRecordAction`'s search all read the collection.
- **Roundcube address book.** One SELECT joining the e-mail table to contacts and companies,
  instead of four.
- **Users.** A user made from a contact takes the contact's primary e-mail address.
- **Customer portal.** `MyContact` declares the same collection fields, so a customer edits their
  own numbers and addresses.
- **Phone Calls.** The Phone Number field suggests the contact's numbers ("Mobile: +48 600 100
  200"). It still stores the number as text, so a call keeps the number it was made to after the
  contact's number changes. The importer maps legacy's phone choice onto kinds.
- **Setup wizard.** The "Your company" step writes its address through `syncCollection()`.
- **Company's Contacts tab** (until step 8 replaces it), the demo seeder and the legacy
  importers, which map the columns above plus legacy's `rc_multiple_emails`.

### Related records

#### What exists

A record's View page lists "records linking here" through several mechanisms, each wired
differently:

- **Tasks, Phone Calls and Meetings tabs.** Hand-written relation managers on Contacts and
  Companies, over `task_customer`, `meeting_customer` and `phone_calls.contact_id`/`company_id`.
  The Contacts tab on a company is one more.
- **Notes.** `epesi_attachment_links`, plus a tab registered for listed record types with
  `RecordExtensions::addon()` (`Attachments::enableFor()` adds more types).
- **E-mails.** `epesi_mail_links`, and Mail's own tab.
- **Link to any record.** `epesi_recordbrowser_links`. It shows only on the linking record, as
  badges; the linked record has no tab listing what links to it.

#### A tab for every recordset that links here

Whether records of recordset A can point at records of recordset B is already declared. It is
any field in A's `fields()` that is a `Relation` or `Relations` with B as its target, or a
`Related` whose recordsets include B. The engine reads that, and gives B's View page one tab per
such recordset ("Tasks", "Meetings", "Invoices"). That needs no code in B's module, and none in
A's beyond the field it already declares.

- **Which records.** The tab lists A's records where any of those fields names this record,
  through A's own query, so the ownership scope applies:
  - `Relation`: `where('company_id', $id)`;
  - `Relations`: `whereHas($relationship, fn ($q) => $q->whereKey($id))`;
  - `Related`: `whereHas('recordLinks', …)` on `field` and the target, which the link table's
    target index covers.

  An administrator's link field counts like a module's, since the registry reads
  `resolvedFields()`. Its relationship is the one `HasCustomFields` registers (`cf12` for column
  `cf_12`, `CustomFieldRegistry::relationshipName()`): a `belongsTo` for one record, a
  `morphToMany` over the link table for many. So `where('cf_12', $id)` or
  `whereHas('cf12', …)` answer it with no special case.
- **One class.** A single relation manager, configured per recordset through Filament's
  `RelationManager::make([...])` (a `RelationManagerConfiguration`). It is registered through
  `RecordExtensions`, so resources don't list it in `addons()`.
- **Columns, search, sorting and filters.** The tab's table comes from the same builder as A's
  list page (`RecordsetResource::table()`, driven by A's `resolvedFields()`). So the tab has A's
  columns, search, sorting and filters, custom fields included, and no code of its own. Two
  adjustments:
  - A linking field that holds **one** record (`Relation`, such as Phone Calls' `company_id`)
    loses its column and filter. Every row in a company's Phone Calls tab has that company in
    it, so the column would repeat one name and the filter would match every row. A linking
    field that holds **several** (`Relations`, `Related`) keeps both, because they show the
    *other* records a row names ("Acme's tasks that also name Beta Ltd").
  - When two or more of A's fields point at this recordset, the tab gains a **Linked as**
    filter. It lists those fields (Customers, Employees, Related) and narrows the tab to rows
    linked through the chosen ones.
- **Actions.**
  - **View** opens the record's page.
  - **Edit** opens the record's own Edit page, and shows only when the user may edit it.
  - **New** opens A's Create page with the link already filled in (`?link=contact:12`, which the
    engine's `CreateRecord` reads into the fields pointing at this recordset).
  - There is no Delete and no bulk action: a record is deleted from its own page, not from a
    page it happens to link to. There are no inline modal forms either.
- **Opting out.** Every such field adds to a tab by default. A field whose tab would be noise
  says `->listedOnTarget(false)`.
- **Order.** `ViewRecord::getAllRelationManagers()` puts tabs registered with `first: true`
  (Notes) first, then the resource's own addons ending with History, then other modules' tabs.
  The linking recordsets' tabs join that last group, ordered by label.

This replaces the seven hand-written relation managers: Tasks, Phone Calls and Meetings on
Contacts and on Companies, and Contacts on Companies. One behaviour changes for users. A
contact's Tasks tab lists every task that names the contact, whether as customer, as employee or
through Related, not only those naming them as customer.

It also means a recordset a module adds later reaches the contact without the Contacts module
knowing about it. Declare `Field::relation('contact_id', Contact::class)`, or a `Related` field,
and the contact's View page gets the tab.

#### Notes and e-mails

Notes and archived e-mail keep their own link tables for now. Both have the shared table's shape
minus `field`: a parent record, a morph pair and timestamps. Step 9 moves them onto
`epesi_recordbrowser_links`: a note's "Attached to" becomes `Field::related('attached_to')`, and
a mail's links a `Related` field. Their tabs then come from the same mechanism, and "what links
to this record" becomes one indexed query. Notes stays the first tab, and Mail keeps linking by
address on its own. Do it after step 8, not before, since it's step 8 that makes the tabs
generic.

### The minimal record

After steps 5–8, a contact's own columns are the facts it has exactly one of: first and last
name, title, company, groups, permission, memo and the linked login. Everything else comes in one
of two forms:

- a collection on its form: e-mail addresses, phone numbers (with the messengers on each),
  addresses, and online accounts, its website included;
- a related record in a tab: notes, e-mails, tasks, phone calls, meetings, and whatever a module
  adds.

A company is the same, with its name, short name, groups, tax ID and permission.

A new contact can then be created with just a name, and gain addresses, numbers and linked
records as they come, none of which needs a column added first.

### Designs rejected

- **More fixed columns** (a `shipping_` prefix next to `home_`). Every slot is a migration, and
  the number is still fixed.
- **A separate recordset for the extras, shown in a tab.** That means two places to look for one
  kind of data, and every consumer has to combine them. This is what `epesi_mail_addresses` is
  today.
- **A JSON column of items** (a `Repeater` saving to JSON). MariaDB 10.4 has no JSON type to
  index, so finding a contact by e-mail address scans every row. It also rules out custom fields
  on an item, and sorting by city needs JSON paths. These are the reasons custom fields are
  columns.
- **Items as independent recordsets linked through `epesi_recordbrowser_links`.** An address
  would get its own list, View page, permissions and search results. It could be linked from two
  owners, and would outlive its contact.
- **One shared table of key/value pairs for every type.** That is EAV, which custom fields already
  rejected.

## Implementation plan

Eleven steps, in order. Each one ships on its own and leaves the engine working.

| Step | What | Size | Needs | Status |
| --- | --- | --- | --- | --- |
| 1 | Minute interval and autonumber pad character | Hours | — | Done |
| 2 | `file` | 1–2 days | — | Done, bar the import mapping |
| 3 | "Link to any record" | 3–5 days | — | Done |
| 4 | Record and shared-list selects in Administration → Fields | 1–2 days | Step 3's link table | Done |
| 5 | Collections, starting with addresses | 4–6 days | — | Done |
| 6 | Phone numbers, with messengers, and online accounts | 2–3 days | Step 5 | |
| 7 | E-mail addresses as a collection | 3–4 days | Step 5 | |
| 8 | A tab for every recordset that links here | 3–5 days | — | |
| 9 | Notes and Mail on the shared link table | 2–3 days | Step 8 | |
| 10 | `->calculated()` | 1 day | — | |
| 11 | `currency` | 3–4 days | A new Currencies module | |

Why this order:

- Step 1 is cheap.
- `file` comes early because its store and a working example (Notes) already exist.
- "Link to any record" comes before the other large items because it was the only gap that lost
  data: the Tasks, Meetings and Phone Calls importers read `f_customers` but not `f_related`, so a
  legacy install's Related links were dropped on import.
- Step 4 reuses step 3's link table for many-record fields, and completes Administration →
  Fields.
- Collections come before `calculated` and `currency` because every contact and company needs
  them now, while no recordset needs the other two yet.
- Addresses go first among collections. They have the most fields and the chained select, and the
  fewest consumers outside Contacts and Companies, so they prove the engine at the least risk.
  E-mail addresses go last: they have the most consumers (Mail, Roundcube, Users, the portal),
  and they absorb Mail's table.
- Step 8 doesn't depend on steps 5–7, and can move ahead of them if the tabs are wanted sooner.
  Step 9 needs step 8.
- `calculated` and `currency` come last because no recordset needs them yet. The first modules
  that handle money will.

### What every new type touches

A type isn't done until all of these handle it:

- a `FieldType` case, with its `label()`, and a `Field` static constructor;
- the four `build*` arms (form, view, list column, filter), plus `cast()` and
  `formatLoggedValue()`/`formatLoggedChange()` for History;
- `CustomFieldSchema::define()`, or a skip in `add()`/`change()`/`sync()` for a type that has no
  column;
- `recordset:check`;
- `isAdministratorDefinable()`, and the type's settings in `CustomFieldForm`;
- the legacy import mapping;
- a feature test under `tests/Feature/Modules/`, next to `CustomFieldsTest`;
- every new string in `lang/pl.json` (`TranslationsTest` fails otherwise);
- this file's [type table](#type-by-type), `FieldType`'s docblock and the list under
  [Limits and available types](#limits-and-available-types).

### Steps 1 and 2: done

The minute interval and the pad character are described under [Type by type](#type-by-type),
and the file field under [`file`](#file), with what is left of it: the import mapping, and one
upload setup shared with Notes.

### Step 3: done

Described under [Selects across several recordsets](#selects-across-several-recordsets). Two
things differ from the plan: the table is `epesi_recordbrowser_links`, named like RecordBrowser's
other tables, and a field narrows what it offers by recordset only, not by record within one.

### Step 4: done

Described under [Limits and available types](#limits-and-available-types). What differs from
the plan: the target setting is labelled **Links to** and offers `LinkableRecordsets` (the main
panel's recordsets with a View page); the shared list is picked from Common Data's arrays (the
top-level lists and any entry with entries of its own) rather than `CommonData::tree()`; a link
field's relationship is named `cf12`, not after its column `cf_12`, so it never shadows the
column; and `isAdministratorDefinable()` now withholds nothing.

### Step 5: done

Described under [Collections](#collections). The pieces:

| File | What it is |
| --- | --- |
| `modules/Epesi/RecordBrowser/src/Models/CollectionItem.php` | The base model of a collection type: `fields()`, `kinds()`, `summary()`, `historyLine()`, the owner |
| `modules/Epesi/RecordBrowser/src/Models/Address.php` | The Address type, alias `address` |
| `modules/Epesi/RecordBrowser/src/Models/Concerns/HasCollections.php` | The relations, `syncCollection()`, the History entry, deleting items with the owner |
| `modules/Epesi/RecordBrowser/src/Recordset/CollectionFields.php` | Which collection fields a model has, read off its resource |
| `modules/Epesi/RecordBrowser/src/History/SaveActivity.php`, `MergeIntoCollectionEntry.php` | One History entry for a save that changes a collection and the record's own fields |
| `modules/Epesi/RecordBrowser/database/migrations/2026_09_28_130000_…`, `…130100_…` | The addresses table and the `Address_Kinds` list |
| `modules/Epesi/CRM/Companies/database/migrations/2026_09_28_140000_…`, `modules/Epesi/CRM/Contacts/database/migrations/2026_09_28_140100_…` | Moving the address columns into items |
| `tests/Feature/Modules/CollectionsTest.php` | The tests |

What differs from the plan:

- **Validation.** An address needs City and Country, not Address 1: many an address brought over
  from legacy Epesi has no street, and requiring one would block every edit of such a record.
- **What the migration moves.** Only an address with a street, a city or a postal code, not one
  holding just the Country and Zone legacy filled in by default. The importers apply the same
  rule (`Address::isAddress()`). The two migrations are the Contacts and Companies modules' own,
  so RecordBrowser's addresses table exists before they run, on a fresh install and on
  `epesi:update` alike.
- **History.** A save that changes the record's own fields and a collection makes one entry
  (`SaveActivity`, `MergeIntoCollectionEntry`), rather than one for each.
- **The customer portal.** `MyContact` takes the addresses collection now rather than in step
  6, since the address columns it edited are gone.
- **What an address holds.** Administration → Fields offers an item's recordset only the types
  an item can hold (`FieldType::fitsCollectionItem()`).
- **Home Phone** moved up beside the contact's other phones: its Home Address section is gone.

### Step 6: phone numbers, with messengers, and online accounts

- **The `PhoneNumber` type.** Its table (`value`, `digits`, `messengers`), its morph alias,
  `phone_number`, `Phone_Kinds` (Work, Mobile, Home, Fax, Other), and `Phone_Messengers`
  (WhatsApp, Signal, Viber, Telegram).
- **Messengers.** A multiple select on each number, and on the View page an icon per messenger
  that opens the app on the number. The apps' links need the international prefix, so the form
  warns when a messenger is ticked on a number that doesn't start with `+`. The list gets a
  Messengers filter ("on WhatsApp").
- **Contacts and Companies.** A contact's four phone columns and a company's two become items. The
  Contacts list keeps its Work and Mobile columns through `->columnsForKinds()`.
- **Phone Calls.** The Phone Number field suggests the contact's numbers, and the importer maps
  legacy's phone choice onto kinds.
- **The `OnlineAccount` type.** Its table (`value`), its morph alias, `online_account`, and
  `Online_Account_Kinds` (Website, LinkedIn, Telegram, Microsoft Teams, Facebook, X, Instagram,
  Other), with the kind required. The View page links each handle by its service. The list gets
  a Service filter ("has LinkedIn"), and global search finds a handle.
- **Websites.** Contacts' and companies' `web_address` becomes an online account of kind Website,
  and a migration then drops the column. The legacy importers and the demo seeder map
  `f_web_address` the same way, and `YourCompanyStep` writes one.
- **Customer portal.** `MyContact` declares both collections.
- **Tests:**
  - searching by digits, and the list's kind columns;
  - the Phone Calls suggestions;
  - a messenger's icon links to the number, and the warning on a number without `+`;
  - the Messengers filter;
  - each service's link, including a pasted URL and an administrator's service;
  - the migration moves a web address;
  - the portal saves a number and an account.

### Step 7: e-mail addresses as a collection

- **The `EmailAddress` type.** Its table (`value`, lower-cased, with a unique index), its morph
  alias, `email_address`, and `Email_Kinds` (Work, Private, Other).
- **Contacts and Companies.** A contact's and a company's `email` becomes their first item, and
  Mail's `epesi_mail_addresses` rows follow it. The migration moves them in that order:
  contacts' addresses, then companies', then Mail's. An address already taken by an earlier
  record isn't moved; the migration lists it with both records for an administrator to settle.
  Mail then drops the table, `MailAddress` and the "E-mail addresses" tab.
- **Consumers.** `ContactMatcher`, `relinkExisting()` on the item's `saved` event,
  `Records::emailsOf()`, the compose page's suggestions, `MailArchiver`, `LinkRecordAction`, the
  Roundcube address book, `UserForm` and `CreateUser`, and the portal.
- **Import.** The contacts and companies importers, and legacy's `rc_multiple_emails`. An
  address already on another record is skipped and reported in the import summary.
- **Tests:**
  - mail from a contact's second address is linked to the contact;
  - adding an address links the mail already archived from it;
  - the address book lists every address;
  - a user made from a contact takes its primary address;
  - a contact's address can't be added to a company or to a second contact, and the message
    names the record that has it;
  - the migration moves a shared address once and lists it.

### Step 8: a tab for every recordset that links here

- **What links where.** A registry that reads every main-panel `RecordsetResource`'s
  `resolvedFields()` for `Relation`, `Relations` and `Related` fields, and maps each target alias
  to the recordsets and fields pointing at it. It is built once per request, since custom fields
  can add to it.
- **The tab.** One relation manager, configured per linking recordset, registered through
  `RecordExtensions` for every target. `->listedOnTarget(false)` opts a field out.
- **Its table.** Built by the linking recordset's own `table()`. Its row actions become View
  and Edit, and both open the record's own pages. Its bulk actions go. A single-record linking
  field's column and filter are dropped, and a **Linked as** filter is added when several fields
  point here.
- **Create from here.** `CreateRecord` fills a link from `?link=alias:id`.
- **Clean-up.** Delete the seven hand-written relation managers, and the inverse relations
  (`tasksAsCustomer` and the like) that nothing else uses.
- **Tests:**
  - a contact's tabs list tasks through each of the three field kinds;
  - a private task stays hidden from another employee;
  - the tab offers the recordset's own filters, including a custom field's, and they narrow it;
  - Linked as narrows a contact's Tasks tab to the tasks naming them as customer;
  - a company's Phone Calls tab has no Company column or filter;
  - Edit shows only to a user who may edit the record, and no row offers Delete;
  - New fills the link in;
  - a test module's recordset with a `Relation` to Contact gets a tab with no code in Contacts.

### Step 9: Notes and Mail on the shared link table

- A migration copies `epesi_attachment_links` into `epesi_recordbrowser_links`
  (`source_type = 'attachment'`, `field = 'attached_to'`), and `epesi_mail_links` likewise
  (`field = 'related'`). It then drops both tables.
- A note's "Attached to" and a mail's links become `Related` fields, and `Mail::linkTo()` writes
  a `RecordLink`.
- Their tabs come from step 8. Notes stays first. `Attachments::enableFor()` goes: the record
  types a note can attach to are its `Related` field's recordsets.
- **Tests:** the existing Notes and Mail tests pass unchanged on the new storage.

### Step 10: `->calculated()`

Make it a modifier on `Field`, not a `FieldType` case, as `page_split` became `->section()` and
`hidden` became `->onlyInTable()`. The field's type still decides how the value looks:

```php
Field::decimal('net_total')->calculated(fn (Invoice $record) => $record->items->sum('net'))
```

- **Where it shows.** Not on the form. The View page and the list take their state from the
  closure.
- **Sort, search and filter.** Off unless the module gives a query: add `->sortUsing()` and
  `->searchUsing()`, which feed Filament's `sortable(query:)` and `searchable(query:)`, and use
  the existing `filterUsing()`. It's left out of global search.
- **History.** Nothing is logged, since nothing is stored.
- **Checks.** `recordset:check` skips the column check.
- **Limits.**
  - It is allowed only on scalar types. On `Relation`, `Relations`, `Related`, `File` or
    `Collection` it throws a `LogicException`.
  - It is never offered in Administration → Fields. Legacy's administrator half means typing PHP
    into the browser, which the port doesn't reproduce.
- **A calculated value that is stored** (legacy's `calculated` with a column type in `param`)
  needs no engine work. It's an ordinary field with `inForm(false)`, which the model fills in its
  own `saving` event. It stays sortable and filterable because it has a column.
- **Tests:**
  - the value shows on View and in the list;
  - the list doesn't sort or search it without a query;
  - `recordset:check` passes.

### Step 11: `currency`

- **Currencies module.** A new core module, `Epesi/Currencies`, alongside CommonData, with
  Administration → Currencies. Its table, `currencies`, has:
  - `code`, a unique `char(3)` ISO 4217 code;
  - `decimals`;
  - `active`;
  - `is_default`.

  Values reference the code, not a numeric id as legacy's do: a code reads the same on every
  install and in raw SQL.
- **Formatting.** It comes from `intl` in the user's locale, through Filament's `->money()`, not
  from legacy's per-currency `symbol`, `decimal_sign`, `thousand_sign` and `pos_before`. That
  makes `intl` a requirement, so the server check in `epesi:install` and the `/setup` wizard must
  test for it.
- **Default currency.** A `currency` column on `epesi_regional_settings`: the user's row, then
  the system row, then the table's `is_default`. That mirrors legacy's per-user
  `default_currency` setting.
- **Storage.** Two columns, `<name>` (`decimal(15, decimals)`) and `<name>_currency`
  (`char(3)`). The amount then sorts, filters by range and sums in SQL, which legacy's encoded
  string can't. For an administrator-added field that means `cf_N` and `cf_N_currency`:
  - `CustomFieldSchema`'s `add()`, `change()`, `drop()` and `sync()` manage the second column;
  - `columnsFor()` returns it, so it's fillable.

  `recordset:check` also checks for the second column on a module's own Currency field.
- **Type.** `FieldType::Currency` (`'currency'`), and `Field::currency($name)`.
- **Form.** A `FusedGroup` of the amount (numeric) and the currency (the active ones, defaulting
  to the user's). Each is bound to its own column.
- **View and list.** `->money()` with the row's currency. The list sorts by amount alone; across
  mixed currencies that means by number only, which the column's tooltip should say.
- **Filter.** An amount range plus a currency select.
- **History.** The currency field's `formatLoggedChange()` reads both keys ("Amount: 10.00 PLN →
  12.00 EUR"). `HistoryRelationManager` skips a key that belongs to another field as its second
  column.
- **Import.** Legacy's `utils_currency` rows become `currencies` (active, default). A legacy
  value `amount__id` is split into the amount and the code for that id.
- **Out of scope.** Exchange rates, and totals across currencies.
- **Tests:**
  - the form saves both columns;
  - the value is formatted per currency;
  - a custom Currency field adds and drops both columns;
  - History shows one line;
  - the import splits the values.

### Not in this plan

- **Registry.** Whether to turn `FieldType` into a registry modules can extend (see
  [The registry gap](#the-registry-gap)). The new cases keep that option open.
- **History of link-table changes.** Logging `Relations` and `Related` changes in History.
- **Callbacks.** Legacy's PHP display and form callbacks typed in the browser.
- **Collection types defined in the browser.** They need tables created at run time (see
  [Custom fields and administrators](#custom-fields-and-administrators)).
- **Choosing tabs per recordset.** An administrator setting for which linking recordsets show a
  tab on a record, beyond a field's own `->listedOnTarget(false)`.
- **More collection types.** Bank accounts, for example: each is a `CollectionItem` and a table
  once step 5 exists.
