# Epesi's core design philosophy

The founding principle, recorded from Janusz Tylek, the framework's creator. This is the
test to apply to any redesign work — not a historical note.

## The principle

**Free the developer from writing view logic, templates, CSS and JavaScript — a module
developer should be able to focus purely on business logic, in PHP.** No HTML, no CSS, no
JavaScript to write or touch, unless a genuinely custom solution is needed. Epesi handles
the common business-application cases with ease: declare *what* the data looks like, and
the framework generates a working UI for it automatically.

This is why `Utils_RecordBrowser` works the way it does (see
[Dev-Tutorial.md](Dev-Tutorial.md) §11): a module declares a table's fields as a plain PHP
array and gets a full add/edit/view/browse/filter/ACL screen for free — zero template code
required. A custom `.tpl` via `set_tpl()`, a `display_callback` and the rest are **opt-in
escape hatches for the exceptional case**, not the normal path a developer is expected to
take.

The same principle decides where computation lives. Layout that a module author would
otherwise have to hand-write is generated for them — whether that generation happens in
PHP, in a template, or by handing the decision to the browser through CSS. Moving it
between those is a mechanism change, not a change of principle, as long as the framework
still produces the whole result from a plain PHP declaration.

## The test to apply

When evaluating any change to how a module's declared data becomes a screen — layout work,
responsive work, anything touching the generated UI — the question is:

**Does this keep an ordinary business-logic module developer free of ever having to write
HTML, CSS or JS for the common case?**

Anything that would require an ordinary module author to hand-write per-screen CSS or JS
*just to make it look right* — rather than that being a genuinely optional escape hatch —
cuts against the founding principle. That is worth raising explicitly rather than accepting
as the cost of modernizing.

## "Lazy delete" — records are marked deleted, not removed

Deletion throughout Epesi is a flag, not a `DELETE`.
`Utils_RecordBrowserCommon::delete_record($tab, $id, $perma = false)` defaults to
`set_active($tab, $id, false)`, with a matching `restore_record()` to flip it back. A real
`DELETE` happens only when a caller explicitly passes `$perma = true`.

**Why**, in two concrete benefits rather than caution for its own sake:

1. **No orphaned records.** Other rows referencing a "deleted" one — links, history
   entries, favorites, watchdog subscriptions, the foreign-key-shaped relations
   RecordBrowser does not enforce at the DB level — keep resolving correctly instead of
   pointing at nothing, because the row is still there.
2. **Better data security.** Nothing a user "deletes" is silently destroyed. An
   administrator, or the record's own history, can always recover it, so a mistaken or
   malicious deletion is reversible rather than an unrecoverable data-loss event.

Some modules go further and label the action honestly in the UI — "Mark message as
deleted" rather than "Delete" — while hiding flagged rows from ordinary users and leaving
the full history visible to an administrator.

**How to apply this:** before treating a "delete doesn't work" or "the deleted item is
still visible somewhere" report as a bug, check whether the table already has a
`deleted`/`active`-style flag and a restore path. If it does, the visible-to-admins-only
behaviour is very likely intentional. Confirm the actual intent before "fixing" what looks
like an incomplete delete.

The converse also exists: a few tables, such as the login audit log, deliberately have no
soft-delete *and* no purge — they are meant to be permanent. See
[dont-reintroduce.md](dont-reintroduce.md).
