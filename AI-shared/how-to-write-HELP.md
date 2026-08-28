# How to write a Base_Help entry for a module (2026-08-28)

`Base_Help` (F1 / Support → Help, or the navbar Help icon on the default theme) is a
search box over guided, coach-mark-style tutorials contributed by *other* modules — it
ships with no content of its own. This doc is what it took to get the very first new
tutorial since `modules/CRM/Contacts/`'s (the only prior worked example) fully working
end to end: `Base_User_Administrator` — "Change your password", confirmed live,
click-by-click, through to completion. Four real bugs surfaced along the way, two of
them in `Base_Help`'s own shared infrastructure (not the new tutorial's content) —
read this before writing the next one, or expect to rediscover the same four.

## The mechanism, end to end

1. A module's `*Common_0.php` exposes a static `help()` method:
   ```php
   public static function help() {
       return Base_HelpCommon::retrieve_help_from_file(self::Instance()->get_type());
   }
   ```
   (identical in every module that has one — copy verbatim). `ModuleManager::
   call_common_methods('help')` (called from `modules/Base/Help/search.php` and
   `suggestions.php`) collects every module's `help()` return value across the whole
   app on every search keystroke.

2. `Base_HelpCommon::retrieve_help_from_file($module)` (`modules/Base/Help/
   HelpCommon_0.php`) reads `modules/<Module path>/help/tutorials.hlp` — a small
   line-based DSL, one entry per `[LABEL:...]` block:
   ```
   [LABEL:Change your password]
   [KEYWORDS:password change reset update credentials login]
   [CONTEXT:true]
   [STEPS:
   hover:Menu
   hover:Menu_My settings
   click:Menu_My settings_Control panel
   click:UserSettings_Password
   fill:[name=new_pass] // Enter your new password
   fill:[name=new_pass_c] // Re-enter your new password to confirm it
   prompt:[name=mail] // Your e-mail address is already filled in - only change it if you also want to update it
   fill:[name=old_pass] // Enter your current password to confirm the change
   click:ActionBar_save // Click Save
   finish:Menu_My settings_Control panel // Your password has been changed
   ]
   ```
   - `LABEL` is the search-result link text.
   - `KEYWORDS` is extra text matched against the search box, not shown to the user.
   - `CONTEXT` (`true`/`false`) is read into the tutorial's data but **not currently
     read anywhere else in `search.php`/`suggestions.php`** — every tutorial from every
     module is shown regardless of the current screen. Set it to `true` to match
     existing convention; don't rely on it actually scoping anything today.
   - `STEPS` is one `operation:target // optional comment` per line, `##`-joined
     internally (blank lines are skipped). Text goes through `_V()` at parse time
     (runtime translation, not compile-time extraction — see below), so plain English
     in the `.hlp` file is enough; no `lang/` file changes are needed to ship it.
   - `LABEL`/`KEYWORDS` text is plain English too, also via `_V()`.

3. Clicking a search result calls `Helper.start_tutorial(steps)`
   (`modules/Base/Help/js/main.js`), which draws a pointing arrow that walks the
   `STEPS` list, auto-advancing or waiting for a real interaction depending on the
   operation (see below).

## `STEPS` operations

| op | advances when |
|---|---|
| `hover:X` | immediately (always "complete") — but the tour still won't move past it until the *next* step's target is visible, so a `hover` on a collapsed submenu's own header effectively blocks there until the user expands it |
| `click:X` | the user actually clicks `X` |
| `fill:X` | `X`'s value is non-empty and stable for 800ms (typing) |
| `prompt:X` | the user clicks `X`, **or** clicks the tutorial overlay's own "Next" button (`Base_Help__button_next`) — use this for an optional/already-filled field you don't want to force interaction with |
| `finish:X` | same as `prompt`, but also ends the tutorial (`Helper.stop_tutorial()`) — always the last step |

`X` (and the `screen->target` form seen in some tutorials, e.g.
`rb_add_contact->#last_name`) is resolved by `Helper.get_help_element()`:
1. First checked against a hooks table of every element in the DOM carrying a literal
   `helpID="..."` **attribute** (built by `get_all_help_hooks()`,
   `jQuery('[helpID]')`) — this is how bare names with no `#`/`.` prefix
   (`Menu`, `Menu_My settings`, `ActionBar_save`) resolve; they are **not** DOM ids.
2. Falls back to a raw jQuery selector (`jQuery(X)[0]`) only if no `helpID` match is
   found. **A plain `$form->addElement('text', 'new_pass', ...)` field renders with a
   `name="new_pass"` attribute but no `id` at all** — confirmed live (DevTools
   inspection) after `fill:#new_pass` silently failed to resolve and the tour walked
   itself backward to the last step that *did* still find a visible target. Use an
   attribute selector instead: `fill:[name=new_pass]`. Contacts' example tutorial
   (`fill:rb_add_contact->#last_name`) uses a bare `#id` selector successfully only
   because that specific RecordBrowser-generated add-form field happens to carry an
   explicit `id` attribute matching its name — this is not a general QuickForm
   guarantee, it's specific to how that field was built. **Default to `[name=X]`
   for any QuickForm field target unless you've confirmed a matching `id` exists.**

**Do not confuse `helpID` with a plain HTML `id` attribute** — `grep`-ing for a DOM
`id="Menu_CRM"` will find nothing and looks like proof the mechanism is stale/broken;
grep for `helpID="` instead. As of this date it is alive and deliberately maintained
in the current AdminLTE theme:

- **ActionBar buttons**: every button added via `Base_ActionBarCommon::add($id, $caption,
  $href)` automatically gets `helpID="ActionBar_$id"` (`modules/Base/ActionBar/
  ActionBar_0.php`) — e.g. `Base_ActionBarCommon::add('save', ...)` → `ActionBar_save`.
  This is generic and free; any module using the normal ActionBar API already has
  working `click:ActionBar_<name>` targets, no extra wiring needed.
- **Sidebar menu items** (`modules/Base/Menu/Menu_0.php`'s AdminLTE-only
  `build_menu_html()`, explicitly commented "emits the same helpID values so the
  Base_Help tutorials keep working"): `$help_id = $prefix . $k`, where `$k` is the
  menu array's own (untranslated, literal-English, can contain spaces) key and
  `$prefix` accumulates as `$prefix . $k . '_'` down each submenu level, starting
  from `'Menu_'` at the top. So a top-level submenu key `'My settings'` containing a
  leaf key `'Change password'` yields `helpID="Menu_My settings"` on the submenu
  header and `helpID="Menu_My settings_Change password"` on the leaf link — spaces and
  all, verbatim in the `.hlp` file's `STEPS` line (the parser only splits on `:` and
  `->`, so embedded spaces are harmless).
- **QuickForm fields**: no `helpID` involved at all — resolved via the plain jQuery
  fallback instead. Default to `[name=<addElement() name>]`, not `#<name>` — see
  above, most QuickForm fields have no matching `id` attribute.
- **`Base_User_Settings`'s "Control panel" hub** (`modules/Base/User/Settings/
  Settings_0.php::main_page()`, the tile grid aggregating every module's
  `user_settings()` entries — reached by users *with* the "Advanced User Settings"
  permission; users without it get a direct single-purpose menu leaf instead, see
  next section) had **no `helpID` on its tiles at all** until this date — confirmed
  live: a tutorial's tour got stuck at "My settings" forever, unable to advance past
  a `click:Menu_My settings_Control panel` step into the hub, because the tile
  itself (`<a class="card" href="...">`) had nothing for `Helper.get_help_element()`
  to match. Fixed by stamping `helpID="UserSettings_<caption>"` on each generated
  tile. **Caveat**: unlike Menu's scheme, `$caption` here is already the
  *translated* label (every `user_settings()` implementation builds its array keys
  with `__()`, which translates immediately, not `_M()`, which only marks) — so
  `UserSettings_<English caption>` only resolves on an English-locale run. This is
  a pre-existing limitation of `user_settings()`'s own convention across every
  module that implements it, not something worth re-deriving `user_settings()`
  around just for Help; a real fix would mean threading an untranslated key through
  every module's `user_settings()` return value, out of scope for adding one
  tutorial. If you add a `helpID` to any other data-driven, per-instance-generated
  UI (grids, dynamic tile lists, anything not already covered by Menu/ActionBar),
  expect the same translated-vs-stable-key tradeoff and document which one you
  picked.

## `Helper.hooks` is a one-time snapshot — any `helpID` not yet on screen at login is unreachable (fixed 2026-08-28)

The worst of the bugs found writing this one tutorial, because it isn't
tutorial-specific — it silently breaks `ActionBar_*` targets (and any other
non-menu `helpID`) in *every* tutorial, including the pre-existing Contacts one.

`Helper.get_all_help_hooks()` (`modules/Base/Help/js/main.js`) builds `Helper.hooks`
— the lookup table `get_help_element()` checks before falling back to a raw jQuery
selector — by scanning `jQuery('[helpID]')` **exactly once**, via a `setTimeout(...,
500)` in `Base_Help::body()`. `Base_Help` itself is static, always-present shell
content rendered once at login, so this scan runs once, ~500ms after login, and
never again. Sidebar menu items are present in the DOM from that first render (just
visually collapsed), so `Menu_*` targets are captured fine. **An `ActionBar_*`
target is not** — that button doesn't exist until the user actually navigates to the
screen that renders it, which is always *after* the one-time scan already ran. The
old fallback (`jQuery(helpid)[0]`) can't rescue this either: `jQuery('ActionBar_save')`
with no `#`/`[...]` prefix is a bare tag-name selector, matching nothing.

**Symptom actually reported**: the tour correctly walked Menu → Control panel →
Password tile → filled every field, arrived at the real "click Save" step, and then
"clicking Save did nothing, the tutorial did not finish" — indistinguishable, from
the outside, between "the click step's target never resolved" and "the redirect
after saving broke something." It was the former: `ActionBar_save` was never in
`Helper.hooks` and never resolved, so the tour was still silently parked on the
prior step no matter what the user clicked.

**Fix**: `get_help_element()` now falls back to a live
`jQuery('[helpID="'+helpid+'"]')` lookup (caching a hit back into `Helper.hooks`)
before trying the old bare-selector fallback — no longer depends on the one-time
scan having caught the element in time.

**This file is loaded once per login session, not per page load** (see
`bug-patterns.md`'s "`load_js()`'s 'already sent' session flag" entry) — a tab that
already ran the old `main.js` will keep running it after this fix ships; test in a
new tab or a fresh login, not a refresh of an already-open one.

## Never chain `click:X` straight into `finish:X` on the *same* target if that click navigates away

Even with the `helpID` lookup fixed above, the tour still didn't stop after Save.
Root cause this time was in the `.hlp` file, not the JS: `click:ActionBar_save`
followed immediately by `finish:ActionBar_save` — same target, twice in a row.
Clicking Save both (a) fires `Helper`'s bound click handler (completing the `click`
step) *and* (b) submits the form, which for this account redirects to a different
screen and destroys that exact button. Advancing from one step to the next
requires the *next* step's target to already be `is_visible()` (checked on the
300ms polling tick, `Helper.update()`) — a race against how fast the AJAX
save+redirect destroys that shared target. Locally it lost the race every time:
the button was already gone by the next poll, so the tour could never actually
transition onto the `finish` step at all, and `finish`'s own tutorial-ending side
effect (`Helper.stop_tutorial()`, inside `operation_complete()`) never got a
chance to run.

Contacts' existing tutorial already avoids this, which is why it wasn't
already-known infrastructure knowledge: its `finish` target
(`rb_view_contact->ActionBar_edit`) is a *different* element than the `click`
before it (`rb_add_contact->ActionBar_save`) — specifically, a landmark on the
**resulting** screen after the save+redirect, not the thing that was just clicked.
`finish`'s completion doesn't require the user to click that exact landmark either
— clicking the tutorial overlay's own "Finish" button also satisfies it — so a
stable, always-visible landmark (a sidebar menu item is ideal: present regardless
of which screen is currently showing, so there's no visibility race at all) is
enough. Fixed here the same way:
`finish:Menu_My settings_Control panel // Your password has been changed`.

**How to apply**: never make a `finish` (or `prompt`) step's target the same
element a preceding `click` step just interacted with, if that action causes
navigation/redirect/DOM replacement. Point it at something on the destination
screen instead — a sidebar menu item is the safest choice, since it's present
regardless of what just got redirected to.

## No conditional branching — pick the one path you can verify, and expect to be wrong on the first guess

The DSL is a flat, linear list — there's no "if user has permission X, do Y instead."
A step whose target never becomes visible for a given user (e.g. a menu leaf that only
exists under a different ACL permission state) just leaves the tour stalled there
forever for that user — not a crash, just a dead end, reported by the user as "stuck"
on whatever step came before it.

Live example from writing the "Change your password" tutorial: `Base_User_
AdministratorCommon::menu()` only contributes a direct "Change password" leaf under
"My settings" for users **without** the "Advanced User Settings" permission; everyone
else instead sees "Control panel" (`Base_User_SettingsCommon::menu()`'s unconditional
entry), a tile hub one level deeper. The first version of this tutorial guessed the
"without the permission" path (reasoning: a basic password-change question sounds like
a regular end user, not an admin) — wrong for the one real account it got tested
against, which had the permission and got stuck at "My settings" with no way to
proceed (confirmed live, not from source: the submenu opened, but neither "Control
panel" nor any other tile advanced the tour, because the tour's *next* expected target
didn't exist in that menu). Rewritten to go through "Control panel" instead once that
was reported. There was no way to know which permission state the real test account
had from source alone — when a feature's navigation genuinely forks on permissions/
role and you can't check live first, say so rather than silently picking one, so a
"why won't it advance" report is fast to diagnose instead of a fresh investigation.

## Verify against the *current* running theme, not just the source — and expect two specific gotchas

`AI-shared/adminlte-theme.md` and `bug-patterns.md` both document this app as
effectively AdminLTE-only day to day (`adminltedark`; the plain `adminlte` theme was
removed). Reading `Menu_0.php`/`ActionBar_0.php` directly (as above) is enough to
write a *plausible* tutorial, but two things only showed up once someone actually
clicked through it live:

1. **The new `help()` method may not show up at all**, even though the file/registration
   is correct. `ModuleManager::check_common_methods('help')` caches "which modules
   have a `help()` method" cross-request (`Cache::get('common_method_help')`, via
   phpfastcache/memcached here, 24h default TTL) — a cache entry from before your
   module had `help()` stays stale until it expires or is cleared. Fix:
   `/c/xampp82/php/php.exe console.php cache:rebuild` (`Cache::clear()` +
   `ModuleManager::create_common_cache()`). Don't assume "it's not appearing in
   search" means your DSL/registration is wrong — clear this cache first.
2. **The guided walk-through can get stuck partway**, in two visibly different ways
   that mean two different bugs:
   - Arrow stays parked on the *current* step, never advancing (e.g. sitting on a
     submenu header forever) → the *next* step's target doesn't exist yet in the DOM
     at all — usually a wrong navigation path (see the permission-branching example
     above).
   - Arrow jumps *backward* to an earlier step it had already passed → the *current*
     step's own target isn't visible/resolvable (`update()`'s backward-walk loop keeps
     retreating until it finds a step whose target still resolves) — usually a wrong
     selector on a field/element that IS on screen right now, not a navigation
     problem. This is what a bad `#id` guess (see above) looks like from the outside:
     it looks like the tour "went back to the previous menu" when the real fix is the
     selector on the step it never actually reached.
   Neither is discoverable from reading `Menu_0.php`/`ActionBar_0.php` alone — only an
   actual click-through surfaces which of the two you're looking at.

If you can't log in to click-test yourself, at minimum verify the registration/parsing
end from the CLI without a browser (standalone entry scripts need a fake `CID` defined
*before* `include.php`, or `include/session.php:492` fatals with "Invalid request
without client id" — `$_REQUEST['cid']` is not enough, it has to be the `CID` constant):
```php
<?php
define('READ_ONLY_SESSION', true);
define('CID', 1);
chdir('C:/xampp82/htdocs/newsetup');
require_once('include.php');
ModuleManager::load_modules();
var_export(ModuleManager::call_common_methods('help')['Your_Module_Name'] ?? 'MISSING');
```
This confirms the file parses and the method is discovered — it does **not** confirm
any `STEPS` target actually exists in the live DOM. Say clearly which of the two you
did when handing this off.

## The other, separate, likely-dead mechanism — don't use it for new content

A second, older help mechanism exists: `Module::register_method('help', ...)`
(`modules/Base/MainModuleIndicator/MainModuleIndicatorCommon_0.php`) lets a module call
`$this->help($caption, $file)` to point at a static `modules/<Module>/help/main.html`
article (plain HTML, shown in an iframe popup — see `modules/Base/Dashboard/help/
main.html` for tone/formatting if you ever need prose-article style: short bolded
intro, `<table>`/`<img>` layout, `<ul>` for button explanations). **No template in
either `theme/` or `theme_adminltedark/` currently wires up that popup's trigger
icon** — it looks unreachable from the live UI today. Don't add new content through
this path; use the `tutorials.hlp` mechanism above.
