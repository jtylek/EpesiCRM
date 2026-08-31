# Evolving an in-development RecordBrowser module without losing data

> **Status:** REFERENCE - evolving a RecordBrowser schema without losing real data.

`AI-shared/Dev-Tutorial.md` §8 correctly says: while a module is still pre-launch
(nothing installed anywhere else), don't write patches for every schema change — just
edit `install()`/`uninstall()` and reinstall through Setup/`console.php module:uninstall`
+ `module:install`. That advice has an unstated assumption: reinstalling drops and
recreates the recordset, so it only works cleanly *before* the module has any data worth
keeping. Building `modules/Premium/PasswordManager/` hit this gap directly: by the time a
later request needed a schema change (adding a `Description` field to a lookup
recordset), a real user had already created several genuine records through the UI (not
throwaway test data) — reinstalling would have silently deleted them.

The fix isn't to start writing dated `patches/` files this early (still the right call
per Dev-Tutorial §8 — there's still no *other* install to carry a patch to). It's to reach
for RecordBrowser's own additive, non-destructive schema APIs instead of
`install_new_recordset()`'s drop-and-recreate, run them once directly (console/CLI) against
the live dev DB, *and* add the equivalent field/callback to `install()`'s own field arrays
so a genuinely fresh install still ends up with the same final shape:

```php
// Add a field to an already-installed recordset without touching existing rows -
// same array shape as install_new_recordset()'s own $fields entries.
Utils_RecordBrowserCommon::new_record_field('passwordmanager_folder', array(
    'name' => _M('Description'),
    'type' => 'text',
    'param' => '255',
    'visible' => true,
));

// Attach/replace a display or QuickForm-rendering callback on an existing field -
// $field is the field's declared display NAME ('Name'), not its lowercased
// internal id ('name') - confirmed by inspecting an already-installed table's
// own <tab>_callback rows before guessing.
Utils_RecordBrowserCommon::set_display_callback('passwordmanager_folder', 'Name',
    array('Premium_PasswordManagerCommon', 'display_folder_name'));
Utils_RecordBrowserCommon::set_QFfield_callback(/* same shape */);

// Register (or re-register) an addon tab - idempotent, safe to call more than
// once: it calls delete_addon() internally before inserting.
Utils_RecordBrowserCommon::new_addon('passwordmanager_folder', 'Premium/PasswordManager',
    'folder_passwords_addon', _M('Passwords'));
```

Before running any of these against a live dev DB, check what's actually there first
(`SELECT * FROM <tab>_data_1`) — don't assume "it's pre-launch" means "there's nothing to
lose" without checking; a developer manually clicking through the UI to test a feature you
just built generates exactly the kind of real (if small) dataset this is about protecting.

## Actually running one of these calls: `console.php shell` doesn't script

`console.php shell` looks like the obvious way to run one real call from a terminal — it
boots the full app context (`Base_AclCommon::set_sa_user()`) and drops into PsySH. But
that's PsySH's *interactive* `Shell::debug()` mode, not a "pipe in one line, get output"
REPL: piping code via stdin (`echo '...;' | php console.php shell`) got interpreted as
PsySH's own navigation commands, not evaluated as PHP — confirmed live, not assumed.

What actually works: a tiny throwaway script doing exactly what `console.php` itself does
before dispatching to any command (its own top few lines) — `require 'include.php';
ModuleManager::load_modules();` — then the one real call, then a verification query
printed to stdout:

```php
<?php
chdir('C:/xampp82/htdocs/newsetup'); // wherever the checkout root actually is
define('SET_SESSION', false);
require 'include.php';
ModuleManager::load_modules();

Utils_RecordBrowserCommon::new_addon('passwordmanager_entry', 'Premium/PasswordManager',
    'access_log_addon', _M('Access Log'));

$row = DB::GetRow("SELECT * FROM recordbrowser_addon WHERE tab='passwordmanager_entry' AND func='access_log_addon'");
echo $row ? "Registered: " . json_encode($row) . "\n" : "FAILED - no row found\n";
```

Write it to the session's own scratch directory, not the repo (see this folder's README's
"Conventions for AI assistants' own tool usage"), and run it with the platform's real PHP
binary (`CLAUDE.md`'s Environment quirks section — the bare `php` on PATH is the wrong
7.4 install on this project's Windows dev machines). It's throwaway once the one real call
has run. Same technique covers any call from this file's own list — `new_record_field()`,
`set_display_callback()`, `new_addon()`, ... — only the one line in the middle changes.

A related but different gotcha hit registering `admin_caption()`/`admin_access()` (`Base/
Admin/README.md`'s convention for a User Management tile) this same way: those are
*code-only* additions, no DB row, so they don't need the script above at all — but
`ModuleManager::check_common_methods()` (`module_manager.php`) caches *which modules
implement a given method* via `Cache::` (memcache-backed here, cross-*request*, not just
request-scoped like most of this file's other examples). A method newly added to an
already-installed module's `Common` class silently won't appear until that cache entry is
cleared. `console.php cache:rebuild` (`Cache::clear()` + `ModuleManager::
create_common_cache()`) does this safely — it's just a cache, always safe to clear in dev.

## `$cols` controls which columns a *specific* browse call shows — `set_header_properties()` doesn't

Two different, easily-confused mechanisms both touch a browse table's columns:

- `Utils_RecordBrowser::set_header_properties($ar)` (`RecordBrowser_0.php`) only
  adjusts `name`/`wrapmode`/`width`/`display`/`order` for a column that's **already**
  going to be shown per the field's own stored `visible` flag — it cannot add a
  normally-hidden field as a column, nor can its `'display'` key remove an
  otherwise-visible one from actually being *included* (confirmed by reading
  `RecordBrowser_0.php`'s own column-inclusion check, not by assumption — an earlier
  attempt at this exact task wrongly assumed `'display'=>false` there was the hide
  mechanism).
- The **`$cols`** parameter — the 3rd positional argument to both
  `Utils_RecordBrowser::body($def_order, $crits, $cols, $filters_set)` (the module's
  default "Browsing" entry point) and `show_data($crits, $cols, $order, ...)` (the
  addon-calling convention, see below) — is the real per-view column override,
  independent of each field's own `visible` flag:
  ```php
  // RecordBrowser_0.php's own inclusion check:
  if ((!$args['visible'] && (!isset($cols[$args['id']]) || $cols[$args['id']] === false))) continue;
  if (isset($cols[$args['id']]) && $cols[$args['id']] === false) continue;
  ```
  `$cols[$field_id] = false` hides an otherwise-visible column for just this call;
  `$cols[$field_id] = true` **forces a normally-hidden (`visible=>false`) field to
  appear** as a column for just this call. `Custom_Tutorial::category_records_addon()`
  already uses the hide half of this (`array('category' => false)`) — Password
  Manager's own Passwords browse table uses both halves at once, dropping
  `url`/`password_updated_on` and forcing on a field (`password`) that's deliberately
  `visible=>false` at the schema level (kept out of every *other* context - export,
  admin column customization - while still appearing in this one specific view):
  ```php
  $cols = array('url' => false, 'password_updated_on' => false, 'password' => true);
  $this->display_module($this->rb, array(array(), array(), $cols)); // body()'s own arg order
  ```

## Addon tabs: reuse the parent's own `$cols`, minus the now-redundant parent-link column

`Dev-Tutorial.md` §11.8 already documents the addon mechanism itself in full (calling
convention, `new_addon()`/`delete_addon()`, the worked `Custom_Tutorial` example) — read
that first, this is only the one extra nuance Password Manager's own addon
(`Premium_PasswordManager::folder_passwords_addon()`) added on top of it: when an addon
shows a set of records already scoped to one parent (every row in this addon belongs to
the Folder record you're already looking at), also drop that parent-link column from the
addon's own `$cols` — it's necessarily the same value on every row, so displaying it a
second time (once as the page you're already on, again as a column) is pure noise:
```php
public function folder_passwords_addon($folder, $rb_parent) {
    $rb = $this->init_module(Utils_RecordBrowser::module_name(), 'passwordmanager_entry');
    $args = array(
        array('folder' => $folder['id']),                 // crits: only this folder's entries
        array('folder' => false) + $this->browse_cols(),   // cols: same as the main browse table, minus the redundant Folder column
        array('title' => 'ASC'),                           // order
    );
    $this->display_module($rb, $args, 'show_data');
}
```
Note the addon method runs on a **fresh module instance** (Dev-Tutorial §11.8's own
calling convention creates a new one via `init_module()` each time), not the same
instance/request context as the module's default `body()` — any per-request static state
`body()` sets up (e.g. this module's own ajax-callback URL for its Reveal/Copy buttons,
`Premium_PasswordManagerCommon::$ajax_reveal_url`) has to be set up again inside the addon
method too, or whatever depends on it will silently no-op there even though it works fine
on the main browse screen.

**Update, found later the same build**: setting it up in every *known* entry point
(`body()`, `folder_passwords_addon()`) still wasn't exhaustive — `Utils_RecordBrowser`'s
own internal `'view'` navigation (e.g. Cancel from Edit, returning to the record's view
mode) re-renders the same field through neither of those, so `$ajax_reveal_url` was still
null there and the Reveal/Copy icons silently vanished (fell back to `display_password()`'s
"no live ajax URL" safe mode). The robust fix wasn't a third manual call site — it was
making the one place that's *guaranteed* to run on every render of the field
(`QFfield_password()`, the field's own `QFfield_callback`, which every mode including
`'view'` goes through) lazily self-heal: `Premium_PasswordManagerCommon::
ensure_reveal_setup($rb_obj)`, guarded by a null check so it's a no-op once already set up
that request. **General lesson**: for per-request static state a custom field/addon
depends on, prefer a self-healing lazy-init at the one call site the framework guarantees
(a `QFfield_callback`, a `display_callback`, ...) over manually duplicating the setup call
at every entry point you can currently think of — there's often one more navigation path
than expected, and RecordBrowser's own internal navigation (Cancel, addon tabs, grid
inline-edit, ...) doesn't always route back through the module's own `body()`.
