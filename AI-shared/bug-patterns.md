# Bug patterns worth recognizing if they recur

> **Status:** REFERENCE - the root-cause *shapes* behind bugs already fixed here, condensed
> into rules. Skim the group matching what you are touching before assuming a fresh bug —
> most of these were originally diagnosed as something else. Full write-ups (symptom,
> investigation, fix, dates) are archived at `AI-private/archive/bug-patterns.md`.

## RecordBrowser and the data layer

**`update_record()` merges old values into partial edits.** `Utils_RecordBrowserCommon::update_record()`
fills in the existing stored value for every field the caller omitted, *before* invoking the
recordset's processing callback. Harmless for ordinary fields; poison for any field whose
callback reads "present and non-empty" as "the user just typed this" — a secret field
re-encrypting merged-in ciphertext corrupts it permanently. Grid inline-edit goes through the
same path. **Rule:** never infer "the user touched this" from the field's own content in
`edit`/`editing` mode. Add a virtual hidden marker field in the `QFfield_callback` (never a
real column, so the merge can't reconstruct it) and gate on the marker.

**Raw DB record vs. form submission — same `$values`, different shape.** A processing callback
gets the raw stored record in `display`/`view` mode (real columns only) and a `$_POST`-shaped
array in `add`/`edit`/`adding`/`editing` (including virtual and checkbox-only keys). A
checkbox-shaped key checked in display mode is simply never set. **Rule:** find the real
persisted column the form key is derived from, and branch on that.

**`get_contact_by_user_id()` returns the whole record, not an id.** It reads like an id lookup
and returns `CRM_ContactsCommon::get_contact($cid)` or `null`. Wrapping it where an int was
expected propagates silently into stored fields. **Rule:** for any helper whose return shape
you are inferring from its name, read the body first; take `['id']` if you need the scalar.

**Comparing "did this change" across two sources.** A `get_record()` value and a
`$form->exportValues()` value do not agree on int-vs-string for numeric-backed columns, so
`!==` reports every save as a change and pollutes edit history. **Rule:** normalize types (or
compare as strings) in any changed-field comparison.

**A multi-leaf module needs `caption()` per leaf.** A second menu leaf that assigns its
RecordBrowser child to a local variable instead of the same property `caption()` reads gets a
silently blank title bar while the rest of the screen renders fine.

**An addon `*_related` admin grid only lists rows added through itself** — not addon wiring
another module did directly in its own `Install.php`. An empty grid is not proof the wiring is
missing.

**A Watchdog type-label callback is also called generically, with no `$rid`.** Indexing a
record in that call fatals. Guard for the no-record case.

**`Utils_GenericBrowser` fed by a raw `DB::GetAll()` + loop never gets a pager**, however much
data is behind it — the pager comes from the query builder, not the row set.

**A record's identity field linking to itself on its own View page.** The shared primitives
(`create_linked_label`/`create_linked_text`/`create_linked_label_r`) are self-link-aware for
free; a hand-rolled tooltip span needs an explicit `is_self_view($tab, $id)` guard.

**A status field's "quick shortcut" click must prompt unconditionally.** Branching on the
current value so that one value silently mutates and reloads, while others prompt, is the bug —
four modules copied it from each other.

## Forms (QuickForm) and fields

**`setDefaults()` must run before `addElement()` for a `static` element**, which pulls its
value at add time. Getting this backwards blanked a whole "Add new record" screen.

**A `QFfield_callback` that adds a *second* `$form->addElement()` renders nothing.** One
callback, one element.

**Setting a `datepicker` value from JS needs the regional format, not ISO.**

**A required `commondata`/`select` field used to offer an empty `'---'` choice** that is
guaranteed to fail that same field's `required` rule. Don't reintroduce the empty option on a
required select.

**"Restore Defaults" must resolve the default through the admin-override chain**
(`Base_User_SettingsCommon::get_admin()`), not from the field's own hardcoded literal — the
two diverge as soon as an administrator configures anything.

**`strtotime()` reads any slash-separated numeric date as m/d/y**, whatever the app locale.
`reg2time()` swaps the capture groups for `%d/%m/%Y`-shaped formats before handing off; any new
day-before-month slash format needs the same treatment.

## JavaScript and client state

**`eval_js_once()` means once per session, not once per render.** Two separate bugs came from
this: a script inside `Base_Box`'s own shell template assumed the shell renders once (it
doesn't), and any script whose target element is re-rendered needs `eval_js()` plus an
idempotency marker property on the element instead.

**`load_js()`/`load_css()` are per-session, not per-file-version.** Editing an already-loaded
JS/CSS file shows nothing until a fresh tab or login. Its "already sent" flag is also set
before the response is actually flushed, so anything discarding queued output
(`Epesi::clean()`) must release those session flags first.

**A `jQuery(document).on('e:...')` handler runs for every trigger app-wide**, including ones
fired by unrelated modules — `Epesi.href()` and form submits fire `e:submit_form`/`e:loading`/
`e:load` globally. An uncaught exception in such a handler silently eats the click that
triggered it, with no error and no network activity; invisible on mobile without a deliberate
`window.onerror`. When a button "does nothing", suspect its own handler chain before the server.

**`Libs_LeightboxCommon::display($id, ...)` with a bare module class name as `$id`** collides
with that module's own DOM ids and breaks its unrelated JS. Namespace the id.

**`Utils_PopupCalendar` has no viewport awareness** — `clonePosition()` never had any — and its
popup wrapper is 1px wide on purpose, so measure `popup.firstElementChild`, never `popup`.
Other `clonePosition()` consumers share the gap and were left alone deliberately.

**One shared timeout tuned for one caller.** `Base_StatusBar`'s auto-dismiss fired the same way
for a background confirmation and an error demanding attention; a mail connect timeout suited a
cron send but not an interactive button. Before reusing a shared timer constant, check whether
the interactive call sites want their own value.

## CSS and theming

**A hardcoded pixel `height` on any rule that includes `<select>` is suspect.** Native
`<select>` chrome ignores CSS sizing the way `<input>`/`<textarea>` don't, and a headless
browser renders it more forgivingly than a real one — verify with a real screenshot at actual
size, not computed bounding boxes. The same widget's percentage `width` is unreliable inside a
CSS multi-column container; check `display` before suspecting specificity.

**`position: absolute` with no `top`/`bottom` in the flex layouts.** The table→flex rewrite
changed what "static position" resolves to, in both axes. Prefer stretching via
`top`/`bottom`/`left`/`right` plus flex centering over hardcoded dimensions.

**`box-sizing` is not agreed between `<select>`/`<button>` and `<input>`/`<textarea>`** in
Chrome's UA defaults. A shared rule setting `width`/`padding` across mixed control tags needs an
explicit `box-sizing`.

**Specificity ties are broken by source order, and the light-mode override block is appended
last.** `theme_adminltedark`'s `[data-bs-theme="light"]` layer is generated by a script that
knows nothing about the per-variant colour rules it may clobber, so an exact tie always goes to
the override. `!important` on the component's own base rule is the fix here, not selector
reshuffling. Narrowing a selector inside that override layer also silently drops the id-weight
its `:is()` was carrying.

**A popup pinned to fixed-light chrome needs `color` *and* `color-scheme: light`** on every
native `<select>`/`<textarea>`/`<input>` it contains — a light `background-color` alone leaves
white-on-white text under dark mode.

**A component-identity class fixed per container is the wrong scope.** Any container can write
its own conflicting rule for `input[type=checkbox]`/`<select>`; when a second module hits the
same bug, escalate to `!important` on the component's own base rule rather than adding another
container-specific patch.

**`theme/` and `theme_adminltedark/` do not cascade.** If both copies of a file exist, a fix to
one is *not* inherited by the other — the resolver picks one file. Port explicitly, and re-check
specificity live rather than assuming a verbatim copy behaves the same.

**A hardcoded field-name-suffix selector** (e.g. matching `[h]` on a `timestamp` sub-field) will
silently misbehave for whichever locale/format branch you didn't test.

## Templates (Smarty)

**A raw `<script>` block in a `.tpl` without `{literal}` fatals every request** — Smarty 2 reads
the JS braces as its own delimiters.

**A template variable a PHP callback assigns *conditionally* needs every read site guarded.**
Under `REPORT_ALL_ERRORS` the first notice blanks the whole module's output, so an unguarded
`{if $var.key}` on a legitimately-absent key is a blank screen, not a warning. A variable
without an `isset()` guard sitting next to siblings that have one is a strong tell — including
for a variable nothing assigns any more.

## Caching and queries

**A cache written but never read**, because the "skip cache" argument defaults to skip. Check
the default before concluding a cache is warm.

**Batching a per-row query changes every integer column's PHP type.** Bound and unbound ADOdb
queries type results differently, so a `!==`/`===` downstream of the batched version breaks
where the per-row version worked.

**Runtime cache and scratch writes belong in `TEMP_DIR`, not `DATA_DIR`** — several call sites
defaulted the wrong way.

**A raw `require_once` of a `Common` file bypasses the module loader's guard** and can take the
whole app down. Go through the module system.

**Caches added for performance are request-scoped by discipline.** Anything cross-request needs
documented invalidation — see [performance-profiling.md](performance-profiling.md).

## Module system, Setup and admin

**`ModuleInstall::simple_setup()` declared non-static drops the module from the Setup screen
entirely** — no error, it just isn't there.

**A local module's Simple Setup package icon needs two opt-ins**, not just a themed asset file.

## Diagnosis traps

**"It looks different in another browser" is usually two different accounts** — or the same
account's settings changed between observations. Check the account's own settings (Quick
Access, user settings) before touching CSS.

**A fixed bug "coming back" in one window but not incognito is stale client state**, not a
regression. Related: a date field that keeps refilling itself across a hard reload and a brand
new tab is browser autofill.

**A plausible-looking negative result — hard refresh, cache clear, restart, no change — often
means you fixed a different code path than the one being looked at.** The same data can reach
the screen through more than one independent rendering path (sync vs. ajax, applet vs. list
row). Grep for every path before declaring done.

**`modules/Premium/` is invisible to every tool here** (gitignored, so Grep, PHPStan and Rector
all skip it). Old-syntax bugs there surface only at runtime; use plain `grep` via Bash when a
sweep must include it.

**Legacy-format access-rule crits with non-string keys** are a 7.4→8.2 migration artifact, not a
fresh bug.

**A RecordBrowser addon-tab `<func>_access()` gate is called with two different arities in the
same render** — a required second parameter fatals on the second call. Give it a default.
