# Epesi's core design philosophy

Recorded 2026-08-03, from Janusz Tylek ("Jasiek"), the framework's creator, explaining
the principle behind Epesi's original design — important context for evaluating any
redesign work, not just a historical note.

## The principle

**Free the developer from writing view logic, templates, CSS, and JavaScript — a
module developer should be able to focus purely on business logic, in PHP.** No HTML,
no CSS, no JavaScript to write or touch, unless a genuinely custom solution is needed.
Epesi should handle the common business-application cases with ease: declare *what*
data looks like, and the framework generates a working UI for it automatically.

This is why `Utils_RecordBrowser` works the way it does (see `Dev-Tutorial.md` §11): a
module declares a table's fields as a plain PHP array and gets a full add/edit/view/
browse/filter/ACL screen for free — zero template code required unless something
bespoke is genuinely needed (a custom `.tpl` via `set_tpl()`, a `display_callback`,
etc.). Those are opt-in escape hatches for the exceptional case, not the normal path a
developer is expected to take.

## How this shaped the original column-splitting design

The record-view field grid's row/column layout (`Utils_RecordBrowser::
view_entry_details()`'s old `$cols` parameter, defaulting to 2, removed 2026-08-03 —
see `adminlte-theme.md`) was deliberately computed *in PHP and the Smarty template*,
not left to the browser or to a CSS author — specifically so a module developer never
had to write a single line of CSS to get a reasonable multi-column layout. Declaring
fields in a PHP array was enough. This wasn't an arbitrary implementation choice; it
was a direct expression of "the developer only touches PHP."

## Where the framework is heading now (2026-08 redesign)

Current direction: dropping table-based layout in favor of flex/CSS Grid throughout,
and making the framework genuinely mobile-device-friendly (see `adminlte-theme.md`'s
history of the AdminLTE rewrite, and the fluid-CSS-columns change that prompted this
note).

**The fluid CSS multi-column replacement for `$cols` is a continuation of this same
principle, not a departure from it.** The developer still never touches CSS to get a
working multi-column record view — the framework still generates the whole result
automatically. What changed is *where* the "how many columns fit" decision gets made:
previously hardcoded in PHP (a fixed number that couldn't actually adapt to different
screen sizes), now delegated to the browser via CSS (`column-width`, letting available
width decide) — a more automatic, less brittle way to keep the same promise, not a
step away from it.

## How to apply

When evaluating any future redesign work (further table→flex/grid conversions, CSS
Grid adoption, responsive/mobile work, anything touching how a module's declared data
gets turned into a screen), the test is:

**Does this keep an ordinary business-logic module developer free of ever having to
write HTML/CSS/JS for the common case?**

Moving computation from PHP to CSS/the browser is *in keeping* with the philosophy as
long as the framework still generates the full result automatically from a plain PHP
declaration. Anything that would require an ordinary module author to hand-write
per-screen CSS or JS "just to make it look right" — rather than that being a genuinely
optional escape hatch for a bespoke case — cuts against the founding principle, and is
worth raising explicitly rather than assuming it's an acceptable cost of modernizing.
