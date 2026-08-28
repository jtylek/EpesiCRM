# Branding: the product name is "Epesi", not "EPESI"

Written 2026-08-28. The application/framework name is styled **"Epesi"** (capital E,
rest lowercase) — not the all-caps "EPESI" that shows up throughout older code, docs,
and translation strings. Use "Epesi" in new prose (docs, comments, commit messages,
UI strings you write) going forward.

## The codebase is inconsistent about this today — don't assume either casing from one call site

Both castings genuinely coexist in the live app right now. Fixed so far (2026-08-28):

- **"Epesi Core"** — full rename, both directions:
  - **Call sites** (not just `SetupInstall.php`): the string `__('EPESI Core')` is the
    grouping key ~57 different `<Module>Install.php::simple_setup()` methods return so
    they all merge into *one* Setup-screen package card (`Base_Setup::simple_setup()`
    groups packages by exact string match on `'package'`). All ~57 call sites, plus
    `Setup_0.php:314`'s `__('EPESI Core can not be uninstalled')` (the
    uninstall-blocked tooltip for a required/core package), were renamed to `Epesi
    Core`/`Epesi Core can not be uninstalled` **together, in one pass** — renaming only
    some would have split the Setup screen's single "Epesi Core" card into two
    separate cards (one per casing), a real visible regression. Two non-translated
    literal-string mentions were also renamed: `include/patches.php:403` (fallback
    "module" label for a patch with no owning module) and `update.php:3` (a doc
    comment).
  - **All 36 `lang/<code>.php` files**: the array **key** (not the translated value)
    was renamed from `'EPESI Core'`/`'EPESI Core can not be uninstalled'` to the
    `Epesi`-cased form, for both strings, in every language file — each language's
    existing translated *value* was left completely untouched (e.g. `de.php` still
    reads `'EPESI Hauptmodul'`/`'EPESI Kern kann nicht deinstalliert werden.'`, just
    filed under the new key). `en.php`'s two entries were reset to empty values
    (`''`) after the key rename, matching the rest of that file's convention (English
    needs no override once the key itself is the desired display text).
  - Verified live via screenshot: a single, unfragmented "Epesi Core" card with its
    "Optional" group (Additional applets, Error reporting, Web Notifications) intact.
- `EpesiStore_0.php`'s own tab is literally `$tb->set_tab('Epesi Store', ...)` — mixed
  case, and `EpesiStoreCommon_0.php:31`'s `Register Epesi!` ActionBar label is mixed
  case too. Neither needed touching.
- Nearly every *other* user-facing string in `modules/Base/Lang/lang/en.php` that
  mentions the product ("You've just installed EPESI!", "EPESI Store", "Register
  EPESI!", "Welcome to EPESI setup!", ...) still has the all-caps source string as its
  key, with an empty EN override — still displays all-caps today. `en.php:500`
  (`'Disable EPESI Store'` → value `'Disable Epesi Store'`) remains the one
  override-only fix (no matching call-site/other-locale-key rename was needed for that
  one, since nothing else groups by that exact string).

**Why the "rename the key everywhere" approach, not another override**: an
`en.php`-only override (as `Disable EPESI Store` uses) is the *lower-risk, smaller*
fix — it changes only what English users see, touches one file, and is right for a
string nothing else depends on matching exactly. It was deliberately **not** used for
"Epesi Core", specifically because the call site's literal string doubles as a
cross-module *grouping key* (not just a translation key) — an override can't fix that,
only a real rename of every call site (kept in lockstep) can. When asked to fix a
product-name casing again, check **first** whether the string is a plain user-facing
label (→ EN-only override, cheap) or something other code also matches on
by exact string — a Setup `simple_setup()` package name, an ACL permission name, a
CommonData array id, anything used as a `switch`/`==` key rather than just displayed
(→ needs the full rename-everywhere treatment done here, coordinated across every call
site and every language file's key).

**Either way, a lang-file edit alone isn't enough to see it live** — both
`load()`'s per-language merge (`Base_LangCommon::load()`) and
`FORCE_CACHE_COMMON_FILES`'s common-class bundle are cached; run
`console.php cache:rebuild` (`Cache::clear()` + `ModuleManager::create_common_cache()`)
after editing any `lang/<code>.php` file, same as the `FORCE_CACHE_COMMON_FILES`
gotcha in `environment-gotchas.md`. The rest of the `EPESI`-keyed strings in `en.php`
are still unfixed — do them opportunistically (checking each one's blast radius as
above first), not in one blind sweep.

## What this means for a "replace EPESI with Epesi" doc-cleanup pass

Done 2026-08-28 across all tracked `*.md` files (`AI-shared/`, `docs/`, `CLAUDE.md`,
module `README.md`s) via `perl -i -pe 's/\bEPESI\b/Epesi/g'` — the `\b` word-boundary
match is what makes this safe to run unattended: `_` counts as a word character, so it
never touches a snake-case identifier fused to `EPESI` (`EPESI_URL`, `EPESI_VERSION`,
`EPESI_REVISION`, `EPESI_LOCAL_DIR`, and doc-placeholder tokens like
`EPESI_INSTALLATION`/`EPESI_address`), and it never touches a directly-fused external
name (`iCalendarEPESI`, a real third-party GitHub repo — see
`modules/CRM/GoogleCalendarSync/README.md`).

**What the blind regex *does* still get wrong, and had to be manually reverted twice
in this same pass**:
- A backtick-fenced **inline code quote of a real call site** (e.g.
  `` `__('EPESI Core')` `` in `AI-shared/Dev-Tutorial.md`) — this is quoting actual PHP
  source, which (per above) still literally says `EPESI Core` today. The regex doesn't
  know a code span from prose; it happily "fixed" the quote into something that no
  longer matches the real file, i.e. turned an accurate doc into an inaccurate one.
- A **before/after narrative in `MIGRATION_NOTES.md`** (§83, "Default Dashboard 'Note'
  applet text"): the note specifically says the *original* seeded text read
  "...installed EPESI!..." and that a fix changed the casing to "Epesi". The regex
  changed the "before" quote too, making the sentence self-contradictory (quotes the
  "before" state as already "Epesi", then says it was "fixed" to "Epesi"). Any doc
  entry structured as "it said X, we changed it to Y" needs the "before" half left
  alone regardless of what a blind pass would do to it — check for this shape
  specifically (grep for "casing", "renamed", "was EPESI", "fixed to") before trusting
  a bulk pass's diff without reading it.

Plain descriptive/paraphrased mentions of UI text (not framed as a literal quote of a
specific historical state) were left as the regex changed them — e.g. bug-patterns.md's
`"EPESI Core" modules` → `"Epesi Core" modules` is now technically ahead of what the
live UI displays (see above), but it's describing the package grouping in the author's
own words, not asserting "the screen says exactly this string" — an acceptable,
intentional style choice per this note's own guidance above, not an error.

**How to apply**: if asked to do a similar text-casing/rename sweep across markdown
docs in this repo again, use the same word-boundary regex approach (correctly skips
identifiers/external names for free), but **always read the full diff afterward**
looking specifically for backtick-fenced code spans and "before/after" narrative
framing — those two shapes are exactly where a mechanical pass silently produces a
wrong-but-plausible-looking result instead of erroring loudly.
