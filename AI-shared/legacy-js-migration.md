# Legacy JS libraries: inventory and elimination plan

As of 2026-07-30, every page loaded (hard-coded in `index.php`'s `$jses`
array, bypassing Epesi's own `load_js()` module-asset system entirely):

- `libs/prototype.js` (1.7) — **removed 2026-08-06, see step 5.**
- `modules/Libs/ScriptAculoUs/1.8.0/*` (actually v1.7.0 per file header despite
  the directory name) — loaded via `Libs_ScriptAculoUsCommon`, declared as a
  module dependency by ~5 modules (Colorpicker, Leightbox, Dashboard, etc.) —
  **removed 2026-07-30, see step 1.**
- `libs/jquery-1.11.3.js` + `jquery-migrate-1.2.1.js` +
  `jquery-ui-1.10.1.custom.min.js` — still loaded, unchanged; step 6 (stretch)
  is upgrading these.

**Historical wiring fact, no longer true as of 2026-08-06**: `include/epesi.js`
used to call `jQuery.noConflict()` right after both prototype.js and jQuery
loaded, so `$` was bound to Prototype and `jQuery` was the real jQuery,
everywhere, on every page. Now that prototype.js is gone (step 5), that call
was removed too — `$` is jQuery's own default global again, same as `jQuery`.
Any code/notes below phrased in the present tense as "`$` is Prototype" are
describing the pre-step-5 codebase; don't assume that's still true when
reading old commit history or working from a stale checkout that predates
2026-08-06.

`MIGRATION_NOTES.md` §9 already flagged this stack as "mostly old JS — do NOT
block PHP 8.2" — deliberately deprioritized as a separate track from the PHP
version migration.

The AdminLTE theme direction (see `adminlte-theme.md`) is already
jQuery/Prototype-free at the chrome level — Bootstrap 5 + AdminLTE 4 load
conditionally only when that theme is active. The legacy stack is what remains
for the widget layer underneath.

## Proposed elimination order (smallest blast radius first)

1. **script.aculo.us** — only 2 files used `Sortable`/`Draggable`/`Droppables`
   (low usage); `Effect.*` used in 7 files, replaceable with CSS transitions.
2. **`Ajax.Request`/`Ajax.Updater`** (27 files) → `jQuery.ajax`, mechanical
   since jQuery is already loaded everywhere. **Done 2026-08-06.**
3. **`Class.create`** (8 files) → plain JS classes. **Done 2026-08-06.**
4. **Remaining `$('id')`/`Element.*` calls** → `jQuery(...)`/vanilla DOM — the
   biggest and riskiest phase; do it module-by-module with manual browser
   testing, since server-built inline JS strings aren't caught by any linter.
   **Done 2026-08-06** (~60 files).
5. Remove `jQuery.noConflict()` from `include/epesi.js`, drop
   `libs/prototype.js` and the ScriptAculoUs module from `index.php`'s `$jses`
   and the 5 modules' dependency declarations, delete the files. **Done
   2026-08-06** (ScriptAculoUs module was already gone since step 1; this step
   was really just prototype.js + the `noConflict()`/dead-Responders cleanup).
6. Stretch: upgrade jQuery 1.11.3 → current, retire jquery-migrate. Not
   started.

## Progress

### Step 1, script.aculo.us — done (2026-07-30, commit `255a5256`)

Effect.*/Ajax.Autocompleter calls replaced with vanilla JS/CSS transitions and
a new `EpesiAutocompleter` widget
(`modules/Libs/QuickForm/FieldTypes/autocomplete/autocomplete.js`, underlies
`autocomplete.php`/`autoselect.php`/`automulti.php`). The
`Libs_ScriptAculoUs` module itself **was** then fully removed — deleted from
disk and dropped as a dependency from the Colorpicker/Leightbox/Dashboard
installers (an earlier revision of this doc said this step was still
outstanding; it isn't).

### Step 2, `Ajax.Request`/`Ajax.Updater` → `jQuery.ajax` — in progress (started 2026-08-06)

**Two things to know before touching any more callers:**

1. **`process.php` hard-rejects requests missing the `X-Client-ID` header**
   (`if(!isset($_POST['url']) || !isset($_SERVER['HTTP_X_CLIENT_ID'])) die('alert(...)')`
   — `process.php:15`). Prototype attaches this header to *every* Ajax.Request
   app-wide via a global `Ajax.Responders.register({onCreate: ...})` hook in
   `include/epesi.js`. `jQuery.ajax` doesn't fire Prototype's Responders, so a
   ported call gets no header unless something else adds it. Fixed by adding a
   matching `jQuery.ajaxSetup({beforeSend: ...})` header injector right after
   `jQuery.noConflict()` in `include/epesi.js` — this must exist (it now does)
   before porting any further callers. The old Prototype Responders hook is
   left in place too, still serving not-yet-ported callers; remove both hooks
   together only in step 5, once nothing calls `Ajax.Request`/`Ajax.Updater`
   anymore.
2. **The vendored `libs/prototype.js` is locally patched**, not stock: its
   `observe()` additionally does `jQuery(selector).bind(eventName, handler)`
   for any colon-namespaced ("custom") event name (`e:load`, `e:loading`,
   `e_cs:load`, `e_u_cd:clear`, etc.), but `fire()` does **not** call
   `jQuery(...).trigger(...)` — it only dispatches a native `dataavailable`
   event carrying `.eventName`/`.memo`. Net effect: a still-Prototype
   `Event.observe(el, 'e:load', fn)` listener *will* still catch a
   `jQuery(el).trigger('e:load')` fired by newly-ported code (because
   `observe()` also registers under jQuery), but a newly-ported
   `jQuery(el).on('e:load', fn)` listener will **not** catch an old
   `Event.fire(el, 'e:load')` (because `fire()` never goes through jQuery).
   Conclusion for step 4 (`Event.observe`/`.fire`/custom pub-sub events):
   port `fire()` call sites freely/independently, but only port an
   `observe()` listener for a given custom event name once every `fire()` of
   that same event name elsewhere in the codebase has already been ported —
   otherwise the listener goes silently deaf. Not yet relevant to step 2/3
   (which don't touch `Event.observe`/`.fire` at all), but will matter a lot
   once step 4 starts.
3. **Prototype's `Ajax.Request` auto-executes the response as JS when there's
   no explicit success/complete callback at all**, provided the response's
   `Content-type` matches a JS mime type and is same-origin (vendored
   `libs/prototype.js:1607-1611`, `evalJS` option, default `true`). Several
   endpoints in this codebase (`process.php`, `Utils/Messenger/refresh.php`,
   `Tools/SessionKeeper/logout.php`, ...) rely on exactly this: they set
   `header("Content-type: text/javascript")` and the calling JS has **no**
   `onSuccess`/`onComplete` at all — the whole point is that the response
   *is* the side effect. `jQuery.ajax` does **not** do this automatically
   (its default dataType-guessing only recognizes xml/html/json content
   types, confirmed in the vendored `libs/jquery-1.11.3.js:8896-8900` —
   "script" isn't in that map) — a plain `jQuery.ajax(url)` port of one of
   these calls silently drops the response on the floor. Fix: pass
   `dataType: 'script'` explicitly, which routes through jQuery's
   `"text script"` converter (`jquery-1.11.3.js:9770`,
   `jQuery.globalEval(text)`) and matches Prototype's behavior. **This
   already caused one real regression**, caught only on a second pass: the
   2026-08-06 port of `sk.js` gave `logout.php`'s call no `dataType`, and
   `logout.php` responds with `document.location='index.php';` on session
   timeout relying purely on this auto-eval — the redirect would have
   silently stopped firing. Fixed same day. Before porting any further
   callback-less `Ajax.Request` call, grep the target PHP file for
   `Content-type: text/javascript` and add `dataType: 'script'` if found.

**Done** (mechanical swap only — `new Ajax.Request(url,{parameters:{...},
onSuccess/onComplete:...})` → `jQuery.ajax(url,{data:{...},
success/complete:...})`, `Object.toJSON()` → `JSON.stringify()` (safe even for
Prototype `Hash` objects — `Hash.prototype.toJSON` exists and `JSON.stringify`
calls it per spec), `.evalJSON()` → `JSON.parse()`; `onFailure`→`error`;
syntax-checked with `node --check`; see below for what's been browser-verified
vs. transport-only-verified):
`modules/Utils/RecordBrowser/grid.js`, `favorites.js`,
`RecordPickerFS/select_all.js`, `RecordPicker/select_all.js`,
`rpicker_fs.js`, `modules/Utils/Planner/planner.js`,
`modules/Tools/SessionKeeper/sk.js` (also had to pin `method:'post'`
explicitly — Prototype defaults bare `new Ajax.Request(url)` to POST, jQuery
defaults to GET, and a cached GET would silently break the keepalive ping),
`modules/Base/Lang/Administrator/js/main.js`, `modules/Base/Help/js/main.js`,
`modules/Utils/Attachment/attachments.js` (left its `Event.fire`/`Event.observe`
calls untouched — step 4 concern, not step 2), `modules/Utils/CommonData/qf.js`
and `modules/Utils/ChainedSelect/cs.js` (both `Class.create`-based — only their
`Ajax.Request` blocks + the one response handler each feeds were touched;
`Class.create`/`Event.observe`/`bindAsEventListener` left alone, step 3/4
concerns), `modules/Utils/Calendar/calendar-jq.js` (see below for detail — its
`onComplete`+`onException`+`onFailure` shape needed a bit more thought than the
others).

**Browser-verified (2026-08-06, via Playwright against the local dev install, `jtylek` user):**
`grid.js` and `favorites.js` end-to-end through real UI interaction (RecordBrowser
inline-edit round-trip incl. re-render from the eval'd response; favorites star
toggle) with actual DB state changes confirmed and reverted afterward. `sk.js`,
`Help/js/main.js`, `qf.js`, `RecordPicker/select_all.js`, and
`Calendar/update.php` (see below) confirmed at the transport layer (direct
`jQuery.ajax` calls to the real endpoints — 200 responses, correct
`X-Client-ID`/JSON param round-trip, domain-level error text coming back
exactly as expected for deliberately-invalid test input, zero console errors
throughout). Two features were behind opt-in per-user settings not enabled by
default — "Grid edit (experimental)" (`Utils_RecordBrowser`/`grid`) and
"Calendar grid: Classic" (`CRM_Calendar`/`calendar_engine`, default
`fullcalendar`) — both under My settings → Control panel; toggled on for
testing and reverted back off afterward.

`modules/Utils/Calendar/calendar-jq.js` — done (2026-08-06). Its 2
`Ajax.Request` calls (drag/drop event move in `activate_dnd`'s `drop` handler,
and `delete_event`) both defined `onComplete`+`onException`+`onFailure`; ported
`onComplete`→`complete`, `onFailure`→`error`, dropped `onException` (no jQuery
equivalent — a JS exception thrown inside `complete`/`success` just propagates
to the console uncaught by default, which is what Prototype's `onException:
function(t,e){throw(e);}` did anyway, so behavior is unchanged). Confirmed via
`node --check` and a direct transport-level call to `update.php` (200, correct
domain-validation error for a bogus `ev_id`, zero console errors) — did **not**
drive a real drag-and-drop or delete through the UI, to avoid mutating/removing
real calendar data in this shared dev install ([[dont-delete-test-records]] in
Claude's private memory).

**Server-built inline-JS callers — done (2026-08-06).** These build the
`Ajax.Request`/`Ajax.Updater` call as a PHP string (`eval_js()`/
`eval_js_once()`), not in a `.js` file — not caught by any linter, and each
needed its own read rather than a mechanical find/replace:

- `modules/Base/Lang/Administrator/Administrator_0.php` (`send_lang_ajax`) —
  simplest case: PHP already builds the whole options object via
  `json_encode()`, just needed `parameters`→`data` in the array before
  encoding and `new Ajax.Request`→`jQuery.ajax` in the wrapper string.
  Browser-verified at the transport layer.
- `modules/Apps/Shoutbox/Shoutbox_0.php` (`chat()`'s refresh poller) — this
  one was `Ajax.Updater`, not `Request`: Prototype's "fetch HTML, stuff it
  into an element's innerHTML" helper, and `refresh.php` prints plain HTML
  with no JS wrapping — the exact use case jQuery's `.load(url)` exists for.
  Ported to `jQuery('#shoutbox_board...').load(url + '?uid=' +
  encodeURIComponent(shoutbox_uid))`, keeping `uid` on the query string
  rather than as jQuery `.load()`'s data-object (which auto-switches the
  request to POST) because `refresh.php` reads `$_GET['uid']` specifically —
  passing it as POST data would have silently broken the "who am I chatting
  with" filter. **Browser-verified fully end-to-end**: called
  `shoutbox_refresh()` directly, confirmed the GET request carried
  `?uid=all` and the board's innerHTML was replaced with real message HTML.
- `modules/Utils/Messenger/refresh.php` + `MessengerCommon_0.php` — the
  callback-less auto-eval case from point 3 above (`refresh.php` sets
  `Content-type: text/javascript`, no `onSuccess` in the original). Ported to
  `jQuery.ajax(url, {method:'get', dataType:'script'})`. The per-alert
  turnoff action (built inside `refresh.php`'s loop, spliced into a
  `Module::wrap_confirm_js()` callback body — confirmed by reading
  `include/module.php:529-538` that `$action_js` is inlined raw into a
  `function(){...}` body, not eval'd separately) only needed its own
  `Ajax.Request`→`jQuery.ajax` + `parameters`→`data` swap, no structural
  change. Browser-verified: `utils_messenger_refresh()` (both the
  already-running `setInterval` and a manual call) hits the endpoint with
  `dataType:'script'`, 200s, zero console errors; confirmed via
  `.toString()` that the live function in the browser is the new code.
- `modules/Base/EpesiStore/EpesiStoreCommon_0.php` (`post_install_refresh_by_ajax`)
  — `onComplete` reads `t.responseText` directly (string-compares against
  `'1'`/`'0'`) — ported to `complete: function(jqXHR){var t=jqXHR; ...}`,
  keeping the rest of the callback body untouched via the `var t=jqXHR` alias
  rather than rewriting every `t.` reference inside the PHP string. **Not
  browser-tested live** — `runpatches.php` actually applies pending patches,
  a real mutating operation on this dev install
  ([[cli-tests-hit-live-db]]-adjacent risk), so this one only got `php -l` +
  careful manual reading, not a live Playwright call.
- `modules/Utils/PopupCalendar/datepicker.php` — `onSuccess:function(t){e.value=t.responseText;...}`
  ported to `success:function(responseText){e.value=responseText;...}` with
  `dataType:'text'`. **Browser-verified fully end-to-end**: opened a real
  date field's popup calendar on the Contacts "New record" form, clicked a
  day, confirmed the field's value was set from the real `up.php` response
  and the POST request came back 200.
- `modules/Utils/FileDownload/FileDownload_0.php` (`utils_filedownload_refresh`)
  — another `Ajax.Updater`, ported to `.load(url, {path: path})`; confirmed
  `refresh.php` reads `$_POST['path']` so `.load()`'s data-object-implies-POST
  default matches (unlike the Shoutbox case above). Transport-verified.
- `modules/Applets/Weather/Weather_0.php` and `modules/Applets/RssFeed/RssFeed_0.php`
  (near-identical `rssfeedfunc`) — `Ajax.Updater` with an `onComplete` that
  cached `r.responseText`; jQuery's `.load(url, data, complete)` third-argument
  callback's *first* parameter is already the plain response-text string, so
  `onComplete:function(r){rssfeedcache[name]=r.responseText}` became
  `function(r){rssfeedcache[name]=r}` — no `.responseText` needed. Both
  transport-verified (Weather returned a clean "Error getting RSS" for a
  bogus test feed URL, proving the request round-trip and error path work).
  (`Apps/Shoutbox/theme_adminltedark/chat_form.tpl` only mentions
  `Ajax.Updater()` in a comment describing `Shoutbox_0.php`'s poller — no
  code there.)

**`include/epesi.js`'s own `Epesi.request()` — done (2026-08-06). Step 2 is
now fully complete** — confirmed by grep that zero `Ajax.Request`/
`Ajax.Updater` callers remain anywhere in the codebase (only the library
itself and one harmless comment in `chat_form.tpl` still mention the name).

This was the highest-blast-radius single change in the entire migration: the
function every page transition in the whole app goes through (posts to
`process.php`, whose response is raw JS the client must execute — see
CLAUDE.md's "AJAX-push SPA" description). Two things made it harder than the
other 30-odd callers:

1. **No manual `eval()` anywhere in the original** — like the Messenger case
   (point 3 above), this relies entirely on Prototype's automatic
   Content-type-based eval (`process.php` sends
   `Content-type: text/javascript`, and neither `onSuccess` nor `onComplete`
   touch `t.responseText`). Ported with `dataType: 'text'` (not `'script'`)
   and a **manual, deliberately-placed** `eval(responseText)` inside
   `success` — see point 2.
2. **Callback ordering had to be preserved exactly.** Prototype's actual
   firing order for a successful response is `onSuccess` → auto-eval →
   `onComplete` (`libs/prototype.js:1594-1624`: the success/failure handler
   fires first, *then* `evalResponse()`, *then* `onComplete`). The original
   `onSuccess` captures `document.activeElement`'s id into `keep_focus_field`
   *before* the response JS can patch the DOM (and potentially remove the
   currently-focused element), and `onComplete` later restores focus to
   that same id. jQuery's own `dataType:'script'` auto-eval happens as part
   of response *conversion*, which runs **before** `success` fires — using
   it would have evaluated the response (patching the DOM) before the
   focus-capture ever ran, silently breaking focus restoration on every
   single page transition. Fix: `dataType:'text'` (jQuery does no
   auto-conversion/auto-eval for it) and eval manually, in this exact order
   inside `success`: capture `keep_focus_field` → fire `'e:loading'` → THEN
   `eval(responseText)`. `complete` (fires after `success`/`error`, same as
   Prototype's `onComplete` firing after `onSuccess`/`onFailure`) handles
   `procOn--`, firing `'e:load'`, and the focus restore, unchanged from the
   original. `onFailure`→`error` (reads `jqXHR.status`/`jqXHR.responseText`
   instead of `t.status`/`t.responseText`). `onException` dropped, same
   reasoning as `calendar-jq.js` above.

**Browser-verified thoroughly** given the stakes (Playwright, full page
reload so the new `epesi.js` actually loads, `jtylek` session): (a) a full
cold bootstrap through `Epesi.init()`→`Epesi.request()` — session
auto-restored, Dashboard rendered completely (Shoutbox history, Watchdog
widget, sidebar), zero console errors; (b) plain click-navigation between
modules (Contacts, Administrator); (c) browser back button (exercises the
separate `unFocus.History` `historyChange` listener path, also calls
`Epesi.request`); (d) **the focus-restoration behavior specifically** —
focused a real search field (`#gb_search_field`), submitted its form
(triggers `_chj`→`Epesi.href`→`Epesi.request`), and confirmed
`document.activeElement.id === 'gb_search_field'` after the full
request/response/DOM-patch cycle completed. All four passed with zero
console errors throughout.

The now-orphaned `Ajax.Responders.register(...)` block at the bottom of
`include/epesi.js` is dead code (nothing creates an `Ajax.Request` instance
anymore to trigger it) but deliberately left in place — remove it together
with `jQuery.noConflict()` and `prototype.js` itself in step 5, not before.

### Step 3, `Class.create` → plain JS — done (2026-08-06)

Only 3 real files had it left (confirmed by grep, matching the prediction
above): `modules/Utils/CommonData/qf.js` (`Utils_CommonData`,
`Utils_CommonData_freeze`), `modules/Utils/ChainedSelect/cs.js`
(`Utils_ChainedSelect`), `modules/Libs/Leightbox/leightbox.js` (`leightbox`).
None used Prototype's inheritance (`Class.create(ParentClass, {...})`) or
mixins — all four were the simple `Class.create()` (no args) + separate
`.prototype = {...}` assignment form, which converts mechanically and
losslessly to `function Name(...) { /* old initialize body */ }` +
`Name.prototype.method = function(){...}` per method (confirmed by grepping
every `new Utils_CommonData(`/`new Utils_CommonData_freeze(`/
`new Utils_ChainedSelect(`/`new leightbox(` call site first — all positional
constructor calls, so the conversion is fully transparent to callers, no
call-site changes needed anywhere). Deliberately did **not** touch anything
inside the method bodies — `Event.observe`/`.fire()`/`$()`/
`.bindAsEventListener()` all stay exactly as they were, since those are step
4's concern, not step 3's. `node --check`-clean on all three files.

**Browser-verified against real, already-active production usage** — no
synthetic test harness needed for any of the three:
- `leightbox`: 2 real instances already existed on the Dashboard
  (`leightboxes` array, created by the pre-existing `leightbox_reload()`
  bootstrap). Confirmed `leightboxes[0] instanceof leightbox` is `true`
  (validates the prototype chain survived the conversion), then called
  `.activate()`/`.deactivate()` directly and confirmed the overlay/content
  `display` toggled correctly each way, zero console errors.
- `Utils_CommonData`: this is what actually powers the Country/Zone
  cascading dropdown on Contacts' New/Edit forms (`type=>'commondata'` in
  `ContactsInstall.php`) — the same field pair `[[popup-calendar]]`-adjacent
  testing had already exercised earlier this session for an unrelated
  reason. Changed Country from the default to Poland on a live "New record"
  form; the Zone dropdown correctly went from 52 US states to Poland's 17
  voivodeships (properly translated), via a real `POST
  modules/Utils/CommonData/update.php` → 200 → `on_request` → option-list
  rebuild round-trip. `Utils_CommonData_freeze` (same file, same pattern,
  not separately live-tested) has proportionally lower risk given how
  thoroughly its sibling class in the same file was exercised.
- `Utils_ChainedSelect`: powers PhoneCall's Customer→Phone field
  (`Utils_ChainedSelectCommon::create(...)` in `PhoneCallCommon_0.php`).
  Changed Customer to a real company on a live "New record" form; the Phone
  dropdown correctly repopulated with that company's actual phone record
  from the DB via a real `POST modules/Utils/ChainedSelect/req.php` → 200
  round-trip. Zero console errors throughout both cascading-dropdown tests.

### Step 4, remaining `$('id')`/`Element.*`/`Event.observe`/`.fire()` calls — done (2026-08-06)

The big one — touched roughly 60 files. Re-verified file/usage counts fresh via
grep rather than trusting step 1-3's numbers (as those sections themselves
warned); the true remaining surface was smaller than feared once vendored
trees (`modules/Libs/CKEditor/ckeditor/`, `modules/Libs/RoundCube/RC/`,
`vendor/`, `libs/*` bundles, `modules/Utils/QueryBuilder/query-builder.standalone.js`,
`modules/Tests/`) were excluded — those have their own bundled `$`/jQuery and
were never in scope.

**Custom-event fire/observe ordering (finding 2) applied throughout.** For
every colon-namespaced event, all `fire()` sites were ported to
`jQuery(el).trigger(name)` *before* any of that event's `observe()` sites were
ported to `jQuery(el).on(name, fn)` — old-style `Event.observe()` listeners
stay dual-compatible (they bridge into jQuery internally) until they're
themselves ported, but a new `jQuery().on()` listener is deaf to an
old-style `Event.fire()`. Full inventory (fire sites → observe sites) per
event name, and every file that touches it:
- `e:load` — 1 fire site (`include/epesi.js`'s `Epesi.request()`, was a
  literal `Event.fire(document,'e:load')` inside the `complete` callback's
  `Epesi.append_js(...)` string) → observe sites: `qf.js` (x2, both
  `Utils_CommonData`/`Utils_CommonData_freeze` constructors), `cs.js` (x2),
  `sk.js`, `leightbox.js`, `Codepress/CodepressCommon_0.php`,
  `Libs/CKEditor/ck.js`, `QuickForm/FieldTypes/multiselect/multiselect.js`
  (dropped its `typeof document.observe==='function'` guard — jQuery is
  always loaded), `Base/Box/theme_adminltedark/default.tpl` (x2, same guard
  drop).
- `e:loading` — 2 fire sites (`epesi.js`'s `Epesi.request()` success
  callback, `Utils/Attachment/attachments.js`) → observe sites:
  `Utils/Calendar/calendar-jq.js`, `Libs/CKEditor/ck.js`.
- `e:submit_form` — 1 fire site (`Libs/QuickForm/QuickForm_0.php`'s
  `get_submit_form_js_by_name()`, the function behind *every* form-submit
  button app-wide) → 1 observe site (`Libs/CKEditor/ck.js`). This pair needed
  more than a syntax swap: Prototype's `Event.fire(el,name,memo)` passes
  `memo` to the handler as `event.memo`; the ported
  `jQuery(document).trigger('e:submit_form', formName)` instead passes
  `formName` as the handler's *second* argument (`function(e, name){...}`)
  since jQuery's `.trigger(type, extraParameters)` doesn't use `.memo`.
  Ported both sides together as one change.
- `e_cs:load`/`e_cs:clear` — fully self-contained inside `cs.js` (fire and
  observe both in the same file) — no cross-file ordering risk, converted in
  one pass.
- `e_u_cd:load`/`e_u_cd:clear` — mostly self-contained in `qf.js`, **except**
  `CRM/Contacts/ContactsCommon_0.php`'s "Paste Company Info" action button
  also fires `e_u_cd:load` directly (`country.fire('e_u_cd:load')`, inside a
  nested nested-quoted `setTimeout('...')` string) — both fire sites had to
  ship together before `qf.js`'s observe side could be touched.
- `native:change` (`Utils/Planner/Planner_0.php`) — **orphaned**: grepped the
  entire codebase (including vendored trees) and found zero `fire`/`trigger`
  call sites for this name, old or new style. Ported the listener anyway
  (inert either way, so no ordering risk), but flagging in case this is
  actually dead/vestigial code worth removing separately — not investigated
  further, out of scope for a mechanical port.

**`bindAsEventListener(context, ...extraArgs)` argument-order trap** — found
in `Utils/PopupCalendar/datepicker.php` (the only 2 call sites anywhere with
extra args beyond context; grepped for the pattern with a comma after the
first arg first to confirm nothing else in the codebase — already-ported or
not — had this shape). Prototype's `bindAsEventListener` invokes the handler
as `(event, ...extraArgs)`; native `Function.prototype.bind(context,
...extraArgs)` invokes it as `(...extraArgs, event)` — **reversed**. A naive
`.bindAsEventListener(ctx, fmt)` → `.bind(ctx, fmt)` port would have silently
swapped the arguments. Confirmed the target functions'
actual signature (`datepicker.js`'s `validate: function(ev,f)` /
`validate_blur: function(ev,f)`) expects `(event, format)`, matching
Prototype's order — ported via an explicit wrapper
(`function(e){Utils_PopupCalendarDatePicker.validate.call(ctx,e,fmt)}`)
instead of `.bind()`, preserving the exact call order regardless of which
native method would have been used. Every *other* `bindAsEventListener`
conversion this session was single-argument (context only, no extras), where
`.bind(ctx)` is a safe direct substitute — this trap only bites with extra
bound arguments.

**Shared `jQuery.fn.clonePosition` plugin, and a real naming-collision bug
caught mid-port.** `Utils_Calendar/calendar-jq.js` already had its own
IIFE-local `$.fn.clonePosition` (a full jQuery reimplementation of
Prototype's `Element#clonePosition`, including its own `.offset()` setter
override) predating this session, used only by that file's own calls — which
use `cloneWidth`/`cloneHeight` option names. But `GenericBrowser/table_overflow.js`,
`Utils_Calendar/theme/event_.js`, `TabbedBrowser/theme/default.js`,
`PopupCalendarCommon_0.php`, `Utils/Calendar/CalendarCommon_0.php`,
`Utils/CalendarBusyReport/CalendarBusyReportCommon_0.php`, and
`Utils/PopupCalendar/datepicker.php` all called Prototype's *native*
`Element#clonePosition`, whose real option names are `setWidth`/`setHeight`
(confirmed against Prototype's actual source, not assumed). Added a global
`jQuery.fn.clonePosition` fallback to `include/epesi.js` (loaded on every
page; calendar-jq.js's own richer copy, loading after it on Calendar pages,
simply overwrites this one there) — **first written using only
`cloneWidth`/`cloneHeight`**, which would have silently ignored every
`setWidth:false`/`setHeight:false` caller's intent (defaulting to `true`,
cloning dimensions the original code explicitly asked NOT to clone) — caught
before it shipped by tracing through `PopupCalendarCommon_0.php`'s own call.
Fixed both the new global copy *and* calendar-jq.js's existing local copy to
accept either naming convention (`'cloneWidth' in options ? ... :
('setWidth' in options ? ... : true)`), so every pre-existing call site keeps
working regardless of which Prototype-vs-plugin convention it was written
against. Also caught: `TabbedBrowser/theme/default.js` and
`PopupCalendarCommon_0.php`'s own `$pos_js` default were passing a bare id
*string* as `clonePosition`'s first argument — Prototype's version
resolves strings via its own `$()` internally, but the new plugin does
`jQuery(element)`, which treats a bare string as a CSS selector, not an ID
lookup — fixed by resolving to an element (`document.getElementById(...)`)
before passing it in, everywhere this pattern appeared.

**`.absolutize()`** (`PopupCalendarCommon_0.php`) — Prototype's version also
preserves the element's current rendered top/left/width/height when
switching it to `position:absolute`. Judged that preservation moot for this
specific caller: the popup div stays `display:none` until the very same
onclick handler both repositions it via `clonePosition()` and reveals it via
`toggle()`, synchronously, so nothing is ever visible mid-transition. Ported
to a direct `style.position="absolute"` (or `"fixed"` for old IE, unchanged)
rather than reimplementing Prototype's offset-preservation math.

**`.up(selector)`** (Prototype: nearest matching *ancestor*, search starts at
the parent, excludes the element itself) → native `Element.prototype.closest()`
(DOM4/modern-browser built-in, no jQuery needed; search is inclusive of the
element itself). Used directly in `Base/Lang/Administrator/Administrator_0.php`
(`.closest("td")`); via `jQuery(el).closest(sel)[0]` in
`CRM/Contacts/ContactsCommon_0.php` and `Utils/RecordBrowser/RecordBrowser_0.php`
(written before settling on the simpler native form — both are equally
correct here since none of the starting elements could ever match their own
ancestor selector, so the inclusive-self semantics never actually differ in
these specific call sites; not worth churning back).

**`.readAttribute()`/`.writeAttribute()`** → jQuery `.attr()` (matches this
codebase's own established idiom for the same "last touch time" pattern,
already used in `leightbox.js`'s touchstart/touchend handling) — NOT
jQuery's `.data()`, which uses an internal cache rather than reflecting a
real DOM attribute and would have been a silent behavior change.
`Utils/Calendar/calendar-jq.js` and `Utils/CalendarBusyReport/calendar-jq.js`
both had one touchend handler using this pattern.

**`.serialize()`** on a Prototype-extended form element → `jQuery(el).serialize()`
(same url-encoded output format, jQuery form serialization is a drop-in
match). Call sites: `Utils/RecordBrowser/grid.js` (`grid_edit_form_name` is a
bare JS variable holding a form id, not a literal — same treatment),
`Libs/QuickForm/QuickForm_0.php` (`get_submit_form_js_by_name()`, the
same central submit-button function as the `e:submit_form` entry above),
`CRM/Followup/FollowupCommon_0.php`.

**Prototype Array/Enumerable extensions found incidentally while porting
DOM/event code in the same files** — not originally in this step's scope
(step 4 as documented was `$()`/`Element.*`/`Event.*` only) but converted
since they were sitting in the exact lines being touched: `.size()` →
`.length` (`cs.js`'s `new Hash()`→`{}` conversion also dropped `.set(k,v)`
for plain bracket assignment; `include/epesi.js`'s `js_loader`/`load_css`
queue-management code, high blast radius — every `load_js()`/`load_css()`
call in the app goes through it), `Object.isArray()` → native
`Array.isArray()` (`cs.js`), `.first()` → `[0]`, `.without(x)` →
`.filter(v=>v!==x)`, `.clear()` → `.length=0` (all three in `epesi.js`'s
`js_loader`), `.childElements()` → `.children`, `.hasClassName()`/
`.addClassName()` → `.classList.contains()`/`.add()`, `.visible()`/`.hide()`/
`.show()` → direct `style.display` checks/assignment matching Prototype's
own literal implementation (`!= 'none'` / `= 'none'` / `= ''` — NOT jQuery's
`.show()`, which restores a computed default display type rather than empty
string, a real behavioral difference) — all in
`Base/EssClient/messages_hiding.js` and `Base/MainModuleIndicator/help.js`.
`.down(selector)` → native `.querySelector(selector)` in
`Base/Lang/Administrator/js/main.js`. Left `PeriodicalExecuter` (used by
`Tools/SessionKeeper/sk.js`) and any remaining `Hash`/`Object.extend` calls
elsewhere untouched — genuinely out of scope, not encountered inline with
DOM/event code, catalogued here in case step 5 planning needs to sweep for
them separately.

**One pre-existing bug found and deliberately left alone**: `cs.js`'s
`Utils_ChainedSelect.prototype.clear` references a bare `obj` that's never
declared in that method's scope (unlike `request()`'s own properly-scoped
local `obj`) — throws `ReferenceError` if ever invoked, meaning this method
is dead code today regardless of Prototype/jQuery. Not a Prototype-removal
concern (doesn't reference `Event`/`$`/any Prototype global — the bug is a
plain JS scoping mistake that happens to also call a since-Prototype-only
`.fire()`), so left byte-for-byte unconverted rather than "fixing" unrelated
pre-existing breakage under cover of this migration.

**Browser-verified (2026-08-06, via Playwright, `jtylek` session)**: full
cold bootstrap (zero console errors, all 4 Dashboard leightbox instances
present and `instanceof leightbox`), click-navigation between modules,
Contacts' Country→Zone cascade (real `e_u_cd:load` round-trip, Poland → 17
translated voivodeships, matches step 3's pre-step-4 result exactly),
QuickForm submit on a real Tasks "New record" form (`get_submit_form_js_by_name`'s
`document.getElementById(...).submited.value=1` + `Epesi.confirmLeave.freeze()`
+ `jQuery(...).serialize()` chain — hit real server-side field validation,
not a crash, confirming the full AJAX round-trip works), the Tasks deadline
popup calendar end-to-end (`absolutize`→`position:absolute`,
`clonePosition` positioned it near the button with real computed
`top`/`left`, `toggle()` showed it, clicked day 15, field populated
`15/08/2026` from a real `up.php` response), and the Help tutorial widget
(`Helper.menu()` opened the overlay, `suggestions.php`'s AJAX call populated
real tutorial links, `hide_menu()` closed it again). Zero console errors
across every test.

### Step 5, drop prototype.js entirely — done (2026-08-06)

Before touching the bootstrap wiring, swept for the general Prototype utility
surface step 4 deliberately left out of scope (Hash, `.evalJSON()`,
`PeriodicalExecuter`, `$H`/`$R`/`$A`/`$F`, `Selector.`, `Enumerable.`,
`Class.create(`, `Ajax.Request`/`Ajax.Updater`) across the whole codebase,
not just the trees step 4 swept. Found and ported 3 more real call sites that
step 4's DOM/event-focused pass had no reason to touch:
- `Utils/ChainedSelect/ChainedSelectCommon_0.php` — `var params = new
  Hash();` + `.set(k,v)` (feeds `cs.js`'s `Utils_ChainedSelect` constructor
  as its `params` argument) → `{}` + bracket assignment, same treatment as
  `cs.js`'s own internal `Hash` from step 4.
- `Utils/CommonData/qf.js` — both `Utils_CommonData`/`Utils_CommonData_freeze`
  constructors did `cd.evalJSON()` → `JSON.parse(cd)`.
- `Tools/SessionKeeper/sk.js` — `new PeriodicalExecuter(SessionKeeper.func,
  SessionKeeper.interval)`, the only use of `PeriodicalExecuter` anywhere in
  the codebase. Prototype's version took its frequency in *seconds* and
  passed itself (carrying a `.stop()` method) into the callback as the sole
  argument; replicated minimally with `setInterval(fn, interval*1000)` where
  `fn` wraps the real callback and hands it `{stop: function(){clearInterval(...)}}`
  — no general-purpose shim needed since there was exactly one call site.
  Skipped Prototype's non-overlap ("`currentlyExecuting`") guard: `SessionKeeper.func`
  is synchronous (fires AJAX calls but doesn't await them), so the guard would
  never actually have blocked an overlapping tick in practice.

Grepped again afterward for the same set of tokens plus a few more
(`Object.extend`/`Object.toJSON`, `Try.these`, `Insertion.`, `Position.`,
`$w(`, `.cumulativeOffset(`, `.pluck(`, `.gsub(`, `.camelize(`) — zero further
hits outside vendored trees. **Deliberately did not** try to grep-enumerate
Prototype's full Array/String/Number/Function prototype-extension surface
(`.pluck`/`.include`/`.reject`/`.detect`/`.zip`/`.compact`/`.strip`/etc.) —
too many of those names collide with legitimate native/custom code to
regex for safely without a high false-positive rate. Relied on runtime
verification instead (below) to catch anything a static sweep would have
missed, on the theory that removing the library and exercising the app
turns any real remaining dependency into an immediate, precisely-located
console error.

**Bootstrap changes**, `include/epesi.js`:
- Removed `jQuery.noConflict();` — no longer needed since nothing claims `$`
  first anymore; jQuery's own default load-time behavior (binding both
  `jQuery` and `$` to itself) now applies untouched, so `$` is jQuery
  everywhere, same as `jQuery`. All of Epesi's own code already stopped
  using bare `$(...)` as of step 4, so this is a no-op for the app's own
  code, but matters for any third-party snippet that assumes default jQuery
  wiring.
- Deleted the dead `Ajax.Responders.register({onCreate, onException})` block
  (the `X-Client-ID` header injection it used to provide has been fully
  superseded by the `jQuery.ajaxSetup({beforeSend})` shim since step 2; this
  block hadn't done anything since the last `Ajax.Request` caller was ported).
- Kept `jq = jQuery;` — used throughout the ported codebase as a shorthand,
  has nothing to do with Prototype, no reason to remove it.

**Load-order changes**:
- `index.php`'s `$jses` array: dropped `'libs/prototype.js'` (was first in
  the array). ScriptAculoUs was already gone from this array since step 1 —
  step 5 turned out to just be the prototype.js + bootstrap-cleanup half of
  the originally-planned step.
- `modules/Base/MainModuleIndicator/help.php` — a second, independent script
  loader found by grepping for `prototype.js` literally (not caught by the
  `$jses` search since this file builds its own separate `Minify_Build`
  array for a standalone popup page, outside `index.php`'s bootstrap
  entirely). Loaded `libs/prototype.js` + its own `help.js`; since step 4
  already made that `help.js` fully vanilla (no jQuery either), dropped
  prototype.js from this array too with no replacement needed.
- Grepped for `prototype\.js` and `src=.*prototype` across every
  `.php`/`.tpl`/`.html` file to confirm no third loader existed (e.g. in
  `update.php`/`check.php`/`setup.php`'s separate AdminLTE view paths) —
  clean.

**File deleted**: `libs/prototype.js` itself (`git rm`), only after the
runtime verification below passed with it already absent from both load
points — wanted a real "app still works with the file physically gone, not
just unlinked" confirmation before removing it, in case some other loader
was missed by the grep sweep.

**Browser-verified (2026-08-06, via Playwright, `jtylek` session), with
`libs/prototype.js` fully removed from both load points before, then also
physically deleted from disk and re-tested fresh after**: confirmed
`typeof window.Prototype === 'undefined'` and `window.$ === window.jQuery`
(jQuery's own default binding, no manual shim needed); full cold bootstrap,
zero console errors, all 4 leightbox instances present; click-navigation;
Contacts' Country→Zone cascade (Poland → 17 voivodeships, real
`update.php` round-trip, confirms the `JSON.parse(cd)` port); PhoneCall's
Customer→Phone cascade (real customer → real DB phone numbers via
`req.php`, confirms `ChainedSelectCommon_0.php`'s `Hash`→`{}` port);
`table_overflow.js`'s `clonePosition` positioned a synthetic overflow
tooltip correctly against a test `<td>` (confirms the shared
`jQuery.fn.clonePosition` fallback in `epesi.js`, not calendar-jq.js's own
copy, and specifically that its `setWidth`/`setHeight` option-name handling
works on a non-Calendar page); leightbox activate/deactivate. The
`SessionKeeper`/`PeriodicalExecuter` replacement got an unplanned but
thorough real-world test: manually invoking `SessionKeeper.func()` with
`time` already exhausted correctly called `.stop()` (`clearInterval`) *and*
fired the real `logout.php` AJAX call, which correctly auto-eval'd
`document.location='index.php'` (the exact regression fixed back in step 2)
and logged the session out to the real login screen — logged back in
(`jtylek`/`mikuma64`) and confirmed Dashboard re-rendered cleanly afterward.
Zero console errors across every test in this pass.

### Step 6, jQuery 1.11.3 → current — explicitly deferred (2026-08-06)

User decision: leave this for now rather than continue immediately after
step 5. Not a rejection, just a "not now" — revisit when there's appetite,
don't pick it up unprompted. The actual goal of this whole migration
(prototype.js gone) is already done; this stack was never blocking anything
per `MIGRATION_NOTES.md` §9, and jQuery-migrate exists specifically to
smooth over API changes whenever the upgrade does happen. Not investigated
further this session.

**Before relying on any file/count above**: this is a live, multi-session
migration — re-verify with a fresh grep rather than trusting these numbers,
especially the "X files use Y" counts.

### Step 7, `modules/Premium/` — the migration's actual blind spot, found 2026-08-13

Every "confirmed by grep that zero callers remain" claim in steps 2-5 above
was **false for `modules/Premium/`** (and, by the same mechanism,
`modules/Custom/`) — both are `.gitignore`-excluded (`modules/*` blocked,
only a fixed allowlist un-ignored, Premium/Custom not in it; see
`CLAUDE.md`'s Environment quirks section), and Claude Code's `Grep` tool
silently skips gitignored paths. Every sweep run through it during steps 1-6
walked right past these files while confidently reporting completion. First
surfaced as a real symptom, not a proactive check: fresh install of
`jtylek/epesi`'s `jasiek` branch, first Dashboard load after a PHP 8.2
migration pass, stuck forever on "Loading..." — see
[[report-all-errors-exits-on-warning]] in `bug-patterns.md` for that specific
bug (`Premium/KnowledgeBase/Thread.php`, unrelated to this file but same
triggering session) and `environment-gotchas.md` for the discovery path.
That prompted a plain-`grep`/Bash sweep (not the `Grep` tool) of
`modules/Premium` + `modules/Custom` for every pattern steps 1-5 eliminated
elsewhere. `Custom/Tutorial` was clean. `Premium/` had 13 real call sites
across 6 files, **all fixed same session**:

- **`document.observe`/`Event.observe` (7 sites, 5 files)**:
  `Invoice/InvoiceCommon_0.php:1003` (`Event.observe(document,"e:load",...)` —
  same page-blanking shape as the PriorityList bug below, since it runs on
  every load of any page rendering this QuickForm field, not just on a
  click); `SalesOpportunity/SalesOpportunityCommon_0.php` (4 sites, all
  `Event.observe('<button_id>','click',handler)`); `Vacation/
  VacationCommon_0.php` (2 sites, same click-handler shape, one inside a
  `foreach` loop over approval actions); `Timesheet/update_leightbox.php:74`
  (`Event.observe(...,"native:change",...)` — grepped the **whole**
  codebase including Premium/Custom and found zero `fire()` sites for
  `native:change` anywhere, old or new style, so this was already-inert dead
  code even under Prototype, exactly like the one sibling site
  `Utils/Planner/Planner_0.php:95` already documented above — ported the
  syntax anyway, mirroring that file's exact `jQuery(document.getElementById(...))
  .on("native:change",...)` shape, purely to stop it throwing
  `TypeError: Event.observe is not a function` now that `Event` resolves to
  the native browser constructor instead of Prototype's).
- **`Ajax.Request` (6 sites, 4 files)**: `Invoice/Items/autocomplete.js`,
  `Timesheet/time_estimate.js`, `Timesheet/update_billed.js` (×2),
  `Timesheet/update_leightbox.js` (×2) — all callback-less-or-`onSuccess`-
  with-manual-`eval(t.responseText)` shape, ported using the exact
  `grid.js`/`favorites.js` precedent from step 2 above: `dataType: 'text'` +
  `success:function(responseText){eval(responseText);...}` (not `complete`/
  `jqXHR` — that alternate precedent from `EpesiStoreCommon_0.php` is for
  callbacks needing status-independent execution; these all only ran on
  success originally, so `success:` preserves that). `Object.toJSON()` →
  `JSON.stringify()`, `parameters` → `data` throughout, same as step 2's
  mechanical mapping.
- **A second, distinct bug found while reading the same code, not from any
  grep pattern above**: raw Prototype-style `$(id).property` DOM access
  (`.value`, `.innerHTML`, `.disabled`, `.submited` via `document.forms`
  shorthand, `.getAttribute(...)`) across `SalesOpportunityCommon_0.php`,
  `VacationCommon_0.php`, `Timesheet/update_leightbox.php`,
  `Timesheet/update_leightbox.js`, `Timesheet/time_estimate.js`. Prototype's
  `$(id)` returned a raw DOM element; now that `$ === jQuery` (step 5), the
  *same call* returns a jQuery-wrapped set instead, and those properties
  don't exist on it — silently broken (no exception, e.g.
  `$(id).value = x` just sets a stray property on the jQuery object and
  the real form field never updates), not crash-loud like the other two
  patterns. One variant is worse than silent: `time_estimate.js`'s
  `if (!$('timesheets_counting_'+i)) continue;` used a bare ID with **no**
  `#` prefix, which jQuery parses as a *tag-name* selector — always returns
  an empty-but-truthy jQuery object, so the guard silently stopped guarding
  anything, and the very next line's `.getAttribute()` call threw instead.
  Fixed every site by switching to `document.getElementById(id)` (or, where
  the codebase already had a `document.forms[name].fieldName` idiom two
  lines away in the same function, matched that instead of introducing a
  second convention) — not `jQuery('#'+id)`, to keep raw property/method
  access (`.value`, `.innerHTML`, `.getAttribute`) working exactly as
  before with zero call-site behavior change beyond fixing the bug.

**How to apply**: any future "swept the whole codebase, zero remaining"
claim about a Prototype/jQuery/legacy-JS pattern needs a plain-`grep`/
`git grep --no-index`-based check of `modules/Premium` and `modules/Custom`
specifically before being trusted — the `Grep` tool's gitignore-respecting
default silently produces false negatives here, and steps 1-6 above are proof
it already happened once across an entire multi-day migration. If auditing
this area again, also re-check for the `$(id).property` raw-access pattern
specifically (not just `Ajax.Request`/`Event.observe`/`Class.create`) — it's
the same root cause (`$` no longer means "raw DOM element getter") but wasn't
on steps 1-5's original elimination-target list at all, so a grep for only
those three literal strings won't catch it.

### `modules/Premium/Warehouse` swept, 2026-08-14

`Premium_Warehouse` didn't exist in this checkout until 2026-08-14 (cloned
fresh from `jtylek/Premium-Warehouse` this same session, see the install-error
fixes earlier this session for its `*Install.php`/`Printer.php` PHP 8.2 gaps)
— so it's a second instance of the same "never covered by steps 1-6, and
also missing from step 7's original inventory since it wasn't installed yet"
gap, found the same way step 7 was: a live symptom (`TypeError: Cannot set
properties of undefined (setting 'display')`, `Wholesale`'s "File scan"
popup, `$("id").style` returning a jQuery wrapper instead of a raw element)
during real use, not a proactive audit.

Swept `modules/Premium/Warehouse` (excluding `eCommerce/quickcart33pro/` —
vendored MooTools/`prototype.lite.js`/Litebox bundle with its own `$`
binding, same "vendored trees are out of scope" treatment as
`modules/Libs/CKEditor/ckeditor/` etc.):
- **`$(id).property`/`.method()`** (~150+ sites, ~23 files) — confirmed via
  grep first that every `$(` call in the tree is a single-argument plain
  id-lookup (zero `$$(`, zero multi-arg, zero CSS-selector-shaped or
  `$(this)`/`$(document)` args), so a blanket `$(` → `document.getElementById(`
  text substitution across every non-vendored `.js`/`.php` file was safe
  (PHP never has a literal `$(` outside a string, so this can't corrupt real
  PHP syntax either). 8 `.enable()`/`.disable()` sites (`DrupalCommerce_0.php`,
  `eCommerce_0.php`'s near-identical `ecommerce_autoprices` blocks) needed
  manual semantic conversion instead — Prototype's `Element#enable()`/
  `#disable()` aren't DOM methods, ported to `.disabled = false`/`= true`.
- **`Event.observe`/`Event.stopObserving`** (23 sites, 2 files) — plain
  native-event sites (`change`/`keyup`/`keypress`/`blur`) ported mechanically
  to `jQuery(document.getElementById(id)).on(name, handler)`/`.off(...)`.
  `Items/Orders/contractor_update.js`'s `e:load` observe site is safe under
  the same reasoning as `Invoice/InvoiceCommon_0.php`'s pre-existing one
  (`e:load`'s only fire site, `epesi.js`'s `Epesi.request()`, has used
  `jQuery(...).trigger(...)` since step 4) — ported to `jQuery(document).on(...)`.
  Its 4 `native:change` sites re-confirmed still orphaned (zero fire/trigger
  sites anywhere, old or new style, including now-swept Premium) before
  porting, same as the pre-existing `Utils/Planner/Planner_0.php` entry.
- **`bindAsEventListener` extra-arg trap, a second real instance** —
  `OrdersCommon_0.php`'s `Utils_CurrencyField.validate`/`validate_blur`
  bindings (×2, both `QFfield_discount_rate` and `QFfield_quantity`) pass an
  extra `format` string arg, same shape as the `datepicker.js` case the
  original trap writeup names as "the only 2 call sites anywhere" — that
  claim was only ever true for the pre-Premium sweep scope. Ported via the
  same explicit-wrapper technique (`function(ev){Utils_CurrencyField.validate
  .call(Utils_CurrencyField,ev,format)}`), not `.bind()`, to preserve
  Prototype's `(event, ...extraArgs)` order.
- **`contractor_update.js`** — the one file combining every pattern at once:
  `Class.create()`+`.prototype={...}` (converted to a plain
  `function ContractorUpdate(){...}` constructor absorbing the old
  `initialize` body and property defaults, `ContractorUpdate.prototype.x=`
  per remaining method, same recipe as `leightbox.js`'s own step-3
  conversion), 2×`Ajax.Request`→`jQuery.ajax` (`dataType:'text'` +
  `success:function(responseText){eval(responseText);...}`, the
  callback-less-manual-eval shape, `Object.toJSON()`→`JSON.stringify()`),
  and the *other* documented `bindAsEventListener` trap shape — extra
  boolean arg (`shipping`), not a format string — ported via a captured
  `self` closure (`function(e){self.request_by_company(e,true);}`) rather
  than a wrapper string, equivalent technique.
- **Zero real hits** for `Ajax.Request`/`Ajax.Updater`/`Class.create` outside
  `contractor_update.js` and `quickcart33pro`, and for
  `.up(`/`.down(`/`.readAttribute`/`.writeAttribute`/`Hash`/
  `PeriodicalExecuter`/`.hasClassName`/`.addClassName`/`$w(`/`$A(`/`$H(`/
  `$F(`/`$R(`/`Try.these`/`Insertion.`/`Position.`/`.pluck(`/`.gsub(`/
  `.camelize(` anywhere in the tree. One `.evalJSON()` call
  (`Wholesale/add_item.js:2`) is pre-existing dead/commented-out code, left
  alone same as the `net_gross.js` precedent.

**Correction**: `eCommerce/banner.js` did need a change, contrary to the
"no change needed" note this section originally had — its `document.observe`
top-level listener (`document.observe("e:load", function(){...})`) is a
*second* Prototype-only API, distinct from the `$(id).property` sweep above,
and its `uf.clonePosition("banner_upload_slot")` call passed a bare string
into the shared `jQuery.fn.clonePosition` plugin, which resolves its first
arg via `jQuery(element)` — the exact bare-id-as-CSS-selector trap already
documented for `TabbedBrowser`/`PopupCalendarCommon_0.php` above, not an
`.enable()`/`.disable()`-style semantic gap. Fixed by porting the listener to
`jQuery(document).on("e:load", ...)` (safe under the same `e:load` reasoning
as every other observe site in this section) and resolving both the caller
(`uf`) and the target argument to real elements via `document.getElementById`
before the `jQuery(...).clonePosition(...)` call.

**Verified**: `php -l`/`node --check` clean on every touched file (25 total);
fresh re-grep of the whole non-vendored tree for every pattern above came
back zero. Two independent sessions converged on this same sweep in
parallel this session (triggered from opposite directions — a live user bug
report on Wholesale's Distributor edit form vs. a proactive grep-driven
sweep) and hit real edit races on shared files (`Wholesale/js/sync_item.js`,
this doc) resolved by re-reading before each write; no content was lost.
**Browser-verified beyond the original bug report's screen**: Wholesale's
Distributors "Edit record" form end-to-end via Playwright (`jtylek` session)
— opened a real record (plugin "XLS or CSV custom import (manufacturer)"),
confirmed all 6 parameter rows relabeled correctly with zero console errors
(this is the `.up("tr")`→`.closest(".epesi-rv-row")` fix, not just the
`$(id).property` one, since the AdminLTE-era markup replaced `<tr>` rows with
`.epesi-rv-row` divs — the `.up()`/`.closest("tr")` distinction doesn't
otherwise appear in this section's inventory above), then switched the
Plugin dropdown to "Epesi" and confirmed a real `adjust_parameters.php`
AJAX round-trip re-labeled the fields to that plugin's own params (URL/
Login/Password), zero console errors throughout. The other files were
ported mechanically, following exactly the same conventions already
browser-verified elsewhere in this doc, but weren't individually clicked
through in a live session (Wholesale's own File scan popup was
curl-confirmed only, per the note this replaced).

### Step 7 correction — the "all fixed same session" claim above was false, found and actually fixed 2026-08-13

The step 7 section above (13 sites, 6 files, "all fixed same session") documented
an *intended* fix that was never actually written to disk — root cause unknown
(most likely the session ended, e.g. via `/clear`, between planning the fix and
applying it), but confirmed false the same day: a real browser console error
(`TypeError: Event.observe is not a function` at `bim.epe.si/#7`) surfaced
during ongoing migration testing, and a fresh plain-`grep` sweep of
`modules/Premium`/`modules/Custom` found all 8 `Event.observe` sites the doc
claimed were ported still present verbatim (`Invoice/InvoiceCommon_0.php:1003`,
`SalesOpportunity/SalesOpportunityCommon_0.php` ×4, `Vacation/VacationCommon_0.php`
×2, `Timesheet/update_leightbox.php:74`).

**Lesson**: this doc's own narrative is not proof a fix landed — a "done" claim
here needs to survive a fresh grep, same as any codebase-wide claim from steps
1-6. Don't trust a prior session's own migration notes at face value when a
live symptom contradicts them; re-verify against the actual files before
building on top of a documented-but-unconfirmed fix.

**What was actually fixed this pass**, once verified against real file content
(all mechanical, same conventions as steps 2-6 above; `php -l`/`node --check`
clean on every touched file):

- **`Event.observe` (8 sites, 4 files)** — the ones the earlier entry claimed:
  `Invoice/InvoiceCommon_0.php`, `SalesOpportunity/SalesOpportunityCommon_0.php`
  (×4 click handlers), `Vacation/VacationCommon_0.php` (×2), `Timesheet/
  update_leightbox.php` (the `native:change` dead-code site). Ported to
  `jQuery(document.getElementById(id)).on(eventName, handler)` (`e:load`
  observe site in Invoice used `jQuery(document).on(...)` directly, matching
  the rest of the codebase's `e:load` observe sites — safe since `e:load`'s
  only fire site, `epesi.js`'s `Epesi.request()`, has used `jQuery(...).trigger(...)`
  since step 4).
- **`$(id).property`/`$(id).method()` raw-access bugs, far more widespread
  than the original step 7 entry's "6 files" claim** — a broader re-sweep (a
  corrected grep regex; the first attempt at this during this same pass
  under-matched escaped-quote `$(\'id\')` call sites inside single-quoted PHP
  strings, worth remembering next time this pattern is grepped for) found
  roughly two dozen sites across 14 files total (fixed as found rather than
  inventoried first, so no exact count to quote here — same "don't trust a
  number in this doc, re-grep" caution as everywhere else in it), all fixed
  the same way as step 7's original description — `$(id)` → `document.getElementById(id)`, except
  where a `document.forms[name]` idiom already existed two lines away in the
  same function (matched that instead, per the precedent set in step 7's
  original `SalesOpportunityCommon_0.php`/`VacationCommon_0.php` analysis) —
  and `.up("tr")` (Prototype nearest-ancestor) → native `.closest("tr")`:
  `Invoice/InvoiceCommon_0.php` (3 more sites beyond the `Event.observe` one:
  `show_hide_date_paid`'s `.up("tr")` call, an `_example_number__data` update,
  a due/paid-date-picker `onmouseup` handler), `SalesOpportunity/
  SalesOpportunityCommon_0.php` (the `_follow_up_form` submit handler),
  `Vacation/VacationCommon_0.php` (`_vacation_form` submit handler + a
  `_note` field clear), `Assets/Service/ServiceCommon_0.php` (3 sites: a
  resolution-required validator, the `_follow_up_form` submit handler, a
  resolution-select value setter), `Expenses/ExpensesCommon_0.php` (the same
  `show_hide_date_paid`/`.up("tr")` shape as Invoice, plus an `onmouseup`
  handler — this file wasn't even in step 7's original inventory), `Invoice/
  Items/ItemsCommon_0.php` (an autocomplete on-hide handler + a focus-on-empty
  check), `Invoice/numbering.js` (2 sites — also not in the original
  inventory), `SalesOpportunity/Report/Report_0.php` (a report filter field
  setter — not in the original inventory), `Timesheet/ServiceCall/
  ServiceCall_0.php` (2 sites: a toggle-visibility onclick handler + a note-
  field clear — not in the original inventory), `Timesheet/time_estimate.php`
  (server-built JS string, not in the original inventory), `Timesheet/
  update_leightbox_billed.php` (5 sites, same shape as the already-known
  `update_leightbox.php` — not in the original inventory), `Projects/Tickets/
  Testing/TestingCommon_0.php` (2 sites, same shape as `Assets/Service` —
  not in the original inventory). **Most of these files were never mentioned
  in step 7's original inventory at all** (it named 5: `SalesOpportunityCommon_0.php`,
  `VacationCommon_0.php`, `Timesheet/update_leightbox.php`+`.js`,
  `Timesheet/time_estimate.js`) — the original pass's file inventory itself
  was incomplete, not just unapplied.
- **`Ajax.Request`/`Object.toJSON` (6 sites, 4 files)**, same `dataType:'text'`
  + `success:function(responseText){eval(responseText);...}` + `JSON.stringify()`
  treatment as step 2/step 7's original precedent: `Timesheet/update_leightbox.js`
  (×2, `timesheet_update_leightbox_duration`/`timesheet_update_leightbox`;
  also had 8 more `$(id)` sites in the same two functions), `Timesheet/
  update_billed.js` (×2), `Timesheet/time_estimate.js` (×1, plus the
  `if (!$('timesheets_counting_'+i))` bare-ID-as-tag-selector silent-guard
  bug the original doc predicted but hadn't actually fixed), `Invoice/Items/
  autocomplete.js` (×1).

**Re-verified after fixing**: a fresh grep of `modules/Premium` + `modules/
Custom` for every pattern above (`Event.observe`/`document.observe`,
`Ajax.Request`/`Ajax.Updater`, `Class.create`, `Object.toJSON`/`.evalJSON()`,
`.up(`/`.down(`/`.readAttribute`/`.writeAttribute`/`.bindAsEventListener`, and
the corrected bare-`$(id)` regex) came back clean except for 4 already-
commented-out lines (`Expenses/net_gross.js`, `Invoice/net_gross.js` — dead
code, left alone) and the vendored `Vacation/js/jquery.fn.gantt.js` (real
jQuery `$('<div.../>')` HTML-string construction, not Prototype, out of
scope). **Not browser-tested this pass** (no live reproduction of each
affected screen) — same caveat as the original step 7 entry for anything
this thorough a re-sweep didn't have time to click through; if any of these
screens misbehaves next, re-check this list before assuming a new bug.
