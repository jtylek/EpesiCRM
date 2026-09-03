# Epesi module development

How to build a module, grounded in the actual source rather than the framework's
aspirational docs.

**Part A** is one worked example, start to finish: a module you can install, reach from the
sidebar, and add records to. It shows exactly one way to do each step. **Part B** is the
reference — every alternative, escape hatch and "why" that Part A deliberately skips.

Read `CLAUDE.md` for the wider architecture (bootstrap chain, rendering, error handling).
A complete example module lives at `modules/Custom/Tutorial/`, tracked in git and paired
with this document; it exercises every field type in §11.2 in one real table.

> **On the official epesi.org/devtutorial site:** it targets an older version of the
> framework and is thinner than the real code in several places. Its directory and naming
> conventions and its general shape are accurate. Where the two disagree, this document —
> verified against the actual code — wins.

---

# Part A — your first module

We will build `Custom/Projects`: a table of projects with a name, a due date, a status and
a description, reachable from the sidebar. Every step is required; nothing here is
optional.

## A1. Scaffold the files

```
php console.php dev:module:create Custom/Projects --require Utils/RecordBrowser
```

Use the real PHP binary, not the bare `php` on PATH — see `CLAUDE.md`. This writes three
files under `modules/Custom/Projects/`, with the naming and the `defined("_VALID_ACCESS")`
guard already correct:

| File | Class | What it is for |
|---|---|---|
| `Projects_0.php` | `Custom_Projects` | the screen — display logic |
| `ProjectsCommon_0.php` | `Custom_ProjectsCommon` | static logic: the menu entry, the icon |
| `ProjectsInstall.php` | `Custom_ProjectsInstall` | the table, its permissions, its removal |

## A2. Declare the table

Open `ProjectsInstall.php` and fill in `install()` and `uninstall()`:

```php
public function install() {
    $fields = array(
        array('name' => _M('Project Name'), 'type' => 'text', 'required' => true,
              'param' => '64', 'visible' => true),
        array('name' => _M('Due Date'),     'type' => 'date', 'visible' => true),
        array('name' => _M('Status'),       'type' => 'commondata',
              'param' => 'Bugtrack_Status', 'visible' => true),
        array('name' => _M('Description'),  'type' => 'long text'),
    );
    Utils_RecordBrowserCommon::install_new_recordset('project', $fields);
    Utils_RecordBrowserCommon::set_caption('project', _M('Projects'));

    Utils_RecordBrowserCommon::add_access('project', 'view',   'ACCESS:employee');
    Utils_RecordBrowserCommon::add_access('project', 'add',    'ACCESS:employee');
    Utils_RecordBrowserCommon::add_access('project', 'edit',   'ACCESS:employee');
    Utils_RecordBrowserCommon::add_access('project', 'delete', 'ACCESS:employee');

    return true;
}

public function uninstall() {
    Utils_RecordBrowserCommon::uninstall_recordset('project');
    return true;
}
```

Three things worth noticing now, explained properly in Part B:

- **`_M()`, not `__()`**, for labels built at install time (§9).
- **`uninstall()` must undo what `install()` did.** `ModuleManager::uninstall()` will not
  drop your tables for you (§4). `uninstall_recordset()` covers everything
  `install_new_recordset()` created.
- **`'visible' => true`** puts the field in the browse grid. `Description` omits it, so it
  shows on the record's own page but not as a grid column.

## A3. Put it in the menu, and give it an icon

In `ProjectsCommon_0.php`:

```php
class Custom_ProjectsCommon extends ModuleCommon {

    public static function menu() {
        if (!Utils_RecordBrowserCommon::get_access('project', 'browse')) return array();
        return array(_M('Projects') => array('__submenu__' => 0));
    }

    public static function bootstrap_icon() { return 'bi-kanban'; }
}
```

**There is no registration call.** A module opts into the menu simply by declaring
`menu()`; the framework scans for the method (§7).

## A4. Render the grid

In `Projects_0.php`:

```php
class Custom_Projects extends Module {

    public function body() {
        $rb = $this->init_module(Utils_RecordBrowser::module_name(), 'project', 'project');
        $this->display_module($rb);
    }
}
```

That is the whole screen. `Utils_RecordBrowser` turns the field declarations from A2 into
add, edit, view, browse, filter and ACL handling.

## A5. Install it

New modules are discovered by scanning `modules/` for a `<Name>Install.php`, but that list
is cached in the `available_modules` table. So:

1. Go to **Administration → Modules Administration & Store**.
2. Click **Rebuild modules database** — otherwise your brand-new module simply will not be
   listed.
3. Find *Projects* and install it.

Or from the CLI:

```
php console.php module:install Custom/Projects
```

## A6. Check it works

**Log out and back in.** The merged menu tree is cached per session, so a new `menu()`
method does not appear on a plain reload (§7). This trips up everyone once.

Then: click **Projects** in the sidebar, add a record, edit it, and confirm it appears in
the grid.

## A7. Confirm it uninstalls cleanly

This is the step most commonly skipped, and the one the framework cannot do for you.
Uninstall the module, then:

```sql
SHOW TABLES LIKE 'project%'
```

It should return nothing. If it returns tables, `uninstall()` is incomplete — fix it now,
while the module has no real users.

**You now have a working module.** Everything from here is Part B.

---

# Part B — reference

## 1. Module anatomy

A module is a PHP package under `modules/<Vendor>/<Name>/` (nesting is allowed:
`modules/CRM/Contacts/Photo/`). The module's **class name** replaces `/` with `_`:
`modules/CRM/Mail/` ⇄ class `CRM_Mail`. This underscore-as-namespace-separator rule
(`ModulePrimitive::get_module_dir()`) is used everywhere — file paths, `requires()`
entries, `init_module()` calls.

Every module contributes up to **three classes**, one per file, named from the *last* path
segment (`CRM_Mail` → base name `Mail`):

| File | Class | Extends | Purpose |
|---|---|---|---|
| `Mail_0.php` | `CRM_Mail` | `Module` | the instantiable tree node — display/instance logic |
| `MailCommon_0.php` | `CRM_MailCommon` | `ModuleCommon` | static/shared logic, callable without an instance |
| `MailInstall.php` | `CRM_MailInstall` | `ModuleInstall` | schema, ACL, dependencies, patches |

**The `_0` suffix is the module's currently-installed version number**, not a convention
you pick — `ModuleManager::include_common()`/`include_main()` build the literal filename
`...Common_<version>.php` from the `version` column of the `modules` table. In practice
**almost every module has a single-element `version()` array and lives permanently at
version 0.** Start every new module at `_0` and do not add a `_1` unless you are doing a
genuine breaking migration on an already-shipped module (§4). `Install.php` has no version
suffix — there is only ever one.

## 2. Scaffolding

```
php console.php dev:module:create Custom/Projects --require Utils/RecordBrowser
php console.php dev:module:patch  Custom/Projects "add priority field"
```

`dev:module:create` writes the essential shape:

```php
class <Vendor_Name> extends Module {
    public function body() {
    }
}

class <Vendor_Name>Common extends ModuleCommon {
}

class <Vendor_Name>Install extends ModuleInstall {
    public function install()   { return true; }
    public function uninstall() { return true; }
    public function requires($v) {
        return [ array('name' => '<required/module>', 'version' => 0), ... ];
    }
    public function version() { return ['0.1']; }
}
```

`dev:module:patch <module> "<title>"` scaffolds a dated stub under that module's `patches/`
directory (§8); you write the body yourself.

## 3. The class hierarchy

```
ModulePrimitive (abstract)          include/module_primitive.php
 ├── ModuleInstall (abstract)       include/module_install.php
 ├── ModuleCommon                   include/module_common.php
 └── Module (abstract)              include/module.php

ModuleManager                       include/module_manager.php   (static factory/registry)
```

### `ModulePrimitive` — shared by all three

- `get_type()` — the module's class name (`CRM_Mail`).
- `get_module_dir()` — `'modules/'.str_replace('_','/',$this->type).'/'`.
- `get_data_dir()` / `create_data_dir()` / `remove_data_dir()` — `data/<Type>/`.
- `static module_name()` — strips a trailing `Common`/`Install` and returns the slash form
  (`CRM_MailInstall::module_name()` → `'CRM/Mail'`). **This is the idiomatic way to
  reference another module** in `requires()`/`init_module()` — it fails at parse time if
  the class does not exist, which is a real safety net against a typo'd module name.
- `static is_installed()`.

### `ModuleInstall` — three methods you must implement

```php
abstract public function install();
abstract public function uninstall();
abstract public function requires($v);
```

Optional but conventional, called only `if is_callable`: `version()`,
`upgrade_N()`/`downgrade_N()`, `info()`, `simple_setup()`.

### `ModuleCommon` — a lazily-upgrading singleton

`Instance()` is seeded with the module's string name and upgrades itself into a real
instance on first call. You rarely need it — most `Common` classes are used purely for
their **static** methods, which is the normal pattern.

### `Module` — the instantiable tree node

The constructor is `final` and takes a Pimple `Container` — **don't call it directly**; use
`init_module()`/`pack_module()` (§5).

- **`get($name)`** — DI container accessor, **currently vestigial**: nothing registers
  anything into the container, so calling it throws `UnknownIdentifierException`. Don't
  reach for it. `twig_display()`/`twig_render()` are dead for the same reason — Smarty 2 is
  the real template engine.
- **Module variables** — session-scoped instance state (§6).
- **Href/callback machinery** — `create_href()`, `create_callback_href()` (wraps a callback
  into a URL that replays it on click — how almost every button in the app works, since
  this is an AJAX-push SPA, not REST), `create_back_href()`,
  `create_confirm_href()`/`create_confirm_callback_href()`.
- **`register_method()`** — lets other code graft an ad-hoc method onto every `Module` via
  `__call()`. Used once, for a deprecated translation shortcut. Not a pattern to extend.

### `ModuleManager`

Static-only; owns `install()`/`uninstall()`/`upgrade()`/`downgrade()` against the `modules`
table, `load_modules()` (boot-time class loading in dependency order), `list_modules()`
(scans `modules/` for any directory containing a `<Name>Install.php` — **this is how a
brand-new module is discovered at all**, there is no manifest to edit), and
`call_common_methods()` (§7).

## 4. Install lifecycle

`requires($v)` returns dependencies, varying by target version if needed:

```php
public function requires($v) {
    return array(
        array('name' => Base_AclInstall::module_name(), 'version' => 0),
        array('name' => Utils_RecordBrowserInstall::module_name(), 'version' => 0),
    );
}
```

`ModuleManager::check_dependencies()`/`satisfy_dependencies()` walk this recursively at
install time, auto-installing required modules first and throwing on a real cycle. The same
data drives `create_load_priority_array()`, which orders boot-time class loading.

**`version()`'s array length, not its string contents, is what matters** —
`ModuleManager::install()` treats `count($version_ret) - 1` as the target version index.
`install()` always runs first (version 0); if `version()` returns more than one element,
`upgrade_1()`, `upgrade_2()`, … run in sequence, with `downgrade_N()` as the rollback path.
**Real modules essentially never do this** — `modules/Tests/Tooltip/` is the one example in
the tree, kept deliberately to demonstrate the mechanism.

### Schema, ACL and the uninstall rule

**Schema creation** goes through ADOdb's portable data dictionary via
`DB::CreateTable($name, $cols, $opts)` / `DB::DropTable($name)` — generic column syntax
(`I4`=int, `C(n)`=varchar, `X`=text/blob, `T`=timestamp, `I1`=tinyint, with
`AUTO`/`KEY`/`NOTNULL` modifiers), portable across MySQL and PostgreSQL. For queries
elsewhere use `DB::Execute()`/`DB::GetOne()`/`DB::GetAssoc()` with `%s`/`%d` placeholders —
**never interpolate raw values into SQL strings.**

**ACL registration** is a direct call from `install()`/`uninstall()`, not a separate file:
`Base_AclCommon::add_permission($name, ...$clearance_rules)` / `delete_permission($name)`.

**Critically: `ModuleManager::uninstall()` does NOT drop your tables.** It handles the
generic bookkeeping — checks nothing else still requires this module, calls your
`uninstall()`, deletes the `modules` row, removes `data/<Type>/`, clears caches. *Your*
`uninstall()` is the only thing that drops the tables, rows and ACL entries *your*
`install()` created. Forgetting this is the single most common way a module leaves orphaned
tables behind. With RecordBrowser, `uninstall_recordset($tab)` does the equivalent full
cleanup — call it from your own `uninstall()`.

## 5. Building the module tree

`Module::init_module($module_type, $args = null, $name = null, $clear_vars = false)`
normalizes the type name, instantiates, and — if the new module defines `construct(...$args)`
— calls it with `$args`. `pack_module(...)` is `init_module()` + an immediate
`display_module()` in one call. Never call `ModuleManager::new_instance()` yourself.

```php
// Wrap a RecordBrowser instance, fixed instance id 'contact' (not auto-numbered)
$this->rb = $this->init_module(Utils_RecordBrowser::module_name(), 'contact', 'contact');
$this->display_module($this->rb);

// init + display in one call, forwarding display args + a function name
$this->m = $this->pack_module($page[0], $page[2], $page[1], $page[3]);
```

A child module's position in the tree is `get_path()` = parent's path +
`/<Type>|<instance_id>`. That path is also the key module variables are stored under (§6).

## 6. State: four different scopes

| Mechanism | Scope | Backing | Typical use |
|---|---|---|---|
| `$this->set_module_variable($n,$v)` / `get_module_variable($n)` | this module **instance**, this browser **tab** | `$_SESSION['client']['__module_vars__'][get_path()]` | UI state for the current screen |
| `$this->set_shared_module_variable()` | every instance of this module **type**, this tab | same tree, keyed by `get_type()` | state shared across instances in one tree |
| `Variable::get($name)` / `set($name,$value)` | app-wide, every user | `variables` table | global config (`default_theme`, `installed_langs`) |
| `Base_User_SettingsCommon::get($module,$name,$user=null)` | per logged-in **user**, any tab | `base_user_settings` table | a user's own preference |
| `History` (`include/history.php`) | per-tab undo/redo | `history` table | not a get/set API — serializes **all** module variables as one blob per navigation step |

**The per-tab mechanism, if you need to reason about it:** `$_SESSION['client']` is loaded
from a storage record keyed by the browser tab's `X-Client-Id` header (`CID`), so module
variables are genuinely per-tab, not just per-login. If a setting "didn't stick", check you
are not comparing two tabs.

## 7. Conventions: menu, settings, home page, ActionBar, icon, Setup

`menu()`, `user_settings()`, `home_page()` and several others (`help`,
`search_categories`, `tray`, `cron`, …) share **one mechanism**:
`ModuleManager::call_common_methods($method)` scans every installed module's `<Type>Common`
class for a method with that exact name and calls it statically if present. **There is no
registration call — a module opts in by declaring the method.**

```php
public static function user_settings() {
    if (Utils_RecordBrowserCommon::get_access('rc_accounts', 'browse'))
        return array(__('E-mail Accounts') => 'account_manager');
    return array();
}

public static function menu() {
    $ret = array();
    $opts[_M('Contacts')] = array('mode' => 'contact', '__icon__' => 'contacts.png');
    if (!empty($opts)) { $opts['__submenu__'] = 1; $ret[_M('CRM')] = $opts; }
    return $ret;
}

public static function home_page() {
    return array(_M('My Contact') => array(CRM_Contacts::module_name(), 'body', array('my_contact')));
}
```

`menu()` returns a nested associative array: label ⇒ either
`array('__submenu__' => 1, ...children)` or a leaf with
`__module__`/`__function__`/`__function_arguments__`/`__icon__` keys. `home_page()`'s value
shape is `[module_name, function_name, function_args]`.

**No single file "owns" a top-level group.** Multiple unrelated modules can each return the
*same* label wrapped in `array('__submenu__' => 1, ...)`, and `Base_Menu::add_menu()` merges
them into one accordion group purely by matching the label string at render time. There is,
for example, no `CRM_0.php` at all — `modules/CRM/`'s top-level contributors are six
independent sibling modules, none more "primary" than another. Moving a module into an
existing group only ever requires editing *that module's own* `menu()`.

**`menu()`'s result is cached per session.** After editing a `menu()` method, a plain reload
in an already-logged-in tab shows nothing — log out and back in.

**`bootstrap_icon()`** is resolved on demand per module, falling back to a generic window
icon if undeclared. See [theming-and-frontend.md](theming-and-frontend.md).

**ActionBar is a different pattern** — not a declared method, but an imperative call from
inside your `body()`, appending a button for the current request:

```php
Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
Base_ActionBarCommon::add('settings', __('Settings'),
    $this->create_callback_href($this->push_settings(...), ['Tray settings']),
    __('Click to edit tray settings'));
```

`$type` (first argument) is either a built-in icon key (`home`/`back`/`edit`/`save`/
`delete`/…) or resolved through the same icon machinery.

**`simple_setup()`** (on the `Install` class) controls whether and how your module appears
in the **Simple view** of Administration → *Modules Administration & Store*:

```php
public static function simple_setup() {
    return array('package' => _M('My Package'), 'version' => '1.0', 'icon' => true, 'core' => 0);
}
```

- Return `false`, or omit the method → hidden from Simple view; the Advanced table still
  lists it.
- Return `true` → shown under "Uncategorized".
- Return a plain string → shown under a package of that name. Most core modules just return
  `__('Epesi Core')`.
- Return an array → `package` (required; groups modules that install/uninstall together as
  one row), `option` (sub-grouping), `version`, `icon` (bool — looks for
  `package-icon.png` next to the module), `url`, `core` (`1` = cannot be uninstalled here).

**The package name is matched by exact string.** `Base_Setup::simple_setup()` groups by it,
so two spellings produce two separate cards. The same hazard applies to any ACL permission
name, CommonData array id, or anything else used as a `switch`/`==` key rather than merely
displayed.

**One practical gotcha:** the module list is cached in the `available_modules` table,
refreshed only when empty or when an admin clicks **Rebuild modules database** on that
screen. A brand-new module will not appear until then.

## 8. Patches

A module's `patches/` directory has **no manifest** — it is scanned by convention
(`PatchUtil::list_for_module()`). Filename convention:
`patches/YYYYMMDD_short_description.php`. Scaffold with
`console.php dev:module:patch <module> "<title>"`.

Patch bodies typically call `PatchUtil::db_add_column()`/`db_drop_column()`/
`db_alter_column()` or straight `DB::Execute()`.

On a brand-new install, all patches that already exist are marked applied without running —
only patches added *after* a site went live actually execute there.

**A patch's identity is its file path, not its content.** Editing an already-applied patch
is silently ignored. Ship a new file for any further change.

### The upgrade gap — the rule most expensive to forget

A fix only reaches real users if it ships in a form that runs against their **existing**
database and files. Classify every fix before calling it done:

| Kind | Reaches | Gap? |
|---|---|---|
| **CODE fix** — PHP logic in a `.php` file | every install, on deploy | none |
| **DATA fix** — an `*Install.php` default, a one-off `UPDATE`, a changed `data/` file | fresh installs **and your dev DB only** | **yes** |

**A data fix must also ship a patch**, which runs on existing instances through
`runpatches.php`/`update.php`. This is the single most common way a "fixed" bug regresses
on upgrade.

Two refinements learned the hard way:

- **Judgment, not reflex.** A cosmetic, low-stakes, pre-release data fix does not always
  earn a permanent patch file. Weigh it, and state the decision either way.
- **Look for a `*_default_*` table.** Anything copied from a template table into a per-user
  row on first use (dashboard applets, admin defaults) is decoupled from the template the
  moment the copy happens — fixing the template alone reaches nobody who already has a copy.

**While a module is still pre-launch, don't write a patch for every schema change.** Just
edit `install()`/`uninstall()` and reinstall. Patches exist to carry a fix to installs that
already ran the old `install()`; a module with no such installs has nothing to carry a patch
to. Switch to patches once the module is genuinely live somewhere and reinstalling would
lose real records. See [recordbrowser-recipes.md](recordbrowser-recipes.md) for how to
evolve a recordset in place once your dev DB has data worth keeping.

## 9. Translations

```php
function __($string, $arg2 = array())  { ... }  // translate now
function _V($string, $arg2 = array())  { ... }  // translate a value previously marked with _M()
function _M($string, $arg2 = array())  { ... }  // MARK for translation without translating yet
```

`__()` behaves like `sprintf` via `vsprintf` on the translated string. Use **`_M()` for
labels built at install or boot time** — menu keys, ACL permission names, `simple_setup()`
package names — where a translated string would not yet make sense out of request context.
The original English passes through unchanged but stays discoverable by translation tooling;
translate it for real at display time with `_V()`.

Language files are `modules/<Module>/lang/<code>.php`, plain PHP:

```php
<?php
global $translations;
$translations['Projects'] = 'Projekty';
$translations['Due Date'] = 'Termin';
```

An empty string value means "not yet translated" and falls back to the English key.

**A lang-file edit alone is never enough to see it live.** `Base_LangCommon::load()` caches
the per-language merge — run `console.php cache:rebuild` after editing any `lang/<code>.php`.

Per-instance custom overrides (entered via the admin Translate screen) are never written
into `modules/` — they live at `data/Base_Lang/custom/<module>/<code>.php`, created on first
write, keeping `modules/` pure shipped source.

## 10. Small conventions

- Every convention method your module declares (`menu()`, `user_settings()`, `home_page()`,
  `bootstrap_icon()`) lives on the **`Common`** class as a `public static` method — never
  on the instance class.
- `_M()` for any label computed outside a live request context; `__()` everywhere else.
- Prefer `Module::module_name()` symbolic references over hardcoded string module names —
  it fails fast if the class does not exist.
- The product name is styled **"Epesi"** — capital E, rest lowercase — in docs, comments,
  commit messages and new UI strings. The codebase is genuinely inconsistent about this
  today, so don't infer the casing from one call site.

## 11. RecordBrowser

Most business modules don't hand-roll a table screen — they register a table's shape with
`Utils_RecordBrowserCommon` and wrap a `Utils_RecordBrowser` instance in their own
`Module`. This is almost always the right starting point for a data-driven module.

### 11.1 Registering a table

```php
$fields = array(
    array('name'=>_M('Project Name'), 'type'=>'text', 'required'=>true, 'param'=>'64', 'visible'=>true),
    array('name'=>_M('Company Name'), 'type'=>'select', 'required'=>true, 'param'=>array('company'=>'Company Name')),
    array('name'=>_M('Due Date'),     'type'=>'date',   'required'=>true),
    array('name'=>_M('Status'),       'type'=>'commondata', 'param'=>'Bugtrack_Status'),
    array('name'=>_M('Description'),  'type'=>'long text'),
);
Utils_RecordBrowserCommon::install_new_recordset('bugtrack', $fields);
Utils_RecordBrowserCommon::set_caption('bugtrack', _M('Bugtrack'));
Utils_RecordBrowserCommon::add_access('bugtrack', 'view',   'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('bugtrack', 'add',    'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('bugtrack', 'edit',   'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('bugtrack', 'delete', array('ACCESS:employee','ACCESS:manager'));
```

```php
// uninstall()
Utils_RecordBrowserCommon::uninstall_recordset('bugtrack');
```

Full real examples: `modules/Tests/Bugtrack/BugtrackInstall.php` (smallest complete one)
and `modules/CRM/PhoneCall/PhoneCallInstall.php` (richest field-type usage in one file).

**What `install_new_recordset()` creates:** `<tab>_field` (column metadata),
`<tab>_data_1` (row storage — `f_<field_id>` per column), `<tab>_callback` (registered
display/QuickForm callbacks per field), `<tab>_access`/`_access_clearance`/`_access_fields`
(ACL), plus rows in the shared `recordbrowser_table_properties`/`_processing_methods`
tables. Two fields are seeded implicitly and never declared by you: `id` (`foreign index`)
and `General` (`page_split`, the first page divider in the edit form).
`uninstall_recordset($tab)` drops every one of these.

Field names are normalized to a column id via
`preg_replace('/[^|a-z0-9]/','_',strtolower($name))` — `_M('Due Date')` becomes column
`f_due_date`. You reference a field by its *display name* in most APIs.

### 11.2 Field types

The authoritative list (`Utils_RecordBrowserCommon::get_default_QFfield_callback()`).
Every type has a real `QFfield_<type>()` method building its widget and an entry in
`actual_db_type()` mapping it to a column type.

| `type` | Storage | Form widget | Notes |
|---|---|---|---|
| `text` | `C(param)` varchar | text input | `param` = max length |
| `long text` | `X` | textarea | BBCode-optimized on save; for rich text see [theming-and-frontend.md](theming-and-frontend.md) |
| `integer` | `I4` | text input | numeric-only rule |
| `float` | `F` | text input | numeric rule |
| `currency` | `C(128)` | amount + currency `<select>` | options from `Utils_CurrencyFieldCommon` |
| `checkbox` | `I1` | checkbox | forced values `('0','1')` |
| `date` | `D` | date picker | |
| `timestamp` | `T` | date+time picker | `param` = minute increment |
| `time` | `T` | time picker | `date=>false` variant of the timestamp widget |
| `commondata` | `C(128)` | `<select>` from a registered `CommonData` array | `param` = array-id string |
| `select` | `I4` (single) or `X` (multiple) | `<select>`, auto-escalating to `autoselect` past ~50 options | `param` points at another recordset — §11.3 |
| `multiselect` | `X` (token list) | dual-listbox, auto-escalating to `automulti` | same `param` grammar as `select` |
| `autonumber` | `C(len)` | read-only static text | `param` = `"prefix__padlength__padchar"`; **auto-populated from the new row's id right after insert** |
| `file` | `X` (JSON list of `Utils_FileStorage` ids) | Dropzone upload | needs `Utils_FileStorage` (+`Utils_FileUpload`) |
| `hidden` | `param` as a raw SQL type fragment, or no column if empty | hidden input | for module-managed values the user never edits — set via a processing callback (§11.5) |
| `calculated` | same as `hidden` | read-only static text | give it a real (if unused) column so `get_val()`'s `array_key_exists` check passes, then drive the displayed value from a `display_callback` reading *other* fields |
| `page_split` | none | tab/page divider | never holds data |

**Don't add a `page_split` speculatively** for a module that currently has one section, even
if you know more are coming. A new recordset already gets an implicit unnamed first page
(the seeded `General` field). A solo explicit `page_split` with every other field under it
produces a broken install. Add them one at a time, when a genuine second section actually
exists.

**Extending beyond this list:** `Utils_RecordBrowserCommon::register_datatype($type,
$module, $func)` registers a callback that *rewrites* a custom type name into one of the
core types before storage. This is the supported extension point, not a second storage
layer. CRM's `crm_contact`/`crm_company_contact` types are exactly this — sugar over
`select`/`multiselect` with baked-in formatting and crits callbacks.

### 11.3 `select`/`multiselect` — pointing at another recordset

The shorthand `'param' => array('company' => 'Company Name')` (target tab ⇒ display column)
is encoded to `'company::Company Name'` at registration and decoded the same way at read
time. Other forms:

- `'__RECORDSETS__::;<crits_callback_class>::<crits_callback_func>'` — pick from *any*
  installed recordset, filtered by a crits callback.
- `'tab::col1|col2;CritsClass::crits_func;AdvClass::adv_func'` — full form with a crits
  callback (restricting which target rows are selectable) and an advanced-params callback.

### 11.3b A ready-made "pick a person" field

If a field references a *person* rather than an arbitrary recordset, don't hand-roll a
`select` pointed at `contact` — `CRM_Contacts` registers `crm_contact` /
`crm_company_contact` datatypes that do this correctly, including the Last Name/First Name
display format:

```php
array('name'=>_M('Manager'), 'type'=>'crm_contact',
    'param'=>array(
        'field_type'=>'select',                                      // or 'multiselect'
        'crits'=>array('Custom_TutorialCommon', 'employees_crits'),  // restrict the picker
        'format'=>array('CRM_ContactsCommon', 'contact_format_no_company'),
    ), 'visible'=>true),
```

`'crits'` is a callback returning a Crits-shaped array restricting which contacts are
selectable. For "this instance's own staff", every CRM module that needs one defines its
**own** identical copy rather than sharing a central one:

```php
public static function employees_crits() {
    return array('(company_name' => CRM_ContactsCommon::get_main_company(),
                 '|related_companies' => array(CRM_ContactsCommon::get_main_company()));
}
```

Follow the same pattern — it also avoids taking on another module as a dependency for one
helper. This datatype requires `CRM_Contacts` in your `requires()`.

### 11.3c Chained `commondata` fields (cascading selects)

A `commondata` field's `param` can be an array of more than one element. The first element
is the `CommonData` array id; each element after it is the *display name* of another field
on the same form — picking a value there repopulates this field's options from the matching
nested branch, client-side, with no page reload. This is what powers Country/Zone fields —
**not** the separate `Utils_ChainedSelect` module, which drives a different, non-CommonData
cascade.

```php
array('name'=>_M('Country'), 'type'=>'commondata', 'param'=>array('Countries'),
    'visible'=>true, 'QFfield_callback'=>array('Data_CountriesCommon', 'QFfield_country')),
array('name'=>_M('Zone'), 'type'=>'commondata', 'param'=>array('Countries', 'Country'),
    'QFfield_callback'=>array('Data_CountriesCommon', 'QFfield_zone')),
```

The nested array needs sub-arrays registered under `'<parent>/<key>'`: `Countries` holds
country names, `Countries/US` holds US states, `Countries/US/PA` holds PA counties.
**Resolution is by field name, not position** — a downstream field's `param` names the exact
display name of the field it depends on, so renaming that field breaks the reference.

### 11.4 Filters

Two independent things:

- **Making a column filterable at all:** `'filter' => true` in the field definition, or
  `Utils_RecordBrowserCommon::new_filter($tab, $col_name)` after the fact. The per-type
  filter widget is built automatically from the column's type.
- **Setting default filter values** when your module embeds the browser:

```php
$this->rb->set_filters_defaults(array('employees'=>$me['id'], 'status'=>'__NO_CLOSED__'));
$this->rb->set_default_order(array('status'=>'ASC', 'date_and_time'=>'ASC'));
```

`set_filters_defaults()` only takes effect the *first* time per session — pass
`$overwrite=true` to force it every load.

### 11.5 Processing callbacks

Register once, from `install()`:

```php
Utils_RecordBrowserCommon::register_processing_callback('phonecall',
    array('CRM_PhoneCallCommon', 'submit_phonecall'));
```

Your callback is called as `callback($values, $mode, $tab)` for **every** lifecycle event on
that table, and must return `$values` to let processing continue — **returning `false`
aborts the operation**:

```php
public static function processing_related($values, $mode) {
    switch ($mode) {
        case 'edit':
            $rs = Utils_RecordBrowserCommon::get_record('phonecall_related', $values['id'])['recordset'];
            self::delete_addon($rs);
            // intentional fallthrough into 'add' to reapply for the new value
        case 'add':
            self::new_addon($values['recordset']);
            break;
        case 'delete':
            self::delete_addon($values['recordset']);
            break;
    }
    return $values;
}
```

Modes, in rough chronological order per user action: `'browse'` (rendering each grid row),
`'adding'` (building New-record form defaults), `'add'` (just before INSERT), `'added'`
(right after, `id` now present), `'display'` (rendering a saved record's view page — results
are `array_merge_recursive`'d across callbacks), `'edit'`/`'edited'`/`'edit_changes'`,
`'delete'`/`'deleted'`, `'restore'`/`'restored'`, `'cloned'`/`'cloning'`, `'index'`.

**Before writing one, read the callback rules** in
[recordbrowser-recipes.md](recordbrowser-recipes.md) — especially that `display`/`view` get
the raw stored record while `add`/`edit` get a form submission, and that `update_record()`
merges omitted fields back in before your callback ever sees them.

### 11.6 ACL

```php
Utils_RecordBrowserCommon::add_access($tab, $action, $clearance, $crits=array(), $blocked_fields=array());
```

One rule per call (`$action` ∈ `view`/`add`/`edit`/`delete`/`print`/`export`); multiple
calls with the same `$action` are **OR'd** together. `$clearance` is one or more strings
checked against `Base_AclCommon::get_clearance()` (`'ADMIN'`, `'SUPERADMIN'`, or a custom
string registered via `add_clearance_callback()`, e.g. `'ACCESS:employee'`). `$crits`
restricts *which records* the rule applies to; `$blocked_fields` hides specific fields even
on an otherwise-visible record.

`install_new_recordset()` auto-adds `print`/`export` rules restricted to `SUPERADMIN` —
override those explicitly if you want broader access. Check at runtime with
`Utils_RecordBrowserCommon::get_access($tab, $action, $record = null)`.

### 11.7 Custom per-table templates

`Utils_RecordBrowserCommon::set_tpl($tab, $filename)` replaces the generic `View_entry.tpl`
auto-column layout with your own Smarty template for that table's record view.
`View_entry.css`'s classes still load, so you can reuse them (`.epesi-rv-columns`,
`.column`, `.epesi-rv-row`, `.label`, `.data`):

```php
Utils_RecordBrowserCommon::set_tpl('phonecall',
    Base_ThemeCommon::get_template_filename(CRM_PhoneCallInstall::module_name(), 'default'));
```

That resolves through the theme layer to the module's own
`theme_adminltedark/default.tpl`. Pass an empty string to clear an override back to the
generic template. A separate mechanism, `set_field_template($tab, $fields, $template)`,
overrides a *single field's* markup rather than the whole view.

### 11.8 Addons — extra tabs on another table's record view

```php
Utils_RecordBrowserCommon::new_addon($tab, $module, $func, $label);   // $module: slash form
Utils_RecordBrowserCommon::delete_addon($tab, $module, $func);
```

`Utils_RecordBrowser::view_entry()` reads `recordbrowser_addon` for the table being viewed,
then for each enabled row:

```php
$addon_instance = $this->init_module($row['module']);           // fresh child instance
$this->display_module($addon_instance, array($this->record, $this), $row['func']);
```

So `$func` must be declared on the **instance** (`Module`) class named by `$module` — not
the `Common` class — with this exact signature:

```php
public function my_addon_func($record, $rb_parent) {
    // $record    = the record currently being viewed
    // $rb_parent = the Utils_RecordBrowser instance rendering it
}
```

A complete example is in `modules/Custom/Tutorial/Tutorial_0.php`; the oldest real one is
`Tests_Bugtrack::company_bugtrack_addon()`. See
[recordbrowser-recipes.md](recordbrowser-recipes.md) for which columns to pass.

**Ownership matters for `uninstall()`.** If your addon is registered on a table *you also
own*, `uninstall_recordset($tab)` deletes its `recordbrowser_addon` row automatically. If
you register an addon on a table *another* module owns, your own `uninstall()` must call
`delete_addon()` explicitly.

### 11.9 Enforcing per-field uniqueness

There is no `'unique' => true` core field option — RecordBrowser has no notion of a unique
constraint. Every real example is a hand-rolled `QFfield_callback` + `$form->addFormRule()`
pair.

**Why `addFormRule()` (whole-submission) rather than the per-field `addRule()`:** a
per-field rule only receives *that field's* own submitted value, with no way to reach the
record's own `id` — needed to exclude itself when editing, or every edit flags itself as a
duplicate. `addFormRule()`'s callback gets the **entire submitted `$data` array**, the only
hook with enough context.

**Why static properties rather than a closure:** this is old-style QuickForm using
`array($class, $method)` callables, and the rule callback's signature is fixed by the
framework (`callback($data)`), so there is no parameter slot for "which field" or "which id
to exclude". The wrapper stashes that context into static properties immediately before
registering the rule. This works only because exactly one form validates per request —
don't reuse the shape for anything that could validate two forms of the same type at once.

**The recipe:**

1. Give the field its own `QFfield_<name>()` wrapping whatever its normal type-default
   would build.
2. Inside it, only for `$mode == 'add' || $mode == 'edit'`, call
   `add_rule_<name>_unique($form, $field, $rid)` — stash `$field` and `$rid`
   (`$rb_obj->record['id'] ?? null`) into two static properties, then
   `$form->addFormRule(array(static::class, 'check_<name>_unique'))`.
3. Write `check_<name>_unique($data)`: bail to `array()` if the submitted value is empty;
   otherwise
   `DB::GetOne('SELECT id FROM <tab>_data_1 WHERE active=1 AND f_<field> '.DB::like().' %s AND id!=%d', array($value, $rid ?: -1))`;
   return `array($field => __('... duplicate found: %s', [...]))` on a hit, `array()` if
   clear.

`DB::like()` is what makes the match portably case-insensitive (MySQL `LIKE` vs PostgreSQL
`ILIKE`); a crits-based `get_records()` lookup would lose that guarantee.
`CRM_Contacts`'s Email field is the canonical working example.

## 12. QuickForm

`modules/Libs/QuickForm/FieldTypes/` holds this codebase's custom QuickForm element types
(`autocomplete`, `autoselect`, `automulti`, `multiselect`, `epesi_checkbox`,
`epesi_advcheckbox`). Each is registered with one line of top-level code in its owning
module:

```php
$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['multiselect'] =
    array('modules/Libs/QuickForm/FieldTypes/multiselect/multiselect.php', 'HTML_QuickForm_multiselect');
```

Called exactly like stock QuickForm: `$form->addElement('text', $field, $label, ...)`.

You will rarely register a new element type — RecordBrowser's field types (§11.2) cover
ordinary needs. Reach for a raw QuickForm element only when building a standalone form
outside RecordBrowser entirely (an admin settings screen via `Libs_QuickForm::add_array()`
/ `display_as_row()` / `display_as_column()`).

One special case: `'crits'` is **not** a real registered element type — a
`$form->addElement('crits', ...)` call is intercepted inside `Libs_QuickForm::__call()` and
redirected to build a `Utils_RecordBrowser_QueryBuilderIntegration` instead. Don't try to
register it as an ordinary type.

## 13. Templates (Smarty 2)

Smarty **2** is the template engine, vendored and patched in place under
`modules/Base/Theme/smarty/`. Four gotchas come with that version:

- **Callback registration cannot take a Closure.** `register_modifier()` and friends embed
  the callback into the compiled template cached to disk, which only serializes a string or
  a `[class, method]` array. A closure fails silently at *render* time ("Object of class
  Closure could not be converted to string"), not at compile time.
- **A literal `{` or `}` inside a raw `<script>` block in a `.tpl` is parsed as a Smarty
  delimiter** and fails to compile. Wrap inline JS in `{literal}...{/literal}`.
- **Smarty's dot notation has no isset guard.** `{if $form_data.errors.host}` throws a PHP
  warning the first time that key does not exist — and under `REPORT_ALL_ERRORS` the first
  warning blanks the whole module's output. Backfill every expected key first
  (`$form_data['errors'][$f] ??= '';`).
- **A GET-method form whose submit button collides with a same-named forwarded `$_GET` key**
  throws "element already exists" — exclude the submit parameter name from any generic
  query-string-forwarding loop.

## 14. Checklist for a new RecordBrowser-backed module

1. `console.php dev:module:create <Vendor/Name> --require Utils/RecordBrowser`
2. In `<Name>Install.php::install()`: build `$fields`, call `install_new_recordset()`, then
   `set_caption()` and `add_access()` (at minimum view/add/edit/delete), plus
   `simple_setup()` if you want it in the Simple view of Administration → *Modules
   Administration & Store*.
3. In `<Name>Install.php::uninstall()`: call `uninstall_recordset()` — and explicitly
   reverse anything else `install()` did that is not part of the recordset (ACL permissions
   registered outside RecordBrowser, `CommonData` arrays via
   `Utils_CommonDataCommon::remove()`, addon registrations via `delete_addon()`).
4. In `<Name>Common_0.php`: add `menu()` so the module is reachable, and `bootstrap_icon()`
   for a real sidebar icon.
5. In `<Name>_0.php::body()`: init and display the RecordBrowser instance — set any
   `set_filters_defaults()`/`set_default_order()`/`set_defaults()` first.
6. `php -l` every file, with the right PHP binary. There is no automated test suite —
   verify by installing the module and using it in a browser.
7. **Uninstall it once**, and confirm no orphaned tables remain
   (`SHOW TABLES LIKE '<tab>%'` returns nothing).

## 15. Where to look next

- `modules/Custom/Tutorial/` — the companion module to this document, exercising every
  field type in §11.2 in one real installable table.
- `modules/Tests/Bugtrack/` — smallest real complete RecordBrowser module, and the only
  side-by-side of the old array-based `install_new_recordset()` and the newer OO
  `RBO_Recordset` API.
- `modules/CRM/PhoneCall/` — richest real field-type usage in one install file.
- `modules/Tests/RecordBrowser/` — every `RBO_Field_*` type, plus field-level ACL.
- `modules/Tests/Callbacks/` — the only end-to-end demo of the request/navigation model
  (`create_callback_href`, `is_back()`, `create_back_href($n)`, `Base_Box::push_main()`,
  and the return-`true`/`false` "render instead of" vs "fall through" semantics). No prose
  covers this.
- `modules/Tests/QuickForm/` — the element-type catalogue.
- [recordbrowser-recipes.md](recordbrowser-recipes.md) — evolving a recordset that already
  holds data, and the callback rules.
- [theming-and-frontend.md](theming-and-frontend.md) — icons, CSS placement, JavaScript
  conventions.
