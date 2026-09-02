# PHP 8 migration: what it means for code you write today

> **Status:** REFERENCE - the durable rules from the PHP 7.4 → 8.2 migration of Epesi 1.9.1.
> The full numbered log (§1–§85, one section per bug/decision) is archived at
> `AI-private/archive/MIGRATION_NOTES.md`. Code comments and older docs cite it by section
> number ("MIGRATION_NOTES.md §55") — those numbers refer to the archive, not to this file.

Epesi 1.9.1 was migrated from PHP 7.4 to 8.2 with a Rector ladder (7.0 → 7.4 → 8.0 → 8.1),
then hardened by driving the real app. The release that came out of it is **Epesi 2.0**
(`EPESI_VERSION`/`EPESI_REVISION` in `include/version.php`).

This file keeps the parts that still change how you write code. The archive keeps the
history: which file broke, when, and what the fix was.

## The PHP floor is 8.1, and it is enforced in exactly one place

`CompatibilityCheck::system_check()`'s `$desired_version` (`include/compatibility_check.php`)
is the single source of truth — read it rather than quoting a number from memory.

- **8.1 minimum.** The bootstrap uses **first-class callable syntax** (`$this->autoload(...)`
  in `include/autoloader.php`, likewise `error.php`/`session.php`/`patches.php`/`module.php`)
  and `: never` return types. Both are 8.1-only, both are *parse* errors on 8.0, and both sit
  in files `include.php` requires on every request — so an 8.0 install fatals at startup.
- **8.2 target.** Nothing in the tree actually needs 8.2; it is what the release is developed
  and tested against.
- **8.3 Rector-clean, which is not an 8.3 support claim.** `rector-php83.php` reports 0 files.
  Rector only rewrites *forward*, so zero findings is equally consistent with never having
  tested 8.3. Say "8.3-clean under Rector", not "supports 8.3".

**The trap worth remembering: a Rector run can raise the language floor as a side effect.**
That is exactly where the first-class callables came from, and neither `php -l` nor PHPStan
catches it — both run at the *target* version, not the floor. Prefer constructs at or below
8.1 in new code. Concretely: use **static properties, not constants, in a trait** (trait
constants are 8.2+); `console/Demo/BusinessHours.php` and `ShortTitle.php` do this on purpose.

## Upgrade-gap discipline (the most expensive rule to forget)

A fix only reaches real users if it ships in a form that runs against their **existing**
database and files. Classify every fix before you call it done:

| Kind | Reaches | Gap? |
|---|---|---|
| **CODE fix** — PHP logic in a `.php` file | every install, on deploy | none |
| **DATA fix** — an `*Install.php` default, a one-off `UPDATE`, a changed `data/` file | fresh installs **and your dev DB only** | **yes** |

**A data fix must also ship a patch** (`modules/<M>/patches/<YYYYMMDD>_<name>.php`), which
runs on existing instances through `runpatches.php`/`update.php`. This is the single most
common way a "fixed" bug regresses on upgrade — it has happened here: a clipboard-pattern fix
applied to `ContactsInstall.php` plus the dev DB worked locally and broke on a real install,
and had to be re-shipped as a patch.

Two refinements learned the hard way:

- **Patches are identified by filepath, not content.** Editing an already-applied patch file
  is a silent no-op. Ship a new file instead.
- **Judgment, not reflex.** A cosmetic, low-stakes, pre-release data fix does not always earn
  a permanent patch file. Weigh the stakes; state the decision either way.
- **Look for a `*_default_*` table.** Anything copied from a template table into a per-user
  row on first use (Dashboard applets, admin defaults) is decoupled from the template the
  moment the copy happens — fixing the template alone reaches nobody who already has a copy.

To find gaps mechanically rather than by clicking: build a clean fresh install on an empty DB,
run the full upgrade on a copy of a real install, and diff the two — schema first, then seed
and config data (`recordbrowser_*` field defs, `commondata` arrays, access rules, default
settings). Every difference is a missing patch.

## Why the old code looks the way it does

These are the shapes you will actually meet in 16-year-old modules. All were fixed in Core;
they recur in `modules/Premium/` and `modules/Custom/`, which no migration tool ever swept
because both trees are gitignored and invisible to Grep, PHPStan and Rector alike.

- **Callbacks are strings or `array($obj, 'method')`, never Closures.** Epesi serializes a
  callback (md5 of path + method) and replays it on a later AJAX request; a Closure cannot be
  serialized. Rector's first-class-callable rule converted `array($this,'m')` → `$this->m(...)`
  and broke this, so `create_callback_name()`/`set_callback()` in `include/module.php` now
  decompose a Closure back into `array($obj, 'method')` via Reflection. Anything that
  inspects, stores or validates a callback *by structure* has this hazard.
- **Arithmetic on a non-numeric string is a `TypeError`** where PHP 7 silently converted. This
  fataled record view through a compiled Smarty template — a template bug that surfaces with a
  compiled-file path, not a source path.
- **Named arguments through `call_user_func_array()`** need `array_values()`; PHP 8 treats
  string keys as named args.
- **Functions removed in 8.0 hide in cold paths** — `get_magic_quotes_runtime()`,
  `create_function()`, `each()`. They fatal only when the path is hit, so runtime testing
  misses them. Grep, don't click.
- **`set_error_handler()` callbacks lost their 5th `$errcontext` argument.** A handler that
  still declares it as required fatals — which took out the patch system itself once.
- **PHP 4-style constructors and relative `require_once` of PEAR classes** appear in custom
  QuickForm field types. There is no PSR-4 autoload for Epesi's own classes, so an explicit
  `require_once` of the class file is the convention — just not a *relative* one that can
  load a stale copy off `include_path`.
- **`ModuleCommon::Instance()`** was broken by PHP 8's static-variable change; several
  unrelated-looking bugs traced back to that one root cause.

## Deliberate decisions that look like unfinished work

- **Smarty 2 is vendored and patched in place** (`modules/Base/Theme/smarty/`), not upgraded.
  Smarty 4/5 removes `{php}` blocks, which templates here still use, and Epesi has its own
  integration layer built on Smarty 2 internals (`ThemeCommon`, `display_smarty`, compiler
  modifications). Replacing the engine is a separate project, deliberately bundled with the
  template redesign that has to touch the same files anyway. The engine's remaining
  `strftime()` deprecations are notices on 8.2, not fatals.
- **`vendor/` is committed** so a deployment needs no composer run. That is why dev tooling
  lives in its own `tools/` composer project — see [load-bearing-oddities.md](load-bearing-oddities.md).
- **Symfony is capped at `^7.4`.** Symfony 8.x requires PHP 8.4.1; raising the floor that far
  is its own decision, not a side effect of clearing a dependency bump.

## Working on a module the migration never reached

`.claude/skills/fix-old-epesi-module/SKILL.md` automates the sweep. By hand, the checklist is:
removed 8.0 functions, PHP 4 constructors, `each()`/`create_function()`, relative QuickForm
requires, error-handler arity, callback structure — then check
[deliberate-removals.md](deliberate-removals.md) for features the module may still call into
that no longer exist.
