# Load-bearing oddities

> **Status:** REFERENCE - code and config that looks like cruft but is deliberate. Read before "tidying up" any of it.

The counterpart to [deliberate-removals.md](deliberate-removals.md). That file says *don't
put this back*; this one says **don't take this out**.

Every entry here is something a reasonable person would look at and want to simplify,
where simplifying it breaks something that is not obvious from the code in front of you.
Each says what it looks like, why it is that way, **what actually breaks**, and how to
check before changing it.

Written 2026-08-31, after a session shipped two regressions of exactly this kind and a
concurrent session shipped a third.

---

## `tools/` is a separate composer project, not root `require-dev`

**Looks like:** a pointless second `composer.json` for two dev dependencies. Obvious
tidy-up: move `phpstan/phpstan` and `rector/rector` into the root `composer.json` and
delete `tools/`.

**Why:** `vendor/` is **committed to this repo** (3,248 tracked files) so a deployment
needs no composer run at all. That makes root dev dependencies a genuinely bad fit:

1. The two packages are **~68 MB / ~3,100 files** - they would more than half again the
   committed tree and ship in every release zip.
2. Gitignoring them instead does not work. Composer writes their bootstraps into
   `vendor/composer/autoload_files.php`, which `vendor/autoload.php` `require`s on **every
   request** - so a fresh clone would fatal on every page load.
3. Regenerating the autoloader with `--no-dev` fixes that but then breaks Rector, which
   needs its own dev autoload entries to resolve its scoped PHPStan classes.

All three were hit in order while trying it. `tools/` sidesteps the whole chain: root
`composer.json`, `composer.lock` and `vendor/` stay untouched, `tools/vendor/` is
gitignored, `tools/composer.lock` is committed so versions stay pinned.

**What breaks if you fold it back in:** a fresh clone fatals on every request, or the
release zip grows by ~68 MB, depending on which half you do.

**Usage:** `composer install -d tools`, then `tools/vendor/bin/phpstan` /
`tools/vendor/bin/rector`. Documented in `README.md` and `CLAUDE.md`.

---

## The `<img>` fallback in `Utils_GenericBrowser::action_icon_tag()`

**Looks like:** dead legacy code. The theme is Bootstrap-Icons-only now, so why does the
function still build an `<img>` at all?

**Why:** it is the path for every icon whose *meaning* cannot be determined.
`Premium/Import` ships its own folder/manual/copy/checkbox artwork and has 22 of its own
`[src*="..."]` CSS rules **in a separate, gitignored git repo**; legacy modules route
their artwork through the same path branch. There is no way to know what glyph an
arbitrary PNG means, so those keep rendering exactly as before.

Note the branch conditions are deliberately narrow, and both were tightened after getting
them wrong:

- The stem lookup is gated on the path being **GenericBrowser's own**, so another
  module's `edit.png` cannot borrow our glyph.
- The identity-icon branch is `/^icon[-_]small$/`, **not** `/[-_]small$/`. The broad
  version matched `copy_small.png` and `cut_small.png`, and `resolve()` then returned the
  *owning module's* identity glyph - CRM_Mail's copy action rendered as an envelope, and
  Utils_Attachment's "Copy link" and "Cut" both became journals, losing their distinction.
  This is **not** fixable by adding entries to `Base_BootstrapIcons::$by_filename`: that
  map is keyed by basename alone, and the same `copy_small.png` deliberately means
  `bi-copy` for CRM_Mail and `bi-link` for Utils_Attachment.

**What breaks if you remove it:** Premium/Import's toolbar icons vanish, and every legacy
module's row artwork with them.

**Converting a module properly** means having it declare `bootstrap_icon()` - nothing in
`action_icon_tag()` needs to change.

---

## `action_button_core` is set server-side, not derived from the `bi-*` name

**Looks like:** a redundant class. The glyph name is right there - why not just check for
`bi-eye`/`bi-pencil-square`/... in `isCoreAction()`?

**Why:** a module's identity glyph can legitimately coincide with a core action's. Marking
core-ness at the point the action is built is the only place that distinction is actually
known.

**What breaks if you derive it instead:** a module whose `bootstrap_icon()` happens to
return a core glyph gets promoted into the inline action row.

**Background:** `isCoreAction()` (`Base_Box/theme_adminltedark/default.tpl`) used to
classify actions by reading the `<img>`'s `src` and matching filename regexes. The glyph
conversion removed the `<img>`, so `src` was `''`, every regex failed, and **every action
on every grid fell through to "extra" and hid behind the More-actions kebab**. See
`performance-profiling.md` for the full post-mortem and the generalised rule.

---

## The default (non-AdminLTE) theme is legacy-only and is being retired

**Looks like:** a second theme worth keeping parity with.

**Why:** it is not selectable. `theme_adminltedark` is the only directory under
`modules/Base/Theme/`, so the admin theme picker can only ever list that one; theme
upload/installation was removed outright (see `deliberate-removals.md`). The per-module
`theme/` folders survive purely as `Base_ThemeResolver`'s **file-fallback layer** for
assets `theme_adminltedark/` does not override - not as a theme a user can run.

Per Jasiek (2026-08-31): the default theme exists only for legacy modules and this is
likely the last release to carry it.

**What this means in practice:** **Bootstrap Icons is the single icon mechanism.** Do not
add raster-icon paths, and do not "restore" sprite usage. A sprite does still exist
(`Base/ActionBar/theme/icons.png`, 16 KB, `background-position`) and is the legacy theme's
original design, but adminltedark does not use it - ActionBar emits `bi-*` classes from
its own template. Re-spriting would be a step backwards.

---

## `phpstan.neon` and both `rector*.php` exclude `modules/Premium/` and `modules/Custom/`

**Looks like:** an odd blind spot - surely you want analysis to cover more code.

**Why:** both are gitignored, separately-licensed nested git repos, so **CI checks out
neither**. Analysing them locally produced findings CI could never report, and a baseline
generated with them present can never match CI's run. That mismatch is exactly what
`phpstan.neon`'s own comment used to complain about; excluding them makes a local run
reproduce CI exactly.

**What breaks if you re-include them:** ~18 extra errors locally that CI never sees, and a
baseline that is wrong for everyone else. If you regenerate the baseline in that state you
push the problem onto every other developer.

**Check before changing:** `tools/vendor/bin/phpstan analyse -c phpstan.neon` must report
**0 new** findings (211 are baselined).

---

## The Rector CI job is `continue-on-error: true` on purpose

**Looks like:** a job nobody bothered to make blocking.

**Why:** Rector applies real rules only rarely on this codebase - the ~10 files it used to
report on every dry-run were whitespace-only re-prints from Rector 2.x, not real findings
(fixed for real 2026-09-01, see `ci-workflow.md`; a clean dry-run now reports 0 files).
`rector-php83.php`'s own header has always described it as advisory. Making it blocking
would fail the build the moment a genuine rewrite opportunity shows up, before anyone's
had a chance to review the diff.

**Check before changing:** `tools/vendor/bin/rector process --dry-run --config
rector-php83.php --output-format=json` - if `applied_rectors` is empty for every file,
there is still nothing to gate on.

---

## The CI docs job checks `CLAUDE.md` only, never `AI-shared/`

**Looks like:** an obvious gap - why not validate every doc?

**Why:** `AI-shared/` is explicitly allowed to contain approved-but-unimplemented plans.
`release-packaging-plan.md` correctly names an `update:apply` console command that does
not exist yet. Running the same check there would fail the build on a document doing its
job.

`CLAUDE.md` is different: it is auto-loaded into every Claude Code session and must be
trustworthy. It had four wrong facts on 2026-08-31 (two invented `console.php` commands, a
removed test skeleton, a CI job that did not exist).

---

## `AI-shared/` and `tools/` are excluded from `dev:dist:create`

**Looks like:** the tutorial and dev docs might be useful to people installing Epesi.

**Why:** these are notes for people working **on** Epesi, not running it - and the
exclusion is a correctness fix, not just a size one. `dev:dist:create` archives the
**working tree, not `git ls-files`**, so a gitignored file that exists locally still ships.
`AI-shared/DirectAdmin-git-sync.md` is gitignored precisely because it holds one
developer's own hosting account details; without the folder-level rule, building a release
on that machine would put them in the SourceForge zip.

**Watch out:** `modules/Tools/` is a real shipped module tree (SessionKeeper, SetDefaults,
WhoIsOnline). Only the `^` anchor keeps `^tools(sep|$)` from eating it - any future
exclusion pattern here needs the same care.

---

## `Base/Notify/refresh.php`'s pre-bootstrap early-out duplicates the literal `30`

**Looks like:** a magic number that should read `Base_NotifyCommon::refresh_rate`.

**Why:** the whole point is to answer "is this poll too early?" **before**
`ModuleManager::load_modules()`, so the constant is not loadable yet. Reaching for it
would reintroduce the ~80 ms bootstrap the early-out exists to avoid.

The check is **deliberately fail-open**: it exits only when it can positively prove the
poll is early. Two conditions are load-bearing and easy to drop by accident:

- It matches the row by derived token **or** `single_cache_uid`, because one_cache mode
  finds the row by uid rather than by `md5(user_id.'__'.session_id)`. Probing only the
  token silently fail-opens for every session except the one that created the row - which
  is exactly the multi-session case one_cache exists for.
- `telegram=0` is **mandatory**. Telegram rows also carry `single_cache_uid` but run on
  `refresh_rate_telegram` (300 s), so letting one match answers this poller with the wrong
  cycle's timestamp.

`NotifyCommon_0.php`'s `refresh_rate` carries a matching cross-reference comment. A
mismatch is not a correctness bug - it costs one wasted bootstrap or one skipped poll.

---

## `prefetch_*` methods are warm-ups, never required steps

`Utils_RecordBrowserCommon::prefetch_record_info()` and
`Utils_WatchdogCommon::_last_event()` prime a request-scoped cache in one grouped query.
Both keep the original per-id path as a self-healing fallback, and `prefetch_record_info()`
deliberately leaves ids with no `_data_1` row uncached so `get_record_info()`'s
`trigger_error()` still fires for a genuinely missing record.

**Do not** "simplify" a caller into assuming the prefetch ran. A grid that adds rows by
another route must still render correctly, just with more queries.

Caches added for performance in this codebase are **request-scoped only** unless the entry
in `performance-profiling.md` says otherwise - that is a deliberate discipline, so that
adding one does not require finding and instrumenting every mutation site. The one
cross-request exception (`get_contact_by_user_id()`) has full invalidation, documented
there.
