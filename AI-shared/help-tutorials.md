# Adding a Help tutorial to a module

`Base_Help` (F1, or Support → Help) is a search box over guided, coach-mark-style
tutorials contributed by *other* modules — it ships with no content of its own. This is how
to add one.

## The mechanism

**1. Expose a `help()` method** on your module's `*Common_0.php`. It is identical in every
module that has one — copy it verbatim:

```php
public static function help() {
    return Base_HelpCommon::retrieve_help_from_file(self::Instance()->get_type());
}
```

`ModuleManager::call_common_methods('help')` collects every module's return value on every
search keystroke.

**2. Write `modules/<Module path>/help/tutorials.hlp`** — a small line-based DSL, one entry
per `[LABEL:...]` block:

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
prompt:[name=mail] // Your e-mail address is already filled in
fill:[name=old_pass] // Enter your current password to confirm the change
click:ActionBar_save // Click Save
finish:Menu_My settings_Control panel // Your password has been changed
]
```

- `LABEL` is the search-result link text.
- `KEYWORDS` is extra text matched against the search box, not shown to the user.
- `CONTEXT` (`true`/`false`) is parsed into the tutorial's data but **not read anywhere
  else** — every tutorial from every module is shown regardless of the current screen. Set
  it to `true` to match convention; don't rely on it scoping anything.
- `STEPS` is one `operation:target // optional comment` per line. Text goes through `_V()`
  at parse time, so plain English in the `.hlp` file is enough — **no `lang/` changes are
  needed to ship a tutorial.**

## `STEPS` operations

| op | advances when |
|---|---|
| `hover:X` | immediately — but the tour still will not move past it until the *next* step's target is visible, so a `hover` on a collapsed submenu header effectively blocks there until the user expands it |
| `click:X` | the user actually clicks `X` |
| `fill:X` | `X`'s value is non-empty and stable for 800 ms |
| `prompt:X` | the user clicks `X`, **or** clicks the overlay's own "Next" button — use for an optional or already-filled field |
| `finish:X` | same as `prompt`, but also ends the tutorial — always the last step |

## How a target resolves

`Helper.get_help_element()` tries two things, in order:

1. **A literal `helpID="..."` attribute** anywhere in the DOM. This is how bare names with
   no `#`/`.` prefix (`Menu`, `Menu_My settings`, `ActionBar_save`) resolve. **These are not
   DOM ids** — grepping for `id="Menu_CRM"` finds nothing and looks like proof the mechanism
   is dead. Grep for `helpID="` instead.
2. **A raw jQuery selector**, only if no `helpID` matched.

What already carries a `helpID`, for free:

- **ActionBar buttons.** Every button added via `Base_ActionBarCommon::add($id, ...)` gets
  `helpID="ActionBar_$id"`. Any module using the normal ActionBar API already has working
  `click:ActionBar_<name>` targets with no extra wiring.
- **Sidebar menu items.** `$help_id = $prefix . $k`, where `$k` is the menu array's own
  untranslated, literal-English key and `$prefix` accumulates down each submenu level from
  `'Menu_'`. A top-level key `'My settings'` containing `'Change password'` yields
  `helpID="Menu_My settings"` and `helpID="Menu_My settings_Change password"` — spaces and
  all, verbatim in the `.hlp` file, since the parser only splits on `:` and `->`.
- **Control panel tiles**, stamped `helpID="UserSettings_<caption>"`. **Caveat:** unlike
  Menu's scheme, that caption is the *translated* label, because every `user_settings()`
  implementation builds its keys with `__()`. So it only resolves on an English-locale run.
  If you add a `helpID` to any other data-driven, per-instance-generated UI, expect the same
  translated-vs-stable-key tradeoff and say which one you picked.
- **QuickForm fields carry no `helpID` at all**, and most have no `id` attribute either — a
  plain `$form->addElement('text', 'new_pass', ...)` renders with `name="new_pass"` and
  nothing else. **Default to `[name=X]`, not `#X`,** for any QuickForm target unless you
  have confirmed a matching `id` exists.

## Three rules that keep a tour from getting stuck

**Never point a `finish:` (or `prompt:`) step at the element a preceding `click:` just
interacted with, if that click navigates away.** Advancing requires the *next* step's target
to already be visible, and the save-plus-redirect usually destroys the button before the
next 300 ms poll — so the tour can never transition onto the `finish` step, and the
tutorial never ends. Point it at a landmark on the *destination* screen instead. A sidebar
menu item is ideal: present regardless of which screen is showing, so there is no visibility
race at all.

**There is no conditional branching.** The DSL is a flat, linear list — no "if the user has
permission X, do Y instead". A step whose target never becomes visible for a given user
leaves that user's tour stalled forever. This is not hypothetical: navigation genuinely
forks on permissions, and the same task can sit behind a direct menu leaf for one user and a
tile hub one level deeper for another. When it does and you cannot check live, **say which
path you picked** rather than picking one silently.

**Clear the cache after adding `help()`.** `ModuleManager::check_common_methods('help')`
caches which modules have the method **cross-request**, with a 24h default TTL. A stale
entry from before your module had `help()` means it never appears in search, no matter how
correct your DSL is. `php console.php cache:rebuild` fixes it. Try that before debugging
anything else.

## Two ways a stuck tour reads, and what each means

- **The arrow stays parked on the current step**, never advancing → the *next* step's target
  does not exist in the DOM at all. Usually a wrong navigation path.
- **The arrow jumps *backward*** to a step it had already passed → the *current* step's own
  target is not resolvable, and the backward-walk loop keeps retreating until it finds one
  that is. Usually a wrong selector on an element that is on screen right now. This looks
  like "it went back to the previous menu" when the real fix is on a step the tour never
  reached.

Neither is discoverable from reading the source — only a click-through tells you which one
you have.

## Verifying without a browser

You can at least confirm the file parses and the method is discovered. A standalone script
needs a fake `CID` defined *before* `include.php`, or session bootstrap fatals with
"Invalid request without client id" — `$_REQUEST['cid']` is not enough, it has to be the
constant:

```php
<?php
define('READ_ONLY_SESSION', true);
define('CID', 1);
chdir('/path/to/checkout');
require_once('include.php');
ModuleManager::load_modules();
var_export(ModuleManager::call_common_methods('help')['Your_Module_Name'] ?? 'MISSING');
```

This does **not** confirm any `STEPS` target exists in the live DOM. Say clearly which of
the two you did when handing work off.
