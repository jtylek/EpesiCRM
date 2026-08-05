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

## "Lazy delete" — records are marked deleted, not removed

Noted 2026-08-05, correcting a misdiagnosis: `Apps_Shoutbox`'s History tab
"delete" icon was logged in `bug-patterns.md` as a "doesn't work" bug. It
isn't one — `Shoutbox_0.php::delete_msg()` does exactly what it's supposed to
(`UPDATE apps_shoutbox_messages SET deleted=1 ...`; the action is even
labelled "Mark message as deleted", not "Delete", in the UI itself), and
`Shoutbox_0.php::can_delete_msg()`/the History view hide `deleted=1` rows from
ordinary users while an administrator still sees the full history. Nothing
was ever broken; a genuine DB row disappearing was never the intent.

This is not a one-off Shoutbox quirk - it's how deletion works **throughout
Epesi by design**: `Utils_RecordBrowserCommon::delete_record($tab, $id, $perma
= false)` defaults to `set_active($tab, $id, false)` (flip an active/inactive
flag) rather than a real `DELETE`, with a matching `restore_record()` to flip
it back - a real `DELETE` only happens when a caller explicitly passes `$perma
= true`. Apps_ActivityReport's own "Record Delete/restore" filter (see
`bug-patterns.md`'s `epesi-switch` entry) tracks exactly this state
transition, not permanent removal - further evidence this is the framework's
normal path, not an exception.

**Why**: two concrete benefits, not just caution for its own sake -
1. **No orphaned records.** Other rows that reference a "deleted" one (links,
   history entries, favorites, watchdog subscriptions, foreign-key-shaped
   relations RecordBrowser doesn't enforce at the DB level) keep resolving
   correctly instead of pointing at nothing, because the row is still there.
2. **Better data security.** Nothing a user "deletes" is ever silently
   destroyed - an administrator (or the record's own history) can always
   recover it, and a mistaken or malicious deletion is reversible rather than
   an unrecoverable data-loss event.

**How to apply**: before treating a "delete doesn't work"/"deleted item still
visible somewhere" report as a bug, check whether the table/module already has
a `deleted`/`active`-style flag and a restore path - if so, the visible-to-
admins-only behavior is very likely intentional, not broken, the same way
[[deliberate-removals]]'s Login Audit purge-removal entry documents the
opposite end of the same instinct (some tables, like the login audit log,
don't even get a soft-delete - they're meant to be fully permanent). Confirm
the actual intent before "fixing" what looks like an incomplete delete.

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
