# Theming: how AdminLTE is wired into Epesi

> **Status:** REFERENCE - the theme layout, the icon system, and the CSS/JS traps that keep
> recurring. The full rewrite history — every dated conversion pass, per-module reskin and
> post-mortem — is archived at `AI-private/archive/adminlte-theme.md`.

## The layout

Every module can ship two template/asset directories:

- `modules/<M>/theme/` — the legacy default theme.
- `modules/<M>/theme_adminltedark/` — the AdminLTE reskin. **This is the theme in use.**

`adminltedark` handles both light and dark; there is no separate `theme_adminlte/` (the
light-only variant was deleted outright in 2026-08). `Base_ThemeCommon::is_adminlte_family()`
is the "are we on the Bootstrap rendering path" check, and `theme_adminltedark` is its only
member. The legacy `theme/` survives as `Base_ThemeResolver`'s **file-fallback layer** for
assets `theme_adminltedark/` doesn't override — not as a theme a user can select. Theme
upload/installation was removed; see [deliberate-removals.md](deliberate-removals.md).

**The two directories do not cascade.** If both hold the same filename, the resolver picks one
file — a fix to one is not inherited by the other. Port explicitly and re-verify specificity
live.

## Module icons

**Bootstrap Icons is the single icon mechanism.** Don't add raster icon paths, don't restore
sprite usage.

A module declares its icon with one method on its own `Common` class — the same "opt in by
defining a conventionally-named method" shape as `menu()`/`user_settings()`/`home_page()`:

```php
public static function bootstrap_icon() { return 'bi-calendar-date'; }
```

A module owning several recordsets can give each its own glyph:

```php
public static function bootstrap_recordset_icons() { return array('company' => 'bi-building'); }
```

Unnamed recordsets fall back to the module's `bootstrap_icon()`, so the common
one-recordset module declares nothing extra. **There is no central module→icon map** — that
was removed deliberately.

`modules/Base/Theme/bootstrap_icons.php` (`Base_BootstrapIcons`, plain PHP, not a `Module`,
because consumers pull it in from Smarty `{php}` blocks) resolves them:

| method | returns | used for |
|---|---|---|
| `resolve($icon, $module, $fallback)` | `'bi-...'` | everything below; pass `$fallback=null` to mean "keep your own raster icon" |
| `tag($module, $classes)` | `<i class="bi ...">` or `null` | record shortcut buttons |
| `type_tag($module, $recordset)` | a muted type glyph, or `''` | a list mixing several record types |
| `resolve_recordset($module, $recordset)` | `'bi-...'` or `null` | the per-recordset override |

`resolve()` tries the `$by_filename` map (basename of an icon file), then the module's
`bootstrap_icon()`, then the fallback. **The filename map is not a place to add module icons** —
it exists only to tell apart two things registered by the *same* module when the caller holds
nothing but a filename.

Icons render in the sidebar menu, the header module indicator, the ActionBar launcher and
launchpad, admin panels, the New-record type chooser, the record shortcut buttons, and
mixed-type list rows.

**`set_icon()` / `recordbrowser_table_properties.icon` is not a second icon source.** It stores
a module-relative path that `RecordBrowser_0::init()` expands and hands to `resolve()`, which
extracts the *module* from the path shape and asks its `Common` class. No raster recordset icon
is ever drawn; the stored path survives only as a module+table discriminator. Don't add a
consumer that renders the PNG, and don't resolve icons from the stored value.

**For a brand-new module: declare `bootstrap_icon()` only.** Skip the legacy
`theme/icon.png`/`icon-small.png` files and the `set_icon()` call, even though
`dev:module:create` and older modules still scaffold them. This is forward-looking guidance for
new modules, not a reason to strip existing ones.

## `.epesi-fullbleed` — a screen that must fill the content column

Put `.epesi-fullbleed` on the single top-level element of the module's own output.
`Base_Box/theme_adminltedark/default.css` then zeroes the content column's three padding
sources and gives that element
`height: calc(100vh - var(--epesi-header-height) - var(--epesi-actionbar-height))`.

It is matched with `:has()` rather than a class on `.app-content` on purpose: this is an
AJAX-push SPA, so `.app-main`/`.app-content`/`.container-fluid` outlive navigation and a class
set while rendering one screen would have to be unset by every other screen. Keying off the
opted-in element makes the exception disappear on its own.

If you need something like it, note the two traps that cost time: an `<iframe>` needs
`display:block` (inline default puts it on a text baseline, and the descender gap pushes it out
of the wrapper), and the overrides win on specificity — `:has()` inherits the specificity of
its most specific argument — not on `!important`.

## Recurring CSS/JS traps (read before touching the theme)

1. **CSS loads per rendering module.** `modules/X/theme_adminltedark/default.css` is fetched
   only when module `X` itself renders. Putting a style under the wrong module produces a
   silently unstyled screen. Verify with `Epesi::get_csses()` after a render.

2. **A module's auto-loaded CSS is named after the *template*, not always `default.css`.**
   `Base_ThemeCommon::display_smarty()` swaps `.tpl` for `.css` on whatever name was passed to
   `display()` — `$theme->display('tree_view')` loads `tree_view.css`. Naming it `default.css`
   when the module doesn't display `'default'` means it silently never loads, and the page
   looks like "this part of the reskin just wasn't done yet". Check the exact string passed to
   `display(...)` before naming the file.

3. **Never reuse AdminLTE's own class names on markup it doesn't control** (`.nav-treeview`,
   `.menu-open`, `--lte-sidebar-*`). Its own CSS/JS partially applies and produces
   silently-broken behaviour — toggles that flip ARIA state but show nothing. Use fresh
   `epesi-*` names.

4. **A `data-bs-theme` pin only wins if it's on every matching ancestor.** AdminLTE ships
   compound selectors like `[data-bs-theme=dark] .app-sidebar` that match through *any*
   ancestor carrying the attribute — including `<html>`, which `adminlte.min.js` sets from OS
   `prefers-color-scheme`. Grep the vendored CSS for the literal selector before assuming a
   scoped pin is enough.

5. **Bootstrap utility classes set their property with `!important`.** Remove the utility class
   from the markup rather than trying to out-`!important` it.

6. **AdminLTE's `.card-body.p-0` reaches into any `<table>` inside it**, at the same
   specificity as a per-column override — needs `!important` to beat.

7. **Fixed-height layout vars must track real content.** `--epesi-header-height` /
   `--epesi-actionbar-height` are kept in sync live by a `ResizeObserver` in
   `Base_Box/theme_adminltedark/default.tpl`, because the bars' heights vary with what's
   rendered. Never make a var that is *written from* an element's measured height also
   *constrain* that element's `min-height` — that is a one-way ratchet that can only grow.

8. **The sidebar out-ranks the navbar's full-width background in z-order**, so the navbar's
   *content* must be offset by `--epesi-sidebar-width`, or it renders unclickable underneath.

9. **Never let a `document.observe("e:load", ...)` handler throw.** That is the app-wide
   "re-init after Epesi's AJAX-patch cycle" hook; the fire and `Epesi.updateIndicator()` share
   one script block and nothing try/catches observers, so one uncaught exception leaves the
   "Loading..." overlay stuck forever, app-wide, with no visible link to the observer that
   threw. Wrap the body in try/catch and guard on dependencies — load order between separately
   `load_js()`'d files is never guaranteed.

10. **A CSS `transform` on any ancestor — even a decorative `:hover` lift — becomes the
    containing block for a `position:fixed` descendant.** Grep descendants for `position:fixed`
    before adding `transform`/`filter`/`will-change` anywhere.

11. **`getBoundingClientRect()` on a `display:none` element returns an all-zero rect.** An
    `onclick="a();b()"` chain where `a()` hides the trigger and `b()` measures it silently
    mispositions everything. Read the rect before mutating visibility.

12. **Reference `var(--epesi-font-size-base)`** (`Base_Theme/theme_adminltedark/fonts.css`)
    rather than a literal pixel size, anywhere a rule must match the app's default text size —
    Bootstrap's rem sizing resolves against the root `<html>`, not the overridden `body` value.

13. **A flex/grid container closed one level too shallow produces no validation error and no
    console warning** — only a silently collapsed column. Live browser verification, not grep
    or lint, is what catches it. (Epesi used to render almost everything as nested `<table>`s;
    both themes are div/flex/grid now, which trades one failure mode for this one.)

14. **Leightbox popups use a fixed grey/black chrome regardless of theme.** Any native
    `<select>`/`<textarea>`/`<input>` inside one needs `color` *and* `color-scheme: light`, not
    just a light background — see [bug-patterns.md](bug-patterns.md).
