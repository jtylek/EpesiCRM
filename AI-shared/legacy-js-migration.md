# Front-end JavaScript: what's loaded, and what old code assumes

> **Status:** REFERENCE - the current JS stack and the traps left behind by the Prototype
> removal. The file-by-file migration log (which module, which call, which date) is archived
> at `AI-private/archive/legacy-js-migration.md`.

## What actually loads

- **jQuery 1.11.3** + `jquery-migrate-1.2.1` + `jquery-ui-1.10.1`, hard-coded in `index.php`'s
  `$jses` array — bypassing Epesi's own `load_js()` module-asset system. Bumping this is still
  open.
- **Bootstrap 5 + AdminLTE 4**, loaded conditionally when that theme is active. The theme
  chrome itself is jQuery-free; the legacy stack is the widget layer underneath.
- **Prototype.js and script.aculo.us are gone** (removed 2026-08-06). `jQuery.noConflict()`
  went with them.

## The trap this leaves in old code

**`$` is jQuery's own default binding now, not Prototype's.** Old code written against
Prototype's `$` still parses and still runs — it just does something different:

```js
$('some_id')            // Prototype: the element, or null
                        // jQuery:    a TAG-NAME selector — an empty collection
```

So an `if (!el) return` guard written for Prototype never fires, and the returned empty
jQuery collection has no `.style` / `.value` / `.disabled` / `.innerHTML`. Assignments to
those are **silent no-ops**, not errors — which is exactly why this shape survives unnoticed:
a checkbox that never disables, a panel that never populates.

**Code needing a raw DOM element must use `document.getElementById(id)`** — not `$(id)`, not
`jQuery(id)`.

**`Event.observe` / `Event.fire` now hit the browser's native `Event` constructor and throw.**
Worse, `serve.php` concatenates its file list into one script and executes it as a unit, so a
top-level throw in one file aborts every file after it in the same bundle — producing an
unrelated-looking second error in a module that has nothing to do with the first. Use
`jQuery(document).on(...)` / `.trigger(...)`.

The replacement recipe throughout is mechanical: `Ajax.Request`/`Ajax.Updater` →
`jQuery.ajax()`, `Object.toJSON` → `JSON.stringify()`, `Class.create` → a plain JS class,
`Element.*` → jQuery or vanilla DOM.

**Where this still bites:** `modules/Premium/` and `modules/Custom/` are gitignored, so no
migration sweep ever covered them and no tool here can see them — use plain `grep` via Bash.
It is also easy to reintroduce when porting an old inline `onclick` or an `eval_js()` string,
because a server-built JS string is caught by no linter.

## Conventions for new interactive UI

- **Prefer native attributes and AdminLTE's own JS over hand-rolled listeners.** Bootstrap
  modal autofocus needs the `autofocus` attribute, not a `shown.bs.modal` listener —
  `adminlte.min.js` already runs its own focus-stealing script.
- **Never call native `confirm()` or `alert()`.** Both are replaced app-wide by styled AdminLTE
  modals: `Module::create_confirm_href()` / `window.epesi_confirm()` and `window.epesi_alert()`.
  Both fall back to the real thing automatically off-AdminLTE.
- **`eval_js()` (`include/misc.php`) is a global helper**, callable straight from module PHP —
  not only from a `{php}` block in a template.
- **`eval_js_once()` means once per session, not once per render.** If the target element can
  be re-rendered, use `eval_js()` plus an idempotency marker property on the element.
- **`load_js()`/`load_css()` are per-session, not per-file-version** — editing an
  already-loaded file shows nothing until a fresh tab or login.
- **Never let a `document.observe("e:load", ...)` handler throw** — see
  [adminlte-theme.md](adminlte-theme.md), trap 9.

## The editor is Quill

CKEditor was replaced by Quill (`modules/Libs/Quill/`). Two inert CKEditor wrapper files are
kept on purpose — see [deliberate-removals.md](deliberate-removals.md) and
[ckeditor-to-quill-migration.md](ckeditor-to-quill-migration.md).
