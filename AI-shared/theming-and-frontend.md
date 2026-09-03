# Theming and front end

CSS, icons and JavaScript for a module. Part 1 is everything an ordinary module needs.
Part 2 is the mechanics behind it, for deeper theme work.

---

# Part 1 — what a module author needs

## There is one theme

Every module can ship two template/asset directories:

- `modules/<M>/theme/` — the legacy default theme.
- `modules/<M>/theme_adminltedark/` — **this is the theme in use.**

`adminltedark` handles both light and dark; there is no separate `theme_adminlte/`
directory anywhere in the tree. `Base_ThemeCommon::is_adminlte_family()` is the "are we on
the Bootstrap rendering path" check, and `theme_adminltedark` is its only member. The
legacy `theme/` survives as a file-fallback layer for assets `theme_adminltedark/` does not
override — not as a theme a user can select.

**The two directories do not cascade.** If both hold the same filename, the resolver picks
one file — a fix to one is not inherited by the other. Port explicitly and re-check
specificity in a real browser.

## Declaring your module's icon

**Bootstrap Icons is the single icon mechanism.** Declare one method on your `Common`
class — the same "opt in by defining a conventionally-named method" shape as `menu()`:

```php
public static function bootstrap_icon() { return 'bi-calendar-date'; }
```

A module owning several recordsets can give each its own glyph:

```php
public static function bootstrap_recordset_icons() { return array('company' => 'bi-building'); }
```

Unnamed recordsets fall back to the module's `bootstrap_icon()`, so the common
one-recordset module declares nothing extra.

**For a brand-new module, declare `bootstrap_icon()` and nothing else.** Skip the legacy
`theme/icon.png` / `icon-small.png` files and the `set_icon()` call, even though
`dev:module:create` and older modules still scaffold them.

## Where your CSS goes, and what to call it

1. **CSS loads per rendering module.** `modules/X/theme_adminltedark/default.css` is
   fetched only when module `X` itself renders. Putting a style under the wrong module
   produces a silently unstyled screen.
2. **The file is named after the *template*, not always `default.css`.**
   `Base_ThemeCommon::display_smarty()` swaps `.tpl` for `.css` on whatever name was passed
   to `display()` — `$theme->display('tree_view')` loads `tree_view.css`. Naming it
   `default.css` when the module does not display `'default'` means it silently never
   loads, and the screen looks like a piece of the reskin nobody finished. Check the exact
   string passed to `display(...)` before naming the file.
3. **Never reuse AdminLTE's own class names** on markup it does not control
   (`.nav-treeview`, `.menu-open`, `--lte-sidebar-*`). Its own CSS/JS partially applies and
   produces silently-broken behaviour — toggles that flip ARIA state but show nothing. Use
   fresh `epesi-*` names.
4. **Reference `var(--epesi-font-size-base)`** rather than a literal pixel size anywhere a
   rule must match the app's default text size. Bootstrap's rem sizing resolves against the
   root `<html>`, not the overridden `body` value.
5. **Don't set `width`/`min-width` `!important` on a grid column.** Grids size their own
   columns from JavaScript, and `!important` fights the inline value that script writes.

## Writing JavaScript

- **`$` is jQuery, not Prototype.** Old code written against Prototype's `$` still parses
  and still runs — it just does something different:

  ```js
  $('some_id')            // Prototype: the element, or null
                          // jQuery:    a TAG-NAME selector — an empty collection
  ```

  So an `if (!el) return` guard never fires, and the returned empty collection has no
  `.style` / `.value` / `.disabled` / `.innerHTML`. Assignments to those are **silent
  no-ops**, not errors — which is exactly why the shape survives unnoticed: a checkbox that
  never disables, a panel that never populates. **Code needing a raw DOM element must use
  `document.getElementById(id)`** — not `$(id)`, not `jQuery(id)`.
- **Never call native `confirm()` or `alert()`.** Both are replaced app-wide by styled
  modals: `Module::create_confirm_href()` / `window.epesi_confirm()` and
  `window.epesi_alert()`. Both fall back to the real thing automatically off-AdminLTE.
- **Prefer native attributes and AdminLTE's own JS over hand-rolled listeners.** Bootstrap
  modal autofocus needs the `autofocus` attribute, not a `shown.bs.modal` listener —
  `adminlte.min.js` already runs its own focus-stealing script.
- **`eval_js()` is a global helper** (`include/misc.php`), callable straight from module
  PHP — not only from a `{php}` block in a template.
- **`eval_js_once()` means once per session, not once per render.** If the target element
  can be re-rendered, use `eval_js()` plus an idempotency marker property on the element.
- **`load_js()`/`load_css()` are per-session, not per-file-version.** Editing an
  already-loaded JS or CSS file shows nothing until a fresh tab or a new login. This is the
  single most common "my change did nothing" in front-end work here.
- **Never let a `document.observe("e:load", ...)` handler throw** — see Part 2.

## Rich-text fields use Quill

`modules/Libs/Quill/` provides the `quill` QuickForm element type. Declare
`Libs_QuillInstall` in your module's `requires()`. The toolbar preset (Basic or Advanced)
comes from the user's own `Base_User_SettingsCommon::get(..., 'editor')` setting, passed as
`setQuillProps()`'s third argument — **don't hardcode a preset.**

Nothing registers a `'ckeditor'` QuickForm type. `modules/Libs/CKEditor/` still exists as
an inert shell; see [dont-reintroduce.md](dont-reintroduce.md).

---

# Part 2 — the mechanics

## What actually loads

- **jQuery 1.11.3** + `jquery-migrate-1.2.1` + `jquery-ui-1.10.1`, hard-coded in
  `index.php`'s `$jses` array — bypassing Epesi's own `load_js()` module-asset system.
- **Bootstrap 5 + AdminLTE 4**, loaded when that theme is active. The theme chrome itself
  is jQuery-free; the legacy stack is the widget layer underneath.
- **Prototype.js and script.aculo.us are not loaded**, and neither is `jQuery.noConflict()`.

`Event.observe` / `Event.fire` therefore hit the browser's native `Event` constructor and
throw. Worse, `serve.php` concatenates its file list into one script and executes it as a
unit, so a top-level throw in one file aborts every file after it in the same bundle —
producing an unrelated-looking second error in a module that has nothing to do with the
first. Use `jQuery(document).on(...)` / `.trigger(...)`.

The replacement recipe for old code is mechanical: `Ajax.Request`/`Ajax.Updater` →
`jQuery.ajax()`, `Object.toJSON` → `JSON.stringify()`, `Class.create` → a plain JS class,
`Element.*` → jQuery or vanilla DOM. It is easy to reintroduce the old shapes when porting
an inline `onclick` or an `eval_js()` string, because a server-built JS string is caught by
no linter.

## Icon resolution

`modules/Base/Theme/bootstrap_icons.php` (`Base_BootstrapIcons`, plain PHP, not a `Module`,
because consumers pull it in from Smarty `{php}` blocks) resolves icons:

| method | returns | used for |
|---|---|---|
| `resolve($icon, $module, $fallback)` | `'bi-...'` | everything below; pass `$fallback=null` to mean "keep your own raster icon" |
| `tag($module, $classes)` | `<i class="bi ...">` or `null` | record shortcut buttons |
| `type_tag($module, $recordset)` | a muted type glyph, or `''` | a list mixing several record types |
| `resolve_recordset($module, $recordset)` | `'bi-...'` or `null` | the per-recordset override |

`resolve()` tries the `$by_filename` map (basename of an icon file), then the module's
`bootstrap_icon()`, then the fallback. **The filename map is not a place to add module
icons** — it exists only to tell apart two things registered by the *same* module when the
caller holds nothing but a filename. There is no central module→icon map.

Icons render in the sidebar menu, the header module indicator, the ActionBar launcher and
launchpad, admin panels, the New-record type chooser, the record shortcut buttons, and
mixed-type list rows.

**`set_icon()` / `recordbrowser_table_properties.icon` is not a second icon source.** It
stores a module-relative path that `RecordBrowser_0::init()` expands and hands to
`resolve()`, which extracts the *module* from the path shape and asks its `Common` class.
No raster recordset icon is ever drawn; the stored path survives only as a module+table
discriminator. Don't add a consumer that renders the PNG, and don't resolve icons from the
stored value.

## `.epesi-fullbleed` — a screen that must fill the content column

Put `.epesi-fullbleed` on the single top-level element of the module's own output.
`Base_Box/theme_adminltedark/default.css` then zeroes the content column's three padding
sources and gives that element
`height: calc(100vh - var(--epesi-header-height) - var(--epesi-actionbar-height))`.

It is matched with `:has()` rather than a class on `.app-content` on purpose: this is an
AJAX-push SPA, so `.app-main`/`.app-content`/`.container-fluid` outlive navigation, and a
class set while rendering one screen would have to be unset by every other screen. Keying
off the opted-in element makes the exception disappear on its own.

Two traps if you build something similar: an `<iframe>` needs `display:block` (the inline
default puts it on a text baseline, and the descender gap pushes it out of the wrapper),
and the overrides win on specificity — `:has()` inherits the specificity of its most
specific argument — not on `!important`.

## Dark is the unscoped default; light is the override

`.app-wrapper` always carries `data-bs-theme="dark"` as a fixed baseline, independent of
`<html>`, which is what the light/dark toggle actually flips. So `[data-bs-theme="dark"] X`
matches **unconditionally** (there is always a dark ancestor) while `[data-bs-theme="light"] X`
matches only in light mode.

Gating dark colours behind `[data-bs-theme="dark"]` looks intuitive and produces a
solid-black editor in light mode too. Every `theme_adminltedark/*.css` file follows the
correct convention; grep for the inverted form if a new dark-mode rule looks backwards.

**A `data-bs-theme` pin only wins if it is on every matching ancestor.** AdminLTE ships
compound selectors like `[data-bs-theme=dark] .app-sidebar` that match through *any*
ancestor carrying the attribute — including `<html>`, which `adminlte.min.js` sets from OS
`prefers-color-scheme`. Grep the vendored CSS for the literal selector before assuming a
scoped pin is enough.

## Remaining CSS and JS traps

1. **Bootstrap utility classes set their property with `!important`.** Remove the utility
   class from the markup rather than trying to out-`!important` it.
2. **AdminLTE's `.card-body.p-0` reaches into any `<table>` inside it**, at the same
   specificity as a per-column override — it needs `!important` to beat.
3. **Fixed-height layout vars must track real content.** `--epesi-header-height` /
   `--epesi-actionbar-height` are kept in sync live by a `ResizeObserver` in
   `Base_Box/theme_adminltedark/default.tpl`, because the bars' heights vary with what is
   rendered. Never make a var that is *written from* an element's measured height also
   *constrain* that element's `min-height` — that is a one-way ratchet that can only grow.
4. **The sidebar out-ranks the navbar's full-width background in z-order**, so the navbar's
   *content* must be offset by `--epesi-sidebar-width`, or it renders unclickable
   underneath.
5. **Never let a `document.observe("e:load", ...)` handler throw.** That is the app-wide
   "re-init after Epesi's AJAX-patch cycle" hook; the fire and `Epesi.updateIndicator()`
   share one script block and nothing try/catches observers, so one uncaught exception
   leaves the "Loading..." overlay stuck forever, app-wide, with no visible link to the
   observer that threw. Wrap the body in try/catch and guard on dependencies — load order
   between separately `load_js()`'d files is never guaranteed.
6. **A CSS `transform` on any ancestor — even a decorative `:hover` lift — becomes the
   containing block for a `position:fixed` descendant.** Grep descendants for
   `position:fixed` before adding `transform`/`filter`/`will-change` anywhere.
7. **`getBoundingClientRect()` on a `display:none` element returns an all-zero rect.** An
   `onclick="a();b()"` chain where `a()` hides the trigger and `b()` measures it silently
   mispositions everything. Read the rect before mutating visibility.
8. **A flex/grid container closed one level too shallow produces no validation error and no
   console warning** — only a silently collapsed column. Live browser verification, not
   grep or lint, is what catches it.
9. **Leightbox popups use a fixed grey/black chrome regardless of theme.** Any native
   `<select>`/`<textarea>`/`<input>` inside one needs `color` **and** `color-scheme: light`
   — a light `background-color` alone leaves white-on-white text under dark mode.

## Quill specifics

- **Quill's format matching is type-strict.** `indent` takes numeric `-1`/`1`; a string
  silently no-ops and logs *"quill:toolbar ignoring attaching to nonexistent format"*
  rather than erroring.
- **Put `load_css()`/`load_js()` in the element class's constructor**, not at the top level
  of a `Common_0.php`. A `Common` file's top-level code is not reliably re-run on every
  request, so CSS loaded there fires intermittently. The constructor path is reliable.
- `Libs/Quill/frontend.css` is a straight port of CKEditor's, kept so old stored HTML using
  CKEditor's Styles classes (`class="Bold"`, `"Title"`, `"Code"`) still renders.
