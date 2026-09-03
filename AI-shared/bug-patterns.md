# Bug patterns

Shapes of mistake that recur here, indexed by **symptom**. Skim the matching entry before
assuming you have found something new — most of these were originally diagnosed as
something else entirely.

**How this file relates to the others.** You read the rest of this folder *before* writing
code; you read this one *after* something misbehaves. A rule you would follow while writing
a callback, a stylesheet or a template lives with that task — in
[recordbrowser-recipes.md](recordbrowser-recipes.md),
[theming-and-frontend.md](theming-and-frontend.md) or
[Dev-Tutorial.md](Dev-Tutorial.md). Where a symptom leads to one of those, this file gives
the symptom and a link, not a second copy of the rule.

---

# Part 1 — the ones that bite everyone

## "I fixed it, and nothing changed"

**A plausible-looking negative result — hard refresh, cache clear, restart, still broken —
usually means you fixed a different code path than the one being looked at.** The same data
reaches the screen through more than one independent rendering path: sync versus ajax,
dashboard applet versus list row, view page versus grid cell. Grep for *every* path that
can produce the output before concluding the fix did not work.

This is the most expensive pattern in this file, because the natural next step — doubting
the fix and rewriting it — takes you further from the answer.

## A screen renders blank, with no error anywhere

Under `REPORT_ALL_ERRORS`, the **first** `E_WARNING`/`E_NOTICE` anywhere in a request blanks
that module's entire rendered output — including from inside a compiled Smarty template,
which routinely trips notices via `{if $var.optional_key}` on a legitimately-absent key.

So a blank module is a *notice*, not a crash. A template variable that a PHP callback
assigns only conditionally needs an `isset()` guard at every read site. A variable sitting
next to siblings that have guards, without one of its own, is a strong tell — including for
a variable nothing assigns any more.

## A button does nothing — no error, no network request

Suspect its own handler chain before the server. A `jQuery(document).on('e:...')` handler
runs for **every** trigger app-wide, including ones fired by unrelated modules —
`Epesi.href()` and form submits fire `e:submit_form`/`e:loading`/`e:load` globally. An
uncaught exception in such a handler silently eats the click that triggered it, with no
console error and no network activity. It is invisible on mobile without a deliberate
`window.onerror`.

## "It looks different in another browser"

Usually **two different accounts**, or the same account whose settings changed between the
two observations. Check the account's own settings — Quick Access, user settings — before
touching any CSS.

Related: a fixed bug that "comes back" in one window but not in incognito is **stale client
state**, not a regression. And a date field that keeps refilling itself across a hard reload
and a brand-new tab is **browser autofill**.

## Your module is missing from Modules Administration & Store

`ModuleInstall::simple_setup()` declared **non-static** drops the module from
Administration → *Modules Administration & Store* entirely. No error, no warning — it
simply is not there.

## Your edit to a JS or CSS file did nothing

`load_js()`/`load_css()` are per-session, not per-file-version. Open a new tab or log in
again. See [theming-and-frontend.md](theming-and-frontend.md).

---

# Part 2 — by subsystem

## Grids and RecordBrowser

**A grid has no pager, however much data is behind it.** It was fed by a raw `DB::GetAll()`
plus a loop — the pager comes from the query builder, not the row set.

**One menu leaf has a blank title bar while the rest of the screen renders fine.** A
multi-leaf module needs `caption()` per leaf. A second leaf that assigns its RecordBrowser
child to a local variable, instead of the same property `caption()` reads, gets a silently
blank title.

**A framework callback is invoked in more than one context than you wrote it for.** Two
instances of the same shape: a Watchdog type-label callback is also called *generically*,
with no `$rid`, so indexing a record in that call fatals; and a RecordBrowser addon tab's
`<func>_access()` gate is called with two different arities in the same render, so a
required second parameter fatals on the second call. Guard the generic case; give the extra
parameter a default.

**A field's value corrupts on save, or a "did the user change this" check misfires.** See
`update_record()`'s merge behaviour and the raw-record-vs-form-submission `$values` shapes
in [recordbrowser-recipes.md](recordbrowser-recipes.md).

**An "Add new record" screen is blank**, or a field renders nothing at all — see the two
QuickForm rules in [recordbrowser-recipes.md](recordbrowser-recipes.md).

**A date is stored a month out.** `strtotime()` reads slash-separated numeric dates as
m/d/y whatever the app locale — see [recordbrowser-recipes.md](recordbrowser-recipes.md).

## Caching and queries

**A cache is written but never read**, because the "skip cache" argument defaults to skip.
Check the default before concluding a cache is warm.

**The whole app is down with a redeclare fatal.** A raw `require_once` of a `Common` file
bypasses the module loader's guard. Go through the module system.

**Query counts changed but the page looks identical** — see
[performance.md](performance.md); batching a per-row query also changes every integer
column's PHP type, which breaks any `===`/`!==` downstream.

## CSS and layout

**A native `<select>` ignores your CSS sizing.** Its chrome does not respond to sizing the
way `<input>`/`<textarea>` do, and a **headless browser renders it far more forgivingly
than a real one** — so verify with a real screenshot at actual size, not with computed
bounding boxes. The same widget's percentage `width` is unreliable inside a CSS
multi-column container; check `display` before suspecting specificity.

**A fix to one theme directory did not take effect.** `theme/` and `theme_adminltedark/` do
not cascade — the resolver picks one file. Port explicitly and re-check specificity live.

**White-on-white text inside a popup.** Leightbox chrome is fixed light regardless of
theme; native controls inside it need `color` *and* `color-scheme: light`. See
[theming-and-frontend.md](theming-and-frontend.md).

## Templates

**A raw `<script>` block in a `.tpl` without `{literal}` fatals every request** — Smarty 2
reads the JS braces as its own delimiters.

## Tooling

**A codebase-wide sweep missed a whole tree.** Gitignored module directories are invisible
to Grep, PHPStan and Rector alike — see
[environment-and-setup.md](environment-and-setup.md). Bugs there surface only at runtime.
