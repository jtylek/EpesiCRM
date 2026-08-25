# Standalone entry points: admin/, update.php, check.php, setup.php

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

## `anonymous_setup` — a real security issue found and fixed here (2026-07-29)

This install has the `anonymous_setup` Variable set to `1`. `Base_AclCommon::
i_am_sa()`/`i_am_admin()` are written as
`Variable::get('anonymous_setup') || <real admin check>` — under this mode they
return `true` for **any visitor, logged in or not**. That's deliberate framework
behavior for a wide-open demo/dev instance, not a bug in `Base_Acl` — but
`admin/AdminIndex.php`, `update.php`, and `check.php` were all trusting
`i_am_sa()`/`i_am_admin()` (or `SimpleLogin::form()`, which structurally can't
even produce a login form once anonymous_setup is on) as their own access gate.
That meant all three admin/maintenance surfaces were reachable by a completely
anonymous visitor on any install with `anonymous_setup=1`.

**Fixed**: all three now require a real logged-in session
(`SimpleLogin::force_login_form()`/`force_login_page()` — new methods that
render the login form without the anonymous_setup bypass) and check
`Base_AclCommon::get_admin_level()` directly (queries the DB, ignores the
bypass) instead of `i_am_sa()`/`i_am_admin()`. If you're auditing access control
anywhere else in the codebase, treat `i_am_sa()`/`i_am_admin()` as
**untrustworthy for gating truly sensitive surfaces** on any instance where
`anonymous_setup` might be on — prefer `get_admin_level()` directly there.

## `check.php` used to do real work on every view (fixed 2026-07-29)

It was unconditionally running `Base_LangCommon::update_translations()`
(rescans every module's `lang/`, rewrites all 37 `data/Base_Lang/base/*.php`
files) and `ModuleManager::create_load_priority_array()` on *every* view, past
login — almost certainly the cause of historical "check.php hangs Apache"
reports for what's supposed to be a read-only compatibility report. Both calls
were removed; `get_orphaned_modules()` (the one thing check.php actually needs)
reads the DB directly.

## FirstRun wizard needed its own theme-fallback fix (2026-07-30)

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
