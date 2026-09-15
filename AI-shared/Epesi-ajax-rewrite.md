# Rewriting the process.php DOM-patch push onto ajax.php's Request/Response model

A plan, not a decision — this documents what the rewrite would actually touch and a phased
path through it. Nobody has committed to doing this yet; read this before starting, and
update it as the real shape of the work becomes clearer than what's guessed here.

## Why this is hard, in one sentence

`process.php` is not just "an old endpoint" — it *is* the module system's render loop
(`Epesi::process()` builds the whole module tree, diffs it against session state, and turns
the diff into a wire format), while `ajax.php` is a narrow one-callback-in, one-`Response`-out
dispatcher with no tree, no diffing, and no history. Making the tree-render engine speak
`ajax.php`'s contract means separating "what changed" (engine logic, mostly transport-agnostic
already) from "a string of JS that does `eval()`-able DOM surgery" (the wire format, which is
not), without breaking multi-tab sessions, browser back/forward, or the ~20 other hand-rolled
endpoints that copy `process.php`'s pattern in miniature.

## Current mechanism (the parts that matter for this rewrite)

CLAUDE.md's Architecture section has the summary; this is the level of detail the rewrite
actually has to deal with.

**`process.php`**: validates `X-Client-ID` + a live `$_SESSION['num_of_clients']` bucket,
parses `$_POST['url']` (a query string, e.g. `module_path=...&field=value`) back into
`$_GET`/`$_POST`/`$_REQUEST`, then calls `Epesi::process()`:

1. `ModuleManager::create_root()` builds the module tree for this request and `Epesi::go()`
   walks it, calling each module's `body()` and capturing its HTML output via `ob_start()`
   plus its queued JS via `$module->get_jses()`, keyed by module path
   (`include/epesi.php:259-292`).
2. `location()` can redirect mid-render — if any module called it, `Epesi::process()`
   recurses into itself with the new params (`include/epesi.php:399-413`).
3. Every rendered module's HTML+JS is diffed against
   `$_SESSION['client']['__module_content__'][$path]` (the `REDUCING_TRANSFER` flag,
   `include/epesi.php:455-482`) — only modules whose content actually changed (or whose
   parent reloaded, or a forced `get_reload()`) get sent. This is *the* reason a click deep in
   a page doesn't re-transfer the whole screen.
4. Whatever needs sending is queued through `Epesi::text()` (an `Epesi.text(html, spanId,
   mode)` call — innerHTML replace/prepend/append by DOM id), `Epesi::js()` (arbitrary JS,
   appended to one blob), `Epesi::load_js()`/`load_css()` (dedup'd per-session against
   `$_SESSION['client']['__loaded_jses__']`), all of which land in one `text/javascript`
   response body via `Epesi::get_output()` (`include/epesi.php:79-95`).
5. `History::set()` persists `$_SESSION['client']['__module_vars__']` to the `history` DB
   table under `(session_name, client_id, page_id)`; browser back/forward is `Epesi.history_add()`
   driving `unFocus.History`, which replays a `history` param back through this same endpoint
   (`include/history.php`, `include/epesi.js:176-216`).
6. The client (`include/epesi.js:218-283`) POSTs to `process.php`, gets the JS blob back as
   `dataType: 'text'`, and **`eval()`s it directly** — that's the entire "apply the patch"
   step. There's a `try/catch` around the eval (added after a bug where an uncaught exception
   in one response permanently wedged `Epesi.procOn`), but the mechanism is still "run
   arbitrary code the server sent," not "apply a structured patch."

**`ajax.php`**: reads `$_GET['key']`/`$_GET['cid']`, loads a callback previously registered by
`Module::create_ajax_callback_url()` (`include/module.php:753-765`, keyed into
`$_SESSION['ajax_callbacks']`), calls it with a Symfony `Request` and its stored `$args`, and
requires the return value to be a Symfony `Response`, which it `send()`s as-is. No module
tree, no diffing, no `History`, no `text/javascript` framing. It's used today for things that
need a *real* HTTP response — file downloads, iframe targets — not DOM patching.

**A third, undocumented family sits between them**: ~20 standalone scripts (
`modules/Utils/Tooltip/req.php`, `modules/Utils/RecordBrowser/grid.php`,
`modules/Utils/RecordBrowser/favorites.php`, `modules/Base/Dashboard/update.php`,
`modules/Utils/Calendar/update.php`, etc. — grep `define('JS_OUTPUT',1)` for the full list)
each hand-roll `process.php`'s bootstrap (`CID` from `$_GET`/`$_POST`, `READ_ONLY_SESSION`,
`require_once('include.php')`, `ModuleManager::load_modules()`) and then `print()` either raw
JS or raw HTML directly, bypassing `Epesi::process()`'s tree/diff machinery entirely. They're
closer to `ajax.php` in shape (one callback, no tree) but predate it and don't use its
callback-registration or `Response` contract. **These are a natural first migration wave** —
see Phase 4.

## What must not break

- **Multi-tab session isolation.** `CID` + `$_SESSION['client']` bucketing (`init_js.php`)
  lets multiple tabs of the same login run independent module trees. Any new endpoint has to
  key off the same `CID`, not invent a parallel session model.
- **Browser back/forward.** `History` serializes `$_SESSION['client']['__module_vars__']` to
  the DB per `(session, client, page_id)`. This is orthogonal to the wire format — it just
  needs the new endpoint to keep calling `History::set()`/`History::get_id()` the way
  `Epesi::process()` does now.
- **The `REDUCING_TRANSFER` diff.** Losing this would mean re-sending every visible module's
  full HTML on every click — a real regression, not just a refactor risk.
- **[design-philosophy.md](design-philosophy.md)'s test**: a module author calls
  `Module::body()`/`RecordBrowser` declarations and writes zero HTML/JS/CSS for the common
  case. The rewrite is a transport change *underneath* `Module`, not a new API module authors
  must learn. `Module::body()` output and `Epesi::text()`/`js()`/`load_js()`/`load_css()` as
  the calls a module author reaches for should stay the same call sites even if what happens
  after them changes.
- **`modules/Premium/`** is gitignored, each module its own separately-licensed repo, never
  swept by any migration pass (see CLAUDE.md's Environment quirks and the Prototype-`$`
  bug shape it documents). Any Premium module calling `Epesi::js()`/`text()` directly, or
  assuming `eval()`-of-a-JS-blob semantics via a raw inline `onclick`/`eval_js()` string,
  keeps working unmodified only if those call sites keep meaning the same thing after the
  rewrite — or there is no way to reach and fix every Premium install.
- **`EPESI_ASSET_VERSION`/`check_version.php`** (`include/epesi.js:422-459`) is the existing
  "your JS is stale, reload" nudge for tabs left open across a deploy. A protocol change is
  exactly the case this exists for — reuse it rather than trying to keep two wire formats
  live forever for tabs that never reload.

## Target shape

Keep `Epesi::process()`'s *engine* (tree build, `location()` redirect loop, `REDUCING_TRANSFER`
diff, `History` calls) almost entirely as-is — it doesn't know or care that its output happens
to currently be serialized as a JS string. Change only the boundary: instead of accumulating
into a single `text/javascript` blob via string concatenation, accumulate into a plain
structured value (a `RenderResult` — new, small, no framework dependency) and let the
transport layer decide how to serialize it:

```
RenderResult {
  html: { spanId: string, html: string, mode: 'i'|'p'|'a' }[]   // was Epesi::text()
  js: string[]                                                   // was Epesi::js()
  loadJs: string[]                                                // was Epesi::load_js()
  loadCss: string[]                                               // was Epesi::load_css()
  historyId: int|null
  redirect: string|null
}
```

A `process`-equivalent `ajax.php` callback wraps `Epesi::process()`, catches the
`RenderResult` instead of letting it `print()`, and returns
`new JsonResponse($renderResult)` (or a plain `Response` with a hand-built body, if avoiding
Symfony's serializer for this is preferred) — i.e. `Epesi::process()` becomes callable as one
more `Module::create_ajax_callback_url()`-registered callback, just one with a fixed,
special-cased signature since it isn't a per-module callback.

Client side, `Epesi.request()` (`include/epesi.js:218-283`) stops doing
`dataType: 'text'` + `eval(responseText)` and instead does `dataType: 'json'`, then applies
each field structurally: `html[]` entries call the existing `Epesi.text()` DOM-id-innerHTML
function directly (no `eval` needed for this part — it's already just data), `js[]` entries
are the only thing that still needs `Function(...)`/`eval()`, and `loadJs`/`loadCss` call the
existing loader functions. This is a meaningfully smaller trusted-eval surface than today
(only the `js[]` entries run as code; HTML/load lists never do), which is worth calling out
as a real security improvement, not just a refactor.

## Phased plan

Ordered so each phase is independently shippable and low-risk, and so the risky part (the
actual wire-format switch) happens last, once everything upstream of it has been proven not
to move.

1. **Inventory.** Grep every direct call site of `Epesi::js()`, `Epesi::text()`,
   `Epesi::load_js()`, `Epesi::load_css()`, `Epesi::alert()`, `Epesi::redirect()`, and every
   inline `onclick`/`eval_js()` string across `include/`, `modules/` (Premium included — needs
   plain `grep`/`git grep --no-index`, not the Grep tool, per CLAUDE.md's Environment quirks),
   and note which assume "the whole response is one evaluated JS blob" (e.g. code that emits
   `</script><script>` boundaries, or relies on execution order across `Epesi::js()` calls
   from different modules within one response) versus which are just "queue this JS snippet"
   (the common, portable case). This inventory is the real scope estimate — do this before
   estimating effort for the rest.
2. **Extract `RenderResult` inside `Epesi::process()`**, purely as an internal refactor:
   `Epesi::text()`/`js()`/`load_js()`/`load_css()` keep their exact signatures and keep being
   the calls module authors reach for, but internally populate the structured value instead of
   (or in addition to) the string accumulators. `Epesi::get_output()` becomes "serialize
   `RenderResult` to the existing `text/javascript` blob" — a pure function, easy to unit-test
   by hand against captured real responses. Zero client-visible change; ships alone.
3. **Add the JSON serialization + a `RenderResult`-returning `ajax.php` callback**, gated so
   old and new clients coexist: e.g. a request header or `$_POST` flag the not-yet-updated
   `epesi.js` never sends, so existing tabs keep hitting the string path unaffected while the
   new path is exercised manually / from a dev build of the client. No behavior change for any
   real user yet.
4. **Migrate the standalone `JS_OUTPUT=1` mini-endpoints first** (Tooltip's `req.php`,
   RecordBrowser's `favorites.php`/`grid.php`, Dashboard's `update.php`, etc.) to real
   `Module::create_ajax_callback_url()` registrations returning a `Response`. These don't
   touch the module tree or `REDUCING_TRANSFER` diffing at all, so they're a much smaller,
   representative proof that "an old raw-`print()`-of-JS-or-HTML endpoint" can become a
   Symfony `Response` endpoint without regressing the callers — do this before touching
   `process.php` itself, both to retire real duplication and to de-risk the harder migration.
5. **Switch `epesi.js`'s `Epesi.request()`** to the JSON contract from step 3, applying
   `RenderResult` fields structurally instead of `eval()`-ing the whole body. Ship behind
   whatever mechanism step 3 used to gate it, flip it for real once manual/staged testing
   looks right, then remove the old string path from the server side.
6. **Force-reload stale tabs** via the existing `EPESI_ASSET_VERSION`/`check_version.php`
   nudge (`include/epesi.js:422-459`) rather than keeping the old wire format alive
   indefinitely — a tab that's been open since before this ships needs a real reload anyway
   once the client-side `eval()` path is gone.
7. **Decide `ajax.php`'s own shape last, not first.** Its current contract — "callback takes
   `(Request, args)`, must return a `Response`" — is a fine fit for the new
   `Epesi::process()` wrapper as one more registered callback, so this plan does *not* require
   inventing a second endpoint file. If a fixed, non-callback-keyed URL (`process.php`'s own
   role) turns out to be worth keeping literally for caching/routing reasons, that's a
   thin wrapper around the same `ajax.php` machinery, not a fork of it.

## Non-goals

- Not a rewrite of `Module::body()`, Smarty templates, or any HTML-generation code — this is
  strictly the transport/patch-application layer underneath them.
- Not a change to the `CID`/`$_SESSION['client']` session model, or to how `History` persists
  back/forward state.
- Not a mandate that every module author change anything — the whole point of keeping
  `Epesi::text()`/`js()`/`load_js()`/`load_css()` as the stable call sites is that ordinary
  module code (including Premium) shouldn't need to know this happened.
- Not a replacement for `ajax.php`'s existing narrow use (file downloads, real non-JS
  responses) — that contract already works and this plan reuses it rather than replacing it.

## Open questions

- Does `RenderResult` need to support `Epesi::js()`'s `$del_on_loc` flag (JS that's dropped if
  a `location()` redirect happens mid-response, `include/epesi.php:175-205`) as structured
  data, or is it fine for the redirect-loop logic in `Epesi::process()` to keep resolving that
  before `RenderResult` is finalized? (Leaning: the latter — it's resolved before serialization
  today too, just via array filtering; no format change needed.)
- `Epesi::discard()`'s session-flag rollback on a fatal-error abort
  (`include/epesi.php:103-127`) is written against the current string accumulators
  (`self::$load_jses`/`self::$load_csses`) — needs to move onto whatever replaces them, and
  the abort path (`ErrorHandler::notify_client()`) needs to know how to emit a `RenderResult`-
  shaped error response instead of a bare JS alert string.
- Debug/profiling output (`Profiling::$modules`/`$sql`, the `debug_content` div,
  `include/epesi.php:495-540`) currently rides along as HTML injected via `Epesi::text()` —
  worth keeping as-is (just another `html[]` entry) rather than inventing a separate debug
  channel in the new format.
- Whether `RenderResult` should be a real typed class/DTO or a plain array — a real class
  gets IDE support and makes the "what shape does the wire format have" question
  self-documenting, at the cost of one more file; given PHPStan is already run at level 2 on
  this tree, a typed DTO is more likely to earn its keep than not.
