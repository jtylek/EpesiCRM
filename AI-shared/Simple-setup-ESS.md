# Simple Setup: per-module "Readme..." button vs. Epesi Store cards

Written 2026-08-28. A "Readme..." pill button on package cards on the admin Setup
screen's Simple view (`Base_Setup::simple_setup()`, `modules/Base/Setup/Setup_0.php`),
opening that module's `README.md` (rendered to HTML) in a new tab — both on the
top-level card and, per-row, inside a bundled package's "Optional" dropdown (see that
section below). See "Status" at the bottom for what shipped and how it was verified.

## The scoping decision

This applies **only to locally-installed/available modules** — the packages built from
the `$module_dirs`/`$structure` scan and each module's own `simple_setup()` return
array. It does **not** apply to Epesi Store packages, added separately by
`Base_Setup::add_store_products()` a bit further down the same method. Store cards keep
their existing, different behavior on purpose: their icon and title already link out to
an external Store description page (`$s['description_url']`/`$s['icon_url']`), which is
a different kind of "more info" affordance than an in-repo README. Don't unify these or
add a Readme button to Store-only cards.

This falls out naturally from where the new `readme_url` package key is populated:

- Set only inside the local-scan loop in `simple_setup()` (`foreach ($structure as $s)`
  building `$packages`), immediately after the existing `icon`/`version`/`url` detection
  — same shape, same loop.
- `add_store_products()` never sets or reads `readme_url`. When it merges store data
  into an *already-locally-known* `$sorted[$name]` entry (the "local presence always
  wins" branch — see the comment above `if (isset($sorted[$name]))` in that method,
  which predates this feature and exists for an unrelated reason: `Premium_Import`'s
  "Data Import" package colliding with a same-named commercial Store product), it
  overwrites `icon`/`url` with the Store's external URLs but leaves `readme_url`
  untouched — so a locally-installed module's Readme button survives that merge.
- For a brand-new Store-only entry (no local `$sorted[$name]` yet), the key is simply
  never set, so the template's `{if $package.readme_url}` guard is false and no button
  renders.

No extra guard code was needed to keep Store cards Readme-free — it's a consequence of
where the key is populated, not a separate check.

## Trigger mechanism

No opt-in flag in `simple_setup()` — presence of `modules/<Path>/README.md` on disk is
the only trigger (`is_file()` check per module during the scan). If a package
aggregates multiple modules under one key (e.g. `CRM` bundles `CRM_Contacts`,
`CRM_Tasks`, `CRM_PhoneCall`, ...), the first module in scan order that has a
`README.md` wins — same "first found" convention already used for that package's icon.

`modules/Custom/Tutorial/README.md` is the first module using this.

## Per-option Readme buttons (CRM's "Optional" dropdown etc.)

A package that bundles several installable sub-modules under one card (e.g. CRM's
"Optional" dropdown: Account Manager, Contact Photo, County, Fax, Notes Aggregate,
Parent Company) shows each sub-module as its own row with its own status dropdown. Each
row gets its own "Readme..." button too, next to its Install/Uninstall button, reading
*that specific sub-module's* `README.md` — not the parent package's.

This works for free from the same per-key `readme_url` detection described above: each
option is its own `$packages[$key]` entry (key = `"<package>|<option>"`), so the
`is_file()` check already runs once per sub-module, not once per package. The only
change needed was propagating `$p['readme_url']` into
`$sorted[$name]['options'][$option]` (`Setup_0.php`, the `else` branch of the
`$option===null` check) and rendering it in the template's per-option action panel
(`theme_adminltedark/default.tpl`) as a real `<a target="_blank">` alongside the
existing onclick-driven Install/Uninstall `<div>`s — CSS modifier `.epesi-setup-subaction.readme`
(blue, same hue as `.store`) in `theme_adminltedark/default.css`.

Verified 2026-08-28 by temporarily dropping a `README.md` into
`modules/CRM/Contacts/Photo/` (a real CRM option) and confirming its own "Readme..."
button opened *that* module's content, not CRM's top-level one — then removed the temp
file, it was never meant to ship.

## Content goal: every local module should ship a README.md

The sections above are the *mechanism*. The actual goal is coverage — every module that
ships locally (core, `Custom/`, and `Premium/` alike) should eventually have a
`README.md` an admin can open straight from the Setup screen and understand without
reading source:

- what the module actually does, concretely — not just restating its one-line
  `simple_setup()`/`info()` description,
- how it enhances/extends Epesi's core CRM/ERP behavior — what it hooks into, what's
  different once it's installed, not just "it's an add-on,"
- and, where relevant, how to actually use it once installed — the screen it adds, the
  field it adds and where, any setup step beyond a plain install.

Established shape (not rigid, but keeps READMEs readable through `markdown_to_html()`
and consistent across the codebase) — see `modules/Custom/Tutorial/README.md` for the
original:

- `# <Path/Under/Modules>` title, matching the module's directory under `modules/` (e.g.
  `# CRM/Contacts/AccountManager`, `# Premium/Import`).
- `## What it does` — concrete, in terms of the actual UI/data it adds.
- `## Why it exists` — the problem it solves / why an admin would install it.
- `## Files` — a table of the main source files and what each does, so a developer can
  go straight to the relevant one.
- `## Installing / removing` — dependencies, and what uninstalling actually cleans up
  (schema/ACL/data) — the thing admins most often get wrong assumptions about.

`**bold**`/`*italic*` are the only supported emphasis syntax (see "Status" below for
why) — don't use `_underscore_` emphasis; underscores inside inline code spans or plain
identifiers are handled fine.

Coverage so far (2026-08-28): `modules/Custom/Tutorial/README.md` (the original,
pre-dates this goal), the CRM "Optional" sub-modules —
`modules/CRM/Contacts/AccountManager`, `.../Photo`, `.../County`, `.../NotesAggregate`,
`.../ParentCompany`, `modules/CRM/Fax` — and `modules/Premium/Import` (commercial,
gitignored — the README still works locally via the same `is_file()` check, it just
never reaches other checkouts through git, so re-add it if that module is ever
reinstalled from a clean Premium checkout). Everything else under `modules/` — most of
core CRM, `Base/`, `Utils/`, `Libs/`, the rest of `Premium/` — still has no README and
won't show a Readme button until one is added. Not a blocking requirement for new
modules, just the ongoing direction: pick it up opportunistically when touching a
module that doesn't already have one.

## Status

Implemented and browser-verified 2026-08-28 (both the top-level package-card button and
the per-option variant above): `readme_url` detection/propagation in `Setup_0.php`,
`Base_SetupCommon::view_readme()` ajax callback + a small dependency-free Markdown→HTML
renderer (`markdown_to_html()`/`markdown_inline()`/`markdown_list_item()`/
`markdown_table_row()`, all in `SetupCommon_0.php`), and the "Readme..." button markup
in `theme_adminltedark/default.tpl` (`modules/Base/Setup/theme_adminltedark/default.css`
for styling). `modules/Custom/Tutorial/README.md` was the only real README driving this
at the time the mechanism itself was verified — see "Content goal" above for the growing
list.

Two real bugs were caught by testing the renderer against that actual README.md before
shipping (not hypothetical edge cases): (1) a list item's text wrapping onto an unmarked
continuation line was losing everything after the wrap and fragmenting one list into
several — fixed by `markdown_list_item()`'s lazy-continuation consumption; (2) back-to-
back inline code spans containing underscores (`` `Tutorial_0.php` ``, `` `TutorialCommon_0.php`
``) got merged by an underscore-italic rule matching straight across both spans — fixed
by extracting code spans/links to placeholders before bold/italic runs, and by dropping
underscore-based emphasis entirely (this codebase's own identifiers are full of
underscores; `**bold**`/`*italic*` are the only supported emphasis syntax for READMEs
meant to be viewed through this renderer).

Flag if the Store-scoping decision above ever needs revisiting (e.g. if Store product
listings gain their own bundled README-equivalent).
