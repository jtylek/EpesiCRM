# AdminLTE theme(s) status

> **Status:** IN PROGRESS - status of the adminlte/adminltedark themes: what is themed, what is not, and the recurring CSS/JS traps.

## Module icons: where they're declared and everything that renders one (2026-09-01)

Reference note, written while adding activity-type icons to the Activities tab / Agenda
applet / Watchdog applet. Consolidates what was previously spread across
`bootstrap_icons.php`'s header and the two icon sections further down this file.

**Where an icon is declared.** One place: a `public static function bootstrap_icon()` on
the module's own `Common` class, returning a Bootstrap Icons class name
(`CRM_MeetingCommon::bootstrap_icon()` -> `'bi-calendar-date'`). Same "module opts in by
defining a conventionally-named method" shape as `menu()`/`user_settings()`/`home_page()`.
~40 modules declare one; not declaring one is the normal case for a module that never
appears in a menu or panel. There is **no central module->icon map** - that was removed
deliberately (renamed from `Base_AdminlteIcons` 2026-08-14).

**Where it's resolved.** `modules/Base/Theme/bootstrap_icons.php` - `Base_BootstrapIcons`,
plain PHP (`require_once`'d, not a `Module`), because consumers pull it in from a Smarty
`{php}` block or mid-render private method. Four entry points:

| method | returns | used by |
| --- | --- | --- |
| `resolve($icon, $module, $fallback)` | `'bi-...'` class name | everything below; `$fallback` defaults to a generic window glyph, pass `null` to mean "keep your own raster icon" |
| `tag($module, $classes)` | `<i class="bi ...">` or `null` | the "New Meeting"/"New Task"/"New Note" record shortcuts |
| `type_tag($module, $recordset)` | `<i class="bi ... epesi-type-icon text-muted me-1">` or `''` | a type glyph before a title in a list mixing several record types |
| `resolve_recordset($module, $recordset)` | `'bi-...'` or `null` | per-recordset override (below) |

`resolve()` tries, in order: the `$by_filename` map (keyed on an icon file's *basename*),
then `$module`'s `bootstrap_icon()`, then `$fallback`. The filename map exists only to tell
apart two things registered by the **same** module where the caller holds nothing but an
icon filename - `companies.png` -> `bi-building` vs CRM_Contacts' own
`bi-person-vcard-fill`. It is not a place to add module icons.

**Per-recordset override (added 2026-09-01).** A module owning several recordsets can give
each its own glyph with a `public static function bootstrap_recordset_icons()` on the same
`Common` class, returning a `recordset-name => 'bi-...'` map. Only `CRM_ContactsCommon`
declares one today (`'company' => 'bi-building'`); every recordset the map doesn't name
falls back to the module's `bootstrap_icon()`, so a module owning one recordset - the
common case - declares nothing extra. Added because Watchdog's applet can name the owning
module (its categories are registered as `<Module>Common::watchdog_label`) but had no other
way to tell `contact` from `company`, so Companies rows carried a person glyph.

**Everything that renders one:**

- **Sidebar menu** - `Base_Menu::build_menu_html()`. Per-link `__icon_small__`/`__icon__`
  first (so one module's two links can differ), then the parent module. Submenus fall back
  to `bi-folder2`, leaves to the generic window glyph.
- **Header module indicator** - `Base_MainModuleIndicator` +
  `theme_adminltedark/default.tpl`. `module_icon` (the active module's `icon()`, when it
  exposes one) takes priority over `module_type`, and `module_type` is deliberately *not*
  passed alongside it: `Utils_RecordBrowser::navigate()` replaces Base_Box's tracked main
  module with a bare `Utils_RecordBrowser`, so `module_type` is wrong for single-record
  views while the icon path stays right.
- **ActionBar launcher / launchpad** - `Base/ActionBar/theme_adminltedark/default.tpl` and
  `launchpad.tpl`.
- **Admin panels** - `Base/Admin/theme_adminltedark/default.tpl` + `access_panel.tpl`, with
  a `bi-gear` fallback.
- **Record shortcuts** (`tag()`) - the New Meeting/Task/Phonecall/Note buttons in
  `CRM_ContactsCommon`, `CRM_MeetingCommon`, `CRM_TasksCommon`, `CRM_PhoneCallCommon`,
  `RecordBrowser_0::add_note_button()`. These pass `$fallback=null` and `?:` back to their
  original `<img>`, so a module without `bootstrap_icon()` keeps its raster icon.
- **New-record type chooser** - `Utils/RecordBrowser/theme_adminltedark/new_record_leightbox.tpl`.
- **Mixed-type list rows** (`type_tag()`, added 2026-09-01) - the Activities tab under a
  Contact/Company (`CRM_Contacts_Activities`), the Agenda applet (`CRM_Calendar::applet()`,
  module derived from each calendar handler's `<Module>Common::crm_calendar_handler`
  callback), and the Watchdog applet (`Utils_Watchdog::applet()`, module from the category
  callback + recordset name from `Utils_RecordBrowserCommon::watchdog_label()`).

**`set_icon()` / `recordbrowser_table_properties.icon` is not a second icon source - don't
read it as one.** It looks like a legacy raster leftover and it half is, but it still feeds
the resolver, so it's easy to get wrong in both directions:
`Utils_RecordBrowserCommon::set_icon()` stores a **module-relative** path
(`Base_ThemeCommon::get_template_filename()` -> `"CRM/Meeting/icon.png"`).
`RecordBrowser_0::init()` immediately expands it through
`Base_ThemeCommon::get_template_file()` into the full `modules/CRM/Meeting/theme/icon.png`,
and `RecordBrowser::icon()` hands *that* to MainModuleIndicator, whose template feeds it to
`resolve()` - which auto-extracts the module from the `modules/<Module>/theme.../` shape and
returns that module's `bootstrap_icon()`. So **no raster recordset icon is ever drawn**; the
stored path survives only as a module+table discriminator. Two consequences: (1) don't add
a new consumer that reads the column and renders the PNG, and (2) don't resolve icons *from*
the stored value in new code either - the raw stored form is module-relative and won't match
`resolve()`'s path branch. Ask the module's `Common` class instead, which is where the icon
actually lives.

## `Utils_Menu` (the JS fly-out menu widget) themed, per a user screenshot of `Tests/Menu` (2026-09-01)

First `theme_adminltedark/` coverage for `Utils_Menu` - previously it only had the
legacy `theme/default.css`, whose bright-green (`#B0FFB0`) boxes stood out badly next
to the rest of an otherwise-`adminltedark` page (reported via a screenshot of the
`Tests/Menu` demo module, which is this widget's only exerciser under this theme -
see below). New file: `modules/Utils/Menu/theme_adminltedark/default.css`.

**Why this was never covered by Base_Menu's own sidebar theming work**: they're two
separate widgets that happen to share the "Menu" name. `Base_Menu` (`Base/Menu/
theme_adminltedark/default.css`) renders the actual sidebar as a static Bootstrap
accordion and, under adminlte, does not use `Utils_Menu` at all - see `Base_Menu::
body()`'s branch on `Base_ThemeCommon::is_adminlte_family()`, and the doc comment on
`build_menu_html()`: "Utils_Menu's vertical mode is built for a floating menu:
submenus are hover-triggered fly-outs... in a scrolling sidebar that is unusable...
Nothing else uses this path, and Utils_Menu is untouched." `Utils_Menu` only renders
under adminlte via `Tests_Menu` (this demo) - confirmed by grepping every
`init_module("Utils/Menu"...)` call site app-wide - so this had zero real-screen
impact before now, just an ugly demo page.

**menu.js's markup couldn't be intercepted, only reached into**: `Utils_Menu::body()`
emits a loading-spinner `<div>`, then `js/menu.js`'s `CustomMenubar` builds the real
`<table>`-based menu client-side and injects it via `innerHTML` - no template step
exists to convert it to div/flexbox markup the way the rest of the legacy theme was
(see this file's "Legacy theme/ converted to div-only layout" entry). Same shape of
problem as `Utils_Tree`/`Premium_KnowledgeBase`'s `tree_view.css` (this file's own
entry above): the new CSS targets the classes `menu.js` already emits
(`.root_item_link`, `.root_item_link_right`, `.root_item_link_down`, `.submenu`,
`.custom_opener`, ...) rather than changing the JS - lower risk given `menu.js` is a
single shared file with no theme branching of its own, so any markup/class change
would apply to the legacy theme's rendering too.

**Visual choice**: reused the fixed grey/black (`#DEE2E6`/`#000`) sidebar-chrome
convention (`Base_Box/theme_adminltedark/default.css`'s `.app-sidebar`, already reused
for Leightbox popups and `Utils_Tooltip` - see "Leightbox popups" entry below) for
both the menu's root box and its fly-out submenu panels, rather than `Base_Menu`'s own
white/blue-accent `.nav-link` palette - this widget floats over arbitrary page content
instead of living in the sidebar itself, so the fly-out-popup convention fit better
than the in-sidebar one. Submenu chevrons are plain CSS border-triangles (no icon
font/image dependency), consistent with `Base_Menu`'s own preference for fresh
`epesi-*`/generic classes over AdminLTE's own component classes (trap #2 below).

Verified live (Playwright, logged in as `admin` on this checkout): `Tests/Menu`'s
vertical menu, its nested fly-out (hover on an "s" entry), and the horizontal `menu2`
variant's dropdown all render with the grey chrome instead of green; zero console
errors.

> **⚠ The harness for that verification no longer exists.** `Tests/Menu` was deleted
> later the same day in the `modules/Tests/` trim (see `deliberate-removals.md`), which
> judged it on teaching value — ~100 copy-pasted `add_link("aaa")` calls — without
> weighing this second role. Since `Base_Menu` does not use `Utils_Menu` under adminlte
> at all (see above), **`modules/Utils/Menu/theme_adminltedark/default.css` now has no
> way to be exercised on any screen in the app**, and a regression in it would be
> invisible. The CSS itself is untouched and still shipped. To re-verify or change it,
> restore the demo from git history (`git checkout <rev> -- modules/Tests/Menu`, e.g.
> from the commit preceding the trim) rather than hand-building a new harness.

## `.epesi-fullbleed`: opt-in for a screen that must fill the content column (2026-08-31)

Added for CRM_Roundcube, whose whole body is one `<iframe>` hosting Roundcube's complete
mail client - its own sidebar, toolbars, message list and preview pane. The content
column's normal breathing room doesn't read as breathing room around something like that,
just as a dead grey frame drawn around a second application, with Roundcube's own bottom
status bar stranded well above the window bottom. Reported as padding on all four sides.

**Four separate sources had to go**, none of them obvious from the module's own markup:

| Side | Source |
|---|---|
| top + bottom | `.app-content { padding: 1rem 0 }` (Base_Box's own rule) |
| left + right | Bootstrap's `.container-fluid` gutter, 0.75rem each side |
| bottom (again) | `adminlte.min.css`'s own `.app-main { padding-bottom }` - 12px at 14px base |
| bottom (again) | `Roundcube_0.php`'s `eval_js()` height guess, `clientHeight - 130` |

That last 12px is the one worth remembering: it is invisible on an ordinary screen (it just
trails the content) but it is exactly the difference between a full-bleed page fitting the
window and being 12px scrollable with a dead band under the pane. Measured, not guessed -
`document.documentElement.scrollHeight` read 912 against a 900px viewport with the first
three already fixed.

**The contract:** a module puts `.epesi-fullbleed` on the single top-level element of its
own output. `Base_Box/theme_adminltedark/default.css` then zeroes all three paddings above
and gives that element
`height: calc(100vh - var(--epesi-header-height) - var(--epesi-actionbar-height))`. Nothing
else on the page changes.

**Why `:has()` and not a class on `.app-content` or a body flag.** This is an AJAX-push SPA:
`Epesi.text()` only ever rewrites the module's own `<span>`
(`Epesi::$content[$path]['span']`) - `.app-main`, `.app-content` and `.container-fluid` all
outlive the navigation. A class set while rendering this screen would therefore have to be
explicitly unset again by *every other screen in the app*, which is exactly the kind of
cleanup that gets missed. Keying off the presence of the opted-in element makes the
exception disappear on its own the moment the module's markup is replaced. Verified:
padding measured back at `16px 0px` / `12px` / `12px` on Dashboard, Shoutbox and Contacts
after visiting mail, and `0px` everywhere on mail itself.

The height being a `calc()` over the two `--epesi-*` variables (rather than a measured pixel
count in JS) is what lets it survive an ActionBar that wraps onto a second row, or collapses
away entirely under `body.epesi-actionbar-empty` - default.tpl's ResizeObserver already
keeps both in sync with the bars' real rendered heights, and a `calc()` referencing them
re-resolves for free. The `eval_js()` it replaced ran once per render and did neither.

Two smaller traps that cost time here:

- **`display:block` on the iframe is required.** An iframe is inline by default, so at
  `height:100%` it sits on a text baseline and the descender gap pushes its bottom few px
  out of the wrapper.
- **Specificity, not `!important`.** `:has()` takes the specificity of its most specific
  argument, so `.epesi-adminlte .app-content:has(.epesi-fullbleed)` is 3 classes against the
  2 of the `.app-content` rule, and the `.container-fluid` and `.app-main` overrides outrank
  their single-class vendor rules regardless of stylesheet load order - which matters,
  since Base_Box's own bundle goes out *before* bootstrap/adminlte (see the `#ActionBar`
  padding comment in that file for the same trap biting the other way).

## Base font size centralized into `--epesi-font-size-base`, bumped 13px → 14px (2026-08-29)

An earlier pass (per explicit request) shrank the app's default body/text size to 13px.
That was done by editing `Base_Box/theme_adminltedark/default.css`'s two "anchor" rules
(`html body { font-size }` and the `.form-control`/`.form-select`/`.form-label`/
`.form-check-label` block — needed separately because Bootstrap's own rem-based sizing on
those resolves against the root `<html>`, not the inherited/overridden `body` value) *plus*
hardcoding a matching literal `font-size: 13px` in ~28 other spots across ~22 unrelated
module `theme_adminltedark/*.css` files, wherever that same root-relative-rem or
equal-specificity gap would otherwise have let a larger size win over the shrunk body
default. That left the actual "app-wide default" duplicated as a bare number with no single
place to change it — asked to raise it to 14px, that would have meant re-editing the same
~24 files again.

**Fix: one new file owns the value.** `modules/Base/Theme/theme_adminltedark/fonts.css`:
```css
:root { --epesi-font-size-base: 14px; }
```
Loaded on every page by `Base_ThemeCommon::load_theme_assets()` (`ThemeCommon_0.php`,
called from Base_Box) right alongside Bootstrap/AdminLTE's own CSS, so the variable is
always defined before any per-module theme CSS references it. All ~28 previously-hardcoded
matches (Base_Box's own two anchor rules; `Utils_RecordBrowser/View_entry.css`;
`Apps_Shoutbox`'s chat forms; `Utils_Calendar`; `Base_Setup`; `Libs_QuickForm`;
`Libs_Leightbox`; `Utils_LeightboxPrompt`; `Base_EssClient`; `Base_About`'s credits;
`Base_Admin`'s access_panel; `Base_Search`; `Base_User_Settings`; `CRM_Mail`) now read
`font-size: var(--epesi-font-size-base)` instead of a literal number; each rule's
explanatory comment was reworded to stop asserting a specific pixel value that would go
stale the next time this changes. The 13→14px bump itself was then a one-line edit.

**Deliberately NOT folded into this variable** — don't "fix" these thinking they were
missed:
- Component-specific `rem` sizes that scale off the browser root independently (headings,
  icons, badges, etc. — dozens of them app-wide, e.g. `0.85rem`/`1.25rem`). These were never
  part of the "match the body's own literal px value" pattern; folding them in too would be
  unrelated scope creep, not a fix.
- `Utils_Tooltip`'s and `Libs_Leightbox`'s own `.8125rem` (== 13px at the default 16px root,
  on the hover-tooltip popup and its "pinned" Leightbox variant). Coincidentally close to the
  old body value but an independent, self-consistent choice shared between just those two
  files (their own comment: "font size matches ... too"), not a body-size match — both popups
  attach directly to `<body>`, outside the normal inheritance chain the base variable relies on.
- `Base_User_Login/theme_adminltedark/default.css`'s `.login-page-adminlte { font-size: 16px }`
  — deliberately *restoring* Bootstrap's un-shrunk default for the login page specifically, not
  matching the app body size, so it stays a literal `16px`.

Verified live (Playwright, logged-in session): `getComputedStyle(document.body).fontSize`
reads `14px` on Dashboard, Contacts (RecordBrowser list, View_entry, Edit form), and the
Admin panel; `.form-control` fields track the same value; zero console errors.

## Default color mode (no stored preference) flipped dark → light (2026-08-25)

Per explicit request, ahead of the SourceForge distribution package: a visitor with no
`localStorage['lte-theme']` yet (fresh browser, fresh install, first-run setup wizard) now gets
seeded to `'light'` instead of `'dark'`. One-line change in `Base_ThemeCommon::load_theme_assets()`
(`modules/Base/Theme/ThemeCommon_0.php`) — the exact spot the 2026-08 comment block already
identifies as "SEEDS a default the first time the key has never been set". A returning visitor's
own stored choice is untouched either way; only the seed value changed. Verified with an isolated
Node mock of the emitted JS snippet (localStorage/document stubbed) rather than a live install,
to avoid mutating this dev DB mid-test — confirmed fresh/no-pref now resolves to light, while
existing `dark`/`light` stored prefs still round-trip unchanged.

`setuptheme/shell.tpl` (the earlier Language/License/Database/Compatibility steps in `setup.php`,
before `FirstRun` takes over) was already unconditionally `data-bs-theme="light"` and doesn't load
`adminlte.min.js`'s color-mode toggler at all — untouched, no dark path existed there to begin with.
The SSR-hardcoded `data-bs-theme="dark"` in `Base_Box/theme_adminltedark/default.tpl` (the
`{if $theme_name=='adminltedark'}dark{else}light{/if}` in the "data-bs-theme is pinned to the active
theme" comment block) was deliberately left alone — it's corrected client-side by the same seeding
JS on every load regardless of stored preference (existing behavior for anyone who already prefers
light), so leaving it dark-first-then-corrected is consistent with how a light-preferring user's
page already rendered before this change, not a new flash-of-dark introduced by it.

## `adminlte` (light) removed entirely — `adminltedark` is now the only AdminLTE-family theme (2026-08-04)

Per explicit direction: stop spending time fixing/debugging the light-only
`adminlte` theme and focus solely on `adminltedark` going forward. All 34
`modules/*/theme_adminlte/` directories in the main repo (75 files) were
deleted outright, not deprecated — same "delete, don't leave inert" approach
as the [[quickjump-removed]]/[[legacy-mobile-removed]]-style removals
elsewhere in this codebase. `modules/Premium/Projects/Tickets/theme_adminlte`
was deliberately left untouched — Premium is a separate, separately-licensed
git repo (see this file's own README), out of scope without a separate
decision there.

Everything below this entry that still describes `adminlte` (light) — the
now-removed `## adminlte (light)` section, and any path example naming
`theme_adminlte` in the traps list further down — is historical, describing
work against files that no longer exist. Left in place rather than deleted
outright: the underlying lessons (CSS-per-rendering-module, class-name
collisions, `data-bs-theme` pin scoping, etc.) still apply verbatim to
`adminltedark`, just substitute `theme_adminltedark` for `theme_adminlte` in
any path mentioned.

**`Base_ThemeCommon::is_adminlte_family()`** (`modules/Base/Theme/ThemeCommon_0.php`)
was narrowed from `array('adminlte', 'adminltedark')` to just
`array('adminltedark')` rather than removed — every one of its ~17 call sites
across the codebase is a legitimate "is this the Bootstrap/AdminLTE-based
rendering path" check that still needs to hold for `adminltedark`, and the
abstraction costs nothing to keep for a hypothetical future family member.

**Two live hardcoded-path bugs found and fixed *before* deleting the
directories** (would otherwise have 404'd for `adminltedark` too, since
neither branches by which family member is active):
`Utils_Tooltip/TooltipCommon_0.php`'s `ajax_open_tag_attrs()` and the
module-load `load_js()` at the bottom of that file both hardcoded
`theme_adminlte/tooltip.js`; `Utils_PopupCalendar/PopupCalendarCommon_0.php`'s
`create_href()` hardcoded `theme_adminlte/main2.js`. Both `theme_adminltedark`
copies were confirmed byte-identical (`diff`, zero output) before repointing,
so this was a latent bug with no behavior change today — it only would have
started 404ing once `theme_adminlte/` was gone.

**Two more hardcoded `<link>`s, in the *permanently*-AdminLTE-styled admin/
setup/login chrome** (`admin/templates/layout.tpl`, `include/templates/
login_page.tpl` — independent of the user-selected app theme, see
`MIGRATION_NOTES.md` and [[admin-tools-adminlte]]/[[update-check-adminlte-split]]):
both had a comment explicitly stating intent — "reused as-is (not copied) so
this shell stays in sync with the real app's own AdminLTE shell/login screen"
— pointing at `Base_Box`'s / `Base_User_Login`'s `theme_adminlte/default.css`.
Repointed to their `theme_adminltedark/default.css` equivalents instead of
forking a static copy, honoring that same stated intent now that the real
app's shell/login screen *is* `adminltedark`. Confirmed safe: both
`theme_adminltedark/default.css` files already carry a full
`[data-bs-theme="light"]` override layer (CSS custom properties redefined
under that selector, auto-generated, see `gen_light_override.js` mentioned in
several `theme_adminltedark/*.css` file headers) — these two templates already
pin `data-bs-theme="light"` on their wrapper, so they render via that override
layer, not the dark defaults.

**Migration patch**: `modules/Base/Theme/patches/20260804_remove_adminlte_theme.php`
flips any existing install's `default_theme` variable from `'adminlte'` to
`'adminltedark'` if still set that way — this dev DB's own `default_theme` was
already `'adminltedark'` (set by `ThemeInstall.php` since before this pass),
and there's no per-user theme override anywhere in the codebase (`default_theme`
is the only place a theme name is ever stored), so this patch only matters for
some other, real existing install where an admin had explicitly picked the
light theme.

**Not done as part of this pass** (flag before assuming it's covered): the
stale `theme_adminlte`-path comments left throughout the codebase (mostly
"see Base_Box/theme_adminlte/default.css" style cross-references explaining
*why* some other file's value was chosen) were not scrubbed — cosmetic
staleness only, the values themselves are still correct. The icon-resolution
class (then `Base_AdminlteIcons`, see update below) and every module's own
icon method were **not** touched as part of *this* pass — that was shared,
family-wide icon-resolution infrastructure `adminltedark` depended on, named
after the framework/family, not the removed theme variant.

**Update (2026-08-14, separate pass)**: that infrastructure *was* later
renamed - `Base_AdminlteIcons`/`adminlte_icons.php` → `Base_BootstrapIcons`/
`modules/Base/Theme/bootstrap_icons.php` - to make the "module declares
`bootstrap_icon()`, resolver looks it up on demand" convention explicitly
theme-agnostic instead of framework-named, ahead of any future non-AdminLTE
theme wanting to reuse it. Every module's own method was renamed
`adminlte_icon()` → `bootstrap_icon()` in the same pass. The adminlte theme
remains the only actual consumer of `resolve()` today; nothing about
*that* changed.

**Update (2026-08-14, same day):** `resolve()`'s own `$fallback` default —
used for modules that don't declare `bootstrap_icon()` when the caller
doesn't supply a more specific fallback — changed from `'bi-gear'` to
`'bi-layout-text-window-reverse'` (a generic "window" glyph, less likely to
misread as "this is a settings/admin screen" than a gear). None of the 7
current call sites actually relied on this default — each already passes
its own context-appropriate fallback (`'bi-gear'` for Admin's tool list,
`'bi-sliders'` for Settings, `'bi-app-indicator'` for MainModuleIndicator,
`'bi-folder2'`/`'bi-dot'` for Menu sub/leaf items, `null` for ActionBar's
launcher/launchpad and LeightboxPrompt to keep a module's original raster
icon instead of forcing a glyph) — those were deliberately left alone. Only
the function's documented default, and the docs describing it
(`Dev-Tutorial.md`, `Custom/Tutorial/TutorialCommon_0.php`'s comment),
changed.

**Update (2026-08-14, later same day):** per explicit follow-up request, the
per-caller overrides above were walked back everywhere they existed purely
to say "no icon declared" (as opposed to a genuinely different structural
meaning) — the whole point of the earlier change was for icon-less modules
to read the same everywhere, and leaving each call site's own override in
place defeated that:
- **Menu** (`Menu_0.php:210`, `Base_Menu::build_menu_html()`): leaf items no
  longer pass `'bi-dot'` — they now omit the 3rd arg and take `resolve()`'s
  own default. Submenu/category entries **still** pass `'bi-folder2'`
  deliberately kept — that's marking "this is a folder in the tree", a
  structural fact, not a per-module icon fallback.
- **ActionBar launcher** (`theme_adminltedark/default.tpl`, the quick-access
  row) and **launchpad** (`theme_adminltedark/launchpad.tpl`, the pinned-icon
  popup): both dropped their explicit `null` 3rd arg and now take the
  generic default too. This is an intentional behavior change from the
  original design noted above (null meant "keep the module's own raster
  icon.png instead of a generic glyph") — an icon-less module's launcher/
  launchpad entry now shows the shared glyph instead of its original
  artwork. `launchpad.tpl`'s `{if $i.bi_icon}...{else}<img src=$i.icon>{/if}`
  branch is effectively dead now (`bi_icon` can no longer come back null from
  these call sites) but was left in place rather than restructured.
- **MainModuleIndicator** (`theme_adminltedark/default.tpl:31`, the "which
  module's content is this" strip above the content pane): dropped its
  explicit `'bi-app-indicator'` 3rd arg the same way.
- **Left unchanged, not part of this request**: Admin's `'bi-gear'` (comment
  there explicitly says gear fits because "this panel is specifically admin
  tools") and Settings' `'bi-sliders'` — both are curated single-purpose
  screens, not "any module might show up here with no icon" surfaces, so
  their fallback is arguably still a real, separate design choice rather
  than an "undeclared" placeholder. LeightboxPrompt's `null` (arbitrary
  per-button icons in a generic modal, not necessarily module icons at all)
  was likewise left alone. Revisit if a future request wants those unified
  too.

## Legacy `theme/` converted to div-only layout (2026-08-04)

The legacy `theme/` (old default, pre-AdminLTE, table-based) is now fully
div/flexbox/CSS-Grid-based, matching what `theme_adminlte(dark)/` already did
per-screen. Scope: all of `theme/` across every module, plus
`admin/templates/`, `setuptheme/`, `include/templates/`, and root `theme/`
(the boot splash). Point 7 below ("Epesi renders almost everything as nested
`<table>`s") is resolved for `theme/` as a result — the whole bug class it
described no longer applies there.

**New Smarty plugin**: `Base_Theme/smarty/plugins/function.html_grid_epesi.php`
replaces `html_table_epesi` for GenericBrowser's core list grid in the legacy
theme (same `loop`/`cols`/`row_attrs`/`table_attr` param contract, emits
`role="table"`/`row`/`columnheader`/`cell` div markup instead of `<table>`).
`function.html_table.php` and `function.html_scrolled_table_epesi.php` (+ its
companion JS) were confirmed to have zero remaining callers and were deleted
outright rather than converted.

**Dropped feature**: GenericBrowser's column-resize (jQuery `colResizable`,
`js/col_resizable.js`) was removed — the vendored plugin hard-requires a real
`<table>` and no div-compatible equivalent was available. Sort/filter/search
are unaffected. `GenericBrowser_0.php`'s `$resizable_columns`/
`set_resizable_columns()` API surface is left in place but now inert.

**PDF/email carve-outs — these 4 templates deliberately still emit
`<table>`, do not "fix" them**:
- `Utils/GenericBrowser/theme/pdf.tpl` — still calls `{html_table_epesi}`.
- `Utils/RecordBrowser/Reports/theme/pdf_row.tpl`
- `CRM/Calendar/Event/theme/pdf_version.tpl`
- `Utils/RecordBrowser/theme/RecordPrint.tpl` — found *during this pass*, not
  in the original inventory. Confirmed via `RecordPrinter.php` →
  `Base_Print_Printer` → `Base_Print_Document_PDF::add_content()` →
  `Libs_TCPDFCommon::writeHTML()`: this exact template's HTML is fed straight
  into TCPDF, which only reliably renders `<table>`-based layout, not
  flex/grid. Verified PDF export still works end-to-end post-conversion
  (`Base_Print/Handle.php` response: `content-type: application/pdf`, 94KB,
  valid).
`Utils/RecordBrowser/theme/changes_list_email.tpl` (an inline-styled **email
body**, not PDF) was left as `<table>` for the same reason — email client CSS
support is closer to TCPDF's than to a browser's. `Base_Theme/smarty/debug.tpl`
(Smarty's own vendored debug console, see `MIGRATION_NOTES.md` §17 on why
Smarty 2 itself is never touched) was also deliberately left alone — not a
carve-out for rendering reasons, just out of scope as vendored code.

**Files missed by the original inventory, found via a post-hoc full-repo
grep sweep and converted in the same pass**: `Utils/LeightboxPrompt/theme/
form.tpl`, `CRM/Followup/theme/leightbox.tpl`, `Utils/FileStorage/theme/
download.tpl`. If a future sweep of this codebase turns up more legacy
`<table>` usage outside `modules/Premium/` (out of scope, separately
licensed/gitignored), treat it the same way, using whichever of these 5
recipes fits — don't assume this pass's inventory was exhaustive:
1. **Label/value row** (`single_field.tpl`): `<tr><td class="label">`/
   `<td class="data">` → `<div class="epesi-rv-row"><div class="label">`/
   `<div class="data">`, tag swap only. Row is `display:flex`.
2. **Generic field-list form** (QuickForm's `column.tpl`): CSS Grid
   (`grid-template-columns: minmax(120px,max-content) minmax(0,1fr)`), label/
   data divs emitted flat — grid auto-placement pairs them, no row wrapper.
3. **Repeating group hand-wrapped into a new `<tr>` every N items**
   (`RecordBrowser/Filters/theme/elements.tpl`): delete the modulo/counter
   bookkeeping, replace with `display:flex; flex-wrap:wrap` on the container.
4. **Genuine 2D data grid** (GenericBrowser's list, calendar month/week/day
   grids, Settings matrix): CSS Grid with explicit `grid-template-columns`
   (computed via `{$x|@count}` when column count is dynamic), plus
   `role="table"`/`"row"`/`"columnheader"`/`"cell"` ARIA attributes to
   restore the semantics real `<table>` markup gave for free. For a row that
   needs to stay hoverable/addressable as a group, wrap it in a
   `display:contents` div with `role="row"` — its cells still lay out
   directly against the grid's own column tracks.
5. **`rowspan`-dependent layout**: prefer native CSS Grid `grid-row:span N`
   over a flex-sibling-stacking workaround — simpler and, per the
   CRM_Meeting bug above, less error-prone. If flex-sibling-stacking is used
   anyway (make the rowspanning cell a flex sibling of a second flex item
   that internally stacks the rows that used to share the rowspanned cell),
   triple-check the closing `</div>` for that second flex item lands *after*
   every row meant to be inside it, and verify live in a browser, not just
   by reading the template.

**A real bug found and fixed during this pass** (not present before this
conversion touched the file, and not caused by the div conversion mechanics
themselves — a nesting mistake made while doing it): `CRM/Meeting/theme/
default.tpl`'s rowspan-replacement wrapper column (`flex: 1 1 0; min-width:
0`, standing in for the original `<td rowspan="3">`'s sibling) was closed one
`</div>` too early, leaving the multiselects/longfields/alert sections as
*siblings* of that wrapper instead of children stacked inside it. Since those
sections have `flex-basis:auto` (auto-sized to content) and the wrapper had
`flex-basis:0` (grows only from `flex-grow`), the auto-sized siblings claimed
essentially all the row's width first, leaving the wrapper — and everything
inside it, including Title/Permission/Employees/etc — collapsed to ~0px
(visually: field values wrapped one character per line). Caught by live
browser verification, not by grep or lint — this class of bug (a flex/grid
container is syntactically valid HTML and produces no console error) is
invisible to static checks. `CRM/Calendar/Event/theme/default.tpl` uses the
same rowspan-replacement pattern and was re-checked by hand against this
exact failure mode — nesting confirmed correct there.

**Also found and fixed: a stale-CSS-selector regression from the GenericBrowser
grid conversion itself.** `Utils_GenericBrowser`'s core list grid moving from
`<table>`/`<thead>`/`<tr>`/`<th>`/`<td>` to `.Utils_GenericBrowser__thead`/
`__tr`/`__th`/`__td` div classes broke any *other* module's CSS that still
targeted the old tag-based selectors for a GenericBrowser widget embedded
outside GenericBrowser's own theme files. Found in `Utils/FrontPage/theme/
default.css` (a whole duplicated block, `.contents table > thead > tr > th`
etc.), `Base/Dashboard/theme/default.css` (`.Utils_GenericBrowser th`), and
`Utils/RecordBrowser/Reports/theme/default.css` (`table.Utils_GenericBrowser
tr:hover`) — all fixed to the current class-based selectors. This class of
bug doesn't show up in a `<table` grep (it's CSS, not HTML) — if
`Utils_GenericBrowser`'s own markup ever changes again, grep for
`Utils_GenericBrowser.*\b(tr|td|th|thead|tbody)\b` and `table\.Utils_GenericBrowser`
across all `*.css` first, not just the files being actively edited.

**Pre-existing bugs surfaced by browser-testing this pass (not caused by it,
left as found — report/decide separately, don't silently fix mid-conversion)**:
- `Base_ActionBar/theme/default.tpl`'s `{if $i.icon_url}` undefined-array-key
  warning — this one **was** fixed, as a low-risk drive-by, since it was in a
  file already being edited for the conversion itself.
- The shared confirm-modal (`Module::create_confirm_href()`/
  `window.epesi_confirm()`) renders unstyled/visible inline off-AdminLTE,
  because Bootstrap's `.modal{display:none}` CSS isn't loaded for the legacy
  theme. Still unfixed.
- `{foreach item=n from=$new}` (a per-record "what's new" tooltip loop) is
  unguarded by `isset()` in **22 View_entry-family templates** across both
  the legacy and AdminLTE-family themes (`View_entry.tpl`, `Contact.tpl` ×2,
  `mails.tpl` ×2, `PhoneCall`'s and `Meeting`'s `default.tpl`,
  `Attachment/View_entry.tpl`, all three themes) — pre-existing in the
  original table markup, carried forward verbatim, not introduced by this
  pass. Triggers `E_WARNING: Undefined array key "new"` under
  `REPORT_ALL_ERRORS` on `view`/`edit` actions where `$new` isn't assigned.
  Still unfixed — systemic enough (22 call sites) to warrant its own pass
  rather than a drive-by fix.
- Similarly-shaped unguarded-array-access warnings exist elsewhere in
  `CRM_Meeting`'s own template (`$event_info.start_date` compared without an
  `isset()` guard, hit by all-day/"Company Holiday"-type records) and in
  `RecordBrowser/theme/RecordPrint.tpl` (`$no_access`) — same "pre-existing,
  verified against the original baseline via git, not this pass's doing"
  story, left alone for the same reason.

**Verification performed**: `php -l` on every touched PHP file; a full-repo
grep sweep for `<table|<tr|<td|<th` across the entire in-scope tree (clean
except the carve-outs above and this file's own explanatory comments); live
Playwright testing of ~15 screens in the legacy theme (top bar/ActionBar
chrome, Dashboard applets, GenericBrowser list view, RecordBrowser
View_entry — Contact/PhoneCall/Task/Meeting incl. recurring and
timeless/all-day variants, PopupCalendar widget incl. year/month drill-down,
Calendar month/week/day/year views, a Leightbox popup) plus the PDF export
pipeline — all confirmed working, zero console errors.

## RecordBrowser's generic View_entry.tpl: fluid CSS columns (2026-08-03)

See [design-philosophy.md](design-philosophy.md) for why this was originally
computed in PHP at all, and why replacing it with CSS is a continuation of that
principle, not a departure from it.

`Utils_RecordBrowser::view_entry_details()` (`RecordBrowser_0.php`) used to take
a `$cols` param (default 2, optionally overridden per-tab from a page_split
field's own `param` column) and pre-compute which field landed in which of N
columns (`$rows`/`$no_empty`/`$cols_percent`, done in the *template*, not PHP).
This has been removed entirely from the **generic** `View_entry.tpl` in all
three themes (`theme/`, `theme_adminlte/`, `theme_adminltedark/`) — replaced by
a plain CSS multi-column container (`.epesi-rv-fluid { column-width: 420px;
column-gap: 24px; }`) that lets the browser decide how many columns fit the
current width, instead of a fixed PHP-computed count. Fields render as a flat
sequence of rows; `break-inside: avoid` keeps each row intact.

**This does NOT apply to the ~6 other per-table templates** (`CRM_Contacts`'s
`Contact.tpl`, `CRM_Contacts_Photo`'s `Contact.tpl`, `CRM_Mail`'s `mails.tpl`,
`CRM_Meeting`'s and `CRM_PhoneCall`'s `default.tpl`, `Utils_Attachment`'s
`View_entry.tpl`) — a deliberate scope decision, confirmed with the user, since
converting those too would be a much bigger change touching core CRM screens.
They still read `$cols`/`$rows`/`$no_empty` for their own fixed-column table/flex
layout, so `view_entry_details()` still assigns `'cols' => 2` as a **permanent
compatibility shim** purely for their benefit — don't remove it without also
converting (or otherwise updating) all of those templates first.

**Why the generic template needed its own field-row markup, not just new CSS**:
`single_field.tpl` (which builds each field's `$f.full_field` HTML) is *shared*
between the generic template and every one of those other per-table templates.
In the legacy `theme/` (table-based) theme, `single_field.tpl` emits `<tr><td>`
— dropping that directly into a `<div>` (as pure CSS multi-column requires) is
invalid HTML that browsers silently relocate via foster-parenting, breaking
layout. So the legacy generic `View_entry.tpl` now builds its rows directly
from the individual pieces `get_field_display_options()` already returns
(`$f.label`/`.html`/`.error`/`.help`/`.required`/`.advanced`/`.style`/`.element`)
instead of going through `single_field.tpl` at all — `single_field.tpl` itself
is untouched, still serving the other templates exactly as before. The two
AdminLTE-family themes didn't need this workaround: their `single_field.tpl`
already emits `.epesi-rv-row` `<div>`s (no table involved anywhere in that
theme), so `{$f.full_field}` drops straight into the new fluid container as-is.

**Legacy theme CSS note**: most of `.label`/`.data`/`.form_error`/etc in
`theme/View_entry.css` are already unscoped from any table-cell requirement and
so were safe to reuse directly for the new div-based rows; only a handful of
rules that *were* `table.view`/`table.edit`-scoped (background colors, the
automulti edit-mode block, a couple of border tweaks) needed an equivalent
added under `.epesi-rv-fluid.view`/`.epesi-rv-fluid.edit` instead. Also fixed,
proactively, in all three themes: the same `.form_error` positioning bug
documented in `bug-patterns.md` (no `top` set, `max-width:50%`) — it would have
resurfaced the moment these rows went through a flex layout instead of a table
cell.

One AdminLTE-based theme exists, under `modules/*/theme_adminltedark/`
(replaces the original `theme/` (legacy, table-based) look; the light-only
`adminlte` sibling was removed 2026-08-04, see the dated entry at the top of
this file). Themes resolve straight from `modules/` — no build step, no
generated copy (see `Base_ThemeResolver::resolve()`: `theme_<name>` first,
falls back to legacy `theme/`). **Any module without its own
`theme_adminltedark/` override silently falls back to the legacy light
table-based theme** — this is still a large gap, not a bug.

## `adminlte` (light) — started 2026-07-26, removed 2026-08-04

Superseded by `adminltedark` below, then deleted outright (see the dated entry
at the top of this file). Before removal it had reached working/
browser-verified coverage of: login, app shell, sidebar menu, GenericBrowser
record lists, ActionBar/Launchpad, Dashboard applet chrome, TabbedBrowser,
Admin/User-Settings panels, RecordBrowser view/edit, Search, Leightbox popup
chrome, module-indicator icons — all since carried forward by `adminltedark`,
which covers the same module set (see below). Kept here only as a pointer for
anyone wondering what happened to it; no per-screen detail preserved since
none of it maps to an existing file any more.

## `adminltedark` — created 2026-08-01, sole AdminLTE-family theme since 2026-08-04

Was a **full independent fork** of `theme_adminlte/` (not resolver-chained —
a module it doesn't cover falls straight to the legacy theme, not to a
now-nonexistent `adminlte`). Covers the same ~34 modules `adminlte` used to;
module-coverage expansion ("Phase 2") was never started and is now the only
remaining path for growing AdminLTE-family coverage. Has a live navbar
light/dark toggle built on AdminLTE's own `data-bs-theme-value` color-mode
toggler (`adminlte.min.js`'s `Me` class) rather than a custom implementation.

**Not yet themed / not audited** (inherited from `adminlte`, never closed):
individual dashboard applets' own inner content (Weather, RssFeed, Shoutbox
history, Calc, etc. mostly `print()` raw HTML), `Base_Admin/theme/
access_panel.tpl`, QuickForm's raw-table renderer (`Libs/QuickForm/Renderer/
EpesiDefault.php`, used by `Utils_Wizard` — its `_headerTemplate`/
`_elementTemplate`/`_formTemplate`/`_requiredNoteTemplate` raw strings were
converted from `<tr>/<td>` to `<div>` as part of the 2026-08-04 legacy-theme
div conversion, since the class is theme-agnostic and shared by every theme;
still a CSS-only reskin, not converted to the Smarty array renderer),
leightbox popup *contents* (e.g. CRM_Filters "manage perspectives"),
Base_Help's tutorial overlay.

**Tooltips** (`Utils_Tooltip`, 2026-08 restyle): grey/black to match the
sidebar (`#DEE2E6`/`#000`, hardcoded not themed — see
`Base/Box/theme_adminltedark/default.css`'s sidebar rule for why). Native
tooltips (`title="..."`, the pre-2026-08 mechanism) can't be recolored by CSS
at all, so recoloring forced a real rendering change. Two things were tried
and rejected before landing on the current approach:
1. A JS-driven Bootstrap tooltip *component*, three separate times, well
   before this restyle — broke real functionality in hard-to-diagnose ways
   each time (load-order races, orphaned popups, conflicts with
   `GenericBrowser`'s own hover-driven `table_overflow_show`). Treat any
   future JS tooltip *component* attempt here as high-risk.
2. A pure CSS `::after` popup (`[data-tooltip]:hover::after`, no JS at all) —
   seemed safest given (1), but had to be dropped too: it's clipped by any
   ancestor with `overflow:hidden`, and that turned out to include plain
   Bootstrap `.card` containers (rounded corners on dashboard applets, admin
   panels, etc.), not just `RecordBrowser`/`GenericBrowser` table cells'
   ellipsis truncation — i.e. most of the app, not an edge case. Confirmed by
   hovering a dashboard applet's own "Remove" icon in a live browser: computed
   `content`/colors were correct, but invisible, clipped by `.card`.

**Ajax tooltip responses are now cached client-side, shared by `tooltip_id`
across every element on the page (2026-08-09).** Previously
`epesi_tooltip_ajax_load()` fetched fresh from `Utils/Tooltip/req.php` on
every element's *first* hover, even when several elements resolved to the
exact same `tooltip_id` (`ajax_open_tag_attrs()`'s
`md5(serialize($tooltip_settings))`) — e.g. a RecordBrowser list where the
same Customer/Contact is linked from several rows (`CRM_ContactsCommon`'s
`company_get_tooltip()`/`contact_get_tooltip()`, or the generic
`create_default_record_tooltip_ajax()` path any other module's record-hover
tooltip goes through) fired one redundant round-trip per row. Now a
page-scoped `epesi_tooltip_ajax_cache` (keyed by `tooltip_id`) is checked
first, and an in-flight `epesi_tooltip_ajax_pending` map lets a second
element hovered while the first's request is still in flight piggyback on
that same response instead of firing its own. Safe to share because two
elements only ever land on the same `tooltip_id` when their
callback+args+`safe_html` serialized identically — i.e. they'd have fetched
byte-identical content anyway.
Caught one regression while landing this: the cache-hit branch first called
`epesi_tooltip_ajax_apply()` (the "update an already-open popup" helper,
gated on `epesi_tooltip_current_el === el`) directly — but nothing had
called `epesi_tooltip_show_popup()` yet to make that true for a fresh cache
hit, so every hover *after* the first one on a shared `tooltip_id` silently
showed nothing at all. Fixed by having the cache-hit branch call
`epesi_tooltip_show_popup()` itself, same as the synchronous
`open_tag_attrs()` path (`epesi_tooltip_show()`) already does.
**Deliberately client-side only, not server-side, for now**: the same
per-`tooltip_id` sharing idea was considered for `req.php` too (there's a
real cross-request cache abstraction already in `include/cache.php`'s
`Cache` class), but `contact_get_tooltip()`/`company_get_tooltip()`'s
`Utils_RecordBrowserCommon::get_access(..., 'view', $record)` ACL check and
every tooltip's `__()` translation calls are both evaluated against
*whoever's currently hovering* at fetch time — neither is part of the
`tooltip_id` hash, so a naive shared server cache could leak an
access-gated tooltip to a user without view access, or serve the wrong
language. Would need the cache key to also fold in the user's effective
permission context and language to be safe, which cuts into the cross-user
hit-rate benefit — not implemented.

What's actually live now: both `open_tag_attrs()` ("help" tooltips, e.g.
action icons) and `ajax_open_tag_attrs()` (RecordBrowser "show in tooltip"
record-hover tooltips) render the same plain `.epesi-tooltip-popup` div,
appended to `<body>` on a bare `onmouseenter`
(`Utils/Tooltip/theme_adminltedark/tooltip.js`) — the same body-append escape
`table_overflow_show` itself already uses for overflowing cell content, not a
component with its own event-delegation lifecycle, so still a fundamentally
different risk profile from (1). Known consequence: since this popup and
`table_overflow_show`'s own overflow-preview box can both trigger off
hovering the same `<td>` (the tooltip span is nested inside the cell
`table_overflow_show`'s `onmouseover` is bound to), a cell whose content is
both truncated *and* tooltip-enabled can show both popups at once. Cosmetic
(nothing errors or breaks), not chased further yet.

As part of the original fork, several modules' nested-`<table>` layout was
rewritten as real flexbox/grid (QuickForm's `row.tpl`/`column.tpl`,
RecordBrowser's `View_entry.tpl` + the per-table overrides, the RecordBrowser
filter bar) — landed in `theme_adminlte/` first, then copied into the dark
fork; that provenance is now purely historical since only the dark fork
remains.

**Touch devices: hold-to-preview collided with the native link context menu
(2026-08-10).** The popup above is wired purely via `onmouseenter` — no
touch handling anywhere. On a phone this meant a quick tap on a
tooltip-bearing `<a>` just navigated before the popup could register, while a
long-press synthesized `mouseenter` around the same hold duration the browser
uses to decide "show the native context menu" (Open in New Tab/Copy Link/...)
— so holding the link showed both the popup *and* the native menu at once.
Neither `open_tag_attrs()` nor `ajax_open_tag_attrs()`'s own
`if(MOBILE_DEVICE) return '';` guard helps here — `MOBILE_DEVICE` has been
permanently `0` since `detect_mobile_device()` was deleted (see
[[deliberate-removals]]'s "Legacy mobile system" entry), so every tooltip
renders fully hover-wired even on a phone; that entry calls the remaining
`MOBILE_DEVICE` checks app-wide "dead-but-harmless," which undersells this one
specifically — its deadness is what let this bug happen.

Fixed client-side only, in `tooltip.js`: `epesi_tooltip_mobile_enhance()` runs
on `window` `load` and on `e:load` (idempotent via an
`epesi-tooltip-mobile-done` marker class), and on any `(hover:none)` device,
every `[data-epesi-tooltip="1"]` element that sits inside a real `<a href>`
gets a small `.epesi-tooltip-mobile-trigger` button inserted right after that
link (a sibling, not a child - nesting `<button>` inside `<a>` is invalid
HTML) wired to the *same* `onmouseenter` handler the server already rendered
(captured by reference before being cleared, not reimplemented). Tapping the
button shows the popup without navigating; tapping the link itself navigates
immediately like any ordinary link, since the link's own `onmouseenter` is
nulled out so the browser's native hold gesture no longer triggers the popup
at all. A deferred (`setTimeout`) document-level capture-phase click listener
dismisses the popup on the next tap elsewhere, since `mouseleave` (the
existing dismiss path, still used for real mouse hover) generally never fires
on touch.

**Follow-up: popup ran off the right edge on a phone (2026-08-10).** Tapping
the new mobile trigger button above worked, but on a narrow viewport the
popup's right edge extended past the screen — confirmed by screenshot (a
"Project Name/Due Date/Description/..." record-info tooltip cut off
mid-sentence). Root cause was in the CSS, not the JS: `.epesi-tooltip-popup`'s
`max-width:480px` is sized for desktop, wider than most phone viewports.
`epesi_tooltip_position()` (`tooltip.js`) can only ever slide the popup's
*left* edge within `[4, innerWidth-width-4]` — once `width` alone already
exceeds `innerWidth-8`, that range goes negative/empty and the clamp falls
back to `left:4px` with the popup still wider than the screen, so the excess
just runs off the right edge uncapped; no amount of JS repositioning can fix
a box that's inherently wider than its container. Fixed by capping the width
itself: `max-width: min(480px, calc(100vw - 16px))` — same 480px cap on
desktop, shrinks to fit the viewport on a phone either way.

## Leightbox popups: fixed grey/black sidebar chrome convention (2026-08-08/09)

Per explicit direction, Leightbox-based popups are being moved, one at a time
as they come up, to the **same fixed grey/black scheme as the app-sidebar**
(`#dee2e6` background, `#000` text — `Base_Box/theme_adminltedark/
default.css`'s sidebar rule, "stays this same color in both light and dark
mode, per request") instead of each popup's own `var(--epesi-*)`-themed dark
chrome. This is not a global default yet — it's applied popup-by-popup on
request, not swept across every Leightbox in the app — but any *new* Leightbox
popup, or one that comes up for other styling work, should default to this
scheme rather than the themed one unless told otherwise. Already converted:
`Libs_Leightbox`'s own header (`#Leightbox_header`) and the Watchdog "what
changed" popup (`#tooltip_leightbox_mode`) — both in `Libs/Leightbox/
theme_adminltedark/default.css`; `CRM_Followup`'s Follow-up popup and
`Premium_Projects_Tickets`' "Change Status" popup (same `_followups_leightbox`
id suffix/plumbing) — `CRM/Followup/theme_adminltedark/leightbox.css` and
`Premium/Projects/Tickets/theme_adminltedark/status_leightbox.tpl`;
`Utils_LeightboxPrompt`'s generic chooser grid (`CRM_Calendar`'s "New Event",
`CRM_Mail`, `Utils_Messenger`, `Base_Lang_Administrator` all reuse it) —
`Utils/LeightboxPrompt/theme_adminltedark/leightbox.css`. See the Tooltips
entry above for the same scheme applied to `Utils_Tooltip`'s hover popup.

**The trap this convention creates: native `<select>`/`<textarea>` text goes
invisible.** Forcing a popup to always-light chrome doesn't stop the *browser*
from still treating the page as dark-themed — `Base_ThemeCommon`'s dark-mode
toggle sets `colorScheme='dark'` on `<html>` (see `ThemeCommon_0.php`), and
browsers use that (not this popup's own CSS) to pick native form-control
colors for anything left unstyled. A `<select>`/`<textarea>` with an explicit
white `background-color` but no explicit `color` renders white-on-white for
both the closed control's own text *and* its native dropdown option list —
`background-color: #fff` alone is not enough. Fix, needed on every such field:
```css
color: #000;
color-scheme: light;
```
`color-scheme: light` is the part that fixes the native dropdown popup
specifically (the option list is browser-native chrome, not something CSS can
otherwise restyle) — `color` alone can fix the closed box but isn't reliably
enough for the open list. Found and fixed in both `CRM_Followup`'s and
`Premium_Projects_Tickets`' popups (same underlying bug, hit independently in
each). See the matching `bug-patterns.md` entry for the full symptom writeup —
check any *other* fixed-light-chrome popup containing a `<select>`/`<textarea>`
for the same gap before assuming only these two had it.

**Another recurring gap in these same fixed-chrome overrides: not viewport-aware
(2026-08-10, Premium_Projects_Tickets' Change Status popup).** Confirmed by
screenshot: on a phone the popup rendered wider than the screen and got cut off
on both edges, unreadable. Same underlying shape as the Tooltip popup fix
above — `#premium_ticket_status_followups_leightbox`'s own override
(`status_leightbox.tpl`) replaced Libs_Leightbox's default *viewport-relative*
sizing (70%/900px) with a *fixed* `max-width:520px`, wider than most phone
viewports; separately, `.epesi-ticket-status-buttons`' `grid-template-columns:
repeat(4, 1fr)` forced 4 equal columns of `white-space:nowrap` buttons
(Mark as New/Reopen/In Progress/On Hold/Resolved/Need Feedback/Close), so even
capping the popup alone wouldn't have been enough — the grid's own minimum
content width could still exceed a narrower popup box. Fixed both together:
`max-width: min(520px, calc(100vw - 24px)) !important` on the popup, and
`grid-template-columns: repeat(auto-fit, minmax(130px, 1fr))` on the button
grid so it reflows to fewer columns instead of forcing an overflowing row.
**`CRM_Followup`'s Follow-up popup checked too (2026-08-10 follow-up)** —
already in better shape than Tickets' popup (`.leightbox[id$="_followups_leightbox"]`
already used `max-width:90vw`, viewport-relative from the start, and
`.epesi-followup-actions` already had `flex-wrap:wrap`), so no outright
overflow/cutoff. Screenshotted after a first-pass fix (`.epesi-followup-row`
given `flex-wrap:wrap` + `.epesi-followup-control` a `min-width:180px`, so the
Status/Note label+control pair drops to its own line instead of squeezing the
control arbitrarily narrow) and the popup rendered *without* overflowing but
noticeably narrower than it needed to be — `width:fit-content` sizes to the
row's own preferred width, which on a mostly-empty `<select>` is barely wider
than the label. Fixed by flooring the popup itself:
`min-width: min(300px, 90vw)` alongside the existing `max-width:90vw`, so it
no longer sizes itself down to whatever a near-empty control happens to want.

**Follow-up 2: popup positioned a third of the way down on mobile, cutting off
the bottom (2026-08-10).** Confirmed by screenshot — nothing visibly occupied
the gap above the popup, yet it rendered well below the true top, pushing the
"Save"/"save and create" row below the fold. Libs_Leightbox's base `.leightbox`
pins `top: calc(--epesi-header-height + --epesi-actionbar-height + 1rem)` —
those two vars are the navbar/ActionBar's *real* `offsetHeight`, kept live via
`ResizeObserver` (`Base_Box/theme_adminltedark/default.tpl`'s `watch()`).
Working theory, not fully confirmed via live DOM inspection: on mobile those
elements are hidden off-canvas rather than height-collapsed, so `offsetHeight`
likely still reports their full desktop-sized height while invisible,
inflating the calc() past anything actually on screen. Rather than chase that
down further, worked around it directly in `CRM_Followup`'s own override:
below `max-width:767.98px`, `top: 0.75rem !important` and
`max-height: calc(100vh - 1.5rem) !important` pin the popup near the real top
regardless of what the header/actionbar vars report. **If any other Leightbox
popup gets the same complaint on mobile, this off-canvas-height theory is the
first thing to verify properly** (inspect `--epesi-header-height`/
`--epesi-actionbar-height`'s live computed value on a narrow viewport) rather
than re-deriving a per-popup workaround each time — a fix in the shared
`Libs_Leightbox` base CSS would be worth it at that point.

**Follow-up 3: still too narrow specifically in mobile landscape (2026-08-10) —
the real bug was shrink-to-fit sizing, not a missing viewport cap.** Both
`CRM_Followup`'s `width:fit-content` and `Premium_Projects_Tickets`'
`width:auto` looked viewport-aware already (paired with `max-width:90vw` /
`min(520px, calc(100vw-24px))`) but never actually grew to use that allowance
— for a `position:fixed` box pinned by `left:50%` alone (no matching `right`),
CSS resolves `auto`/`fit-content` width via **shrink-to-fit**: the box sizes to
its own content's preferred width, full stop, regardless of how much more
space `max-width` would allow. A landscape phone has a much wider viewport
than the same phone in portrait, but since neither popup was actually sizing
off the viewport, orientation made no difference — both stayed pinned near
their content-minimum width either way, which is why "make them wider" didn't
already happen for free at the wider landscape width. Fixed by giving both an
**explicit** width instead of `auto`/`fit-content` (explicit lengths on a
fixed/absolute box are used directly, not shrink-to-fit):
`width: max(280px, min(90vw, 480px))` (Followup) /
`width: max(280px, min(90vw, 520px)) !important` (Tickets) — now genuinely
scales up with the viewport, landscape included, instead of merely being
capped by one. **Any other custom-width Leightbox override should be checked
for this same `width:auto`/`fit-content`-on-a-fixed-box trap** before assuming
its own `max-width` is doing anything in landscape — it likely isn't. The base
`Libs_Leightbox` `.leightbox` rule itself is fine (explicit `width:70%`, not
auto/fit-content), so this is specific to popups that override it.

## Premium_Payments: theme_adminltedark added for its 3 per-record view templates (2026-08-14)

First AdminLTE coverage for `Premium_Payments`, on explicit request after a user screenshot
showed the "Payment Entries: View record" screen (a single payment attached to an Invoice's
"Payments" tab) rendering in the unstyled legacy look despite the rest of the app being
`adminltedark`. Root cause was the same "no `theme_adminltedark/` override" gap as every
other never-touched Premium module (see the "Not yet themed" list below and the
`Premium_KnowledgeBase` entry) - `Premium_Payments` used a **custom per-recordset `$tpl`**
(`EntriesRS`/`AgentsRS`/`AddonsRS`'s own `install2()` each call `set_tpl(...)`, pointing at
`view_entry_entries`/`view_entry_agent`/`view_entry_one_col` respectively), so it never fell
through to the generic, already-themed `Utils_RecordBrowser/theme_adminltedark/View_entry.tpl`
at all - RecordBrowser_0.php's `view_entry_details()` looks up a `theme_<name>/<that literal
filename>.tpl` the same way any other template resolves (`Base_ThemeResolver::resolve()`), so a
per-table override needs its **own** `theme_adminltedark/` copy even when its content is
otherwise a plain field-list view no different from the generic template.

All three of `modules/Premium/Payments/theme/view_entry_{entries,agent,one_col}.tpl` turned out
to be byte-identical (confirmed via `diff`) - each is a separate file only because
`EntriesRS`/`AgentsRS`/`AddonsRS` each independently call `set_tpl()` with a different filename,
not because their content ever diverged. Themed all three the same way, in one pass, rather than
just the one in the bug report - Agents/Addons are reachable from Admin ("Payment services" tool)
and would have hit the exact same unstyled-view bug the moment anyone opened one.

New `theme_adminltedark/view_entry_{entries,agent,one_col}.tpl` (identical content across all
three, mirroring `Utils_RecordBrowser/theme_adminltedark/View_entry.tpl`'s own structure almost
verbatim: `.epesi-rv-header`/`.epesi-rv-tools` tools row with module icon+caption+required-note
dropped per that file's established convention, `.epesi-rv-card.card` wrapper,
`.Utils_RecordBrowser__container` > `.Utils_RecordBrowser__View_entry` > `.epesi-rv-fluid` for
regular fields + a `multiselects` fluid block + `.longfields` for long-text fields). **No new CSS
file** - `RecordBrowser_0.php`'s `view_entry_details()` already calls
`Base_ThemeCommon::load_css('Utils_RecordBrowser','View_entry')` whenever a custom `$tpl` is set
(true for all three here), which resolves to `Utils_RecordBrowser/theme_adminltedark/View_entry.css`
under the active theme - reusing that file's exact class names for free inherits its dark card
chrome, fluid-column field grid, and (per the request to "use existing bootstrap icons") its
already-built bootstrap-icons glyph-swap for the info/clipboard/history tooltip icons
(`.epesi-rv-tools a:has(img[src*="RecordBrowser"]...)::before`), with zero new icon assets.
`$fields`/`$longfields`/`$action`/`$main_page`/etc. are assigned by `view_entry_details()`
unconditionally before it picks which template to render, so a custom `$tpl` template can read
them exactly like the generic one, including any per-instance custom fields added later through
RecordBrowser's own "Manage fields" admin screen (e.g. this install has extra `Account`/`First
Name`/`Last Name` fields on `payments_entries` not present in `EntriesRS::fields()`) - no
per-field markup needed in the template either way.

Verified live end-to-end (Playwright driving this machine's installed Edge): the Entries view
from the original bug report (Invoice → Payments tab → click a payment row), that same record's
edit mode (form controls - selects/currency field/textareas - already correctly dark-styled via
`Libs/QuickForm/theme_adminltedark/default.css`, unrelated to this change), and the sibling
Addons/Agents single-record views reached via Admin → "Payment services". Zero console errors.
The embedded Payments-tab list itself (`addon.tpl`, `Utils_GenericBrowser`-based) was already
rendering correctly pre-existing - only the drill-down single-record view was ever broken.

**Found, not fixed - pre-existing, unrelated to theming**: both view and edit mode render a
plain unstyled `<h2>Warning! Balance is already 0.</h2>` above the field grid
(`EntriesRS::QFfield_amount()` prints it directly via raw `print()`, not through a themed
component) - present in the original bug-report screenshot too, so not introduced by this pass.
Cosmetic (a plain white heading against the dark card, no layout break), left alone as out of
scope for a theming-only request.

## Premium_KnowledgeBase: theme_adminltedark added (2026-08-13)

First AdminLTE coverage for `Premium_KnowledgeBase`'s tree view (`KnowledgeBase_0.php`'s
`tree_view()`) — previously had no `theme_adminltedark/` at all, so it always fell back
to the legacy theme regardless of the active app theme. New files:
`theme_adminltedark/tree_view.tpl` (wraps the tree in a `card`/`card-header`/`card-body`,
icon + breadcrumb-style title built from the existing `$title`/`$main_href` vars) and
`theme_adminltedark/tree_view.css`.

**`Utils_Tree` itself still has no `theme_adminltedark` of its own** — confirmed via a
full-repo grep for `init_module('Utils/Tree')`, `Premium_KnowledgeBase` is its only real
caller (the one other hit, `Develop/ModuleCreator`, doesn't render it in a themed
context). It also isn't template-driven at all: `Tree_0.php`'s `print_structure()` builds
raw `<table>` HTML directly in PHP (id/class strings concatenated inline,
`onmouseover`/`onmouseout` swapping a node's `className` between
`utils_tree_node`/`utils_tree_node_hover`), so its legacy `theme/default.css` always
loads no matter which theme is active — nothing to "resolve" per-theme since there's no
template step to intercept. Rather than fork/rewrite this shared, JS-driven widget (real
risk: `Develop/ModuleCreator` also depends on its exact behavior), the new
`tree_view.css` reaches into the borrowed markup with selectors scoped under
`.epesi-kb-body`. The one genuinely functional fix in there (not just cosmetic): the
legacy stylesheet's `.utils_tree_node_hover` hardcodes `background-color:#FFFFFF` — fine
against that theme's white page, but unreadable (near-invisible light text on a white
box) once the surrounding card is dark. Overridden to `var(--bs-tertiary-bg)` instead.

**Used Bootstrap 5.3's own `--bs-*` theme tokens** (`--bs-tertiary-bg`, `--bs-link-color`,
`--bs-body-color`, `--bs-border-color`, `--bs-secondary-color`) **instead of this
codebase's `--epesi-*` custom properties** (`Base/Box/theme_adminltedark/default.css`'s
"Color palette" block). Bootstrap 5.3 (vendored at `libs/bootstrap-5.3.8/`) already flips
these automatically off `data-bs-theme`, same as every stock `.card`/`.card-header` in
the app — so this file needs no hand-written `[data-bs-theme="light"]` override block at
all, unlike most other `theme_adminltedark/*.css` files in this codebase. Worth reaching
for on new module CSS generally: the `--epesi-*` set exists because `Base_Box`'s own
shell (sidebar/navbar) needs values Bootstrap has no token for at all
(`--epesi-sidebar-width`, `--epesi-header-height`, ...) — anywhere a plain Bootstrap
component color would already do the job, `--bs-*` is less code and self-maintaining.

Separately, same day: moved from a top-level sidebar entry to a submenu under "CRM"
(`KnowledgeBaseCommon_0.php::menu()`) — unrelated to theming as such, see
`Dev-Tutorial.md` §7 for the menu-merge mechanism itself.

Verified live end-to-end (Playwright driving this machine's installed Edge — see
`environment-gotchas.md`'s browser-testing entry): root tree, breadcrumb drill-down into
a subcategory, and the reskinned hover state all confirmed logged-in, real data, zero
console errors.

## Recurring CSS/JS traps (read before touching the theme)

1. **CSS loads per rendering module.** `modules/X/theme_adminltedark/default.css` is
   only fetched when module `X` itself renders — putting a style under the
   wrong module (e.g. sidebar CSS under `Utils/Menu` when `Base_Menu` is what
   actually renders it) produces a silently-unstyled screen. Verify with
   `Epesi::get_csses()` after a render rather than assuming.

2. **Never reuse AdminLTE's own class names on markup it doesn't control** (e.g.
   `.nav-treeview`/`.menu-open`, or its `--lte-sidebar-*` color variables) —
   its own CSS/JS will partially apply and produce silently-broken-looking
   behavior (toggles that flip ARIA state but don't show/hide anything). Use
   fresh `epesi-*`-prefixed class names instead.

3. **A `data-bs-theme` pin only wins if it's on every matching ancestor.**
   AdminLTE ships compound selectors like `[data-bs-theme=dark] .app-sidebar`,
   which match through *any* ancestor carrying the attribute — a pin on one
   wrapper doesn't override a conflicting value further up the tree (e.g.
   `<html>`, which `adminlte.min.js` sets directly from OS `prefers-color-scheme`
   detection). Grep the vendored CSS for the literal `[data-bs-theme=dark]`
   selector before assuming a scoped pin is sufficient.

4. **Fixed-height layout vars (`--epesi-header-height` etc.) must track real
   content, not a guess** — the navbar/ActionBar heights vary with what's
   rendered (widget widths, wrapped text). Both are kept in sync live via a
   `ResizeObserver` in `Base_Box/theme_adminltedark/default.tpl`. Never make a CSS
   var that's *written from* an element's measured height also *constrain*
   that same element's `min-height` — creates a one-way ratchet that can only
   grow.

5. **The sidebar has a higher z-index than the navbar's own full-width
   background** — the navbar's *content* (not just its background) must be
   offset by `--epesi-sidebar-width` via `margin-left`, or it renders
   unclickable underneath the sidebar.

6. **Never let a `document.observe("e:load", ...)` handler throw.** This is the
   app-wide "re-init after Epesi's AJAX-patch cycle" convention
   (`Epesi.append_js('Event.fire(document,\'e:load\');Epesi.updateIndicator();')`
   in `include/epesi.js`) — both statements share one script block, Prototype
   doesn't try/catch observers, and an uncaught exception in *any* one observer
   silently aborts `Epesi.updateIndicator()` right after it too (symptom: the
   "Loading..." overlay gets stuck forever, app-wide, with no visible link to
   the actual observer that threw). Any new `"e:load"` handler should wrap its
   body in try/catch and guard on its dependencies actually being loaded
   (load order between independently-`load_js()`'d files is never guaranteed).

7. **Epesi used to render almost everything as nested `<table>`s, not divs** —
   a systemic source of width/sizing bugs, not a one-off. **Resolved for the
   legacy `theme/` as of 2026-08-04** (see the dated entry at the top of this
   file) — that whole theme is now div/flexbox/CSS-Grid-based, the same as
   `theme_adminlte(dark)/` already was. A nested `<table>` with a percentage
   width has no resolvable preferred width under auto-layout, so sizing
   becomes unpredictable and content-dependent; the flex/grid equivalents
   don't have that failure mode, but introduce a different one instead — see
   the CRM_Meeting div-nesting bug in that same entry: a flex/grid container
   closed one level too shallow produces no HTML validation error and no
   console warning, only a silently-collapsed column, so live browser
   verification (not grep/lint) is what actually catches it.

8. **AdminLTE's `.card-body.p-0` CSS reaches into any `<table>` inside it**
   (adds `padding-left/right` to first/last-of-type cells at the same
   specificity as a per-column override — needs `!important` to beat).
   (`Utils/GenericBrowser/js/col_resizable.js`, previously noted here for
   stripping the `width` HTML attribute from header cells at runtime, was
   deleted 2026-08-04 along with the column-resize feature itself — see the
   dated entry at the top of this file.)

9. **Bootstrap utility classes (`shadow-sm`, `border-0`, etc.) set their
   property with `!important`** — remove the utility class from markup rather
   than trying to out-`!important` a custom override targeting the same
   element/property.

10. **A CSS `transform` on any ancestor — even a decorative `:hover` lift —
    becomes the containing block for a `position:fixed` descendant.** A
    JS-positioned fixed dropdown nested inside a hoverable card can get
    violently re-anchored the instant the mouse re-enters the card. Grep
    descendants for `position:fixed` (inline or JS-driven) before adding any
    `transform`/`filter`/`will-change` to an element, anywhere in either theme.

11. **`getBoundingClientRect()` on a `display:none` element returns an
    all-zero rect.** An `onclick="a();b();"` chain where `a()` hides the
    trigger and `b()` measures it (in that order) silently mispositions
    anything JS-positioned. Read the rect before mutating visibility, never
    assume inline-onclick order is layout-safe just because it reads
    left-to-right.

12. **A module's auto-loaded CSS file is named after the *template*, not always
    `default.css`.** `Base_ThemeCommon::display_smarty()` derives the CSS path by
    swapping `.tpl` for `.css` on whatever template name was actually passed to
    `display()` — `$theme->display('tree_view')` loads `tree_view.css`, not
    `default.css` (only `$theme->display()` with no argument resolves to
    `default.tpl`/`default.css`). `Utils/RecordBrowser/theme_adminltedark/
    View_entry.css` is a pre-existing example of this same naming; the
    `Premium_KnowledgeBase` entry above is another. Naming a new theme CSS file
    `default.css` when the module's own template isn't literally displayed as
    `'default'` means it silently never loads — no error anywhere, the page just
    renders with the legacy/unstyled markup and it's easy to mistake for "this
    part of the reskin just wasn't done yet." Check the exact string passed to
    `$theme->display(...)` before naming the CSS file.

13. **A rule that needs to explicitly match the app's default text size should
    reference `var(--epesi-font-size-base)`** (`Base_Theme/theme_adminltedark/
    fonts.css`), never hardcode a literal pixel number. Needed anywhere
    Bootstrap's own rem-based sizing (resolves against the root `<html>`, not
    the inherited/overridden `body` value) or an equal-specificity rule would
    otherwise win over the body default — see the dated entry at the top of
    this file for the full list of existing call sites and why each needed it.

## `Premium_ListManager` / `Premium_CampaignManager`: theme_adminltedark added, including the admin config panel (2026-08-19)

First AdminLTE coverage for both modules, on explicit request including "the Administrator
control panel" specifically (`Premium_CampaignManager::admin_main()`'s Settings tab,
`theme/admin.tpl`). Both are separately-licensed nested git repos under `modules/Premium/`
(see main `CLAUDE.md`) - same never-swept gap as every other first-time Premium theming pass
here (`Premium_Payments`/`Premium_KnowledgeBase` entries above).

**Files added**: `Premium/ListManager/theme_adminltedark/{filter,history_filter,add_new,
add_history,dynamic_list_management}.tpl` + one shared `default.css`;
`Premium/CampaignManager/theme_adminltedark/{admin,cron,daily_limit,filters,default}.tpl` +
one shared `default.css`. `default.tpl` (CampaignManager's message record view) reuses
`Utils_RecordBrowser/theme_adminltedark/View_entry.css`'s own class names
(`.epesi-rv-card`/`.Utils_RecordBrowser__container`/`.Utils_RecordBrowser__View_entry`/
`.epesi-rv-row`/`.label`/`.data`) rather than inventing new ones, mirroring
`CRM/Contacts/theme_adminltedark/Contact.tpl` - valid here because `RecordBrowser_0.php::
view_entry_details()` unconditionally loads that CSS whenever a per-table `$tpl` is set,
same guarantee `Contact.tpl`'s own comment documents. Every other screen in both modules is
raw PHP-built HTML with no such guarantee, so those use each module's own `.epesi-lm-*`/
`.epesi-cm-*` classes instead - reusing another module's theme_adminltedark class names
outside that guarantee is the collision risk this file's top-level README already warns
about.

**Bootstrap Icons, per explicit request ("replace all old icons")**:
- `Premium_ListManagerCommon::bootstrap_icon()` → `bi-list-check`,
  `Premium_CampaignManagerCommon::bootstrap_icon()` → `bi-mailbox-flag` (sidebar/launcher -
  see `modules/Base/Theme/bootstrap_icons.php`).
- Row-action PNGs still swapped via the established hide-`<img>`-add-`::before`-on-the-
  wrapping-`<a>` technique (`Premium/Import/theme_adminltedark/default.css`'s own
  precedent): ListManager's `edit_votes.png`→`bi-hand-thumbs-up`, `add_note.png`→
  `bi-journal-plus`. **Every codepoint used was verified against the vendored
  `libs/bootstrap-icons-1.13.1/bootstrap-icons.min.css`** rather than typed from memory -
  Import's own file already documents one specific glyph (`bi-square`, `\f584`) silently
  failing to render in this vendored build despite matching the font's own docs, so a wrong
  guess here fails silently (blank icon), not with an error.
- `warning.png` (ListManager's inline "no contact info" flag, `display_data_details()`) had
  no wrapping element at all (`::before` on a bare `<img>` is undefined/unreliable per spec
  for replaced elements) - fixed by wrapping it in a `<span class="epesi-lm-warning-icon">`
  in `ListManagerCommon_0.php` itself, the one PHP-markup change this pass needed.
  `close_black.png` (CampaignManager's reply-delete icon, actually `Utils_RecordBrowser`'s
  own asset, borrowed via a CampaignManager-only class `Campaign_manager_del_icon`) got the
  same span-free treatment since that class is already unique to this one usage.
- Recordset tab icons (`icon.png`/`element_icon.png`/`history_icon.png`) were deliberately
  **not** touched: `Utils_RecordBrowser/theme_adminltedark/{Browsing_records,View_entry}.tpl`
  already drop the module icon+caption from those screens entirely under adminltedark (see
  their own "per request" comments), so these PNGs don't actually render as visible `<img>`s
  in the screens either module touches - swapping them would be effort spent on dead code.

**Gotcha worth repeating for the next module**: `Base_ThemeCommon::load_css($module)` calls
`get_template_file($module,'default.css')` and, if that resolves to nothing under the
*currently active* theme, hits `trigger_error(..., E_USER_ERROR)` by default - a real fatal,
not a blanked-module warning (contrast `CLAUDE.md`'s `REPORT_ALL_ERRORS` note, which is about
E_WARNING/E_NOTICE). `Premium/ListManager/theme/` had no `default.css` at all before this
pass (only `.tpl`/`.png` files) - adding `load_css($this->get_type())` calls without also
adding an (empty, comment-only) `Premium/ListManager/theme/default.css` would have fataled
every one of these screens the moment anyone ran the *legacy* (non-adminltedark) theme.
CampaignManager didn't need this fallback since `theme/default.css` already existed there.
Before copying this module's `load_css($this->get_type())` pattern into a Premium module
that has never had *any* CSS file (Import's own precedent already had one, which is why its
own version of this note never had to mention this), check for/add the legacy-theme fallback
file first.

**Not touched (explicitly out of scope for a theming pass)**: this pass's own scoping research
(see the conversation, not repeated here) found several JS files across both modules still
mid-`legacy-js-migration.md` - `Premium/CampaignManager/js/{manage_emails,autosave}.js` still
use `Ajax.Request`/`Object.toJSON`/`Event.observe`, and `placeholders.js`'s reply-add/delete
handlers do too (its blind/expand functions were already fixed for the removed `Effect.*`
dependency in an earlier session - see `legacy-js-migration.md`'s own scriptaculous-gap entry).
None of this blocks the CSS/template work above, but is a separate, pre-existing gap - not
something this reskin introduced or fixed.

**Follow-up, same day: `Utils_TabbedBrowser` replaced with a native `<details>`/`<summary>`
accordion in `admin()` specifically, per explicit request** (the user saw its 3 tab captions
render as a plain unstyled bullet list - `Utils_TabbedBrowser/theme_adminltedark/default.tpl`'s
own `.epesi-tb-nav`/`.epesi-tb-item` CSS wasn't the actual problem, that file/CSS pair is fine;
this was a "replace the component" ask, not a "fix its styling" one). Chose native
`<details>`/`<summary>` over Bootstrap 5's own `data-bs-toggle="collapse"` accordion
specifically to avoid a theme-detection branch in PHP: Bootstrap 5's JS only loads under
adminltedark (see `legacy-js-migration.md`), so a `data-bs-toggle`-based accordion would have
needed `admin()` to check the active theme and fall back to something else under the legacy
theme. `<details>`/`<summary>` needs no JS at all and degrades to the browser's native
disclosure triangle under the legacy theme (unstyled but functional) while getting a real
AdminLTE look under this theme's `default.css` (chevron `::before` on `<summary>`, rotated via
`[open] > summary::before { transform: rotate(90deg) }` - `::marker` can't be transitioned
directly since it isn't a real box, hence suppressing it and drawing the glyph separately).

**The real complexity wasn't the markup, it was `Base_ActionBarCommon`.** `Utils_TabbedBrowser`
only ever calls the *selected* tab's callback per request (`display_contents()`'s
`$selected || $val['js']` guard) - so `admin_main()`/`admin_lists()`/`admin_email_server()`
could each freely call `Base_ActionBarCommon::add('save', ..., $form->get_submit_form_href())`
without colliding, since only one of them ever ran per request. An accordion has no such
concept from PHP's side - all three sections (three independent `Libs_QuickForm` instances,
three independent submit flows) now render unconditionally on every request, `admin()` just
prints each one wrapped in its own `<details>`. Three calls to `Base_ActionBarCommon::add('save',
...)` with the same key in one request don't merge or stack - last-write-wins, so two of the
three forms would have silently lost their only way to submit (no visible button anywhere,
since the href being clicked doesn't matter until something points a real element at it).
**Fixed by moving each form's Save trigger inline into its own accordion body** instead of the
shared ActionBar: `admin_main()` now assigns `save_href` for `admin.tpl` (both theme copies) to
render as a link using the same `get_submit_form_href()` mechanism; `admin_lists()` folds the
same link into its `Utils_GenericBrowser` postfix (inside the same `<form>`); `admin_email_server()`
gets a real QuickForm `submit` element instead (simplest of the three, since that method already
uses QuickForm's own generic renderer, which themes a submit button automatically - no manual
markup needed). `admin_email_server()`'s `clone`/`search` ActionBar entries ("Copy Epesi
settings"/"Test") were left as-is in the shared ActionBar - unique keys, no collision, the only
change is they're now always visible rather than only while that section happened to be "the
active tab", a minor UX quirk not a functional bug. `admin_main()`'s and `admin_lists()`'s own
redundant `Base_ActionBarCommon::add('back', ...)` calls (duplicating `admin()`'s own, always
harmless since 'back' never differed) were dropped during this pass, not because they were
broken.

**Not done**: no attempt to preserve `Utils_TabbedBrowser::tag()`'s hidden
`<span>md5(...)</span>` (some kind of change-detection marker) - dropped along with the rest of
TabbedBrowser for this screen; if some caching/refresh mechanism elsewhere in the app turns out
to depend on it specifically for this screen, that would be a new, currently-undiagnosed
symptom to trace back to this change. Not browser-tested live this pass (`php -l`/Smarty
tag-balance/CSS brace-balance checked only) - if the accordion or any of the three forms
misbehaves, re-check this entry before assuming a fresh bug.

**Correction, same day**: the scoping research above also called `admin.js`'s bare `$(...)`
calls "not itself evidence of remaining migration work," trusting `CLAUDE.md`'s (stale) claim
that `$` is bound to Prototype on this codebase. It isn't, hasn't been since 2026-08-06, and
that specific bare-`$('id')` pattern is exactly what threw `Uncaught TypeError: Cannot set
properties of undefined (setting 'overflow')` opening this same admin screen live - see
`legacy-js-migration.md`'s own new entry for the full fix list (`admin.js` in full,
`placeholders.js`'s two reachable sites, two `CampaignManagerCommon_0.php` eval_js strings, and
`CampaignManager_0.php`'s `admin_lists()` checkbox toggle). Don't repeat that mistake -
`CLAUDE.md`'s Rendering section needs a correction, flagged to the user rather than edited
solo.

**Reverted, 2026-08-20: back to `Utils_TabbedBrowser` in `admin()`.** The native
`<details>`/`<summary>` accordion from the entry above didn't look good in practice - reverted on
explicit request the same day it was tried, back to the original `$tb = $this->init_module
('Utils_TabbedBrowser'); ...; $tb->tag();` call. Undid, in `CampaignManager_0.php`: the accordion
markup in `admin()`; `admin_main()`'s/`admin_lists()`'s/`admin_email_server()`'s inline Save
triggers, restored to shared `Base_ActionBarCommon::add('save', ...)` calls (safe again now that
only one tab's callback runs per request, the original collision this pattern was built to avoid
no longer exists). Undid, in the templates: the `$save_href`-rendering blocks added to both
`theme/admin.tpl` and `theme_adminltedark/admin.tpl`. Undid, in `theme_adminltedark/default.css`:
the `.epesi-cm-accordion-*`/`.epesi-cm-list-save`/`.epesi-cm-form-actions` rules, now dead. Left
alone: everything else from the theming pass above (the six-card `admin_main()` layout in
`theme_adminltedark/admin.tpl`, Bootstrap Icons swaps, `Utils_TabbedBrowser`'s own
`theme_adminltedark/default.css` `.epesi-tb-nav`/`.epesi-tb-item` styling) - none of that was
the complaint, only the accordion-vs-tabs choice was. If tabs are asked to change again, don't
reach for this same accordion approach without checking this entry first.

**Follow-up, same day: the reverted tabs still rendered as a plain unstyled bullet list -
root-caused to a one-character CSS authoring bug, not the tabs-vs-accordion choice.**
After the revert above, `Utils_TabbedBrowser`'s admin() tabs still showed no styling at all
(bullet list, `display:block` instead of the `.epesi-tb-nav` flex layout) - the same symptom
the original accordion-switch entry blamed on "TabbedBrowser is unstyled under this theme."
That diagnosis was wrong. The real cause: `Premium/CampaignManager/theme_adminltedark/
default.css`'s own top-of-file header comment contained the prose "Utils_RecordBrowser's own
.epesi-rv-" followed directly by a wildcard asterisk and a slash before the next word - i.e.
the literal two-character comment-close sequence, typed as normal English inside the comment,
not as code. CSS comments don't nest and don't care about intent: that sequence closed the
block comment nine lines early, and everything from there to the *real* intended close became
live, garbled "CSS" - which doesn't just corrupt one rule, it corrupts *all CSS in that same
parse* from that point on, including every other bundled file (`admin()`'s screen bundles this
file together with `Utils_RecordBrowser/View_entry.css` and `Utils_TabbedBrowser`'s own CSS via
`theme_css.php`/`libs/minify`, so the tabs' styling - a completely unrelated file - silently
disappeared too). Confirmed with `CampaignManager/theme_adminltedark/default.css` alone in
isolation: 0 CSSOM rules parsed from an otherwise well-formed file, until the comment was fixed.
Fixed by rewording the sentence so the asterisk and slash are never adjacent (spelled out as
"epesi-rv prefixed classes" instead of the wildcard-glob shorthand) - two follow-up attempts at
a fix ironically re-introduced the exact same bug by describing the problem using the literal
two-character sequence inside the fix's own explanatory comment; the final wording avoids typing
that sequence anywhere, including in prose.

**Not a general multi-file-bundling bug** - a live sweep of the rest of `modules/` for the same
pattern (a wildcard-glob-style comment ending in an asterisk immediately before a slash) found
this to be the only occurrence codebase-wide, and a clean 2-file bundle (`View_entry.css` +
`Utils_TabbedBrowser`'s own CSS, no CampaignManager file involved) parses and applies correctly.
So `theme_css.php`/Minify's file-combining itself is fine; this was a one-file content bug that
happened to have an outsized, hard-to-trace blast radius because of what it silently corrupted
downstream. Worth remembering next time a reskinned screen looks completely unstyled for no
apparent reason: check whether *any* CSS file sharing that page's bundle request has a comment
whose prose happens to spell out those two characters back-to-back - `sheet.cssRules.length`
on the individual file in isolation (via devtools/console, not a text-level `/*`/`*/` count,
which has the same blind spot as the bug itself) is the fastest way to confirm it.

**Follow-up, 2026-08-20: footer editor (`admin_edit_footer()`) reported "broken" - two real
bugs, both in `theme_adminltedark/default.tpl`, not the placeholder/reply-link panel's own
CSS.** User screenshot showed the footer editor (Admin → Campaign Manager → Settings tab →
"you can edit the footer by clicking **here**") rendering with no visible card boundary at
all - floating directly on the page background - and its instructional field label truncated
mid-sentence to "Message Footer - leave". Both root-caused to the same file:

1. `<div class="epesi-rv-card{if $main_page} card{/if}">` only adds Bootstrap's `.card` class
   (border/shadow/rounded-corner clipping - see `Utils_RecordBrowser/theme_adminltedark/
   View_entry.css`'s own comment on `.epesi-rv-card.card`) when `$main_page` is true.
   `admin_edit_footer()` deliberately passes `main_page=false` (see this file's own dated
   entry above explaining why - it needs the header/tools row's own `$main_page` gate, not the
   card class, and originally had no way to ask for one without the other). Fixed by widening
   the template's condition to `{if $main_page || isset($footer_mode)}` - `$footer_mode` is
   already assigned `true` by this exact rendering path and used elsewhere in the same
   template, so this needed no new PHP variable.
2. The `message_subject` field's `.epesi-rv-row` has no `.data` sibling in footer mode (no
   `isset($fields.message_subject.html)`), so its lone `.label` div - built for a short field
   name like "Name"/"List" (`flex:0 0 150px; white-space:nowrap; overflow:hidden` in
   `View_entry.css`) - was carrying the whole instructional sentence
   (`admin_edit_footer()`'s `'Message Footer - leave the field empty to use default footer'`)
   and clipping it. Same underlying shape as the legacy `theme/default.tpl`'s own
   `label`/`label_top`+`colspan=2` special-case for this exact field, which the adminltedark
   port never carried over. Fixed with a new modifier class (`epesi-cm-label-only`, added to
   the row only when `!isset($fields.message_subject.html)`) whose `.label` override resets
   `flex`/`white-space`/`overflow` to let it wrap and grow like a heading instead.

**Separately, live-testing this screen surfaced a real functional bug in
`Premium/CampaignManager/js/placeholders.js`, unrelated to theming**: every "Paste" button
(Insert Placeholder/Insert Reply link/Insert Attachments) threw
`Uncaught ReferenceError: CKEDITOR is not defined` on click -
`campaign_manager_placeholders_insert()` was never ported off the CKEditor API when this
module's message-body field switched to Quill (`QFfield_ckeditor()`/`admin_edit_footer()`
both already call `addElement('quill', ...)` - only this one JS function still called
`CKEDITOR.instances.ckeditor_message.*`). Missed by the original CKEditor→Quill sweep because
that sweep was PHP-side (`addElement('ckeditor', ...)` call sites - see
`ckeditor-to-quill-migration.md`'s own "Gap found" entry on `modules/Premium/`/`modules/Custom/`
not being covered by the original repo-wide sweep); this was a JS-side leftover with no PHP
signature to grep for. Ported to `quills['quill_message']` (`Libs/Quill/qu.js`'s live-instance
registry, keyed by `quill.php`'s own `'quill_'.$elementName` id convention) -
`insertText()`/`getText()`/`deleteText()`/`clipboard.dangerouslyPasteHTML()` replacing
`insertText()`/`getSelection()`/`insertHtml()`. One follow-on bug caught by testing the fix
live, not obvious from reading the code: plain-token inserts (the single-arg call shape, e.g.
`{target.first_name}`) landed right after an already-inserted reply link picked up that link's
own formatting (Quill inherits the format of the character immediately before the insertion
point unless told otherwise) - the placeholder token rendered as blue/underlined link text
instead of plain text. Fixed by passing an explicit `{link: false}` formats argument to
`insertText()`.

**Also fixed, same file, pre-existing and already flagged (not closed) by this doc's own
2026-08-19 entry above**: `campaign_manager_new_reply()`/`campaign_manager_delete_placeholder()`
still used `Ajax.Request`/`Object.toJSON`/`element.up(...)` (all Prototype APIs removed
2026-08-06 - see `legacy-js-migration.md`). Ported to `jQuery.ajax()`/`JSON.stringify()`/
`element.closest(...)`, same mechanical recipe as every other `Ajax.Request` port in that doc.
`new_reply.php`'s own response string had the matching bare-`$(id)`-is-jQuery's-tag-selector
bug this doc's "Recurring CSS/JS traps" list and `CLAUDE.md` both warn about
(`$("campaign_manager__replies").innerHTML=$("campaign_manager__replies").innerHTML+"..."` -
silently no-ops, never threw) - fixed to `document.getElementById("campaign_manager__replies").
innerHTML+="..."`, same convention as every other site in `legacy-js-migration.md`'s own fix
list for this module.

Verified live via Playwright (both light and dark mode): footer editor card boundary and full
label text now render correctly; a plain placeholder token, a reply-link token, "Add new reply
option" (round-trips through `new_reply.php`, new button appears with correct styling), and its
delete (X) button (confirm dialog → row hidden → `delete_reply.php` round-trip) all verified
end-to-end with zero console errors, on both the footer editor and the regular message Add/Edit
screen. `manage_emails.js`/`autosave.js` still have their own separate,
already-documented-elsewhere gaps (Ajax.Request/Object.toJSON for the former;
`autosave.js` reads a stale `#ckeditor_message` field id that doesn't exist in the DOM anymore
for its own message-body slice specifically - found but not fixed this pass, out of scope for
what was reported broken) - not touched this pass.

**Follow-up, 2026-08-20: footer editor still visually cramped after the fixes above - two
more layout bugs, both root-caused to the same `CampaignManager` files (not `View_entry.css`
this time).** User screenshot showed the Quill editor's text area a hair narrower than its
own toolbar above it (a visible jog on the right edge), and the whole editor boxed into only
the right half of the screen with a large unused gap to its left.

1. **Editor body narrower than its own toolbar.** `quill.php`'s `toHtml()` only applies an
   inline `width` to the container div Quill turns into `.ql-container` - the toolbar Quill
   auto-generates is inserted as that container's *preceding sibling* (see `qu.js`'s own
   comment on this), gets no inline width of its own, and defaults to 100% of the parent.
   Both `CampaignManager_0.php::admin_edit_footer()` and `CampaignManagerCommon_0.php::
   QFfield_ckeditor()` (the regular Add/Edit message field - same underlying bug, same fix)
   called `setQuillProps('99%', ...)` - a container 1% narrower than its own toolbar. Every
   *other* `setQuillProps()` caller in the codebase (`Applets/Note`, `CRM/Mail`,
   `Utils/Attachment`, `Utils/RecordBrowser`) already passes `null` for width, which is what
   keeps their toolbar/container in sync. Fixed by changing both CampaignManager call sites
   from `'99%'` to `null` to match.
2. **Editor stuck in a half-width column with unused space to its left.** `default.tpl`'s
   two `.column` divs (placeholders panel, message editor) are unconditionally 50%/50w -
   fine for the regular Add/Edit screen, where the left column also holds five metadata
   rows (name/list/date/scheduled/sent_to), but in `$footer_mode` those rows are hidden
   (`{if !isset($footer_mode)}`) and the left column holds only the placeholders tree, which
   never needed anywhere near half the row. Fixed with a `$footer_mode`-conditional inline
   style: the left column becomes a fixed `320px` sidebar (`flex: 0 0 320px`, matching its
   actual rendered content width, unchanged from before this fix) and the right column
   switches to `flex: 1 1 auto` to fill the reclaimed space - same shape as every other
   `$footer_mode`-only tweak already in this file (the card-class/label-wrap fixes above).
   Regular (non-footer) Add/Edit screen deliberately left at 50/50, unchanged.

Verified live via Playwright (both light and dark mode, `jtylek` login): footer editor's
toolbar and text area now line up flush on the right edge, and the editor now spans nearly
the full viewport width instead of half of it with a dead gap alongside. Regular message
Add/Edit screen (`List Messages` → `New`) re-checked side by side - still 50/50, toolbar/
editor width now matches there too, five metadata fields unaffected.

**Follow-up, 2026-08-20: footer editor opened blank instead of showing the current footer,
and Save then threw a JS crash - two more bugs in `CampaignManager_0.php`, unrelated to
theming.** User reported the Settings tab's "Current footer:" preview showed real text but
clicking "here" to edit it opened an empty Quill editor; saving from there (even unchanged)
then threw `Uncaught TypeError: Cannot set properties of null (setting 'innerHTML')`.

1. **Blank editor.** `admin_edit_footer()` preloaded the Quill field straight from the raw
   `campaign_manager_footer_text` Variable, which is empty until an admin has ever saved a
   custom footer. The Settings-page preview instead calls
   `Premium_CampaignManagerCommon::get_footer()`, which falls back to hardcoded default text
   when that Variable is empty - so the preview showed real content while the editor, reading
   the raw Variable directly, opened blank. Fixed by falling back to `get_footer()` in the
   editor too when the raw value is empty, so it preloads the same effective text the preview
   just showed.
2. **Save crash.** `admin_edit_footer()` built its full render output (`$form->accept($renderer)`,
   theme assigns, and a `get_placeholders_html(null)` call) unconditionally, *before* checking
   `$form->validate()`. `get_placeholders_html()` (and the reply/attachment sub-calls it makes)
   each call `eval_js('document.getElementById("campaign_manager__replies").innerHTML = ...')`
   assuming its own returned stub `<div id="campaign_manager__replies"></div>` HTML lands in the
   same response right after - true on GET/redisplay, since that HTML gets printed via
   `$theme->display()`. On a successful Save, though, the function took the
   `Variable::set(...); return false;` branch and never printed anything - the queued eval_js
   still fired (append_js has no idea the print never happened), so
   `document.getElementById("campaign_manager__replies")` returned null and `.innerHTML=`
   threw. Same shape applies to `campaign_manager__attachments`. Fixed by moving the
   `$form->validate()` check up to right after `setDefaults()`, before any of the
   renderer/theme/`get_placeholders_html()` work - the save path now returns before any of that
   runs, matching how `admin_main()`/`admin()` elsewhere in this same file already validate
   before building render output.

Also split the Settings-page "You can edit the footer by clicking here. Current footer:" hint
into two lines (own `<div class="epesi-cm-hint">` each in `admin.tpl`, backed by a new
`custom_footer_current_label` theme var alongside the existing `custom_footer_label`) per user
request, and made the preview substitute real values for `{main_company.*}` tokens instead of
showing them raw - `get_footer()`'s default/custom text both leave those unresolved (real
substitution normally only happens at send time, in `prepare_message()`, which needs a
target/employee context this settings-page preview doesn't have). Added
`Premium_CampaignManagerCommon::get_footer_preview()`, a display-only wrapper around
`get_footer()` that resolves `{main_company.*}` using the same company-field-loop shape as
`prepare_message()`'s own cache-building, and pointed `admin_main()`'s `custom_footer_info`
assign at it instead of the raw `get_footer()`. `get_footer()` itself (used by the editor's
own default-fallback and by actual message sending) is untouched.

Verified live via Playwright (`jtylek` login): editor now preloads the same text as the
preview; clicking Save with no changes round-trips cleanly with zero console errors (previously
threw immediately); Settings-page preview now reads "You can edit the footer by clicking
here." / "Current footer:" on separate lines with `{main_company.company_name}` resolved to
the real company name (`{main_company.email}` resolved to an empty string, correctly, since
this dev company record has no email set).

## New modules: `bootstrap_icon()` only, no `theme/icon*.png` (2026-08-21)

Established while building `Premium_Domains` (a brand-new module, not a retrofit):
per explicit request, a module written from scratch should declare its icon **only**
via `bootstrap_icon()` on the `Common` class (see the "renamed to theme-agnostic"
update above) and skip the legacy per-module `theme/icon.png`/`icon-small.png` raster
files and the matching `Utils_RecordBrowserCommon::set_icon(...)` call entirely - don't
scaffold them just because `console.php dev:module:create`/older modules do. This is
narrower than the general resolve() precedent already documented above (real modules
still keep their existing raster icons where callers deliberately pass `$fallback=null`
to preserve one, e.g. ActionBar's launcher/launchpad and LeightboxPrompt): those are
existing, already-shipped icons with call sites relying on them, not something to
newly add. A brand-new module has no such install base to preserve, so there's no
reason to ship a PNG nobody reads once `bootstrap_icon()` covers the (currently only
real) consumer, `Base_BootstrapIcons::resolve()`. Both `Base_ThemeCommon::
install_default_theme()`/`uninstall_default_theme()` are literal no-ops today (see
`ThemeCommon_0.php`) regardless of whether a `theme/` directory exists, so calling them
with no `theme/` dir at all is harmless and still fine to keep for convention/future-proofing.

This is a forward-looking convention for new modules, not a retrofit instruction -
don't go remove existing modules' `theme/icon*.png` files/`set_icon()` calls under this
note alone.

See `MIGRATION_NOTES.md` for the PHP-version-migration side of this codebase;
these theme notes are a separate, still-ongoing effort.
