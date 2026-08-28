# Simple Setup: per-module "Readme..." button vs. Epesi Store cards

Written 2026-08-28, revised 2026-08-28 (new-tab → Leightbox). A "Readme..." pill button
on package cards on the admin Setup screen's Simple view (`Base_Setup::simple_setup()`,
`modules/Base/Setup/Setup_0.php`), opening that module's `README.md` (rendered to HTML)
in a Leightbox popup — both on the top-level card and, per-row, inside a bundled
package's "Optional" dropdown (see that section below). See "Status" at the bottom for
what shipped and how it was verified.

**Revision note**: originally shipped opening the README in a *new browser tab* via a
`Base_SetupCommon::view_readme()` ajax callback returning a full standalone HTML page.
Changed same-day to open in-page in a Leightbox instead, for consistency with Advanced
Setup's per-module "i" info icon (which got the same README content slightly later, also
via a Leightbox) - two different UI affordances for the same underlying content no longer
made sense once both existed. `view_readme()` was removed outright (nothing else called
it); `Base_SetupCommon::get_readme_html()` - already built for Advanced Setup's popup -
is now the only renderer, reused here too. Every `readme_url` field this doc describes
below is `readme_id` today (a Leightbox id, not a URL) - read those mentions accordingly.

## The scoping decision

**Original 2026-08-28**: the Readme button applied only to locally-installed/available
modules — the packages built from the `$module_dirs`/`$structure` scan and each module's
own `simple_setup()` return array — not to Epesi Store packages added separately by
`Base_Setup::add_store_products()` a bit further down the same method. Store cards kept a
different, older behavior instead: their icon and title linked out to an external Store
description page (`$s['description_url']`/`$s['icon_url']`).

**Revised 2026-08-28 (later same day, twice)**: that icon/title external link is gone
entirely now — `theme_adminltedark/default.tpl`'s icon/title is plain markup, never
wrapped in an `<a>`, for every card. In its place, every card with a `url`
(`$package.url`) now gets a "Readme..." button one way or the other:

- Locally-known module with a `README.md` on disk → `readme_id` is set → the button opens
  it in a Leightbox popup, as before.
- Store-only card (no local module, so no `readme_id`) → the template's
  `{if $package.readme_id}{elseif $package.url}` falls through to a plain external link
  (`target="_blank"`, same button styling) to `$package.url` — the Store's
  `description_url` — instead of a Leightbox, since there's no local content to render.
- A locally-known module that's *also* a Store product (e.g. "Epesi Core", "CRM") has
  both `readme_id` and `url` set (the latter filled in by `add_store_products()`'s "fill
  in whichever the local module left empty" merge — see below); `readme_id` wins per the
  `{if}/{elseif}` order, so it still gets the in-page Leightbox, not the external link.

So "Store-only cards never get a Readme button" (the original framing above) no longer
holds — they do now, it's just an external-link variant of the same button rather than a
Leightbox. What's unchanged: no card's icon/title is ever clickable, and a Store-only
card with no `description_url` at all shows no button. `modules/Base/Setup/theme/default.tpl`
(the pre-AdminLTE legacy theme) never got the Readme feature or this button fallback — it
still wraps icon/title in the old unconditional `{if $package.url}` link, since that's
still its only "more info" affordance for every card, local or Store.

This falls out naturally from where the `readme_id` package key is populated:

- Set only inside the local-scan loop in `simple_setup()` (`foreach ($structure as $s)`
  building `$packages`), immediately after the existing `icon`/`version`/`url` detection
  — same shape, same loop.
- `add_store_products()` never sets or reads `readme_id`. When it merges store data
  into an *already-locally-known* `$sorted[$name]` entry (the "local presence always
  wins" branch — see the comment above `if (isset($sorted[$name]))` in that method,
  which predates this feature and exists for an unrelated reason: `Premium_Import`'s
  "Data Import" package colliding with a same-named commercial Store product), it
  leaves `readme_id` untouched — so a locally-installed module's Readme button survives
  that merge. **Revised 2026-08-28**: it used to also unconditionally overwrite
  `icon`/`url` with the Store's remote values at this same spot, even when the local
  module already had its own perfectly good icon — fixed to only fill in whichever of
  the two the local module left empty (`if (empty($sorted[$name]['icon'])) ...`), same
  "local wins" principle the comment already claimed but didn't actually apply to those
  two fields. Caught because `Premium_Import` — the exact module that comment names —
  showed a broken image after `icon_url` from `ess.epe.si` was briefly slow/unreachable,
  even though nothing local was wrong.
- For a brand-new Store-only entry (no local `$sorted[$name]` yet), `readme_id` is
  explicitly set to `null` (not omitted — an unset array key under `{if $package.readme_id}`
  is an E_WARNING under PHP 8.2, not a graceful falsy), so the template's
  `{if $package.readme_id}` guard is false and it falls through to the `{elseif $package.url}`
  external-link button instead — see the revision note above.

No extra guard code was needed to distinguish the two Readme-button variants — it's a
consequence of where `readme_id` is populated (or left `null`), not a separate check; the
template's `{if $package.readme_id}{elseif $package.url}` does the rest.

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

This works for free from the same per-key `readme_id` detection described above: each
option is its own `$packages[$key]` entry (key = `"<package>|<option>"`), so the
`get_readme_html()` check already runs once per sub-module, not once per package. The
only change needed was propagating `$p['readme_id']` into
`$sorted[$name]['options'][$option]` (`Setup_0.php`, the `else` branch of the
`$option===null` check) and rendering it in the template's per-option action panel
(`theme_adminltedark/default.tpl`) as a real `<a class="lbOn" rel="{$action.readme_id}"
href="javascript:void(0)">` alongside the existing onclick-driven Install/Uninstall
`<div>`s — CSS modifier `.epesi-setup-subaction.readme` (blue, same hue as `.store`) in
`theme_adminltedark/default.css`. (Originally a plain `<a target="_blank">` to an ajax
URL — see the revision note at the top of this doc for why that changed to a Leightbox.)

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

## Two Leightbox-level improvements that came out of viewing real READMEs

Both landed the same day as the new-tab → Leightbox switch, in `modules/Libs/Leightbox/`
rather than anything Setup-specific — general improvements to the shared Leightbox
component itself, discovered by actually reading README content through it, not
scoped to Simple Setup's own code:

- **Maximize/restore toggle** (`theme_adminltedark/default.tpl`'s new button,
  `theme_adminltedark/default.js`'s `epesi_leightbox_toggle_maximize()`,
  `theme_adminltedark/default.css`'s `.leightbox.maximized`). A longer README (or any
  other Leightbox content) can outgrow the default ~70%-width/900px-max popup with no
  way to see more at once. Deliberately **not** the old `libs_leightbox_resize()`
  (`theme/default.js`, shared with the default theme) — that writes inline
  top/left/width/height styles, which is exactly why this theme's own resize button was
  omitted in the first place (see `default.css`'s top-of-file comment): those inline
  writes fight the transform-based centering (`left:50%; transform:translateX(-50%);`)
  and visibly jump the popup off-screen. The new toggle is a plain CSS class swap
  instead, so it overrides the geometry cleanly, transform included, and also swaps its
  own icon (`bi-arrows-fullscreen` ↔ `bi-fullscreen-exit`) and title between
  `$maximize_label`/`$restore_label` (added to `Libs_LeightboxCommon::get()`'s Smarty
  assigns alongside the existing, still-unused-by-this-theme `$resize_label`).
- **Compact heading/code/table/blockquote typography, scoped to `#Leightbox_content`**
  (`theme_adminltedark/default.css`). `markdown_to_html()` emits bare `h1`-`h4` etc. with
  no classes, so before this fix they inherited the ambient admin theme's default
  heading sizes (~2.5rem for a plain `h1`) — a README's own `# Module/Path` title (or
  Advanced Setup's plain-info-table fallback, which also renders an `<h1>`) read like a
  full page title crammed into a small popup. Same underlying symptom `#clipboard h3`
  a few lines above it in the same file already patched for one specific popup —
  generalized here so *any* Leightbox content with headings gets sane sizes by default,
  not just ones someone happened to patch individually. Dark-first (matches this file's
  own convention), with a `[data-bs-theme="light"]` counterpart added by hand near the
  bottom alongside the auto-generated block (not itself auto-generated).

Both are reusable outside Simple Setup's own "Readme..." button — Advanced Setup's "i"
info icon (which shares the same Leightbox popup content, see that screen's own
`AI-shared` coverage if one exists) benefits from both for free, as would any other
future Leightbox content.

## Status

Implemented and browser-verified 2026-08-28 (both the top-level package-card button and
the per-option variant above): `readme_id` detection/propagation in `Setup_0.php`, a
small dependency-free Markdown→HTML renderer (`markdown_to_html()`/`markdown_inline()`/
`markdown_list_item()`/`markdown_table_row()`, all in `SetupCommon_0.php`, exposed via
`Base_SetupCommon::get_readme_html()`), and the "Readme..." button markup in
`theme_adminltedark/default.tpl` (`modules/Base/Setup/theme_adminltedark/default.css`
for styling) — a `Libs_LeightboxCommon::display()`/`get_open_href()` pair, same pattern
Advanced Setup's "i" info icon uses, not the original `view_readme()` ajax callback (see
the revision note at the top — that method was removed once nothing called it anymore).
`modules/Custom/Tutorial/README.md` was the only real README driving this at the time the
mechanism itself was first verified — see "Content goal" above for the growing list
(57 Epesi Core submodules as of the 2026-08-28 revision, tracked via
`AI-shared/bug-patterns.md`'s Leightbox-id-collision entry from that same sweep).

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

**Revised 2026-08-28 (icon/title link removal)**: the icon/title `<a href="{$package.url}">`
wrapper and its `.epesi-setup-card-link`/`.epesi-setup-card-link:hover` CSS rules were
removed outright from `theme_adminltedark/default.tpl`/`default.css` — dead code once the
`{if $package.readme_id}{elseif $package.url}` button (see "The scoping decision" above)
became every card's single "more info" affordance. `Setup_0.php`'s two comments
referencing the old "Store cards get no Readme button" framing were updated to match.

Flag if the Store-scoping decision above ever needs revisiting (e.g. if Store product
listings gain their own bundled README-equivalent).
