# Branding: the product name is "Epesi", not "EPESI"

> **Status:** REFERENCE - naming convention, plus the one thing that makes a rename risky.
> The log of the 2026-08-28 rename pass is archived at
> `AI-private/archive/branding-epesi-casing.md`.

The application and framework name is styled **"Epesi"** — capital E, rest lowercase. Use it
in everything you write: docs, comments, commit messages, new UI strings.

**The codebase is genuinely inconsistent about this today.** Both castings coexist: most
user-facing strings in `modules/Base/Lang/lang/en.php` still have the all-caps source string as
their key and still display all-caps. Don't infer either casing from one call site — check.

## Before renaming a product-name string, check its blast radius

There are two very different fixes, and picking the wrong one causes a visible regression:

- **A plain user-facing label** → an `en.php` override is the right fix. Cheap, one file,
  changes only what English users see.
- **A string other code matches on by exact value** → the label alone is not enough; every call
  site *and* every language file's array key must be renamed together, in one pass.

The second case is easy to miss. `__('Epesi Core')` is the grouping key that ~57 different
`<Module>Install.php::simple_setup()` methods return so they all merge into **one** Setup-screen
package card — `Base_Setup::simple_setup()` groups by exact string match. Renaming only some
call sites splits that single card into two, one per casing. The same hazard applies to any
Setup package name, ACL permission name, CommonData array id, or anything used as a
`switch`/`==` key rather than merely displayed.

When renaming the key in `lang/<code>.php` files, rename the **key** only and leave each
language's existing translated *value* untouched.

**A lang-file edit alone is never enough to see it live.** `Base_LangCommon::load()` caches the
per-language merge via `Cache::get('lang_merged_'.$lang_code)` — run `console.php cache:rebuild`
after editing any `lang/<code>.php`.

## If you run a bulk doc-casing sweep

`perl -i -pe 's/\bEPESI\b/Epesi/g'` is safe to run unattended over markdown: `_` counts as a
word character, so the `\b` never touches a snake-case identifier fused to `EPESI`
(`EPESI_URL`, `EPESI_VERSION`, `EPESI_LOCAL_DIR`) or a directly-fused external name.

**Always read the diff afterwards**, looking for the two shapes where a mechanical pass
produces a wrong-but-plausible result:

- **A backtick-fenced quote of a real call site.** The regex doesn't know a code span from
  prose, so it happily "fixes" a quote of PHP source that still literally says `EPESI Core` —
  turning an accurate doc into an inaccurate one.
- **A "before/after" narrative.** Any entry shaped "it said X, we changed it to Y" needs the
  *before* half left alone, or the sentence contradicts itself. Grep for "casing", "renamed",
  "was EPESI", "fixed to" before trusting the diff.
