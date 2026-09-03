# RecordBrowser recipes

[Dev-Tutorial.md](Dev-Tutorial.md) §11 covers declaring a recordset. This file is what you
need once one exists and you are changing it. Part 1 is task recipes; Part 2 is the
callback rules that decide whether those recipes work.

---

# Part 1 — recipes

## Adding a field to a module you are still building, without losing your test data

[Dev-Tutorial.md](Dev-Tutorial.md) §8 says that while a module is pre-launch you should not
write a patch for every schema change — just edit `install()`/`uninstall()` and reinstall,
either from Administration → *Modules Administration & Store* or with
`console.php module:uninstall` + `module:install`.

That has an unstated assumption. **Reinstalling drops and recreates the recordset**, so it
only works cleanly before the module has data worth keeping. As soon as someone has clicked
through the UI and created a few genuine records, reinstalling silently deletes them — and
a developer manually testing a feature generates exactly that kind of small but real
dataset.

The fix is not to start writing dated `patches/` files this early. It is to use
RecordBrowser's own **additive, non-destructive** APIs against the live dev DB, *and* add
the equivalent field to `install()`'s own arrays so a genuinely fresh install ends up with
the same final shape.

```php
// Add a field to an already-installed recordset without touching existing rows —
// same array shape as install_new_recordset()'s own $fields entries.
Utils_RecordBrowserCommon::new_record_field('mymodule_folder', array(
    'name'    => _M('Description'),
    'type'    => 'text',
    'param'   => '255',
    'visible' => true,
));

// Attach or replace a display / QuickForm-rendering callback on an existing field.
// $field is the field's declared display NAME ('Name'), not its internal id ('name') —
// confirm by inspecting the installed table's own <tab>_callback rows rather than guessing.
Utils_RecordBrowserCommon::set_display_callback('mymodule_folder', 'Name',
    array('MyModuleCommon', 'display_folder_name'));
Utils_RecordBrowserCommon::set_QFfield_callback(/* same shape */);

// Register (or re-register) an addon tab — idempotent, it calls delete_addon() internally.
Utils_RecordBrowserCommon::new_addon('mymodule_folder', 'MyModule',
    'folder_items_addon', _M('Items'));
```

**Check what is actually there first** (`SELECT * FROM <tab>_data_1`). Don't assume
"pre-launch" means "nothing to lose".

## Running one of those calls: `console.php shell` does not script

`console.php shell` looks like the obvious way to run one real call — it boots the full app
context and drops into PsySH. But that is PsySH's *interactive* mode, not a "pipe in one
line, get output" REPL: piping code via stdin gets interpreted as PsySH's own navigation
commands, not evaluated as PHP.

What works is a throwaway script doing exactly what `console.php` does before dispatching —
then the one real call, then a verification query:

```php
<?php
chdir('/path/to/checkout');
define('SET_SESSION', false);
require 'include.php';
ModuleManager::load_modules();

Utils_RecordBrowserCommon::new_addon('mymodule_entry', 'MyModule',
    'access_log_addon', _M('Access Log'));

$row = DB::GetRow("SELECT * FROM recordbrowser_addon WHERE tab='mymodule_entry' AND func='access_log_addon'");
echo $row ? "Registered: " . json_encode($row) . "\n" : "FAILED - no row found\n";
```

Write it to a scratch directory, **not the repo**, and run it with the platform's real PHP
binary (the bare `php` on PATH is often the wrong version — see `CLAUDE.md`). It is
throwaway once the call has run; only the middle line changes for any other API above.

**A related trap for code-only additions.** Adding `admin_caption()`/`admin_access()` to an
already-installed module's `Common` class needs no DB row at all — but
`ModuleManager::check_common_methods()` caches *which modules implement a given method* via
`Cache::`, which is **cross-request**, not request-scoped. A newly added method silently
will not appear until that entry is cleared. `console.php cache:rebuild` does it safely.

## Choosing which columns a browse call shows

Two easily confused mechanisms:

- **`Utils_RecordBrowser::set_header_properties($ar)`** only adjusts
  `name`/`wrapmode`/`width`/`display`/`order` for a column that is **already** going to be
  shown per the field's own stored `visible` flag. It cannot add a normally-hidden field,
  and its `'display'` key cannot remove an otherwise-visible one.
- **The `$cols` column-override argument** — third positional argument to both
  `Utils_RecordBrowser::body($def_order, $crits, $cols, $filters_set)` and
  `show_data($crits, $cols, $order, ...)` — is the real per-view override, independent of
  each field's `visible` flag:

```php
if ((!$args['visible'] && (!isset($cols[$args['id']]) || $cols[$args['id']] === false))) continue;
if (isset($cols[$args['id']]) && $cols[$args['id']] === false) continue;
```

`$cols[$field] = false` hides an otherwise-visible column for this call only;
`$cols[$field] = true` **forces a `visible => false` field to appear** for this call only —
useful for a field deliberately kept out of export and admin column customization while
still showing in one specific view.

## Addon tabs: reuse the parent's `$cols`, minus the parent-link column

[Dev-Tutorial.md](Dev-Tutorial.md) §11.8 documents the addon mechanism itself. Two nuances
on top of it.

**Drop the parent-link column.** Every row in the addon belongs to the record you are
already looking at, so that column is the same value on every row — pure noise.

```php
public function folder_items_addon($folder, $rb_parent) {
    $rb = $this->init_module(Utils_RecordBrowser::module_name(), 'mymodule_entry');
    $args = array(
        array('folder' => $folder['id']),                // crits: this folder only
        array('folder' => false) + $this->browse_cols(), // cols: same as the main grid, minus Folder
        array('title' => 'ASC'),                         // order
    );
    $this->display_module($rb, $args, 'show_data');
}
```

**The addon method runs on a fresh module instance**, not the same one as `body()`. Any
per-request static state `body()` sets up has to exist there too — and setting it up in
every *known* entry point still is not exhaustive: RecordBrowser's own internal navigation
(Cancel from Edit, returning to view mode, grid inline-edit) re-renders fields through
neither.

**The robust fix is not a third manual call site.** Make the one place the framework
*guarantees* runs on every render of the field — its own `QFfield_callback` or
`display_callback`, which every mode including `'view'` goes through — lazily self-heal,
guarded by a null check so it is a no-op once set up for the request.

## Adding a mouseover tooltip to a browse column

**The symptom:** a browse column (usually the recordset's title column — the one that links
to the full record) shows no tooltip on hover, while other references to the same kind of
record elsewhere in the app show a rich card popup.

**The cause:** check the field's `display_callback` in the module's `*Install.php`. If it is
the generic `array('Utils_RecordBrowserCommon', 'display_linked_field_label')`, that is it —
that function calls `create_linked_label_r()`, whose `$tooltip` parameter defaults to
`false`. It renders the linked label with no popup, by design. Other generic formatters have
the same gap; grep the callback's body for `create_linked_label_r(` / `create_linked_text(`
and check whether a `$tooltip` argument is actually passed.

**Step 1 — reuse or add a `*_get_tooltip($record)` builder.** Look for an existing one
first; `CRM_ContactsCommon::company_get_tooltip()` / `contact_get_tooltip()` already exist
and are reused by every other company/contact reference in the app. A new field-specific
tooltip is only warranted when the existing one's field list is wrong for this context.

```php
public static function xxx_get_tooltip($record) {
    if (!$record[':active']) return '';
    if (!Utils_RecordBrowserCommon::get_access('<tab>', 'view', $record)) return '';
    return Utils_TooltipCommon::format_info_tooltip(array(
        __('Label') => $record['field'],
        // ...
    ));
}
```

`format_info_tooltip()` already skips any row whose value is null or empty after
`strip_tags()`+`trim()` — **don't** hand-roll empty-field filtering in the caller.

**Step 2 — point the column at it.** Add (or reuse) a display-callback wrapper that renders
the column's own text but attaches the tooltip via `create_linked_text()`'s `$tooltip`
parameter as an `array(callback, args)` pair instead of a plain bool:

```php
public static function xxx_format_default($record, $nolink = false) {
    if (is_numeric($record)) $record = self::get_record($record);
    if (!$record || $record == '__NULL__') return null;
    return Utils_RecordBrowserCommon::create_linked_text($record['field'], '<tab>', $record['id'], $nolink,
        array(array('Module_Common', 'xxx_get_tooltip'), array($record)));
}
```

`CRM_ContactsCommon::company_format_default()` / `contact_lastname_format_default()` are
working examples of exactly this shape — copy one rather than writing from scratch.

**Step 3 — wire it up in two places. This is the step most likely to get skipped.** See
"Changing a field callback reaches only fresh installs" in Part 2.

**Which fields warrant one:** in practice, a browse table's own title/identifier column —
the one a user is most likely to hover *instead of* opening the record. Not every column,
and not every contact/company reference field elsewhere, which already get a tooltip by
default unless deliberately suppressed.

---

# Part 2 — the callback rules behind the recipes

## Changing a field callback reaches only fresh installs

A field's `display_callback` is **stored in the DB**, not read live from `*Install.php` —
in a table named `<tab>_callback` (`field`/`callback`/`freezed` columns), populated once at
install time. Editing `*Install.php` alone changes nothing on any install that already ran
it.

So a callback change is two edits:

1. **`*Install.php`** — change the field definition. Reaches fresh installs.
2. **A patch** — a matched-old-value `UPDATE`, guarded so a value a user has since
   customized is not clobbered. This is what reaches every existing install.

```php
DB::Execute('UPDATE <tab>_callback SET callback=%s WHERE field=%s AND freezed=1 AND callback=%s', array(
    'Module_Common::xxx_format_default',
    'Field Display Name',
    'Utils_RecordBrowserCommon::display_linked_field_label',
));
```

**There is a second, different callback-storage mechanism.** A contact/company-picker field
stores its `format` callback pre-serialized inside `<tab>_field`'s `param` string (built by
`new_record_field()` at install time), not in a `<tab>_callback` row. That needs a
different patch shape — a matched-old-value `UPDATE <tab>_field SET param=... WHERE
field=... AND param=...` against the whole serialized string. If a guarded `UPDATE` runs but
affects 0 rows on a DB you expected it to hit, check which of the two mechanisms applies
before assuming the patch is wrong; a drifted stored value on one install is also possible.

## `update_record()` merges old values into partial edits

`Utils_RecordBrowserCommon::update_record()` fills in the existing stored value for every
field the caller omitted, *before* invoking the recordset's processing callback. Harmless
for ordinary fields; poison for any field whose callback reads "present and non-empty" as
"the user just typed this" — a secret field re-encrypting merged-in ciphertext corrupts it
permanently. Grid inline-edit goes through the same path.

**Rule:** never infer "the user touched this" from the field's own content in
`edit`/`editing` mode. Add a virtual hidden marker field in the `QFfield_callback` — never a
real column, so the merge cannot reconstruct it — and gate on the marker.

## The same `$values`, two different shapes

A processing callback gets the **raw stored record** in `display`/`view` mode (real columns
only) and a **`$_POST`-shaped array** in `add`/`edit`/`adding`/`editing` (including virtual
and checkbox-only keys). A checkbox-shaped key checked in display mode is simply never set.

**Rule:** find the real persisted column the form key is derived from, and branch on that.

## Checkbox fields: pass `0`/`1`, never a PHP `bool`

`Utils_RecordBrowserCommon::new_record()` calls `trim()` on every non-array field value
before its `is_bool()`-to-`0`/`1` coercion runs. `trim(false)` casts to `''` first, so the
later `is_bool()` check never fires and `''` gets bound against the column's `%d`
placeholder, throwing `Argument N is not number(%d)`. Passing a plain `0`/`1` integer
sidesteps the coercion order entirely.

Leaving the key unset instead is *also* wrong, but silently: the column's own DB default
may not be what you assume, so a record can come out with the opposite value from the one
you intended.

## `new_record()` bypasses QuickForm, but not the processing callback

`Utils_RecordBrowserCommon::new_record()` skips the form layer entirely, but **not** the
recordset's registered processing callback — that is still called once with `mode='add'`.
Before writing raw `$values` into any recordset, read its `submit_*($values, $mode)`
`case 'add':` (and anything it falls through from) to make sure nothing there silently
rewrites or requires a key your array does not set.

## Two QuickForm rules that produce blank screens

- **`setDefaults()` must run before `addElement()` for a `static` element**, which pulls its
  value at add time. Getting this backwards blanks a whole "Add new record" screen.
- **A `QFfield_callback` that adds a *second* `$form->addElement()` renders nothing.** One
  callback, one element.

## Other field-level rules

- **Setting a `datepicker` value from JS needs the regional format, not ISO.**
- **`strtotime()` reads any slash-separated numeric date as m/d/y**, whatever the app
  locale. `reg2time()` swaps the capture groups for `%d/%m/%Y`-shaped formats before handing
  off; any new day-before-month slash format needs the same treatment.
- **Comparing "did this change" across two sources needs type normalization.** A
  `get_record()` value and a `$form->exportValues()` value do not agree on int-vs-string for
  numeric-backed columns, so `!==` reports every save as a change and pollutes edit history.
- **Enforcing uniqueness is a whole-form rule, not a field option.** There is no
  `'unique' => true`; see [Dev-Tutorial.md](Dev-Tutorial.md) §11.9 for the
  `addFormRule()` recipe.
