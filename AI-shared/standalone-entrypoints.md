# Standalone entry points: admin/, update.php, check.php, setup.php

> **Status:** REFERENCE - admin/, update.php, check.php, setup.php: the PHP/view split, the
> Smarty 2 traps that come with it, and how these surfaces gate access. The pre-split version
> is at `AI-private/archive/standalone-entrypoints.md`.

These four run outside (or before) the normal module/theme pipeline — they must
work pre-install, pre-login, or without a full session, so none of them go
through `Base_ThemeResolver`/`Base_ThemeCommon::init_smarty()` the way ordinary
Epesi modules do. As of late July 2026 all four were split from inline
`print()`/string-built HTML into PHP-logic + Smarty-template pairs and restyled
with AdminLTE, following one consistent pattern:

- `admin/` → `admin/AdminSmarty.php` + `admin/templates/*.tpl`
- `update.php` → `UpdateSmarty` + `include/templates/update/*.tpl`
- `check.php` → same `check_results.tpl`, reused standalone and embedded inside
  setup.php's compatibility-check step
- `setup.php` → `setuptheme/SetupSmarty.php` + `setuptheme/*.tpl`

The shared Smarty-array-form renderer used by several of these
(`EpesiSmartyRenderer` + `HTML_QuickForm_Renderer_EpesiArray`) now lives at
**`include/EpesiSmartyRenderer.php`** / `include/EpesiArray.php` (moved from
`modules/Libs/QuickForm/Renderer/`). **`modules/Libs/QuickForm` itself is NOT legacy code and was not
touched** — only the renderer files moved out of it; don't confuse the two.

## Smarty 2 gotchas hit repeatedly in this work

- Smarty 2's `register_modifier()`/callback registration **cannot take a
  Closure** — its compiler embeds the callback into the compiled template file
  cached to disk, which only serializes a string or `[class, method]` array.
  Using a closure fails silently at *render* time ("Object of class Closure
  could not be converted to string"), not at compile time.
- A literal `{`/`}` inside a raw `<script>` block placed directly in a `.tpl`
  gets parsed as a Smarty delimiter and fails to compile. Wrap inline JS in
  `{literal}...{/literal}`.
- Smarty's dot notation has no isset-guard — `{if $form_data.errors.host}`
  throws a PHP 8 warning (which blanks the module's output under
  `REPORT_ALL_ERRORS`) the first time that key doesn't exist yet. Backfill
  every expected key first (`$form_data['errors'][$f] ??= '';`).
- A GET-method form whose submit button collides with a same-named forwarded
  `$_GET` key throws "element already exists" — exclude the submit param name
  from any generic query-string-forwarding loop.

## `anonymous_setup`: the bootstrap flag, and how it is kept out of the ACL primitives

`anonymous_setup` is a bootstrap flag stored as a `Variable`. It exists because `setup.php` and
FirstRun have to install modules and write configuration **before any account exists to
authenticate as** — the classic chicken-and-egg: you cannot require an admin login before there
is an admin.

It used to be folded directly into `Base_AclCommon::i_am_sa()`/`i_am_admin()`, which made both
return `true` for **any visitor** on an install where it was set. That is why `admin/`,
`update.php` and `check.php` each had to be special-cased *around* the primitives rather than
fixed once — every call site inherited the bypass and had to know to opt out.

**As of 2026-09-02 the primitives don't consult it at all**, so `i_am_admin()` and `i_am_sa()`
mean what they say. Two things replaced it:

- **`Base_AclCommon::anonymous_setup_active()`** — read this, never
  `Variable::get('anonymous_setup')`. It ignores the flag once a real super-admin
  (`user_login.admin=2`) exists, so the bootstrap window cannot outlive itself, and treats a
  missing row as "off" rather than throwing. Only two callers remain, both UI gates for the
  bootstrap window itself: `Base_SetupCommon::admin_access()` and `SimpleLogin::form()`.
- **`Base_AclCommon::begin_bootstrap_install()` / `end_bootstrap_install()`** — a process-local
  elevation, never persisted, set from exactly one place: `FirstRun::done()`, around
  `ModuleManager::install('Base')`, the one install step that runs before the super-admin
  exists. **No request can turn it on.** Don't add a second caller without a very good reason;
  if you want "is this install still bootstrapping?" for a *UI* gate, that's
  `anonymous_setup_active()`.

`admin/AdminIndex.php`, `update.php` and `check.php` keep their own stronger gate regardless:
`SimpleLogin::force_login_form()`/`force_login_page()` (which render a login form without the
bypass) plus a direct `Base_AclCommon::get_admin_level()` check.

**For a genuinely sensitive admin or maintenance surface, still prefer `get_admin_level()`** —
it queries the DB and depends on nothing else. `i_am_admin()`/`i_am_sa()` are now trustworthy
for ordinary in-app authorization.

## `check.php` is meant to be read-only — keep it that way

It used to unconditionally run `Base_LangCommon::update_translations()`
(rescans every module's `lang/`, rewrites all 37 `data/Base_Lang/base/*.php`
files) and `ModuleManager::create_load_priority_array()` on *every* view, past login — almost
certainly the cause of historical "check.php hangs Apache" reports for what is supposed to be
a read-only compatibility report. Both calls were removed; `get_orphaned_modules()`, the one
thing check.php actually needs, reads the DB directly.

## Anything running before `Base` installs needs its own theme fallback

`modules/FirstRun/FirstRun_0.php` (the post-setup admin-creation wizard) runs
**before** the `Base` module installs — at that point
`Variable::get('default_theme', false)` is genuinely `false`, and both
`Base_ThemeCommon::get_default_template()` and `index.php`'s own duplicated
copy of that logic fell back to the literal string `'default'` (the legacy
theme), even though fresh installs set `default_theme` to `'adminlte'` once
`Base` installs. Both fallbacks were changed to `'adminlte'`. **If a
pre-install or pre-login screen ever looks unthemed despite `default_theme`
being `adminlte` in the DB, check whether it runs before Base installs first**
— same root cause, not necessarily a missing template.
