# Framework internals

How the framework itself is built — as opposed to how you build *on* it, which is what the
rest of this folder covers. You need this file if you are changing the framework's own
machinery: the grid renderer, the theme resolver, the standalone entry points, the menu.
If you are writing a module, you do not need it.

Function and file names are given; line numbers deliberately are not, because they rot
faster than anything else here and a wrong one reads as authoritative. Grep for the
function name instead.

---

# 1. Grid column sizing and mobile reflow

The on-screen grid is **not a real `<table>`**. It is CSS table-display `<div>`s —
`Utils_GenericBrowser__thead` / `__tbody` / `__tr` / `__th` / `__td` — emitted by
`Base/Theme/smarty/plugins/function.html_grid_epesi.php`. The wrapper carries an inline
`width:100%; table-layout:fixed`, so it always fills its container exactly and the
surrounding `.table-responsive` (`overflow-x:auto`) never engages. That is why a narrow
viewport squeezes columns instead of scrolling.

Because it is divs, **a `width=` attribute does nothing** — `html_grid_epesi_attrs_to_div()`
rewrites it into an inline `style="width:N%"`. There is no `width` attribute in the live
DOM, so JS reading `getAttribute('width')` finds nothing. A test harness that puts
`width="10%"` on a div is not reproducing the real markup.

## Column sizing on desktop

**All the width maths is client-side JS in exactly one place:**
`modules/Base/Box/theme_adminltedark/default.tpl`, in the `{php}` block, as
`epesiSizeGbActions()` inside the third `eval_js_once(...)`. Nothing under
`modules/Utils/GenericBrowser/` does width maths beyond emitting the initial percentages.

Consequences worth internalising:

- **Theme-scoped.** The legacy `theme/` path has no equivalent — there the server-emitted
  percentages are the final word.
- **Page-wide, not per-module.** The entry point is
  `document.querySelectorAll('.Utils_GenericBrowser')`, so it sizes every grid on the
  page, including ones nested in dashboard applets, Search results and Shoutbox history.
- The name says "Actions" for historical reasons. It sizes every column.
- It runs on initial load, `document.fonts.ready`, jQuery `e:load`, and a 150 ms-debounced
  `window resize`. It does **not** run when a dashboard applet is dragged into a
  different-width column, so widths there stay stale until one of those fires.

Three column kinds are classified **once** into `data-epesi-col-kind` and never re-derived:

| Kind | Declared as | Sized by |
|---|---|---|
| percent | numeric weight (the common case) | weighted split + redistribution |
| absolute | non-numeric CSS length, e.g. `'12em'` | measured content + 6px |
| fixed icon | `Utils_RecordBrowser__favs` / `__watchdog` (`'24px'`) | measured content + 2px, or 0 when hidden at the mobile breakpoint |

**The one-shot classification is load-bearing.** The same pass writes plain `NNpx` back
onto `th.style.width`, so on the next run a percent column would look exactly like an
absolute one and get routed into content measurement — which, for a free-text Note column,
means measuring thousands of px of unwrapped content and locking the column there.
`data-epesi-orig-percent` caches the original percentage for the same reason.

The pipeline: measure the actions column if there is one (a grid without one reserves 0
and continues) → measure fixed/absolute columns → force-collapse expanded rows (skipped on
resize, or a row the user just expanded gets re-collapsed) → split the remaining width
among percent columns in proportion to their cached percentages → redistribute.

**Redistribution moves surplus to shortfall, smallest-deficit-first.** A column can only
ever donate what its content does not need, which is what keeps grids with meaningful
weights safe. Filling smallest-first rather than proportionally matters: proportional
sharing hands nearly the whole pool to a bottomless free-text column and leaves a genuinely
fixable 28px shortfall still clipping. No shortfall or no surplus anywhere makes the pass a
no-op.

### The declared weights are not dead code

They are still the baseline (they decide who has surplus and who is short), the *entire*
answer whenever demand exceeds supply (a narrow container leaves no pool), the only sizing
the legacy theme has, and literal pixels under `absolute_width(true)` — which RecordBrowser
sets for PDF export, where no JS is involved at all.

What *is* effectively dead is the uniform default: RecordBrowser gives every ordinary text
column the same weight, so "proportional" degrades to "every column identical regardless of
content". That is the whole reason redistribution exists.

### Four traps, each paid for already

- **Never measure a live cell's `scrollWidth`.** Under `table-layout:fixed` a body cell's
  box tracks the column's *current* width — including the width this function assigned last
  run. Measure, add a buffer, repeat, and the column grows unboundedly on every resize
  tick. Clone into a detached `table-layout:auto` holder that keeps the `.epesi-gb` /
  `.Utils_GenericBrowser` classes, so the theme's icon and hidden-image rules still apply.
- **Round so the row lands *under* the container, never over.** `Math.floor` for shares,
  `Math.ceil` only for values working against the total, minus a 2px buffer. Chrome and
  Firefox do not round table columns identically, and 1px over triggers
  `.table-responsive`'s scrollbar.
- **CSS must not set `width`/`min-width` `!important`** on these columns — it fights the
  inline value the script writes.
- The whole body is wrapped in `try/catch` deliberately: an uncaught exception in an
  `e:load` observer aborts the entire shared script line it runs in.

## Mobile reflow (the 2-line grid)

At `max-width: 767.98px` each row becomes an N-column CSS Grid instead of letting
`display:table-cell` squeeze every column proportionally. Header and body rows share the
`__tr` class, so one rule keeps them column-aligned.

`theme_adminltedark/default.tpl` computes `mobile_cols = max(1, ceil($visible_count/2))`
and appends `--epesi-gb-mobile-cols` to the wrapper's inline style; `default.css` consumes
it in `grid-template-columns: repeat(var(--epesi-gb-mobile-cols, 2), 1fr)`. Computing it
per table is what lets one generic rule work for grids with any number of columns.

Three details that are easy to get wrong:

- **Force the ancestors to `display:block` first.** A `display:grid` child of a
  `display:table-row-group` parent risks the browser generating an anonymous `table-row`
  wrapper per the CSS table anonymous-box rules, breaking the wrap.
- **Move the row separator from `__td` to `__tr`.** On `__td` it fires on every *physical*
  line, so one logical row reads as two rows with a separator between them.
- **`__th`/`__td` need `width: auto !important`** inside the same block. Their inline
  `style="width:N%"` is sized against the whole row for the desktop layout; on a grid item
  a percentage resolves against its own already-narrow track, compounding down to
  near-zero and clipping headers to one or two characters. Only `!important` beats an
  inline style.

At `max-width: 991.98px` a separate, older rule collapses the row-actions column into a
kebab menu and hides the favs/watchdog columns. Extend that pattern rather than inventing a
new one.

---

# 2. Don't tidy these up

Each entry is something a reasonable person would look at and want to simplify, where
simplifying it breaks something not visible from the code in front of you. Each says what
it looks like, why it is that way, what actually breaks, and how to check first.

## `tools/` is a separate composer project, not root `require-dev`

**Looks like:** a pointless second `composer.json` for two dev dependencies. Obvious
tidy-up: move `phpstan/phpstan` and `rector/rector` into the root `composer.json` and
delete `tools/`.

**Why:** `vendor/` is **committed to this repo** so a deployment needs no composer run at
all. That makes root dev dependencies a bad fit, and all three consequences were hit in
order while trying it:

1. The two packages are ~68 MB / ~3,100 files — they would more than half again the
   committed tree and ship in every release zip.
2. Gitignoring them instead does not work: composer writes their bootstraps into
   `vendor/composer/autoload_files.php`, which `vendor/autoload.php` requires on **every
   request** — so a fresh clone fatals on every page load.
3. Regenerating the autoloader with `--no-dev` fixes that, but then breaks Rector, which
   needs its own dev autoload entries to resolve its scoped PHPStan classes.

`tools/` sidesteps the whole chain: root `composer.json`, `composer.lock` and `vendor/`
stay untouched, `tools/vendor/` is gitignored, `tools/composer.lock` is committed so
versions stay pinned.

**What breaks if you fold it back in:** a fresh clone fatals on every request, or the
release zip grows by ~68 MB — depending on which half you do.

**Usage:** `composer install -d tools`, then `tools/vendor/bin/phpstan` / `rector`.

## `phpstan.neon` and both `rector*.php` exclude the gitignored module trees

**Looks like:** an odd blind spot — surely you want analysis to cover more code.

**Why:** those trees are gitignored and separately distributed, so **CI checks out none of
them**. Analysing them locally produces findings CI could never report, and a baseline
generated with them present can never match CI's run.

**What breaks if you re-include them:** extra local errors CI never sees, and a baseline
that is wrong for everyone else — pushed onto every other developer the moment you
regenerate it in that state.

**Check before changing:** `tools/vendor/bin/phpstan analyse -c phpstan.neon` must report
**0 new** findings.

## The `<img>` fallback in `Utils_GenericBrowser::action_icon_tag()`

**Looks like:** dead legacy code. The theme is Bootstrap-Icons-only now, so why build an
`<img>` at all?

**Why:** it is the path for every icon whose *meaning* cannot be determined. Modules
distributed outside this repo ship their own artwork with their own `[src*="..."]` CSS
rules; legacy modules route their artwork through the same branch. There is no way to know
what glyph an arbitrary PNG means, so those keep rendering exactly as before.

Both branch conditions are deliberately narrow, and both were tightened after being got
wrong:

- The stem lookup is gated on the path being **GenericBrowser's own**, so another module's
  `edit.png` cannot borrow our glyph.
- The identity-icon branch is `/^icon[-_]small$/`, **not** `/[-_]small$/`. The broad
  version matched `copy_small.png` and `cut_small.png`, and `resolve()` then returned the
  *owning module's* identity glyph — a mail module's copy action rendered as an envelope,
  and an attachment module's "Copy link" and "Cut" both became journals. This is **not**
  fixable by adding entries to `Base_BootstrapIcons::$by_filename`: that map is keyed on
  basename alone, and the same `copy_small.png` deliberately means `bi-copy` for one module
  and `bi-link` for another.

**What breaks if you remove it:** externally-distributed modules' toolbar icons vanish, and
every legacy module's row artwork with them.

**Converting a module properly** means having it declare `bootstrap_icon()` — nothing in
`action_icon_tag()` needs to change.

## `action_button_core` is set server-side, not derived from the `bi-*` name

**Looks like:** a redundant class. The glyph name is right there — why not check for
`bi-eye`/`bi-pencil-square` in `isCoreAction()`?

**Why:** a module's identity glyph can legitimately coincide with a core action's. Marking
core-ness where the action is built is the only place that distinction is known.

**What breaks if you derive it instead:** a module whose `bootstrap_icon()` happens to
return a core glyph gets promoted into the inline action row.

**Background:** `isCoreAction()` used to classify by reading the `<img>`'s `src` and
matching filename regexes. The glyph conversion removed the `<img>`, so `src` was `''`,
every regex failed, and **every action on every grid fell through to "extra" and hid behind
the More-actions kebab**.

## `Base/Notify/refresh.php`'s pre-bootstrap early-out duplicates the literal `30`

**Looks like:** a magic number that should read `Base_NotifyCommon::refresh_rate`.

**Why:** the whole point is to answer "is this poll too early?" **before**
`ModuleManager::load_modules()`, so the constant is not loadable yet. Reaching for it would
reintroduce the ~80 ms bootstrap the early-out exists to avoid.

The check is **deliberately fail-open** — it exits only when it can positively prove the
poll is early. Two conditions are load-bearing and easy to drop by accident:

- It matches the row by derived token **or** `single_cache_uid`, because one_cache mode
  finds the row by uid rather than by `md5(user_id.'__'.session_id)`. Probing only the
  token silently fail-opens for every session except the one that created the row —
  exactly the multi-session case one_cache exists for.
- `telegram=0` is **mandatory**. Telegram rows also carry `single_cache_uid` but run on
  `refresh_rate_telegram` (300 s), so letting one match answers this poller with the wrong
  cycle's timestamp.

`NotifyCommon_0.php`'s `refresh_rate` carries a matching cross-reference comment. A
mismatch is not a correctness bug — it costs one wasted bootstrap or one skipped poll.

## The legacy `theme/` directories

**Looks like:** a second theme worth keeping parity with.

**Why:** it is not selectable. `theme_adminltedark` is the only directory under
`modules/Base/Theme/`, so the admin theme picker can only ever list that one, and theme
upload/installation was removed outright. The per-module `theme/` folders survive purely as
`Base_ThemeResolver`'s **file-fallback layer** for assets `theme_adminltedark/` does not
override — not as a theme a user can run. It exists for legacy modules.

**What this means in practice:** Bootstrap Icons is the single icon mechanism. Do not add
raster-icon paths and do not "restore" sprite usage. A sprite does still exist
(`Base/ActionBar/theme/icons.png`) and is the legacy theme's original design, but
adminltedark does not use it — ActionBar emits `bi-*` classes from its own template.
Re-spriting would be a step backwards.

---

# 3. Standalone entry points

`admin/`, `update.php`, `check.php` and `setup.php` run outside (or before) the normal
module/theme pipeline — they must work pre-install, pre-login, or without a full session,
so none of them go through `Base_ThemeResolver`/`Base_ThemeCommon::init_smarty()` the way
ordinary modules do. All four follow one PHP-logic + Smarty-template pattern:

- `admin/` → `admin/AdminSmarty.php` + `admin/templates/*.tpl`
- `update.php` → `UpdateSmarty` + `include/templates/update/*.tpl`
- `check.php` → the same `check_results.tpl`, reused standalone and embedded inside
  `setup.php`'s compatibility-check step
- `setup.php` → `setuptheme/SetupSmarty.php` + `setuptheme/*.tpl`

The shared Smarty-array-form renderer several of these use
(`EpesiSmartyRenderer` + `HTML_QuickForm_Renderer_EpesiArray`) lives at
`include/EpesiSmartyRenderer.php` / `include/EpesiArray.php`. **`modules/Libs/QuickForm`
itself is not legacy code** — only the renderer files live outside it; don't confuse the
two.

## `anonymous_setup`: the bootstrap flag, and how it is kept out of the ACL primitives

`anonymous_setup` is a bootstrap flag stored as a `Variable`. It exists because `setup.php`
and FirstRun have to install modules and write configuration **before any account exists to
authenticate as** — you cannot require an admin login before there is an admin.

**The ACL primitives do not consult it.** `i_am_admin()` and `i_am_sa()` mean what they
say. Two things do the job instead:

- **`Base_AclCommon::anonymous_setup_active()`** — read this, never
  `Variable::get('anonymous_setup')`. It ignores the flag once a real super-admin
  (`user_login.admin=2`) exists, so the bootstrap window cannot outlive itself, and treats
  a missing row as "off" rather than throwing. Only two callers exist, both UI gates for
  the bootstrap window itself: `Base_SetupCommon::admin_access()` and `SimpleLogin::form()`.
- **`Base_AclCommon::begin_bootstrap_install()` / `end_bootstrap_install()`** — a
  process-local elevation, never persisted, set from exactly one place: `FirstRun::done()`,
  around `ModuleManager::install('Base')`, the one install step that runs before the
  super-admin exists. **No request can turn it on.** Don't add a second caller without a
  very good reason; for "is this install still bootstrapping?" as a *UI* gate, use
  `anonymous_setup_active()`.

`admin/AdminIndex.php`, `update.php` and `check.php` keep their own stronger gate
regardless: `SimpleLogin::force_login_form()`/`force_login_page()` (which render a login
form without any bypass) plus a direct `Base_AclCommon::get_admin_level()` check.

## `check.php` is meant to be read-only — keep it that way

It used to unconditionally run `Base_LangCommon::update_translations()` (rescans every
module's `lang/`, rewrites all 37 `data/Base_Lang/base/*.php` files) and
`ModuleManager::create_load_priority_array()` on *every* view, past login — almost
certainly the cause of historical "check.php hangs Apache" reports for what is supposed to
be a read-only compatibility report. Both calls are gone; `get_orphaned_modules()`, the one
thing check.php actually needs, reads the DB directly.

## Anything running before `Base` installs needs its own theme fallback

`modules/FirstRun/FirstRun_0.php` (the post-setup admin-creation wizard) runs **before** the
`Base` module installs. At that point `Variable::get('default_theme', false)` is genuinely
`false`, and both `Base_ThemeCommon::get_default_template()` and `index.php`'s own
duplicated copy of that logic fall back to a literal string. Both fall back to
`'adminlte'`. **If a pre-install or pre-login screen ever looks unthemed despite
`default_theme` being set in the DB, check whether it runs before Base installs** — same
root cause, not necessarily a missing template.

---

# 4. Menu render paths

## Tree construction (shared by both paths)

`Base_MenuCommon::get_menus()` calls `ModuleManager::call_common_methods('menu', false)`,
which scans every installed module's `<Name>Common` class for a `menu()` method — opt-in by
declaration, no central registry. Each module's own `menu()` does its own ACL check before
contributing entries, so the merged tree is already permission-filtered per user by the
time it reaches rendering — **nothing in the DOM needs a second, client-side ACL check.**

The merged result is cached in `$_SESSION` via `Module::static_set_module_variable()`, so
editing a module's `menu()` does not show up until re-login.

In `Menu_0.php`: `add_menu()` merges every module's per-label contribution into one tree by
label-string matching (no single "owner" file per top-level group); `sort_menus()` applies
`__weight__` ordering; `body()` assembles `$modules_menu` and picks a render path based on
`Base_ThemeCommon::is_adminlte_family()`.

## Two independent render paths

- **AdminLTE family:** the sidebar is built straight from `$modules_menu` (each top-level
  group gets its own row, not collapsed under one "Menu" root) via `build_menu_html()` — a
  private method returning a literal HTML string, assigned directly to the `menu` template
  var.
- **Legacy theme:** goes through `Utils_Menu` instead, rendered as a hover fly-out widget
  via the older `build_menu()`. Separate code path entirely — changes to one do not affect
  the other.

`modules/Base/Menu/theme/default.tpl` is a one-line `{$menu}`, theme-agnostic. There is
**no** `theme_adminltedark/default.tpl` override for this module; `build_menu_html()`
pre-builds the whole HTML string in PHP before the template runs, so any HTML/JS addition
to the AdminLTE sidebar menu is done by editing the string `body()` assigns, without
touching a template.

## The AdminLTE sidebar DOM

```html
<ul class="nav flex-column epesi-menu">  <!-- epesi-submenu at depth>0 -->
  <li class="nav-item">
    <a href="#" class="nav-link menu-parent collapsed" data-bs-toggle="collapse"
       data-bs-target="#epesi_menu_<md5>" aria-expanded="false" helpID="Menu_Accounting">
      <i class="bi bi-folder2 nav-icon"></i><span class="nav-label">Accounting</span>
      <i class="bi bi-chevron-right nav-arrow"></i>
    </a>
    <div class="collapse" id="epesi_menu_<md5>"> <!-- nested <ul> recurses here --> </div>
  </li>
</ul>
```

- **Deliberately not AdminLTE's own `nav-treeview`/`menu-open` classes.** AdminLTE hides
  `.nav-treeview` unless the parent carries `.menu-open`, which would fight the Bootstrap 5
  `.collapse` mechanism used here.
- Expand/collapse is **pure Bootstrap 5 `data-bs-toggle="collapse"`**, no custom JS.
  Chevron rotation is CSS keyed off `[aria-expanded="true"]`, not a JS-toggled class.
- Labels are plain escaped text (`htmlspecialchars(_V($k))`) inside `<span class="nav-label">`
  — no data attribute carries the untranslated key, so anything matching against display
  text reads `.nav-label`'s `textContent`, already in the user's own language.
- **The full tree is always in the DOM**, rendered once per shell render, not built
  incrementally. Submenus are only visually collapsed — so a client-side feature that
  filters or searches the menu can work purely on the existing DOM, with no server round
  trip.

## Shell wiring

`modules/Base/Box/theme_adminltedark/default.tpl` holds the outer shell; `#MenuBar` is
Base_Menu's own render target, sitting directly under `.sidebar-brand`. Content prepended
to the `$menu` string in `Menu_0.php::body()` lands there — which is how anything is added
above the menu without touching Box's template.

`#MenuBar` is **not** permanent shell chrome — it is part of Box's own output and can be
swapped for a fresh node mid-session. Any JS bound to it must follow the convention:
`eval_js()` on **every** render (not `eval_js_once()`), guarded by an idempotency marker
property on the element (e.g. `bar.__epesiCloseBound`), so a re-bind on a fresh node
happens exactly once instead of assuming the original node lives forever.

`modules/Base/Menu/theme_adminltedark/default.css` is the only stylesheet for this markup,
everything scoped `.epesi-adminlte #MenuBar ...`. A module's CSS loads only when that
module renders, so sidebar styling belongs in Base_Menu's own stylesheet, not Box's, even
though Box owns the `#MenuBar` wrapper element.
