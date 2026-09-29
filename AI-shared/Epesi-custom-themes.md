# Custom themes

The design for making epesi skinnable: what a theme can change, who authors one, how a user
picks it, and whether ready-made Filament themes fit. The base theme and the admin-authored
Themes module (accent colour, density, font size) are built. The plan at the end tracks progress.

## In short

- In Filament, a theme is a CSS file. There is no template layer to override. A theme changes
  how the `fi-*` markup looks, never the markup itself.
- A **Theme is admin-authored and named**, not a free-form per-user preference: an administrator
  creates one on Administration → Themes (an accent colour, a density and a font size), and every
  user picks one of the admin's themes for themselves on Appearance, in user-settings — or
  nothing, which follows whichever theme the administrator marked default. This is the
  `Epesi/Appearance` module.
- A Theme has, cheapest first:
  1. an **accent colour**, applied at render time with no build step;
  2. a **density** (compact or comfortable);
  3. a **font size** (small, default or large);
  4. — not built yet — a **stylesheet skin** for corner radius, surfaces, the sidebar and
     borders, that a Theme could reference alongside its colour, density and font size.
- All panels share one **base theme**, a CSS file holding Filament's own CSS plus epesi's
  overrides. A future stylesheet skin (layer 4) would be a thin layer on top of it, mostly CSS
  variables; a Theme's accent colour, density and font size (layers 1–3) are applied per request
  instead, not as CSS files.
- A future **module-shipped skin** would have to ship as a finished CSS file: it can't build CSS
  on the customer's server, because the server has no Node.
- **Ready-made Filament themes** work if they are written for Filament 4 or 5 (Tailwind 4).
  Filament 3 themes don't.

## What's there today

- Every panel loads epesi's **base theme** (see "The base theme" below) in place of Filament's
  precompiled stylesheet.
- Each panel sets its own fallback colours in its provider: Amber for main, user settings and
  setup, Slate for Administration, Emerald for the customer portal. The colour tells a user
  which panel they are in — a Theme's accent colour only ever overrides main and user-settings
  (see "Which panels" below).
- Light, dark and system mode are Filament's own. The choice is stored in the browser's
  `localStorage`, so it is per browser, not per user.
- **Administration → Themes** (`ThemeResource`): an admin creates, edits and deletes named
  themes — a colour, a density, a font size, and which one (at most one) is the default.
- **Appearance**, in user-settings next to Regional Settings: every user picks one of the
  admin's themes, or leaves it to the default.
- Compact density and default font size are still this app's own unconditional default: a guest,
  a user who hasn't chosen, and a fresh install with no themes yet all get exactly today's
  shipped look.

## The layers

### Accent colour

`FilamentColor::register()` accepts a closure. Filament evaluates it when the page is rendered
and merges the result over the panel's own colours. Registrations are applied in order, so the
last one wins. A plain hex string is enough: Filament generates the shades from 50 to 950 from
it (`Color::generatePalette()`).

`Epesi\Modules\Appearance\Http\Middleware\ApplyThemeColor`, wired directly into
`MainPanelProvider` and `UserSettingsPanelProvider`'s own `->middleware([...])` lists (right
after `AuthenticateSession`, the same way `SetLocale` is), registers the effective theme's
`accent_color` — nothing if it has none, or there is no effective theme at all.

Two traps:

- **The panel's `->colors(fn () => …)` can't read the user.** The panel boots in
  `SetUpPanel`, the first middleware in a panel's stack, before `StartSession`. At that point
  `auth()->user()` is `null`. A **plugin's `boot()`/`register()` can't either**, for the same
  reason — it also runs from `SetUpPanel`. This is why the colour needs real middleware, not a
  render hook or a plugin hook: middleware is the earliest point in a panel's own request
  lifecycle where the signed-in user is known.
- **`ColorManager` caches the colours the first time they are read**, and Filament reads them
  very early in `<head>` (`@filamentStyles`, before any render hook fires) — too early for a
  `STYLES_AFTER` hook to still be in time. Middleware runs before the response starts rendering
  at all, so it's always in time regardless of exactly where in `<head>` that read happens.

This layer alone answers most "I want it blue" requests. It needs no build step and no files.

### Density

The compact spacing in the base theme's `compact-tables.css` is a design decision, and epesi's
own unconditional default — until this module, "unconditional" meant every visitor, with no way
out. The whole file's rules now sit under `html.epesi-compact :where(.fi-panel-main, …)` instead
of the bare `:where(...)`: **compact is what happens when the class is present**, and a theme
whose density is "comfortable" is the one thing that leaves it off, falling back to Filament's
own roomier spacing.

Unlike dark mode, this doesn't need `localStorage`: density comes from the signed-in user's own
theme, a fact the server already knows by the time a render hook runs (every panel middleware,
including auth, has already executed for that request). `App\Support\Appearance\CurrentTheme::appearanceScript()`
still uses the same *technique* dark mode does — an inline, synchronous script in `<head>`
(`PanelsRenderHook::HEAD_END`) that runs before first paint, so there's no flash of the wrong
density (or font size — the same script and technique carries both, see "Font size" below). The
server also emits an `epesi-density` meta tag with `compact` or `comfortable`. The script reads
that tag on initial load and on `livewire:navigated`, toggling the class in both directions.
Livewire replaces the HTML element's attributes on SPA navigation but does not rerun identical
head scripts; the event listener therefore reads the new page's metadata instead of retaining the
first page's density. This also covers cached back/forward navigation. The navigation lifecycle
regression check is `node --test tests/js/appearance-density.test.mjs`.

One CSS trap this raised, worth knowing before wrapping any existing unconditional ruleset the
same way: a rule nested as `.dark & { … }` compiles by substituting `&` with its *entire*
resolved parent chain, not just the immediate selector. Nested under the new
`html.epesi-compact :where(...)` wrapper, `.dark &` would have compiled to
`.dark html.epesi-compact :where(...)` — requiring `dark` to be an *ancestor* of `html`, which
never matches, since both classes land on `<html>` itself. The one place `compact-tables.css`
did this (the heading-row toolbar's dark border colour) is now a separate, flat, non-nested rule
instead: `html.epesi-compact.dark :where(...) .fi-ta-header-ctn:has(...) { … }`.

### Font size

`Theme::font_size` is `small`, `default` or `large`. `resources/css/filament/epesi/font-size.css`
toggles the root `<html>` font size between 87.5%, 100% (no class, the shipped default) and
112.5% behind `epesi-font-sm`/`epesi-font-lg`, using the same `appearanceScript()` mechanism and
`epesi-font-size` meta tag as density, above. Unlike `compact-tables.css`'s per-panel overrides,
this can't be scoped with `:where(.fi-panel-…)`: Filament and Tailwind size almost everything in
`rem`, which is always relative to the *root* font size, not the nearest ancestor, so the class
has to sit on `<html>` itself to reach every `rem`-sized element under it. It still only ever
applies where it's meant to, because the classes are only ever toggled on pages that render
`appearanceScript()` — main, user settings and Administration, never setup or the portal.

### Stylesheet skin — not built

Corner radius, surface and border colours, and the sidebar's look. This is closer to what people
mean by "a theme" than colour, density and font size alone. Not built: `Theme` doesn't yet carry a
skin reference. The design below (a CSS file, referenced by a Theme, chosen the same way) still
holds; it's the natural next field to add to the `Theme` model and its admin form. It is built on
the base theme and would ship as a layer on top.

## The base theme

Every panel provider calls `->viteTheme('resources/css/filament/epesi/theme.css')`: one theme
for all five panels, not one per panel. The files are in
[resources/css/filament/epesi/](../resources/css/filament/epesi/):

| File | What it holds | Panels |
| --- | --- | --- |
| `theme.css` | The entry point: Filament's `theme.css`, the five files below, and the `@source` paths Tailwind scans (`app/Filament`, `resources/views`, and each module's views and `src`) | all |
| `compact-tables.css` | Table, page-header and sidebar density, the 3rem topbar, the 10px page gutter, the addon tab strip's spacing | main, user settings, Administration |
| `font-size.css` | The root `<html>` font size, small/default/large | main, user settings, Administration |
| `boxed-fields.css` | The shaded label and value boxes of inline-label fields, tighter sections, `.rb-column-flow` | main, user settings |
| `small-card-corners.css` | `--radius-sm` on card-like containers | main, user settings, Administration, setup |
| `auth-branding.css` | The login page's brand name larger than its heading | main, user settings, Administration, portal |

The theme **replaces** Filament's precompiled stylesheet: the same Filament CSS, compiled by
Tailwind 4 together with epesi's sources.

- **Tailwind utility classes in epesi's and modules' views work.** Filament's precompiled CSS
  contains almost none, so before the base theme a class such as `mt-6` in epesi's own markup did
  nothing.
- **epesi's overrides are outside any cascade layer.** They win over Filament's layered rules
  whatever the selector, as the inline `<style>` tags they replaced did.
- **Each override file names its panels.** It wraps its rules in `:where(.fi-panel-…)`, the class
  Filament puts on `<body>`. `:where()` adds nothing to a selector's specificity. Which panels get
  which file is the same as before the move; whether all panels should get all four is a
  separate decision.
- **Panel-specific CSS stays with its panel.** The setup panel's wizard-header spacing
  (`SetupPanelProvider::compactWizardHeaderStyles()`) and the CSS that modules add through
  `STYLES_AFTER` (the file pills, the History addon's colours, the collection repeaters) are
  still inline `<style>` blocks.
- **The theme is part of the frontend build.** It is an `input` in `vite.config.js` and is built
  into `public/build/` by `npm run build`, so the release zip from `epesi:package` carries it. A
  panel page fails with a missing-manifest-entry error until the theme has been built. The test
  suite doesn't need a build: `Tests\TestCase` calls `withoutVite()`.

`@source` over `modules/**` covers the modules present when the release is built. A module
installed later from a zip isn't scanned, so a module's views can rely only on classes that
are already in the build (see "Skins shipped by modules").

After changing the theme, run `npm run build` and compare screenshots of the same pages before
and after, in light and dark (the `run-epesi` skill's `shoot.sh`, then ImageMagick's
`magick compare -metric AE`).

## Stylesheet skin, still a design

Not built (see "Stylesheet skin — not built" above). If a `Theme` grows a `skin` column, a
stylesheet skin would be a small CSS file that comes **after** the base theme and sets:

- Filament's CSS variables: `--primary-*`, `--gray-*`, `--font-family`, and the radius and
  surface variables Filament reads;
- epesi's own variables, once the base theme has any (for example the colours the calendar
  uses);
- where a variable can't reach, a few rules on `.fi-*` classes.

A skin shouldn't be a whole Filament build. That would mean one full copy of Filament's CSS
per skin, and every skin would have to repeat epesi's overrides.

A render hook would emit the resolved theme's skin after the base theme:

```php
->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): ?Htmlable => Theme::resolveFor(Auth::user())?->skinTag())
```

Why not `->viteTheme()` per user: the panel is built while providers register, before there is
a request, let alone a user, so its theme is fixed per panel. `STYLES_AFTER` renders after
Filament's theme, so the skin's rules win without `!important`.

epesi's own built-in skins would live next to the base theme
(`resources/css/filament/epesi/skins/*.css`), listed in `vite.config.js`, and emitted with
`@vite(...)`.

## Skins shipped by modules — still a design

A skin is a natural thing to distribute as a module and offer through Store. The constraint is
the build: a customer's server has no Node, and `module:install` unpacks a zip. So:

- A module skin would ship **finished CSS**. Its author builds it. The server never compiles it.
- It may use Filament's and epesi's CSS variables, `.fi-*` classes, and utility classes already
  in the base build. It can't introduce new Tailwind utilities.
- A module would register its skin file with `Theme`'s admin form (e.g. a discovered-skins list
  the `skin` select reads from), the same way `Epesi\Modules\Appearance` itself registers no
  such thing yet.
- How the CSS reaches the browser is still open. The render hook can inline it (works on any
  host, and a skin layer is a few KB), or the installer can publish it to `public/` (cacheable,
  but no module does this yet, so it would be the first).

## Letting the user choose

Built as `Epesi\Modules\Appearance`, a core module (`panels: ["administration",
"user-settings"]` — not "main": see "Why core code never names Theme directly" below):

- **Themes are admin-authored, not a free per-user preference.** `Theme`
  (`epesi_appearance_themes`: `name`, `accent_color`, `density`, `font_size`, `is_default`) is a
  plain named row an administrator creates and edits on **Administration → Themes**
  (`ThemeResource` — List/View/Create/Edit, the same shared RecordBrowser page bases every other
  Administration resource uses, e.g. Users and Modules; no Policy, for the same reason those two
  don't need one — every visitor to the Administration panel already is a `super_admin`). Saving
  a theme with `is_default` true un-sets every other row's flag (`Theme::booted()`'s `saving`
  hook) — application-level, not a DB constraint, the same spirit as `RegionalSetting`'s own
  default row.
- **Three named themes ship as the starting point.** A migration
  (`2026_09_30_090100_seed_default_themes`) inserts them only when the table is still empty, so
  it never overwrites an install's own edits: **Epesi Green** (`#269c34`, compact, `is_default`)
  — the brand colour, replacing the Amber panel fallback — **Blue Compact** (`#3767db`, compact)
  and **Orange Comfortable** (`#e08122`, comfortable), the latter there so an admin sees both
  densities without building one from scratch. Nothing enforces these names or stops them being
  edited, un-defaulted or deleted; it's a starting point, not a constraint.
- **A new theme starts as a copy of the current default theme.** `ThemeForm`'s `accent_color`,
  `density` and `font_size` fields default (on Create only — Edit fills from the record itself) to
  `Theme::default()`'s own values, so making a colour variant of "Epesi Default" means opening
  Create and changing only the accent colour, not starting from Filament's bare defaults and
  redoing the density and font size choices too.
- **A user only picks one — no free colour/density/font size of their own.** `UserAppearance`
  (`epesi_appearance_selections`: `user_id` unique, `theme_id` nullable) is a row created only
  once a user actually saves a choice on **Appearance**, in user-settings next to Regional
  Settings (`Filament\Pages\Appearance` — a `Radio` of every theme's name, its density and font
  size as the description under each). Unlike `RegionalSetting::current()`, there is no
  `firstOrCreate()` on read: `Theme::resolveFor($user)` is a plain nullable lookup, so a user who never visits the
  page never gets a row, and reads nothing but the default theme, forever following whatever the
  administrator marks default.
- **This is why Appearance doesn't touch the core `users` table.** A `theme_id` column added
  directly to `users` would need `App\Models\User` (fillable, casts, a relation) edited for a
  single module's sake. Keeping the pointer in the module's own table, `user_id` pointing *out*
  to `users`, is exactly `RegionalSetting`'s own choice, for the same reason.
- **No themes yet, or no default marked:** `Theme::resolveFor()` returns `null`. Every caller
  (`ApplyThemeColor`, `CurrentTheme::appearanceScript()`) treats `null` as "today's shipped
  look" — no colour override, compact density, default font size — so a fresh install, or one
  where nobody has created a theme yet, looks exactly as it did before this module existed. The
  Appearance page itself shows a plain message instead of an empty picker until a theme exists.
- **When it applies:** the main panel runs in SPA mode, and SPA mode never removes a stylesheet.
  The user-settings panel is outside SPA (it is in the main panel's `spaUrlExceptions`), so
  returning from it to the CRM is a full page load, and `save()` explicitly redirects to reload
  the Appearance page itself too — density and font size are decided by a script in `<head>`, once
  per page load, not reactively.
- **Which panels:** the accent colour applies to main and user-settings only (`ApplyThemeColor`
  is wired into just those two panel providers) — Administration keeps its own Slate so it stays
  recognisable, and the customer portal and setup have neither. **Density and font size follow the
  user further**, into Administration too (`CurrentTheme::appearanceScript()` is wired into all
  three panel providers directly): they're ergonomics preferences, not a "which area am I in"
  signal, so there's less reason to withhold them from a `super_admin` working in Administration.

### Why core code never names `Theme` directly

The first live version of this crashed the entire main panel: `ApplyThemeColor` lived in the
module and was referenced by class-string straight from `MainPanelProvider`'s own middleware
list. A module's classes only autoload once it's registered *and* enabled in the `modules`
table (`ModuleServiceProvider::registerAutoloader()`, reading `ModuleRegistry::enabled()`) —
and `MainPanelProvider` is core, loaded on every request regardless of any module's state. The
gap between "the module's files exist on disk" and "`module:register` has actually run" is
real, not hypothetical: it's exactly the state a fresh checkout, or an existing install before
`epesi:update`, sits in.

The fix is the same hook pattern `App\Support\Locale\Locales` already uses for
`RegionalSettings` (`SetLocale`, core, never names `RegionalSetting`): **`App\Support\Appearance\CurrentTheme`**
holds a resolver closure that starts `null` and returns `null` from `forUser()`/`appearanceScript()`
until something sets it. `AppearanceServiceProvider::boot()` — which only ever runs once the
module is actually registered and enabled — is the only place that calls
`CurrentTheme::resolveUsing(...)`. Core code (`App\Http\Middleware\ApplyThemeColor`, and the
appearance render hook in all three panel providers) calls `CurrentTheme` only, never `Theme`, so
it degrades to "today's shipped look" instead of a 500 whatever the module's state.

`module:register` also surfaced two more bugs, both instructive:

- **`Theme` had no `protected $table`.** Eloquent defaulted it to `themes` (the plain
  pluralised class name), not the migration's `epesi_appearance_themes` — a `QueryException`
  ("table 'themes' doesn't exist") the moment anything queried it. `UserAppearance` had its
  `$table` set correctly; `Theme` didn't. Worth an explicit look whenever a new model's table
  name doesn't match Eloquent's own convention, since nothing catches the mismatch until a query
  actually runs.
- **`Theme` needs a morph alias.** Every resource built on RecordBrowser's shared `ViewRecord`
  (Administration → Themes included) tries to attach every registered `RecordExtensions` addon
  (here, PriorityList) to whatever record it's showing, whether or not that resource uses the
  addon — which touches a polymorphic relation keyed by the model. `AppServiceProvider`'s own
  morph map (`registerMorphAliases()`) is enforced, so an unmapped model throws the moment
  anything polymorphic touches it — CLAUDE.md already documents this as a general rule for any
  new model used polymorphically. Fixed the same way the CRM modules do it: `Relation::morphMap(['theme' => Theme::class])`
  in `AppearanceServiceProvider::register()` (not core's `AppServiceProvider`, so the alias
  stays with the module that owns the model), merging into the enforced map.

## Ready-made Filament themes and plugins

- **Filament 4 and 5 themes work.** They are Tailwind 4 CSS, and Filament 5 kept Filament 4's
  styling. Aura's Filament presets (eight of them) change only CSS variables and support both
  versions, but they need a paid Aura UI Pro licence.
- **Filament 3 themes don't.** They are built on Tailwind 3 with a `tailwind.config.js` preset,
  and much of the theme ecosystem is still there. The best known, Hasnayeen/themes (Dracula,
  Nord, Sunset, with a per-user mode), has 3.x as its repository's default branch. Its v5
  support is unconfirmed.
- **Switcher plugins for Filament 5 exist:** supianidz/palette, nrep/filament-theme-switcher and
  cuongpham2107/filament-theme-customizer. Each is a small single-author package with its own
  settings storage, which would have sat alongside the Regional Settings pattern. epesi wrote its
  own instead (`Epesi\Modules\Appearance`, above) and would treat a third-party theme as a
  source of CSS for a future stylesheet skin, not as the switcher itself.
- **Check every theme against epesi's overrides.** The base theme's `compact-tables.css` forces
  table padding, sidebar spacing and a 3rem topbar with `!important`. A theme that changes
  density will fight those rules. Look at it in light and dark before offering it.

## What a skin doesn't reach

- **The Roundcube mailbox.** Roundcube's Elastic skin has its own LESS-based skin system. Only
  dark mode is passed through today (see
  [Epesi-Laravel-Roundcube.md](Epesi-Laravel-Roundcube.md)). An accent colour could be passed
  the same way, but a full skin would need an Elastic skin of its own.
- **The calendar.** [resources/css/calendar.css](../resources/css/calendar.css) sets
  FullCalendar's `--fc-*` variables to fixed values. They should read the skin's variables
  instead.
- **Pages outside the panels,** such as the "being updated" and demo-reset pages, and e-mail
  bodies. They keep their own colours on purpose. There is no user or panel to take a skin
  from, and an e-mail's reader isn't the user who picked the skin.

## How Epesi's themes map here

Legacy Epesi's `Base/Theme` module keeps one theme for the whole system: the `default_theme`
variable, which the administrator sets in `Base/Theme/Administrator`. A theme is a
`theme_<name>/` directory inside every module, holding that module's templates and CSS. File by
file, it falls back to the module's plain `theme/` directory.

epesi replaces that as follows:

- **No per-module template directories.** Filament owns the markup, so a theme is CSS (plus,
  today, three request-time settings — colour, density and font size) only.
- **The system-wide theme becomes the administrator's default Theme.** Each user can pick a
  different one of the administrator's own themes; legacy had no such per-user choice at all.
- **A theme that touched every module becomes one CSS layer over shared variables and `fi-*`
  classes**, once the stylesheet-skin layer exists. The modules themselves carry no theme files.

## Plan

1. **Base theme.** Create it, point every panel at it, move the four traits' CSS into it and
   delete the traits. Nothing changes on screen. Checked with before/after screenshots. — done
2. **Admin-authored named themes, users pick one:** the `Epesi/Appearance` module — `Theme`
   (name, accent colour, density, default flag) and its Administration → Themes CRUD;
   `UserAppearance` and the Appearance page in user-settings; `ApplyThemeColor` middleware;
   `compact-tables.css` gated behind `epesi-compact` on `<html>`, on by default; translations.
   — done
3. **Font size:** a `font_size` field on `Theme` (small/default/large), `font-size.css` gated
   behind `epesi-font-sm`/`epesi-font-lg` on `<html>`; `CurrentTheme::densityScript()` generalised
   to `appearanceScript()` to carry both settings. — done
4. **Stylesheet skin:** a `skin` field on `Theme`, a skin registry, and epesi's own two or three
   built-in skins. Point the calendar's colours at skin variables. — not started
5. **Module-shipped skins:** the registration contract, and the choice between inlining and
   publishing the CSS. — not started
6. **Portal skin,** chosen by the administrator. — not started
