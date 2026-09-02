# Evolving an in-development RecordBrowser module without losing data

> **Status:** REFERENCE - the additive schema APIs, how to run one against a live dev DB, and
> the two column-control mechanisms that get confused with each other. The pre-split version,
> with its Premium-module worked example, is at
> `AI-private/archive/recordbrowser-live-schema-changes.md`.

[Dev-Tutorial.md](Dev-Tutorial.md) §8 says: while a module is still pre-launch, don't write a
patch for every schema change — just edit `install()`/`uninstall()` and reinstall through
Setup or `console.php module:uninstall` + `module:install`.

That advice has an unstated assumption. **Reinstalling drops and recreates the recordset**, so
it only works cleanly before the module has data worth keeping. As soon as someone has clicked
through the UI and created a few genuine records, reinstalling silently deletes them — and a
developer manually testing a feature you just built generates exactly that kind of small but
real dataset.

The fix is not to start writing dated `patches/` files this early (still the right call per
§8 — there is no *other* install to carry a patch to). It is to use RecordBrowser's own
**additive, non-destructive** APIs against the live dev DB, *and* add the equivalent field to
`install()`'s own arrays so a genuinely fresh install ends up with the same final shape.

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

## Running one of these calls: `console.php shell` does not script

`console.php shell` looks like the obvious way to run one real call — it boots the full app
context and drops into PsySH. But that is PsySH's *interactive* `Shell::debug()` mode, not a
"pipe in one line, get output" REPL: piping code via stdin gets interpreted as PsySH's own
navigation commands, not evaluated as PHP.

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
binary (the bare `php` on PATH is often the wrong version — see `CLAUDE.md`). It is throwaway
once the call has run; only the middle line changes for any other API in the list above.

**A related trap for code-only additions.** Adding `admin_caption()`/`admin_access()` to an
already-installed module's `Common` class needs no DB row at all — but
`ModuleManager::check_common_methods()` caches *which modules implement a given method* via
`Cache::`, which is **cross-request**, not request-scoped. A newly added method silently won't
appear until that entry is cleared. `console.php cache:rebuild` does it safely.

## `$cols` controls a specific browse call's columns — `set_header_properties()` does not

Two easily confused mechanisms:

- **`Utils_RecordBrowser::set_header_properties($ar)`** only adjusts
  `name`/`wrapmode`/`width`/`display`/`order` for a column that is **already** going to be
  shown per the field's own stored `visible` flag. It cannot add a normally-hidden field, and
  its `'display'` key cannot remove an otherwise-visible one from being included.
- **`$cols`** — the third positional argument to both
  `Utils_RecordBrowser::body($def_order, $crits, $cols, $filters_set)` and
  `show_data($crits, $cols, $order, ...)` — is the real per-view override, independent of each
  field's `visible` flag:

```php
if ((!$args['visible'] && (!isset($cols[$args['id']]) || $cols[$args['id']] === false))) continue;
if (isset($cols[$args['id']]) && $cols[$args['id']] === false) continue;
```

`$cols[$field] = false` hides an otherwise-visible column for this call only;
`$cols[$field] = true` **forces a `visible => false` field to appear** for this call only —
useful for a field deliberately kept out of export and admin column customization while still
showing in one specific view.

## Addon tabs: reuse the parent's `$cols`, minus the redundant parent-link column

[Dev-Tutorial.md](Dev-Tutorial.md) §11.8 documents the addon mechanism itself. Two nuances on
top of it:

**Drop the parent-link column.** Every row in the addon belongs to the record you are already
looking at, so that column is the same value on every row — pure noise.

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
per-request static state `body()` sets up has to exist there too — and setting it up in every
*known* entry point still isn't exhaustive: RecordBrowser's own internal navigation (Cancel
from Edit, returning to view mode, grid inline-edit) re-renders fields through neither.

**The robust fix is not a third manual call site.** Make the one place the framework
*guarantees* runs on every render of the field — its own `QFfield_callback` or
`display_callback`, which every mode including `'view'` goes through — lazily self-heal,
guarded by a null check so it is a no-op once set up for the request.
