# Load-bearing oddities

> **Status:** REFERENCE - code and config that looks like cruft but is deliberate. Read before
> "tidying up" any of it. The converse of [deliberate-removals.md](deliberate-removals.md):
> that file says *don't put this back*, this one says **don't take this out**. The pre-split
> version, with the repo-maintenance entries, is at
> `AI-private/archive/load-bearing-oddities.md`.

Every entry is something a reasonable person would look at and want to simplify, where
simplifying it breaks something not visible from the code in front of you. Each says what it
looks like, why it is that way, what actually breaks, and how to check first.

---

## `tools/` is a separate composer project, not root `require-dev`

**Looks like:** a pointless second `composer.json` for two dev dependencies. Obvious tidy-up:
move `phpstan/phpstan` and `rector/rector` into the root `composer.json` and delete `tools/`.

**Why:** `vendor/` is **committed to this repo** so a deployment needs no composer run at all.
That makes root dev dependencies a bad fit, and all three consequences were hit in order while
trying it:

1. The two packages are ~68 MB / ~3,100 files — they would more than half again the committed
   tree and ship in every release zip.
2. Gitignoring them instead does not work: composer writes their bootstraps into
   `vendor/composer/autoload_files.php`, which `vendor/autoload.php` requires on **every
   request** — so a fresh clone fatals on every page load.
3. Regenerating the autoloader with `--no-dev` fixes that, but then breaks Rector, which needs
   its own dev autoload entries to resolve its scoped PHPStan classes.

`tools/` sidesteps the whole chain: root `composer.json`, `composer.lock` and `vendor/` stay
untouched, `tools/vendor/` is gitignored, `tools/composer.lock` is committed so versions stay
pinned.

**What breaks if you fold it back in:** a fresh clone fatals on every request, or the release
zip grows by ~68 MB — depending on which half you do.

**Usage:** `composer install -d tools`, then `tools/vendor/bin/phpstan` / `rector`.

---

## `phpstan.neon` and both `rector*.php` exclude `modules/Premium/` and `modules/Custom/`

**Looks like:** an odd blind spot — surely you want analysis to cover more code.

**Why:** both are gitignored, separately-licensed nested repos, so **CI checks out neither**.
Analysing them locally produces findings CI could never report, and a baseline generated with
them present can never match CI's run.

**What breaks if you re-include them:** ~18 extra local errors CI never sees, and a baseline
that is wrong for everyone else — pushed onto every other developer the moment you regenerate
it in that state.

**Check before changing:** `tools/vendor/bin/phpstan analyse -c phpstan.neon` must report
**0 new** findings.

---

## The `<img>` fallback in `Utils_GenericBrowser::action_icon_tag()`

**Looks like:** dead legacy code. The theme is Bootstrap-Icons-only now, so why build an
`<img>` at all?

**Why:** it is the path for every icon whose *meaning* cannot be determined. Premium modules
ship their own artwork with their own `[src*="..."]` CSS rules, in separate gitignored repos;
legacy modules route their artwork through the same branch. There is no way to know what glyph
an arbitrary PNG means, so those keep rendering exactly as before.

Both branch conditions are deliberately narrow, and both were tightened after being got wrong:

- The stem lookup is gated on the path being **GenericBrowser's own**, so another module's
  `edit.png` cannot borrow our glyph.
- The identity-icon branch is `/^icon[-_]small$/`, **not** `/[-_]small$/`. The broad version
  matched `copy_small.png` and `cut_small.png`, and `resolve()` then returned the *owning
  module's* identity glyph — CRM_Mail's copy action rendered as an envelope, and
  Utils_Attachment's "Copy link" and "Cut" both became journals. This is **not** fixable by
  adding entries to `Base_BootstrapIcons::$by_filename`: that map is keyed on basename alone,
  and the same `copy_small.png` deliberately means `bi-copy` for one module and `bi-link` for
  another.

**What breaks if you remove it:** Premium modules' toolbar icons vanish, and every legacy
module's row artwork with them.

**Converting a module properly** means having it declare `bootstrap_icon()` — nothing in
`action_icon_tag()` needs to change.

---

## `action_button_core` is set server-side, not derived from the `bi-*` name

**Looks like:** a redundant class. The glyph name is right there — why not check for
`bi-eye`/`bi-pencil-square` in `isCoreAction()`?

**Why:** a module's identity glyph can legitimately coincide with a core action's. Marking
core-ness where the action is built is the only place that distinction is known.

**What breaks if you derive it instead:** a module whose `bootstrap_icon()` happens to return a
core glyph gets promoted into the inline action row.

**Background:** `isCoreAction()` used to classify by reading the `<img>`'s `src` and matching
filename regexes. The glyph conversion removed the `<img>`, so `src` was `''`, every regex
failed, and **every action on every grid fell through to "extra" and hid behind the
More-actions kebab**.

---

## The default (non-AdminLTE) theme is legacy-only and is being retired

**Looks like:** a second theme worth keeping parity with.

**Why:** it is not selectable. `theme_adminltedark` is the only directory under
`modules/Base/Theme/`, so the admin theme picker can only ever list that one, and theme
upload/installation was removed outright. The per-module `theme/` folders survive purely as
`Base_ThemeResolver`'s **file-fallback layer** for assets `theme_adminltedark/` does not
override — not as a theme a user can run. It exists for legacy modules, and this is likely the
last release to carry it.

**What this means in practice:** **Bootstrap Icons is the single icon mechanism.** Do not add
raster-icon paths and do not "restore" sprite usage. A sprite does still exist
(`Base/ActionBar/theme/icons.png`) and is the legacy theme's original design, but adminltedark
does not use it — ActionBar emits `bi-*` classes from its own template. Re-spriting would be a
step backwards.

---

## `Base/Notify/refresh.php`'s pre-bootstrap early-out duplicates the literal `30`

**Looks like:** a magic number that should read `Base_NotifyCommon::refresh_rate`.

**Why:** the whole point is to answer "is this poll too early?" **before**
`ModuleManager::load_modules()`, so the constant is not loadable yet. Reaching for it would
reintroduce the ~80 ms bootstrap the early-out exists to avoid.

The check is **deliberately fail-open** — it exits only when it can positively prove the poll
is early. Two conditions are load-bearing and easy to drop by accident:

- It matches the row by derived token **or** `single_cache_uid`, because one_cache mode finds
  the row by uid rather than by `md5(user_id.'__'.session_id)`. Probing only the token silently
  fail-opens for every session except the one that created the row — exactly the multi-session
  case one_cache exists for.
- `telegram=0` is **mandatory**. Telegram rows also carry `single_cache_uid` but run on
  `refresh_rate_telegram` (300 s), so letting one match answers this poller with the wrong
  cycle's timestamp.

`NotifyCommon_0.php`'s `refresh_rate` carries a matching cross-reference comment. A mismatch is
not a correctness bug — it costs one wasted bootstrap or one skipped poll.

---

## `prefetch_*` methods are warm-ups, never required steps

`Utils_RecordBrowserCommon::prefetch_record_info()` and `Utils_WatchdogCommon::_last_event()`
prime a request-scoped cache in one grouped query. Both keep the original per-id path as a
self-healing fallback, and `prefetch_record_info()` deliberately leaves ids with no data row
uncached so `get_record_info()`'s `trigger_error()` still fires for a genuinely missing record.

**Do not** "simplify" a caller into assuming the prefetch ran. A grid that adds rows by another
route must still render correctly, just with more queries.

Caches added for performance here are **request-scoped only** unless
[performance-profiling.md](performance-profiling.md) says otherwise — a deliberate discipline,
so that adding one does not require finding and instrumenting every mutation site.
