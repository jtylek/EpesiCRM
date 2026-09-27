# Custom fields: administrator-added fields on any recordset

The port of Epesi's per-module "Custom Fields" (`Utils_RecordBrowserCommon::new_record_field()`,
Administration → Records Browser). A `super_admin` adds a field to an existing recordset from
the browser — no code, no deploy, no migration to write by hand — and it behaves exactly like a
field the module shipped with: it appears on the form, the view, the list, the filters and the
column chooser, and it is covered by the record's History tab.

## What makes this safe: a real column, not a blob

An administrator's field is a genuine column on the model's own table (`companies.cf_1`, named
after the `custom_fields` row's own id), added and dropped with real DDL
(`Epesi\Modules\RecordBrowser\CustomFields\CustomFieldSchema`). That was a deliberate choice
over a single JSON column or an EAV side table — see the class docblock — because it means
sorting, filtering, export and any raw SQL/reporting see it exactly like a shipped column, with
no per-consumer special-casing. `custom_fields` is the source of truth; the schema is always
derived from it, which is what makes "Repair columns" below possible.

A model opts in with one trait, `Epesi\Modules\RecordBrowser\Models\Concerns\HasCustomFields`
— that's the whole integration. It makes the field mass-assignable (so a Filament form saves it
with no extra code) and, because `spatie/laravel-activitylog`'s `logFillable()` reads the same
fillable list, its edits land in History automatically. Today the five CRM recordsets
(Companies, Contacts, Tasks, Meetings, PhoneCalls) all use it, and `make:epesi-recordset`
scaffolds it into a new recordset by default, so a module doesn't have to opt in by hand.

## Where administrators use it: Administration → Fields

`Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\CustomFieldResource` — gated to
`super_admin` like the rest of the Administration panel. It lists every custom field across
every recordset, grouped by recordset by default, with a **Recordset** filter and column to
narrow that down.

### Adding a field

**New field** opens a form with two sections:

- **Recordset** and **Type** — pick once, locked after the field is created (moving a field to
  another table, or changing its type, would leave its data behind or unable to survive the
  conversion; see "Changing a field" below).
- **Label** — what users see; typing it fills in **Name** (a lowercase slug) automatically,
  until the admin edits Name by hand. Name is what code, imports and history keys use, so it
  can't be renamed after the fact the way Label can.
- Type-specific parameters appear once a type is picked: **Maximum length** for text-like types,
  **Decimal places** for Decimal, **Choices** (stored value → shown label) for Select and
  Multiple select.
- **Where it appears**: **Group** (a heading that gathers fields sharing the same group into
  their own card — Epesi's `page_split`), **Position** (lower numbers first), and toggles for
  **On the add/edit form**, **On the record view**, **As a list column** (off still offers it in
  the column chooser), **As a list filter**, **Required**, **Included in exports**, and
  **Active**.

Saving runs the `ALTER TABLE` immediately. If it fails (e.g. the per-recordset field limit is
hit), the definition is not left behind half-built — nothing is saved.

**Included in exports** is stored but not wired to anything yet — the app has no export feature
to consult it. It's there for when one is built; toggling it today has no visible effect.

### Editing a field

Recordset and Type stay locked on the edit form. Everything else — Label, Name, Group,
Position, Required, the four `show_in_*`/filterable toggles, Active, Help text — can be changed
freely; none of it touches the database column.

Changing the *type* of an existing field is not offered from this screen at all. The model layer
(`CustomFieldSchema::assertChangeAllowed()`) would allow some widening conversions if a type
change ever reached it programmatically (Text → Long text, Integer → Decimal, and similar), and
refuses anything that would lose data (Date → Integer, Long text → a short Text). From the admin
screen, though, the only way to change a field's type is to add a new field and migrate the data
by hand.

### Removing a field

One destructive action, the **Drop column** icon (a red trash icon in the row, or a labelled
button on the View page): it drops the column *and every value stored in it, on every record*,
then deletes the definition. It asks for confirmation and names the exact `table.column` it is
about to remove. There is no undo.

To hide a field without losing data, turn its **Active** toggle off instead — reversible, and
the field's data and column stay exactly as they were. (An earlier version of this screen had a
second action, "Remove definition", that deleted only the `custom_fields` row and left an
orphaned column with its data behind, recoverable only through SQL. It was removed as a UI
option because it didn't do enough to be a distinct action, and it invited a confused state
that "Active off" already covers cleanly and Drop column already covers destructively.)

### Repair columns

The wrench button in the list's header. It walks every field definition and, for any whose
target table exists but is missing that field's column, adds the column back
(`CustomFieldSchema::sync()`). Existing columns and their data are never touched — it only fills
in what's missing.

Two situations call for it:

- A column was lost some other way than Drop column (a manual `ALTER TABLE`, a restore that
  didn't include the schema change, etc.) while its `custom_fields` row survived.
- Field definitions were copied into a fresh install's `custom_fields` table directly (rather
  than through the admin form) — this is how their columns actually get created there.

It reports what it created, or that every field already had its column.

## Limits and available types

A recordset can carry at most 64 administrator-added fields
(`CustomFieldSchema::MAX_FIELDS_PER_MODEL`) — well under InnoDB's own row-width limits, but high
enough that hitting it is a modelling conversation, not a real constraint.

Types offered from the GUI (`FieldType::isAdministratorDefinable()`): Text, Long text, Integer,
Decimal, Checkbox, Date, Date and time, Time, Select, Multiple select, E-mail, Web address,
Phone number. Two kinds are deliberately withheld from this screen and only available to a
module declaring a field in code — see
[filament-fields.md](filament-fields.md#needs-building) for why:

- **Link to one/many records** (`Relation`/`Relations`) — needs a target-recordset picker and a
  foreign key that survives the target being uninstalled.
- **Shared list** (`CommonData`) — meaningless without an array of choices to point at, which
  this form has no picker for yet.

## Files

| File | What it is |
|---|---|
| `modules/Epesi/RecordBrowser/database/migrations/2026_09_07_110000_create_epesi_recordbrowser_custom_fields_table.php` | The `custom_fields` table |
| `modules/Epesi/RecordBrowser/src/Models/CustomField.php` | One field definition; its model events run the DDL on create/update, and refresh the registry on save/delete |
| `modules/Epesi/RecordBrowser/src/CustomFields/CustomFieldSchema.php` | The DDL: add, change, drop, sync, and the widening-conversion and per-recordset-limit rules |
| `modules/Epesi/RecordBrowser/src/CustomFields/CustomFieldRegistry.php` | Which recordsets can carry custom fields (derived from the morph map), and the active definitions, cached to `bootstrap/cache/epesi-custom-fields.php` |
| `modules/Epesi/RecordBrowser/src/Models/Concerns/HasCustomFields.php` | The one-line opt-in trait a model adds |
| `modules/Epesi/RecordBrowser/src/Filament/Resources/CustomFields/` | Administration → Fields: the resource, form, infolist, table and the drop-column action |
| `modules/Epesi/RecordBrowser/src/Console/CustomFieldsSyncCommand.php` (`customfields:sync`) | The command-line form of "Repair columns" |

## Related docs

- [filament-fields.md](filament-fields.md) — the `FieldType` enum and the `Field` DSL a custom
  field compiles down to, and legacy Epesi's field-type parity.
- [conventions.md](conventions.md#custom-fields) — the one-paragraph version, for a resource
  author wondering what `HasCustomFields` buys them.
