# Epesi Module Development Tutorial

> **Status:** REFERENCE - how to build an Epesi module. Paired with modules/Custom/Tutorial/.

A from-the-ground-up guide to writing modules for this codebase, grounded in the actual
source (not the framework's aspirational docs) plus the official developer tutorial at
[epesi.org/devtutorial](https://epesi.org/devtutorial). Written 2026-08-03. See
`CLAUDE.md` for the wider architecture (bootstrap chain, rendering, error handling) —
this file goes deep on one thing: how to author a module and a RecordBrowser-backed
data table correctly.

A **complete, working example module** built alongside this tutorial lives at
`modules/Custom/Tutorial/` — tracked in git (unlike `modules/Premium/`, which is a
separate, gitignored licensed tree — see `CLAUDE.md`/`.gitignore`), since it's
referenced directly from this document and is meant to travel with the repo. It uses
every field type documented below in one real
table (`tutorial`), plus a second lookup table (`tutorial_category`) demonstrating a
registered-datatype contact picker (§11.3) and a RecordBrowser addon tab (§11.8). Read
this document section by section, then read the actual module files — seeing the two
side by side is the fastest way to internalize the conventions.

> **A note on the official epesi.org/devtutorial site**: it's written for an older
> version of the framework and is thinner than the real code in several places (e.g.
> it doesn't mention the AdminLTE theme, `console.php` scaffolding, or the exact
> `simple_setup()`/ACL mechanics). Its directory/naming conventions and its general
> shape (Install/Common/Main classes, `_0` suffix, translations, ActionBar, RecordBrowser)
> are accurate and match this checkout. Where the two disagree, this document — verified
> against the actual code — wins. Sub-pages worth opening directly if you want the
> narrative version: `/devtutorial/creating-modules`, `/devtutorial/helloworld`,
> `/devtutorial/helloworld/usingrecordbrowserrbo`, `/devtutorial/utilsrecordbrowser`.

---

## 1. Module anatomy

A module is a PHP package under `modules/<Vendor>/<Name>/` (nesting is allowed:
`modules/CRM/Contacts/Photo/`). The module's **class name** replaces `/` with `_`:
`modules/CRM/Mail/` ⇄ class name `CRM_Mail`. This underscore-as-namespace-separator
rule (`ModulePrimitive::get_module_dir()`, `include/module_primitive.php:40`) is used
everywhere — file paths, `requires()` entries, `init_module()` calls.

Every module contributes up to **three classes**, one per file, all in the module's
own directory, named from the *last* path segment (`CRM_Mail` → base name `Mail`):

| File | Class | Extends | Purpose |
|---|---|---|---|
| `Mail_0.php` | `CRM_Mail` | `Module` | The instantiable tree-node — display/instance logic |
| `MailCommon_0.php` | `CRM_MailCommon` | `ModuleCommon` | Static/shared logic, callable without an instance |
| `MailInstall.php` | `CRM_MailInstall` | `ModuleInstall` | Schema, ACL, dependency declaration, patches |

**The `_0` suffix is the module's currently-installed version number**, not a
convention you pick — `ModuleManager::include_common()`/`include_main()`
(`include/module_manager.php:77-135`) build the literal filename
`...Common_<version>.php` / `..._<version>.php` from the `version` column of the
`modules` DB table for that module. In practice **almost every module in this
codebase has a single-element `version()` array and lives permanently at version
0** — start every new module at `_0` and don't add a `_1` unless you're doing a
genuine breaking schema/behavior migration on an already-shipped module (see §4).
The `Install.php` file has no version suffix — there's only ever one.

## 2. Scaffolding a new module

Use the console command rather than hand-writing the three files — it gets the
naming and `defined("_VALID_ACCESS")` guard right every time:

```
php console.php dev:module:create Custom/Tutorial --require Utils/RecordBrowser
php console.php dev:module:patch Custom/Tutorial "add priority field"
```

(**Correction to `CLAUDE.md`**: the actual registered command names are
`dev:module:create` / `dev:module:patch` / `dev:module:test` —
`console/Develop/CreateModuleCommand.php` etc. — not `dev:create:module`. `CLAUDE.md`
has this backwards; trust this file and the real `console.php` output over that line.)

`dev:module:create` writes exactly this (trimmed to the essential shape):

```php
// <Name>_0.php
class <Vendor_Name> extends Module {
    public function body() {
    }
}

// <Name>Common_0.php
class <Vendor_Name>Common extends ModuleCommon {
}

// <Name>Install.php
class <Vendor_Name>Install extends ModuleInstall {
    public function install() { return true; }
    public function uninstall() { return true; }
    public function requires($v) {
        return [ array('name' => '<required/module>', 'version' => 0), ... ];
    }
    public function version() { return ['0.1']; }
}
```

`dev:module:patch <module> "<title>"` scaffolds a dated stub under that module's
`patches/` directory (see §9) — you write the actual patch body yourself.

## 3. The class hierarchy

```
ModulePrimitive (abstract)          include/module_primitive.php
 ├── ModuleInstall (abstract)       include/module_install.php
 ├── ModuleCommon                   include/module_common.php
 └── Module (abstract)              include/module.php

ModuleManager                       include/module_manager.php   (static factory/registry — not part of the tree)
```

### `ModulePrimitive` — shared by all three

Key final methods every subclass inherits:
- `get_type()` — the module's class name (`CRM_Mail`).
- `get_module_dir()` — `'modules/'.str_replace('_','/',$this->type).'/'`.
- `get_data_dir()` / `create_data_dir()` / `remove_data_dir()` — `data/<Type>/`.
- `static module_name()` — strips a trailing `Common`/`Install` and returns the
  slash form (`CRM_MailInstall::module_name()` → `'CRM/Mail'`). **This is the
  idiomatic way to reference another module** in `requires()`/`init_module()` —
  it fails at parse time if the referenced class doesn't exist, which is a real
  safety net against typo'd module names.
- `static is_installed()` — `ModuleManager::is_installed(static::module_name()) >= 0`.

### `ModuleInstall` — three methods you must implement

```php
abstract public function install();
abstract public function uninstall();
abstract public function requires($v);
```
Optional-but-conventional (called only `if is_callable`): `version()`,
`upgrade_N()`/`downgrade_N()`, `info()`, `simple_setup()`.

### `ModuleCommon` — a lazily-upgrading singleton

`Instance()` is seeded by `ModuleManager::include_common()` with the module's
string name, then on first real call upgrades itself into an actual instance —
this is what makes `Some_ModuleCommon::Instance()` work. You rarely need
`Instance()` directly; most `Common` classes are used purely for their **static**
methods (`CRM_ContactsCommon::get_my_record()`, etc.) — that's the normal usage
pattern, not instantiation.

### `Module` — the instantiable tree node

Constructor is `final` and takes a Pimple `Container` — **don't call it
directly**; use `init_module()`/`pack_module()` (§5). Methods you'll actually use:

- **`get($name)`** — DI container accessor. **Currently vestigial**: nothing in
  this codebase registers anything into the container (`ModuleManager::
  get_container()` creates it empty and nothing ever populates it) — calling
  `$this->get('anything')` today throws Pimple's `UnknownIdentifierException`.
  Don't reach for this; it's infrastructure for a direction that was never
  finished. `twig_display()`/`twig_render()` (also on `Module`) are dead code for
  the same reason — Smarty 2 is the real template engine.
- **Module variables** — session-scoped instance state, see §6.
- **Href/callback machinery** — `create_href()`, `create_callback_href()` (wraps
  a closure/callback into a URL that replays it on click — this is how almost
  every button/link in the app works, since the whole app is an AJAX-push SPA,
  not REST), `create_back_href()`, `create_confirm_href()`/`create_confirm_callback_href()`
  (styled confirm modal — see `CLAUDE.md`'s Rendering section).
- **`register_method()`** — lets other code graft an ad-hoc method onto every
  `Module` instance via `__call()`. Used once, for a deprecated `$module->t()`
  translation shortcut (§10) — not a pattern to add to yourself.

### `ModuleManager` — the tree builder and lifecycle owner

Static-only; owns `install()`/`uninstall()`/`upgrade()`/`downgrade()` (against
the `modules` DB table), `load_modules()` (boot-time class loading in dependency
order), `list_modules()` (scans `modules/` for any directory containing a
`<Name>Install.php` — **this is how a brand-new module is discovered at all**,
no manifest/registry file to edit), and `call_common_methods()` (§7's "opt in by
declaring a method" convention).

## 4. Install lifecycle: `requires()`, `version()`, upgrade/downgrade

`requires($v)` returns dependencies as `array('name'=>..., 'version'=>...)`,
varying by target version `$v` if needed:

```php
public function requires($v) {
    return array(
        array('name'=>Base_AclInstall::module_name(), 'version'=>0),
        array('name'=>Utils_RecordBrowserInstall::module_name(), 'version'=>0),
    );
}
```
`ModuleManager::check_dependencies()`/`satisfy_dependencies()` walk this
recursively at install time, auto-installing required modules first (throws on
a real dependency cycle). The same data drives `create_load_priority_array()`,
which orders every module's boot-time class loading.

`version()`'s **array length**, not its string contents, is what matters —
`ModuleManager::install()` treats `count($version_ret) - 1` as the target
version index (0-based). `install()` always runs first (that's version 0); if
`version()` returns more than one element, `upgrade_1()`, `upgrade_2()`, …
run in sequence afterward, with `downgrade_N()` as the rollback path on
failure. **Real-world modules essentially never do this** — grep found exactly
one example in the whole tree (`modules/Tests/Tooltip/`, a deliberate fixture
demonstrating the mechanism) — every shipped module has a single-element
`version()` and lives at `_0` forever. Don't build multi-version machinery into
a new module unless you're genuinely shipping a breaking change to an
already-live module later.

### A complete, real `install()`/`uninstall()` pair

(`modules/Apps/Shoutbox/ShoutboxInstall.php`, trimmed):
```php
public function install() {
    $ret = DB::CreateTable('apps_shoutbox_messages','
        id I4 AUTO KEY,
        base_user_login_id I4 NOTNULL,
        message X,
        posted_on T DEFTIMESTAMP,
        deleted I1',
        array('constraints'=>', FOREIGN KEY (base_user_login_id) REFERENCES user_login(ID)'));
    if (!$ret) { print('Unable to create table.<br>'); return false; }
    Base_ThemeCommon::install_default_theme($this->get_type());
    Base_AclCommon::add_permission(_M('Shoutbox'), array('ACCESS:employee'));
    return $ret;
}
public function uninstall() {
    DB::DropTable('apps_shoutbox_messages');
    Base_AclCommon::delete_permission('Shoutbox');
    Base_ThemeCommon::uninstall_default_theme($this->get_type());
    return true;
}
```
**Schema creation** goes through ADOdb's portable data dictionary via thin
wrappers `DB::CreateTable($name, $cols, $opts)` / `DB::DropTable($name)`
(`include/database.php`) — generic-type column syntax (`I4`=int, `C(n)`=varchar,
`X`=text/blob, `T`=timestamp, `I1`=tinyint, `AUTO`/`KEY`/`NOTNULL` modifiers),
portable across MySQL/PostgreSQL. For queries elsewhere, use `DB::Execute()`/
`DB::GetOne()`/`DB::GetAssoc()` etc. with `%s`/`%d` placeholders (parameterized —
never interpolate raw values into SQL strings).

**ACL registration** is a direct call from `install()`/`uninstall()`, not a
separate file: `Base_AclCommon::add_permission($name, ...$clearance_rules)` /
`delete_permission($name)`. Note `_M()`, not `__()`, for the label — `_M()`
marks a string as translatable without translating it right now (useful at
install time, before a request-scoped locale is necessarily meaningful) — see
§10.

**Critically: `ModuleManager::uninstall()` does NOT drop your tables for you.**
It handles the generic bookkeeping (checks nothing else still requires this
module, calls your `uninstall()`, deletes the `modules` table row, removes
`data/<Type>/`, clears caches) — but *your* `uninstall()` is the only thing
that drops the tables/rows/ACL entries *your* `install()` created. Forgetting
this is the single most common way a module leaves orphaned tables behind
after being removed. If you use RecordBrowser, `Utils_RecordBrowserCommon::
uninstall_recordset($tab)` does the equivalent full cleanup for you (§11) —
call it from your own `uninstall()`.

## 5. Building the module tree: `init_module()` / `pack_module()`

`ModuleManager::new_instance()` is the actual constructor call — never call it
yourself. `Module::init_module($module_type, $args=null, $name=null,
$clear_vars=false)` is the wrapper you call: it normalizes the type name,
instantiates via `new_instance()`, and — if the new module defines a
`construct(...$args)` method — calls it with `$args`. `pack_module(...)` is
`init_module()` + an immediate `display_module()` in one call; most module
bodies just call `pack_module()`.

```php
// Wrap a RecordBrowser instance, fixed instance id 'contact' (not auto-numbered)
$this->rb = $this->init_module(Utils_RecordBrowser::module_name(), 'contact', 'contact');
$this->display_module($this->rb);

// init + display in one call, forwarding display args + a function name
$this->m = $this->pack_module($page[0], $page[2], $page[1], $page[3]);
```
Naming: `CRM_Mail` ⇄ `modules/CRM/Mail/`, class files inside named from the last
segment (`Mail_0.php`). A child module's position in the tree is
`get_path()` = parent's path + `/<Type>|<instance_id>` — this path is also the
key module variables are stored under (§6).

## 6. State: module variables vs. `Variable::` vs. per-user settings vs. History

Four different scopes exist — know which one you actually want:

| Mechanism | Scope | Backing | Typical use |
|---|---|---|---|
| `$this->set_module_variable($n,$v)` / `get_module_variable($n)` | This module **instance**, this browser **tab** | `$_SESSION['client']['__module_vars__'][get_path()]` | UI state for the current screen (selected filters, current page, etc.) |
| `$this->set_shared_module_variable()` | Every instance of this module **type**, this tab | same session tree, keyed by `get_type()` instead of `get_path()` | state that should be shared across multiple instances of the same module in one tree |
| `Variable::get($name)` / `set($name,$value)` | App-wide, every user | `variables` DB table | global config (`default_theme`, `default_module`, `installed_langs`) |
| `Base_User_SettingsCommon::get($module,$name,$user=null)` | Per logged-in **user**, any tab/session | `base_user_settings` DB table | a user's own preference, independent of which tab they're in |
| `History` (`include/history.php`) | Per-tab undo/redo | `history` DB table, one row per navigation step | not a get/set API — serializes/restores **all** module variables as one blob per back/forward step |

**Per-tab scoping mechanism, if you need to reason about it**: `$_SESSION['client']`
is loaded from a storage record keyed by the browser tab's `X-Client-Id` header
(`CID`, `include/session.php`) — so module variables are genuinely per-tab, not
just per-login. This matters if you're ever debugging "my setting didn't
stick" — check you're not comparing two different tabs.

## 7. Menu / user-settings / home-page / ActionBar / icon conventions

`menu()`, `user_settings()`, `home_page()`, and several others (`help`,
`search_categories`, `tray`, `cron`, ...) all share **one mechanism**:
`ModuleManager::call_common_methods($method)` scans every installed module's
`<Type>Common` class for a method with that exact name and calls it statically
if present. **There is no separate registration call — a module opts in simply
by declaring the method.**

```php
// modules/CRM/Mail/MailCommon_0.php
public static function user_settings() {
    if (Utils_RecordBrowserCommon::get_access('rc_accounts', 'browse'))
        return array(__('E-mail Accounts') => 'account_manager');
    return array();
}
```
```php
// modules/CRM/Contacts/ContactsCommon_0.php
public static function menu() {
    $ret = array();
    $opts[_M('Contacts')] = array('mode'=>'contact', '__icon__'=>'contacts.png');
    if (!empty($opts)) { $opts['__submenu__'] = 1; $ret[_M('CRM')] = $opts; }
    return $ret;
}
public static function home_page() {
    return array(_M('My Contact') => array(CRM_Contacts::module_name(), 'body', array('my_contact')));
}
```
`menu()`'s return shape: nested associative array, label ⇒ either
`array('__submenu__'=>1, ...children)` or a leaf with
`__module__`/`__function__`/`__function_arguments__`/`__icon__` keys.
`home_page()`'s value shape is `[module_name, function_name, function_args]`.

**No single file "owns" a top-level group.** Multiple, unrelated modules can each
return the *same* translated label (`_M('CRM')`, `_M('Reports')`, ...) wrapped in
`array('__submenu__'=>1, ...)`, and `Base_Menu::add_menu()` (`modules/Base/Menu/
Menu_0.php`) merges every module's contribution into one accordion group purely by
matching the label string at render time, after `Base_MenuCommon::get_menus()`
(`MenuCommon_0.php`) has collected every module's `menu()` result. There is, for
example, no `CRM_0.php`/`CRMCommon_0.php` file at all — `modules/CRM/`'s top-level
`_M('CRM')` contributors (`Contacts`, `Tasks`, `PhoneCall`, `Meeting`, `Fax`,
`Calendar`) are six independent sibling modules, none more "primary" than another.
So moving a module from a top-level entry into an existing group (or vice versa) only
ever requires editing *that module's own* `menu()` — nothing under the target group's
own modules needs touching (confirmed 2026-08-13, moving `Premium_KnowledgeBase`'s
entry from top-level to a new child under "CRM").

**`menu()`'s result is cached per-session**, not recomputed every request —
`Base_MenuCommon::get_menus()` stores the merged-per-module result in
`$_SESSION['client']['__module_vars__']` (`Module::static_get/set_module_variable()`).
After editing a `menu()` method, a plain reload in an already-logged-in browser tab
won't show the change — log out and back in (or start a fresh session) to see it,
same trap as any other module-variable cache (§6).

**`bootstrap_icon()`** (AdminLTE theme only — see `AI-shared/adminlte-theme.md`)
is resolved on-demand per module rather than aggregated, via
`Base_BootstrapIcons::resolve()`, falling back to a generic window icon
(`bi-layout-text-window-reverse`) if undeclared, unless the caller supplies
its own more context-appropriate fallback:
```php
public static function bootstrap_icon() { return 'bi-envelope-fill'; }  // Bootstrap Icons class name
```

**ActionBar is a different pattern** — not a declared method, but an imperative
call made *from inside your `body()`* for the current request, appending a
button:
```php
Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
Base_ActionBarCommon::add('settings', __('Settings'),
    $this->create_callback_href($this->push_settings(...), ['Tray settings']),
    __('Click to edit tray settings'));
```
`$type` (first arg) is either a built-in icon key (`home`/`back`/`edit`/`save`/
`delete`/…) or resolved through the same `Base_BootstrapIcons` machinery on that
theme.

**`simple_setup()`** (on the `Install` class) controls whether/how your module
shows up in the **Epesi Store → Simple view** (`modules/Base/Setup/Setup_0.php::
simple_setup()`, the same screen `Base_EpesiStore` embeds):
```php
public static function simple_setup() {
    return array('package'=>_M('My Package'), 'version'=>'1.0', 'icon'=>true, 'core'=>0);
}
```
- Return `false` (or omit the method) → hidden from Simple view (Advanced/table
  view still lists it).
- Return `true` → shown under an "Uncategorized" package.
- Return a plain string → shown under a package named that string (this is
  legal and used — e.g. `Apps_ShoutboxInstall::simple_setup()` just returns
  `__('EPESI Core')`).
- Return an array → `package` (required, groups modules that install/uninstall
  together as one row), `option` (sub-grouping within a package), `version`,
  `icon` (bool — if true, looks for `package-icon.png` next to the module),
  `url`, `core` (`1` = treated as core, cannot be uninstalled from this screen).
- All modules sharing the same `package` name install/uninstall **together** as
  one row/button in Simple view.

One practical gotcha: the Simple/Advanced view's module list is cached in the
`available_modules` DB table, refreshed only when empty or when an admin clicks
**"Rebuild modules database"** in the Setup screen — a brand-new module you just
added to `modules/` won't appear until that button is clicked (or the table is
otherwise empty).

## 8. Patches

A module's `patches/` directory has **no manifest** — it's scanned by
convention (`PatchUtil::list_for_module()`, `include/patches.php`). Filename
convention: `YYYYMMDD_short_description.php`. **A patch's identity is its file
path, not its content** — editing an already-applied patch is silently
ignored; ship a new file for any further change (see `AI-shared/`'s own
cross-reference to this in the codebase's general conventions). On a brand-new
install, all patches that already exist at install time are marked applied
without running — only patches added *after* a site went live actually execute
there. Patch bodies typically call `PatchUtil::db_add_column()`/
`db_drop_column()`/`db_alter_column()` (also ADOdb-dictionary-backed) or
straight `DB::Execute()`. Scaffold with `console.php dev:module:patch <module>
"<title>"`.

**While a module is still being actively developed and hasn't shipped
anywhere else, don't write a patch for every schema/field change** — just
edit `install()`/`uninstall()` directly and uninstall+reinstall the module
through Setup/Epesi Store to pick up the change. Patches exist to carry a
fix to installs that already ran the old `install()` and can't be
uninstalled/reinstalled without losing real data; a module with no such
installs yet has nothing to carry a patch *to*. Writing patches during this
phase just means maintaining two places (the patch *and* `install()`, since
a fresh install still needs the current `install()` to reflect the final
shape) for no real benefit. Switch to patches once the module is genuinely
live somewhere and reinstalling would mean losing real records.

## 9. Translations

```php
function __($string, $arg2=array())  { ... }  // translate now
function _V($string, $arg2=array())  { ... }  // translate a value that was previously marked with _M()
function _M($string, $arg2=array())  { ... }  // MARK for translation without translating yet
```
(`modules/Base/Lang/LangCommon_0.php`). `__()` behaves like `sprintf` via
`vsprintf` on the translated string. Use `_M()` for labels built at
install/boot time (menu keys, ACL permission names, `simple_setup()` package
names) where a translated string wouldn't yet make sense out of request
context — the original English string passes through unchanged, but stays
discoverable by translation tooling; translate it for real at display time with
`_V()`.

Language files: `modules/<Module>/lang/<code>.php`, plain PHP:
```php
<?php
global $translations;
$translations['Projects']='Projekty';
$translations['Due Date']='Termin';
```
An empty string value means "not yet translated" (falls back to the English
key). Per-instance custom overrides (entered via the admin Translate screen)
are never written into `modules/` - they live at
`data/Base_Lang/custom/<module>/<code>.php` (gitignored, created on first
write by `Base_LangCommon::append_custom()`), keeping `modules/` pure shipped
source.

## 10. Adding an admin-facing icon, and other small conventions

- Every convention-method your module declares (`menu()`, `user_settings()`,
  `home_page()`, `bootstrap_icon()`) lives on the **`Common`** class, as a
  `public static` method — never on the instance (`Module`) class.
- `_M()` for any label computed outside of a live request context (install
  time, ACL permission names); `__()` everywhere else.
- Prefer `Module::module_name()` symbolic references
  (`Utils_RecordBrowserInstall::module_name()`) over hardcoded string module
  names — it fails fast (at parse/autoload time) if the class doesn't exist.

## 11. RecordBrowser: the generic data-grid/CRUD framework

Most business modules (Contacts, Companies, Tasks, PhoneCall, ...) don't hand-roll
a table screen — they register a table's shape with `Utils_RecordBrowserCommon`
and wrap a `Utils_RecordBrowser` instance in their own `Module`. This is almost
always the right starting point for a new data-driven module.

### 11.1 Registering a table

```php
// <Module>Install.php::install()
$fields = array(
    array('name'=>_M('Project Name'), 'type'=>'text', 'required'=>true, 'param'=>'64', 'visible'=>true),
    array('name'=>_M('Company Name'), 'type'=>'select', 'required'=>true, 'param'=>array('company'=>'Company Name')),
    array('name'=>_M('Due Date'),     'type'=>'date',   'required'=>true),
    array('name'=>_M('Status'),       'type'=>'commondata', 'param'=>'Bugtrack_Status'),
    array('name'=>_M('Description'), 'type'=>'long text'),
);
Utils_RecordBrowserCommon::install_new_recordset('bugtrack', $fields);
Utils_RecordBrowserCommon::set_caption('bugtrack', _M('Bugtrack'));
Utils_RecordBrowserCommon::set_icon('bugtrack', Base_ThemeCommon::get_template_filename('Tests/Bugtrack', 'icon.png'));
Utils_RecordBrowserCommon::add_access('bugtrack', 'view',   'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('bugtrack', 'add',    'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('bugtrack', 'edit',   'ACCESS:employee');
Utils_RecordBrowserCommon::add_access('bugtrack', 'delete', array('ACCESS:employee','ACCESS:manager'));
```
```php
// <Module>Install.php::uninstall()
Utils_RecordBrowserCommon::uninstall_recordset('bugtrack');
```
(Full real examples: `modules/Tests/Bugtrack/BugtrackInstall.php` — smallest
complete one; `modules/CRM/PhoneCall/PhoneCallInstall.php` — richest, uses
most of the field vocabulary in one place.)

**What `install_new_recordset()` actually creates**: `<tab>_field` (column
metadata), `<tab>_data_1` (the real row storage — `f_<field_id>` per column),
`<tab>_callback` (registered display/QuickForm-element callbacks per field),
`<tab>_access`/`_access_clearance`/`_access_fields` (ACL rules), plus rows in
the shared `recordbrowser_table_properties`/`_processing_methods` tables. Two
fields are seeded implicitly and never declared by you: `id` (`foreign index`)
and `General` (`page_split`, the first tab/page divider in the edit form).
`uninstall_recordset($tab)` (§4's uninstall note) drops every one of these —
call it from your module's `uninstall()` and you're done; no field-by-field
cleanup needed.

Field names are normalized to a column id via
`preg_replace('/[^|a-z0-9]/','_',strtolower($name))` — `_M('Due Date')` becomes
column `f_due_date`. You reference a field by its *display name* string
(`'Due Date'`) in most APIs; RecordBrowser resolves the id internally.

### 11.2 Full field-type vocabulary

This is the authoritative list (`Utils_RecordBrowserCommon::
get_default_QFfield_callback()`, `RecordBrowserCommon_0.php`) — every type has a
real `QFfield_<type>()` method building its form widget and an entry in
`actual_db_type()` mapping it to a real column type.

| `type` | Storage | Form widget | Notes |
|---|---|---|---|
| `text` | `C(param)` varchar | text input | `param` = max length |
| `long text` | `X` (blob/text) | textarea | BBCode-optimized on save |
| `integer` | `I4` | text input | numeric-only rule |
| `float` | `F` | text input | numeric rule |
| `currency` | `C(128)` (amount+code encoded) | amount + currency `<select>` | options from `Utils_CurrencyFieldCommon` |
| `checkbox` | `I1` | checkbox | forced values `('0','1')` |
| `date` | `D` | date picker | |
| `timestamp` | `T` | date+time picker | `param` = minute increment |
| `time` | `T` | time picker | `date=>false` variant of timestamp widget |
| `commondata` | `C(128)` | `<select>` from a registered `CommonData` array | `param` = array-id string |
| `select` | `I4` (single target recordset) or `X` (multiple) | `<select>` (auto-escalates to `autoselect` past ~50 options) | `param` points at another recordset — see §11.3 |
| `multiselect` | `X` (token list) | dual-listbox (auto-escalates to `automulti` past ~50 options) | same `param` grammar as `select` |
| `autonumber` | `C(len)` | read-only static text | `param` = `"prefix__padlength__padchar"`; **auto-populated from the new row's real `id` right after insert** — you never set this yourself |
| `file` | `X` (JSON list of `Utils_FileStorage` ids) | Dropzone upload widget | needs `Utils_FileStorage` (+`Utils_FileUpload`) installed |
| `hidden` | `param` as a raw SQL type fragment, or no column if `param` is empty | hidden input | for module-managed values the user never edits directly — set via a processing callback (§11.5) |
| `calculated` | same as `hidden` | read-only static text | give it a real (if unused) column so `get_val()`'s `array_key_exists` check passes, then drive its displayed value entirely from a `display_callback` reading *other* fields in the record |
| `page_split` | none (pseudo-type) | tab/page divider | never holds data; purely organizes the edit form into pages |

**Don't add a `page_split` speculatively for a module that currently has only
one section**, even if you already know more sections are coming later. A
brand-new recordset already gets an implicit unnamed first page (the seeded
`General` field mentioned in §11.1) — a solo explicit `page_split` with every
other field under it (found 2026-08-06, `modules/Premium/Grants/`) produced a
broken install that had to be fixed by removing the field and
uninstalling/reinstalling. Add `page_split` fields explicitly, one at a time,
at the point a genuine second/third/... section is actually being added to an
already-working module — not in advance of the section existing.

**Extending beyond this list**: `Utils_RecordBrowserCommon::register_datatype($type,
$module, $func)` registers a callback that *rewrites* a custom type name into one
of the core types above before storage — this is the supported extension point,
not a second storage layer. Real example: CRM's `crm_contact`/
`crm_company_contact` types are pure sugar over `select`/`multiselect` with
baked-in formatting/crits callbacks aimed at the `contact`/`company` recordsets
(`modules/CRM/Contacts/ContactsCommon_0.php::crm_contact_datatype()`).

### 11.3 `select`/`multiselect` — pointing at another recordset

The array shorthand `'param'=>array('company'=>'Company Name')` (target tab ⇒
display column) is encoded to the string form `'company::Company Name'` at
field-registration time and decoded the same way at read time
(`Utils_RecordBrowserCommon::decode_select_param()`). Other forms:
- `'__RECORDSETS__::;<crits_callback_class>::<crits_callback_func>'` — pick from
  *any* installed recordset, filtered by a crits callback.
- `'tab::col1|col2;CritsClass::crits_func;AdvClass::adv_func'` — full form with
  a crits callback (restricting which target rows are selectable) and an
  advanced-params callback.

### 11.3b A ready-made "pick a contact" field: the `crm_contact`/`crm_company_contact` datatypes

If a field needs to reference a *person* (an employee, a manager, a customer contact)
rather than an arbitrary recordset, don't hand-roll a `select`/`multiselect` pointed at
`contact` — `CRM_Contacts` already registers `crm_contact`/`crm_company_contact` as
datatypes (§11.2's extension mechanism) that do this correctly, including the
Last Name/First Name display format:
```php
array('name'=>_M('Manager'), 'type'=>'crm_contact',
    'param'=>array(
        'field_type'=>'select',                                       // or 'multiselect'
        'crits'=>array('Custom_TutorialCommon', 'employees_crits'),    // restrict the picker
        'format'=>array('CRM_ContactsCommon', 'contact_format_no_company'),
    ), 'visible'=>true),
```
`'crits'` is a callback (`array($class, $method)`) returning a Crits-shaped array that
restricts which contacts are selectable — for "this instance's own staff", every CRM
module that needs one (`CRM_PhoneCallCommon`, `CRM_MeetingCommon`, `CRM_TasksCommon`)
defines its **own** identical copy rather than sharing a central one:
```php
public static function employees_crits() {
    return array('(company_name'=>CRM_ContactsCommon::get_main_company(),
                  '|related_companies'=>array(CRM_ContactsCommon::get_main_company()));
}
```
Follow the same pattern (define your own copy) rather than reaching for another
module's — it also avoids taking on that module as a dependency just for one helper.
This datatype requires `CRM_Contacts` in your `requires()` (it rewrites the field to
point at CRM_Contacts's own `contact` recordset).

### 11.3c Chained `commondata` fields (cascading selects, e.g. Country/Zone)

A `commondata` field's `param` can be an array of more than one element instead of a
single array-id string. The first element is still the `CommonData` array id; each
element after it is the *display name* of another field on the same form — picking a
value there repopulates this field's options from the matching nested branch of the
array, client-side, no page reload. This is what actually powers CRM_Contacts's own
Country/Zone fields (`ContactsInstall.php`) — **not** the separate `Utils_ChainedSelect`
module (that one drives a different, non-`CommonData` cascade; see `CRM_PhoneCall`'s
Customer→Phone field for a real example of it instead).

```php
// <Module>Install.php::install() — reusing the shared, already-registered
// 'Countries' tree (Data_CountriesInstall) rather than inventing a fresh one
array('name'=>_M('Country'), 'type'=>'commondata', 'param'=>array('Countries'),
    'visible'=>true, 'QFfield_callback'=>array('Data_CountriesCommon', 'QFfield_country')),
array('name'=>_M('Zone'), 'type'=>'commondata', 'param'=>array('Countries', 'Country'),
    'QFfield_callback'=>array('Data_CountriesCommon', 'QFfield_zone')),
```

The nested array itself just needs sub-arrays registered under `'<parent>/<key>'`:
`Countries` holds country names, `Countries/US` holds US states, and it nests further
still — `Countries/US/PA` holds PA counties, which is what `CRM_Contacts_County`'s
3-level Country/Zone/County chain (`County/CountyInstall.php`) points at. Field-name
resolution for the chain is by *name*, not position — a downstream field's `param`
names the exact display name of the field it depends on (`'Country'` above), so
renaming the field it chains from breaks the reference.

Real example: `modules/Custom/Tutorial/TutorialInstall.php`'s own Country/Zone
fields, added specifically to demonstrate this — reusing `Data_CountriesCommon`'s
`QFfield_country`/`QFfield_zone` verbatim rather than writing new ones, the same
way §11.3b's Manager field reuses `crm_contact` instead of hand-rolling a picker.

### 11.4 Filters

Two independent things:
- **Making a column filterable at all**: `'filter'=>true` in the field
  definition, or `Utils_RecordBrowserCommon::new_filter($tab, $col_name)`
  after the fact. The per-type filter widget (date range, numeric range, plain
  value) is built automatically from the column's type.
- **Setting default filter values** when your module embeds the browser:
  ```php
  $this->rb->set_filters_defaults(array('employees'=>$me['id'], 'status'=>'__NO_CLOSED__'));
  $this->rb->set_default_order(array('status'=>'ASC', 'date_and_time'=>'ASC'));
  ```
  `set_filters_defaults()` only takes effect the *first* time per session
  (session-persisted guard) — pass `$overwrite=true` to force it every load.

### 11.5 Processing callbacks — hooking record add/edit/view

Register once, from `install()`:
```php
Utils_RecordBrowserCommon::register_processing_callback('phonecall', array('CRM_PhoneCallCommon', 'submit_phonecall'));
```
Your callback is called as `callback($values, $mode, $tab)` for **every**
lifecycle event on that table, and must return `$values` (possibly modified) to
let processing continue — **returning `false` aborts the operation**:
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
Modes actually used, in rough chronological order per user action: `'browse'`
(rendering each grid row), `'adding'` (building New-record form defaults),
`'add'` (just before INSERT), `'added'` (right after, `id` now present),
`'display'` (rendering a saved record's view page — results are
`array_merge_recursive`'d across callbacks, handy for injecting extra
quick-action buttons), `'edit'`/`'edited'`/`'edit_changes'`, `'delete'`/
`'deleted'`, `'restore'`/`'restored'`, `'cloned'`/`'cloning'`, `'index'`
(search indexing).

**A trap worth knowing before you write one of these**: `'display'`/`'view'`
modes receive the **raw stored DB record** (real columns only); `'add'`/`'edit'`/
`'adding'`/`'editing'` receive a **form submission** (may include virtual,
non-column form fields like a checkbox name with no backing column). A callback
that checks a form-checkbox-shaped key (`$values['some_checkbox']`) while
running in display/view mode will find it simply unset — see
`AI-shared/bug-patterns.md` for a real bug this caused (`CRM_MeetingCommon`
checking a nonexistent `timeless` key instead of the real `duration==-1` signal).

### 11.6 ACL

```php
Utils_RecordBrowserCommon::add_access($tab, $action, $clearance, $crits=array(), $blocked_fields=array());
```
One rule per call (`$action` ∈ `view`/`add`/`edit`/`delete`/`print`/`export`);
multiple calls with the same `$action` are **OR'd** together (any matching rule
grants access). `$clearance` is one or more strings checked against
`Base_AclCommon::get_clearance()` (`'ADMIN'`, `'SUPERADMIN'`, or a custom
string registered via `Base_AclCommon::add_clearance_callback()`, e.g.
`'ACCESS:employee'`). `$crits` restricts *which records* the rule applies to
(a `Utils_RecordBrowser_Crits`-shaped array, e.g.
`array('(!permission'=>2, '|employees'=>'USER')`); `$blocked_fields` hides
specific fields even on an otherwise-visible record. `install_new_recordset()`
auto-adds `print`/`export` rules restricted to `SUPERADMIN` — override those
explicitly if you want broader access. Check access at runtime with
`Utils_RecordBrowserCommon::get_access($tab, $action, $record=null)`.

### 11.7 Custom per-table templates

`Utils_RecordBrowserCommon::set_tpl($tab, $filename)` replaces the generic
`View_entry.tpl` auto-column layout with your own Smarty template for that
table's record view — `View_entry.css`'s classes still load, so you can reuse
them (`.epesi-rv-columns`, `.column`, `.epesi-rv-row`, `.label`, `.data`) rather
than starting from scratch:
```php
Utils_RecordBrowserCommon::set_tpl('phonecall', Base_ThemeCommon::get_template_filename(CRM_PhoneCallInstall::module_name(), 'default'));
```
Points at `modules/CRM/PhoneCall/theme_adminlte/default.tpl` (theme-resolved).
Pass an empty string to clear an override back to the generic template. A
separate, unrelated mechanism, `set_field_template($tab, $fields, $template)`,
overrides a *single field's* markup rather than the whole record view.

### 11.8 Addons — extra tabs on another table's record view

An addon adds a tab to a table's record-view screen (alongside "Details"/"History"
etc.) that typically shows related data from a *different* table. Registration:
```php
Utils_RecordBrowserCommon::new_addon($tab, $module, $func, $label);   // $module: slash form, e.g. 'Custom/Tutorial'
Utils_RecordBrowserCommon::delete_addon($tab, $module, $func);
```
**Calling convention** (`Utils_RecordBrowser::view_entry()`, `RecordBrowser_0.php` —
reads `recordbrowser_addon` for the table being viewed, then for each enabled row):
```php
$addon_instance = $this->init_module($row['module']);           // fresh child instance, no fixed name
$this->display_module($addon_instance, array($this->record, $this), $row['func']);
```
So `$func` must be declared on the **instance** (`Module`) class named by `$module` —
not the `Common` class — with this exact signature:
```php
public function my_addon_func($record, $rb_parent) {
    // $record   = the record currently being viewed (the table $tab belongs to)
    // $rb_parent = the Utils_RecordBrowser instance rendering it
}
```
A real, complete example — showing every `tutorial` record belonging to the category
currently being viewed (`modules/Custom/Tutorial/Tutorial_0.php`):
```php
public function category_records_addon($category, $rb_parent) {
    $rb = $this->init_module(Utils_RecordBrowser::module_name(), 'tutorial');
    $args = array(
        array('category' => $category['id']),  // crits: only this category's records
        array('category' => false),             // cols: hide the now-constant Category column
        array('title' => 'ASC'),                // order
    );
    $this->display_module($rb, $args, 'show_data');
}
```
(`show_data($crits=array(), $cols=array(), $order=array(), ...)` on `Utils_RecordBrowser`
is the plain grid-browsing entry point — the 3-element `$args` array is spread as its
first three positional parameters.) The oldest real example of this exact shape is
`Tests_Bugtrack::company_bugtrack_addon()`, showing a company's Bugtrack projects.

**Ownership matters for `uninstall()`.** If your addon is registered on a table *you
also own*, `Utils_RecordBrowserCommon::uninstall_recordset($tab)` deletes its
`recordbrowser_addon` row automatically (it's a plain `DELETE ... WHERE tab=%s`) — no
extra cleanup needed, as with the `tutorial_category` example above. If instead you
register an addon on a table *another* module owns (e.g. adding a tab to CRM_Contacts's
own `company` record view, the way `Tests_Bugtrack` does), your own `uninstall()` must
call `delete_addon()` explicitly — the table you don't own has no reason to know your
module is going away.

### 11.9 Enforcing per-field uniqueness

There's no `'unique'=>true` core field option — RecordBrowser itself has no notion of
a unique constraint. Every real example of this is a **per-field, hand-rolled
`QFfield_callback` + `$form->addFormRule()` pair**, not something you turn on in the
field definition. The canonical example is `CRM_Contacts`'s Email field:

```php
// ContactsInstall.php
array('name' => _M('Email'), 'type'=>'email', 'param'=>array('unique'=>true), ...),
```
`'email'` is a registered datatype (§11.2's extension mechanism —
`CRM_ContactsCommon::email_datatype()`) that, when it sees `param['unique']`, points
`QFfield_callback` at `QFfield_unique_email()` instead of the plain `QFfield_email()`.
That wraps the normal email input, then — only in `add`/`edit` mode — calls:
```php
public static function add_rule_email_unique($form, $field, $rset=null, $rid=null) {
    self::$field = $field;   // static scratch state, see below
    self::$rset = $rset;
    self::$rid = $rid;
    $form->addFormRule(array('CRM_ContactsCommon', 'check_email_unique'));
}
public static function check_email_unique($data) {
    if (!isset($data[self::$field]) || !$data[self::$field]) return array();
    $rec = self::get_record_by_email($data[self::$field], self::$rset, self::$rid);
    if (!$rec) return array();
    return array(self::$field => __('E-mail address duplicate found: %s', [...]));
}
```
(`ContactsCommon_0.php:1351-1365`; `get_record_by_email()` runs a direct
`f_email `.DB::like().` %s AND id!=%d` query against the table, case-insensitively.)

**Why `addFormRule()` (whole-submission) instead of the per-field `addRule()`
everywhere else**: a per-field `addRule()` callback only ever receives *that field's*
own submitted value — no way to reach the record's own `id` (needed to exclude itself
when editing, or every edit would flag itself as a duplicate of itself) or any other
submitted data. `addFormRule()`'s callback gets the **entire submitted `$data` array**
instead, which is the only hook with enough context to do this check at all.

**Why static properties, not a closure carrying the field/id**: this is old-style
QuickForm using string/array callables (`array($class, $method)`), not closures — the
rule callback's signature is fixed by the framework (`callback($data)`), so there's no
parameter slot for "which field" or "which id to exclude." The `add_rule_*_unique()`
wrapper stashes that context into static properties *immediately before* registering
the rule, and the rule callback reads it back out when QuickForm invokes it later in
the same request. This only works because there's exactly one form validating at a
time per request — don't reuse this shape for anything that could validate two forms
of the same type in one request.

**Second real example**, added building `Premium_Domains` (`modules/Premium/Domains/
DomainsCommon_0.php`): `add_rule_domain_name_unique()`/`check_domain_name_unique()` on
the Domain Name field, same shape, generalized to a plain
`SELECT id FROM <tab>_data_1 WHERE active=1 AND f_<field> `.DB::like().` %s AND id!=%d`
against the field's own recordset table (domain names, like email addresses, are
conventionally case-insensitive — `DB::like()` is what makes the match
cross-DB-portable case-insensitive, MySQL `LIKE` vs PostgreSQL `ILIKE`; a plain
`Utils_RecordBrowserCommon::get_records($tab, $crits)` crits-based lookup would lose
that portability guarantee without extra work).

**Recipe for a new unique field** (no CRM_Contacts dependency needed — this pattern
is entirely reusable per-module):
1. Give the field its own `QFfield_<name>()` wrapping whatever the field's normal
   `QFfield_callback`/type-default would build.
2. Inside it, only for `$mode=='add'||$mode=='edit'`, call a small
   `add_rule_<name>_unique($form, $field, $rid)` — stash `$field` (and `$rid` from
   `$rb_obj->record['id'] ?? null`) into two static properties, then
   `$form->addFormRule(array(static::class, 'check_<name>_unique'))`.
3. Write `check_<name>_unique($data)`: bail to `array()` if the field's submitted
   value is empty; otherwise `DB::GetOne('SELECT id FROM <tab>_data_1 WHERE active=1
   AND f_<field> '.DB::like().' %s AND id!=%d', array($value, $rid ?: -1))`; return
   `array($field => __('... duplicate found: %s', [...]))` on a hit, `array()` if
   clear.

## 12. QuickForm field types (the widget layer underneath RecordBrowser)

`modules/Libs/QuickForm/FieldTypes/` holds this codebase's custom QuickForm
element types (`autocomplete`, `autoselect`, `automulti`, `multiselect`,
`epesi_checkbox`, `epesi_advcheckbox`). Each is registered with one line of
top-level code in its owning module:
```php
// modules/Libs/QuickForm/QuickForm_0.php
$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['multiselect'] = array('modules/Libs/QuickForm/FieldTypes/multiselect/multiselect.php','HTML_QuickForm_multiselect');
```
Called exactly like stock QuickForm: `$form->addElement('text', $field, $label, ...)`.
You'll rarely register a brand-new element type yourself — RecordBrowser's field
types (§11.2) cover ordinary business-module needs; reach for a raw QuickForm
element only when building a standalone form outside RecordBrowser entirely
(e.g. an admin settings screen via `Libs_QuickForm::add_array()`/
`display_as_row()`/`display_as_column()`).

One special case: `'crits'` is **not** a real registered element type — a
`$form->addElement('crits', ...)` call is intercepted inside `Libs_QuickForm::
__call()` and redirected to build a `Utils_RecordBrowser_QueryBuilderIntegration`
instead (used for advanced-filter-rule editors). Don't try to register `'crits'`
as if it were an ordinary type.

## 13. Practical checklist for a new RecordBrowser-backed module

1. `console.php dev:module:create <Vendor/Name> --require Utils/RecordBrowser`
2. In `<Name>Install.php::install()`: build the `$fields` array, call
   `install_new_recordset()`, then `set_caption()`/`set_icon()`/`add_access()`
   (at minimum `view`/`add`/`edit`/`delete`) and `simple_setup()` if you want it
   in the Store's Simple view.
3. In `<Name>Install.php::uninstall()`: call `uninstall_recordset()` — and
   explicitly reverse anything else `install()` did that isn't part of the
   recordset itself (ACL permissions registered outside RecordBrowser,
   `CommonData` arrays via `Utils_CommonDataCommon::remove()`, addon
   registrations via `delete_addon()`).
4. In `<Name>Common_0.php`: add a `menu()` method so the module is reachable,
   and `bootstrap_icon()` if you want a real sidebar icon instead of the
   fallback gear.
5. In `<Name>_0.php::body()`: `$this->rb = $this->init_module(Utils_RecordBrowser::module_name(), '<tab>', '<tab>'); $this->display_module($this->rb);` — set any
   `set_filters_defaults()`/`set_default_order()`/`set_defaults()` first.
6. `php -l` every file (with the right PHP binary — see `CLAUDE.md`'s
   Environment quirks). There's no automated test suite in this repo (see
   `CLAUDE.md`) — verify by actually installing the module through Setup/Epesi
   Store and using it in a browser.
7. Uninstall it once, too, and confirm no orphaned tables remain
   (`SHOW TABLES LIKE '<tab>%'` should return nothing) — this is the step most
   commonly skipped, and the one `ModuleManager::uninstall()` cannot do for you
   (§4).

## 14. Where to look next

- `modules/Custom/Tutorial/` — the companion module to this document,
  exercising every field type in §11.2 in one real, installable table.
- `modules/Tests/Bugtrack/` — smallest real complete RecordBrowser module.
- `modules/CRM/PhoneCall/` — richest real field-type usage in one install file.
- `modules/Tests/Tooltip/` — the one real example of the `_1` multi-version
  mechanism (§4), kept specifically to demonstrate it.
- `AI-shared/adminlte-theme.md`, `AI-shared/bug-patterns.md` — theming
  a module's screens, and RecordBrowser-specific bug shapes worth knowing.
