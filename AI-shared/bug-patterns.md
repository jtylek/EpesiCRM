# Bug patterns worth recognizing if they recur

These are already-fixed bugs, kept here not for their fix (see git history/
commit messages for that) but because their *root-cause shape* is generic
enough to plausibly recur elsewhere in this codebase.

## Raw DB record vs. form submission — same variable, different shape

`RecordBrowser`'s processing-callback convention passes the *same* `$values`
parameter in multiple modes, but its shape differs by mode: `'display'`/
`'view'` modes receive the **raw stored DB record** (real column names only);
`'add'`/`'edit'`/`'adding'`/`'editing'` modes receive a **form submission**
(`$_POST`-shaped, includes virtual/checkbox-only fields with no backing
column). A callback that checks `$values['some_checkbox']` (a form-only key)
while running in display/view mode will find it simply never set —
`isset(...)` is always false there, silently flipping whatever conditional
logic depended on it.

Found in `CRM_MeetingCommon::submit_meeting()`'s `'display'` case: it checked
`$values['timeless']` (the edit-form's checkbox name) to decide whether to
render a time/duration row, but a raw meeting record has no `timeless`
column — the real persisted signal is `duration == -1`, which every *other*
timeless check in that same file already used correctly. Fixed by matching
the existing pattern.

**How to apply**: when a `RecordBrowser`/`GenericBrowser` processing callback
checks a form-checkbox-shaped key directly, check what mode it's meant to run
in — if it can run against a raw record, look for the real persisted column
that key is actually derived from instead.

## `strtotime()` always reads slash dates as m/d/y, regardless of app locale

`Base_RegionalSettingsCommon::reg2time()` fed the regional-formatted date
string straight into PHP's `strtotime()`. For any user configured to
`%d/%m/%Y`, this is wrong: `strtotime()` interprets a slash-separated numeric
date as **month/day/year** no matter what `setlocale()` or app-level format
settings say (a documented PHP quirk, not something app config affects). Day
≤ 12 silently transposed day/month; day > 12 failed outright and callers'
`date('Y-m-d', false)`-style conversions coerced the `false` result to epoch
`1970-01-01`.

This is the single shared parser behind essentially every date-type field in
the app (birth date, meeting/fax dates, any RecordBrowser 'date'/'timestamp'
field) — a regression here is not scoped to one screen. Fixed by detecting a
`%d/%m/%Y`-shaped format and swapping the day/month capture groups before
handing off to `strtotime()`.

**How to apply**: any future date-format addition to
`$date_formats_proto` that puts day before month in a slash-separated pattern
needs the same swap-before-`strtotime()` treatment, or it will reproduce this
exact bug for that new format.

## "Restore Defaults" bypassing the admin-configured-default override chain

`Base_User_Settings`'s form builder used
`Base_User_SettingsCommon::get_admin($module, $name)` (which correctly falls
back through admin-configured → hardcoded literal) for a setting's
*displayed* value, but the *reset* value baked into the "Restore Defaults"
button's JS still came from each field's hardcoded literal directly — so a
regular user's Restore Defaults silently ignored whatever an administrator
had configured via **My settings/Administration → Default user settings**.

**How to apply**: any UI that offers "reset to default" needs to resolve
"default" through the same admin-override chain the rest of the settings
system uses (`get_admin()`), not re-derive it from the field's own
declaration — the two can silently diverge exactly like this.

## A settings/data report that looks like a browser bug usually isn't

An ActionBar showing far more icons than expected, reported as "looks fine in
Firefox, Chrome shows everything," turned out to be **Quick Access settings
data** (`Base_Menu_QuickAccessCommon::user_settings()`, 100% DB-backed per
`user_login_id`, nothing in that render path is browser-conditional) — not a
CSS/rendering difference. Two per-module toggles exist there: "Dashboard"
(confusingly named — actually means "show inline in the ActionBar") and
"Launchpad" (show in the Launchpad popup), both defaulting to on.

**How to apply**: before touching ActionBar/Leightbox/Launchpad CSS or JS for
a "shows too many/wrong icons" report, check that account's
**My settings → Control panel → Quick Access** first — a "different browser
looks different" report for this specific screen is almost always two
different accounts, or the same account's settings changed between
observations (e.g. an accidental Restore Defaults click), not a rendering bug.

## `setDefaults()` must run before `addElement()` for a QuickForm `static` element

Found 2026-08-03 while exercising RecordBrowser's `'file'` field type for the first
time in `modules/Custom/Tutorial/` (see `Dev-Tutorial.md` §11.2) — not specific to that
module; the same bug is latent anywhere the `'file'` type is used, including
`Utils_Attachment`'s own real production usage.

`Utils_FileUpload_Dropzone::add_to_form()` (`modules/Utils/FileUpload/Dropzone.php`)
originally did:
```php
$form->addElement('static', $identifier, $label, $content)->freeze();
$form->setDefaults(array($identifier => $content));
```
`HTML_QuickForm::addElement()` synchronously fires an `updateValue` event on the new
element, which reads whatever default the form *already* has for that field name —
at that point still the record's raw `'file'`-column value (an empty array on a
brand-new record, since no files are attached yet), set earlier in RecordBrowser's
generic per-field default pass. `HTML_QuickForm_static::setValue()` then does
`(string) $text` on that array, emitting `E_WARNING: Array to string conversion` —
which, since `REPORT_ALL_ERRORS` is on for this instance, blanks the whole module's
rendered output (see `CLAUDE.md`'s Error handling section) rather than just logging.
`setDefaults()` a line later, meant to supply the correct HTML string, comes too late —
the bad value was already consumed.

**Fix**: swap the two calls — `setDefaults()` first, so the correct string default is
already in place by the time `addElement()`'s synchronous `updateValue` fires:
```php
$form->setDefaults(array($identifier => $content));
$form->addElement('static', $identifier, $label, $content)->freeze();
```

**How to apply**: this is a general QuickForm trap, not specific to Dropzone — for any
custom element wrapper that both adds a `static` (or other auto-value-pulling) element
*and* wants to control its own default, set the default before adding the element, not
after. If a `'file'`-type field ever produces a blank RecordBrowser screen on "Add new
record" again, suspect this exact class of bug before assuming it's something new.

## Dead Smarty template variable with no `isset()` guard: `$new` in Contact.tpl

Found 2026-08-03, same session as the Dropzone bug above, via the same error-log
monitor — triggered by adding a new Contact in the browser under `adminltedark`.
`modules/CRM/Contacts/Photo/theme_adminlte/Contact.tpl` and its `theme_adminltedark`
copy both had:
```smarty
{foreach item=n from=$new}
    {$n}
{/foreach}
```
with no `{if isset($new)}` guard — unlike every other conditional var in the same
tooltip block right above it (`$subscription_tooltip`, `$fav_tooltip`, `$info_tooltip`,
`$clipboard_tooltip`, `$history_tooltip`, all correctly `{if isset(...)}`-wrapped).
Confirmed via grep (core + the one installed Premium module, `Premium/Projects`) that
**nothing anywhere assigns a `new` template variable** — this is dead/vestigial
markup, not a live feature with a producer that's merely sometimes absent. This is a
concrete instance of the general trap `CLAUDE.md`'s Error Handling section already
warns about: an unset key referenced in a compiled Smarty template throws
`E_WARNING: Undefined array key`, which — since `REPORT_ALL_ERRORS` is on for this
instance — blanks the whole module's rendered output (here: the entire "Add new
Contact" screen), not just a cosmetic gap.

**Fix**: wrapped both copies in `{if isset($new)}...{/if}`, matching the sibling blocks'
existing style exactly.

**How to apply**: when auditing a custom RecordBrowser `.tpl` (or any Smarty template)
for this class of bug, a variable referenced without `isset()`/`{if isset(...)}` next to
several siblings that *do* have the guard is a strong tell — check whether anything
in the codebase (core **and** Premium, since Premium is gitignored and invisible to the
Grep tool) actually ever assigns it before assuming the guard is unnecessary.

## `.form_error`'s `position: absolute` had no `top`, so it escaped its field row

Found 2026-08-03, same session as the two bugs above, while exercising required-field
validation in `modules/Custom/Tutorial/` under `adminltedark` — but not specific to
that module or theme; the rule is byte-identical (and equally missing `top`) in both
`modules/Utils/RecordBrowser/theme_adminlte/View_entry.css` and its `adminltedark`
copy, so any required RecordBrowser field under either theme was affected.

The error badge itself is generated in core, not a theme file:
`include/TCMSArray.php`'s array-form renderer emits
`<span class="form_error" id="...">...</span>` right after each field's control
(`error` key in the array `EpesiSmartyRenderer`/`TCMSArray` builds per element).
`View_entry.css` styles it:
```css
.Utils_RecordBrowser__View_entry .form_error:not(:empty) {
	position: absolute;
	right: 0px;
	height: 15px;
	...
	/* no top/bottom set at all */
}
```
`position: absolute` with no `top` falls back to the browser's "static position"
algorithm — workable enough under the original `<table>`-cell layout, but unreliable
once the row became a flex container (`.epesi-rv-row { display: flex }`, part of the
adminlte(dark) nested-table→flexbox rewrite — see `adminlte-theme.md`). Symptom:
the red "Field required" badge rendered detached from its own field, overlapping
unrelated content (observed: hanging up near the tab strip above).

**Fix, part 1**: pin it explicitly — added `top: 0;` (anchors to the top-right corner
of `.data`, which already has `position: relative`) to both theme copies of the rule.

**Fix, part 2** (found immediately after, same badge): `max-width: 50%` sized the
badge relative to `.data`'s own width - fine for a genuinely variable-length message,
but "Field required" (`__('Field required')`, `modules/Libs/QuickForm/QuickForm_0.php`)
is always short and fixed-length. On a wide field, 50% of the container is still wide,
and since the badge is anchored by `right: 0` with no `left`, it grows **leftward**
from the right edge to fill that width - overhanging neighboring content instead of
sitting as a compact tag. Changed to a fixed `max-width: 200px` in both theme copies,
which keeps it compact regardless of the row's actual width.

**Fix, part 3** (found immediately after that, same badge again - reported as "not
rendered full height"): the rule also had a hardcoded `height: 15px`, sized for the
original `<table>` layout's shorter rows. Under the flex rewrite, `.data` rows are
taller (fields fill the row via `height: 100%`), so the 15px badge only covered a
sliver at the top of the row instead of the row's real height. Fixed by replacing
`height: 15px` with `top: 0; bottom: 0` (stretches the badge to fill `.data`'s full
height, since it's already `position: absolute` inside `.data`'s `position: relative`)
and switching `display: block` + fixed vertical padding to `display: flex; align-items:
center` (so the text stays vertically centered at whatever height the row ends up
being, instead of being pinned near the top with a small fixed padding).

**How to apply**: any `position: absolute` rule in this theme pair that doesn't set
`top`/`bottom` is suspect — the flex rewrite changed what "static position" resolves
to versus the original table layout it replaced, in both width AND height. A hardcoded
pixel `height` (or a percentage `max-width` paired with `right:0`-anchoring) on an
element inside a row whose height/width is no longer fixed table-cell geometry is the
recurring shape of this whole bug: prefer stretching via `top/bottom`/`left/right` plus
flex-centering over hardcoded dimensions, for anything inside these flex rows.

## `<select>` percentage width unreliable inside a CSS multi-column container

Found 2026-08-03, same session as the fluid-columns redesign (see
`adminlte-theme.md`) - reported live on a real `company` record (which, like
any table with no custom `tpl`, renders through the generic `View_entry.tpl`):
plain text fields (Company Name, Address 1, City, ...) filled their `.data`
cell's full width correctly; `<select>`-based fields (a short commondata list
like Permission) visibly did not, leaving a gap between the dropdown and the
cell's right edge. Two other selects on the same screen (Country, Zone) looked
fine - but only because their option text ("United States of America") is
long enough to make the select's own intrinsic content width fill most of the
cell anyway, coincidentally masking the same underlying bug rather than being
genuinely unaffected by it.

**Root cause**: `<select>` defaults to `display: inline-block` in the browser's
UA stylesheet. Its percentage-width resolution against an ancestor is
unreliable specifically inside a CSS multi-column container (`.epesi-rv-fluid`,
introduced by the fluid-columns redesign) in at least some browsers - a quirk
that doesn't affect text `<input>`/`<textarea>`, which is exactly why only
dropdown fields showed a visible gap.

**Fix**: added `display: block;` to the shared `.data select` rule in all
three themes. An inline-block element already at `width:100%` renders
identically to a block one outside a multicol context, so this has no visible
effect on the six other per-table templates that don't use `.epesi-rv-fluid`
at all - it only changes behavior where the quirk was actually possible.

**How to apply**: if a form control other than plain text/textarea (a custom
widget, a future field type) ever looks mis-sized specifically inside
`.epesi-rv-fluid`, but not in the six untouched per-table templates, suspect
this same inline-block-in-multicol class of quirk before assuming it's a
field-definition or specificity problem - check the element's default
`display` value first.

**Same investigation, a second symptom**: after the width fix, select-holding
rows (Permission, Country, Zone) were still visibly *shorter* than input rows
(Phone, Fax, Address 1) on the same screen - a different root cause, not a
leftover of the first bug. Each `.epesi-rv-row` is its own independent flex
container (unlike the old `<table>` layout, where every row shared one table
and so one row height); with nothing enforcing a floor, a row's height is
whatever its own content naturally is, and `<select>`'s native rendering (OS
dropdown chrome) tends to compute a few pixels shorter than an `<input>`'s
even at identical CSS padding/font-size - not something either theme's CSS
directly controls.

First attempt - a shared `.epesi-rv-row { min-height: 28px }` plus an explicit
`line-height: 1.5` on the combined input/select/textarea rule - **did not
fully fix it**, confirmed live. The real cause turned out to be simpler and
entirely self-inflicted: the file already had a *second*, more specific
`.data select { ... }` rule further down, re-declaring `height: 100%` (left
over from before this session's multicol work). Same selector specificity as
the combined rule above it, but later in source order - so it silently won
every time and undid whatever height the combined rule declared, including
the `min-height`/`line-height` attempt.

**Actual fix**: replaced the combined rule's `height: 100%` with an explicit
`height: 32px` (percentage height was the ambiguity in the first place - an
explicit pixel value is unambiguous for a form control regardless of the
parent's own height), and **removed** the conflicting `height: 100%` from the
later, more specific `.data select` rule entirely rather than trying to keep
two competing declarations in sync. The legacy theme had the same conflict in
a different shape (`height:14px` on the combined rule vs. an explicit
`height:18px; padding:0` on `.data select` alone) - resolved the same way, by
deleting the select-specific override so it inherits the combined rule's
values instead of fighting them.

**How to apply**: before declaring `height`/`width`/any property on a
type-specific rule (`.data select`) that a broader combined rule (`.data
input, .data select, .data textarea`) already sets, grep for the type-specific
selector first - if the property is already provided by the combined rule, a
later, same-specificity re-declaration is redundant at best and silently wins
by source order at worst. This is the same shape as the `.form_error` bug two
entries up (a later rule quietly winning) - check for a duplicate/competing
declaration before assuming a value you set isn't being applied due to something
more exotic.

## Meeting day-card widget: `$event_info` unset for timeless meetings, guarded inconsistently

Found 2026-08-04 via the PHP error-log monitor, triggered by viewing an all-day
"Company Holiday" meeting. `CRM_MeetingCommon::submit_meeting()`'s `'display'` case
(`MeetingCommon_0.php:460-461`) only assigns `$ret['event_info']` when
`$values['duration'] != -1` — i.e. it's simply **not set** for a timeless meeting.
That's correct and intentional (see the "Raw DB record vs. form submission" and
timeless-meeting entries in this same file/history), but both view templates
(`CRM/Meeting/theme/default.tpl` and `theme_adminltedark/default.tpl`) read
`$event_info.start_date`/`.end_date` in **four** places to decide the day-card's
multi-day colspan/weekday/day/month layout — added later, during the grid/multi-day
rework in commit `bb6e0df6` ("Fix Dashboard column layout, Meetings date box...") —
and only the two pre-existing time/duration rows kept an `{if isset($event_info)}`
guard; the four newer checks didn't. Viewing any timeless meeting threw `E_WARNING`
at all four spots (`Undefined array key "event_info"` / `access array offset on
value of type null`), which — `REPORT_ALL_ERRORS` being on for this instance —
blanks that module's rendered output, not just logs quietly.

**Fix**: added `isset($event_info) &&` to all four `{if $event_info.start_date !=
$event_info.end_date}` checks in both theme templates, matching the guard style
already used by the neighboring time/duration rows.

**How to apply**: this widget has broken from this same root cause three times now
(see the timeless-meeting bugs earlier in this file/session history) — any future
edit to either theme's `CRM/Meeting/default.tpl`, or to `MeetingCommon_0.php`'s
`'display'` case, should grep **every** `$event_info` usage in both template copies
and confirm each is `isset`-guarded, rather than adding one more guarded read next
to existing unguarded ones. More generally: a Smarty template variable that a PHP
callback assigns conditionally needs every one of its read sites guarded, not just
the ones added in the same pass as the conditional assignment.

## Legacy theme add/edit form fields: wrapped, misaligned, clipped, then overflowing — four stacked bugs, one investigation

Found 2026-08-04, all in the legacy default theme, all on `modules/Utils/RecordBrowser/
theme/View_entry.css`'s add/edit form rendering, surfaced one at a time as each fix
revealed the next: "Date and Time field doesn't align on one line" → (after fixing
that) "aligned but hours are cut off on top, minutes cut off on bottom" → (after
fixing that) "select boxes don't fill the entire height making it invisible to the
user... only multiselect renders fine" → (after fixing that) "fields are a little bit
too wide - see Title field extending onto the Date label." Four separate,
independently-necessary root causes; fixing #1 and #2 alone still left every plain
`<select>` in the theme (Permission, Priority, Status, Recurring Event, ...) rendering
with blank space under
a top-pinned option label, not just the timestamp widget's hour/minute pair.

**Bug 1**: `modules/Utils/RecordBrowser/theme/View_entry.css` (legacy theme) was
missing a `.data .timestamp select { width: auto; height: auto; }` override that
`theme_adminltedark/View_entry.css` already had — without it, the generic `.data
select { width: 99% }` rule forces each hour/minute `<select>` onto its own
full-width line. Ported the adminltedark rule over; fixed the wrapping.

**Bug 2** (the "cut off" follow-up, not scoped to either theme — lives in the shared,
globally-loaded `modules/Libs/QuickForm/theme/default.css`): its universal
time-picker rule only lists `select[name$="[h]"], [i], [a]`. `HTML_QuickForm_date`
(`vendor/openpsa/quickform/lib/HTML/QuickForm/date.php:296-362`) builds each
select's field-name key directly from the PHP date-format character in use —
`'h'` for 12-hour format (paired with an `[a]` am/pm select), `'H'` (capital) for
24-hour format, with no am/pm select at all. CSS attribute selectors are
case-sensitive, so on any account running in 24-hour mode
(`Base_RegionalSettingsCommon::time_12h()` false), the hour select's name ends in
`[H]`, silently fails to match, and falls back to the un-sized/un-`vertical-align`d
default — producing a several-pixel vertical offset from the minute select right
next to it (which does match `[i]`), which reads as "hour clipped at top, minute
clipped at bottom" at this theme's compact 14px row height.

**Fix**: added `select[name$="[H]"]` to that selector list alongside the existing
lowercase `[h]`/`[i]`/`[a]`.

**How to apply**: this file is loaded globally (not per-theme), so the fix covers
every theme at once — but any *other* CSS in this codebase that targets
`HTML_QuickForm_timestamp`'s sub-fields by name suffix should be grepped for the
same lowercase-only `[h]` assumption, since it will silently misbehave for any
account/install using 24-hour time. More generally: a hardcoded field-name-suffix
CSS selector next to a form element whose field names are generated from
locale/format config is a recurring trap — check every format branch the
generating code can take, not just the one you happened to test with.

**Bug 3** (the broader "invisible select" follow-up — not timestamp-specific at all):
`View_entry.css`'s shared `.data input/select/textarea` rule hardcoded `height: 14px`
on all three, with a comment explaining the intent — force `<input>` and `<select>` to
render at an identical height, since browsers don't apply their default line-height
the same way to each. That reasoning holds for `<input>`, which has no OS-drawn chrome
and can be squeezed to whatever pixel height CSS declares. It does not hold for
`<select>`: real (non-headless) browsers won't actually shrink a native select's
rendered box down to 14px — the *declared* box stayed 14px but the option text
rendered pinned to the top of a visually taller box, leaving blank space below. Every
plain `<select>` in the legacy theme's add/edit forms had this to some degree; it read
as "invisible" because the visible sliver of text at the top is easy to miss,
especially on an unfocused field. `theme_adminltedark/View_entry.css` never had this
problem because its equivalent combined rule uses an explicit `height: 32px` instead
of fighting the native minimum.

**Fix**: dropped `height: 14px` from the combined rule entirely (for `<input>`,
`<select>`, and `<textarea>` alike) — `line-height: 1.5` plus the existing `padding:
3px 5px` now define a natural, browser-respected height for all three instead of a
value real browsers silently override for one of them. Removed the then-redundant
`height: auto` from Bug 1's `.timestamp select` override (still keeps `width: auto` —
that part is still needed) since the general rule no longer forces a height to
override in the first place.

**How to apply**: a hardcoded pixel `height` on a rule that includes `<select>` is
inherently suspect in this codebase - see also the "`<select>` percentage width
unreliable..." entry above, a different symptom (width, not height) of the same
general class of bug: native `<select>` chrome does not obey CSS sizing the way
`<input>`/`<textarea>` do, and headless/automated browser checks can fail to catch it
since headless rendering is sometimes more compliant than a real browser's native
widget rendering. Prefer `height: auto` (or an explicit value generous enough to clear
the OS-chrome minimum, as adminltedark's 32px does) over a small fixed height on any
rule touching `<select>` — verify with a real browser screenshot at actual size, not
just computed-style bounding boxes, since a headless engine may render the same CSS
more forgivingly than what the user actually sees.

**Bug 4** (found immediately after fixing Bug 3, same combined rule): with `height:
14px` gone, the rule's other pre-existing issue became visible - it never declared
`box-sizing`, so each element type fell back to its own browser UA default. Chrome
defaults `<select>`/`<button>` to `box-sizing: border-box` but `<input>`/`<textarea>`
to `content-box`. At `width: 99%` with `padding: 3px 5px`, a `content-box` element
adds that padding *on top of* the 99%, overflowing its `.data` cell by (padding − 1%
of the cell's width) - measured ~5.6px on the Title field. `<select>` never showed
this since border-box already keeps padding inside the declared width. Reported as
"fields are a little too wide - Title field extends onto the Date label."
Mathematically this overflow existed before Bug 3's fix too (box-sizing math is
independent of height), but a few px of horizontal bleed under a 14px-tall,
mostly-empty-looking field was easy to miss next to the two more obvious bugs.

**Fix**: added `box-sizing: border-box` to the same combined rule.

**How to apply**: whenever a shared rule sets `width`/`padding` across multiple
different form-control tags without an explicit `box-sizing`, don't assume browser
UA defaults agree between them - `<select>`/`<button>` and `<input>`/`<textarea>`
diverge in Chrome specifically. Check for this any time a "field is slightly too
wide/narrow" report follows a `<select>` field looking fine right next to an
`<input>` field that doesn't, in the same shared-style row.

## Base_StatusBar: one shared timeout/fade for every message severity, and every caller

Found 2026-08-04/05, reported as "I got an error [testing Mail server settings] and
the alert quickly self-disappears; it should stay and let me close it" followed by
"[Mail server settings'] Test button - I am trying to test e-mail sending - got
Loading... only."

**Bug 1**: `modules/Base/StatusBar/js/main.js`'s `Epesi.updateIndicator()` scheduled
`setTimeout(statusbar_fade, 5000)` unconditionally for every message -
`Base_StatusBarCommon::message()`'s `$type` ('normal'/'warning'/'error') only affects
the injected div's CSS class (color/background), never whether it auto-dismisses. A
"Settings saved" toast disappearing after 5s is fine; an error message a user needs
to actually read (and the existing "click anywhere to dismiss" affordance, which the
5s timer raced against and always won) getting yanked away just as fast is not.
**Fix**: skip the `setTimeout` specifically when the injected HTML contains `message
error` (`statusbar_message_t.indexOf('message error')`), leaving errors up until
manually dismissed while normal/warning messages keep the original 5s auto-fade.

**Bug 2** (surfaced once Bug 1 let the user actually watch a slow Test attempt
instead of it vanishing): `Base_MailCommon::send()` - used both for real mail sending
*and* by `Base_Mail::test_mail_config()` (the admin "Test" button) - never overrode
PHPMailer's `$Timeout` (`modules/Base/Mail/class.smtp.php:144`, default 300s/5min).
An unreachable host:port doesn't fail fast; `stream_socket_client()` just blocks
until that timeout, so the UI sat on "Loading..." for up to 5 minutes with zero
feedback. Root cause of the *specific* report was the configured port (25) being
filtered outbound rather than actively refused - see
`environment-gotchas.md`'s "Outbound SMTP port 25 is blocked" entry - but the missing
timeout is the real bug: even a fast-refusing bad config would still block the UI far
longer than an interactive "Test" click should ever wait.
**Fix**: added an optional `$timeout` param to `Base_MailCommon::send()` (`null` =
unchanged 300s default, so cron/app-triggered real mail sends keep their existing
generous allowance for a slow-but-working server) and pass `timeout: 10` from
`test_mail_config()` specifically, since that call is interactive and should fail
fast on a bad host/port instead of hanging.

**How to apply**: both bugs are the same shape - **a single shared mechanism
(a fixed auto-dismiss timer; a fixed connect timeout) tuned for one calling context
gets silently inherited by a different context with different needs** (a background
confirmation vs. an error demanding attention; a background/cron send that can
tolerate a slow server vs. an interactive button that should fail fast). Before
reusing a shared timeout/timer constant, check whether every call site actually wants
the same value, or whether the "interactive/user-is-waiting" call sites need their
own override.

## `epesi-switch` checkboxes and `timestamp` fields losing CSS fights inside RecordBrowser's `.data` cell

Found 2026-08-05, two related bugs in the same session: CRM_Meeting's "Timeless"
switch rendered as a near-invisible sliver instead of a pill toggle, and
CRM_PhoneCall's "Date and Time" field (a `timestamp`-typed field) had its
hour/date/minute sub-elements stacked vertically instead of on one line - both
under `adminltedark` only.

**Bug 1 (epesi-switch)**: `Utils_RecordBrowserCommon::QFfield_checkbox()`'s
default renderer tags every plain RecordBrowser checkbox field with class
`epesi-switch` (see the "Render checkboxes as on/off switches app-wide" commit),
whose own look comes from `Libs/QuickForm/theme_adminltedark/default.css`'s
`input.epesi-switch[type="checkbox"]` rule. But `Utils/RecordBrowser/
theme_adminltedark/View_entry.css` independently has `.data input:not([type=
button])`/`.data input[type="checkbox"]{width:auto}` rules (written for
ordinary fields, not switches) with **equal-or-higher specificity**, so they
silently win and squash the switch's width/height/background/border back down.
`Utils_Attachment` had already hit this exact conflict for its own Sticky/
Crypted fields and "fixed" it with `#crypted.epesi-switch, #sticky.epesi-switch`
**id selectors** duplicated in its own `theme_adminltedark/View_entry.tpl` - a
per-field patch that only covered those two ids and did nothing for any other
module's switch (CRM_Meeting's Timeless included).

**First fix attempt**: moved the override into `View_entry.css` itself, scoped
to the **class** `epesi-switch` instead of specific field ids
(`.Utils_RecordBrowser__View_entry .data input.epesi-switch[type="checkbox"]`,
plus `:checked`/`:focus`/light-theme variants) - fixed every RecordBrowser
checkbox tagged `epesi-switch` **inside a RecordBrowser view/edit field**, and
let the now-redundant per-id block in Attachment's own template be deleted.

**Still not the actual permanent fix**: the very same session, `Apps_
ActivityReport`'s filter-form checkboxes ("New record"/"Record edit"/"Record
Delete-restore"/"Files", also tagged `epesi-switch`) showed the identical
squashed-to-a-dot symptom - but this time from `Apps_ActivityReport`'s own
`theme_adminltedark/default.css` (`.epesi-ar-check input[type="checkbox"]
{width:1.05rem;height:1.05rem}`), a completely unrelated module/container
that isn't RecordBrowser's `View_entry` at all, so the `View_entry.css` fix
above didn't reach it. Three unrelated modules hitting the exact same shape
(Attachment, Meeting, ActivityReport) is the tell that per-container patches
are the wrong level to fix this at - **any** module can add its own ordinary
`input[type=checkbox]` sizing rule with no idea a specific instance carries
`epesi-switch`, and each one is a fresh chance to tie or beat the switch
component's own specificity by accident.

**Actual fix**: added `!important` to every core visual property (width,
height, appearance, background-color/image/position/repeat/size, border,
border-radius) on the base `input.epesi-switch[type="checkbox"]` rule itself,
in `Libs/QuickForm/theme_adminltedark/default.css` - plus its `:checked` and
light-theme variants. A checkbox opting into `epesi-switch` is declaring
"always render as a switch regardless of surrounding context," which is
exactly the case `!important` exists for (same reasoning already used
elsewhere in this file for beating Bootstrap's own `.form-control:focus`).
This made the `View_entry.css` class-scoped fix above almost entirely
redundant - trimmed it back down to just the `:focus`/`:focus-visible`
border-radius fight (`View_entry.css`'s own `.data input:focus{border-radius:
0 !important}` still needs a matching `!important` to lose, since only
`!important` beats `!important` regardless of specificity) and removed the
now-dead width/height/background/border declarations from both the dark and
light-mode copies.

## RecordBrowser edit history logging a field as "changed" on every save, even when untouched

Found 2026-08-07 on a `contact` record's "Company Name" field (`crm_company`
type, backed by an `int(11)` column) - Changes History/Record historical view
showed a `company_name` row on *every* edit of the record, old value and new
value both rendering as the same company, even when the edit only touched
unrelated fields (Home Phone, Country/Zone, Title, Group, ...).

**Root cause**: `Utils_RecordBrowserCommon::update_record()`'s diff detection
(the generic, non-multiselect/non-file branch) compared the DB-fetched record
value against the freshly submitted form value with strict `===`. Confirmed
via a debug script that `get_record()` returns this field as a native PHP
`int` (`int(2)`) for its `int(11)` column, while `$form->exportValues()`
always yields `string` (`"2"`) - `2 === "2"` is `false`, so the field reads as
"changed" unconditionally, regardless of whether the selection actually
changed. Plain-text (`varchar`) fields never showed this because both sides
are already strings there; only int-backed fields (recordpicker-style
`select`/`crm_company`/`crm_contact` link fields, `type=>'integer'` fields
like `login`) are exposed to it, and only when they're *not* falsy on the
"stayed the same" round trip already caught elsewhere (checkbox fields are
already explicitly normalized to `0`/`1` a few lines above this branch).

**Fix**: cast both sides to `(string)` before comparing
(`RecordBrowserCommon_0.php`, `update_record()`) rather than relying on the
DB driver and form layer to agree on a PHP type.

**How to apply**: any future "did this field actually change" comparison
between a `get_record()`-shaped raw value and a `$form->exportValues()`-shaped
submitted value should compare as strings (or otherwise normalize types)
rather than using `===`/`!==` directly - the two sources aren't guaranteed to
agree on int-vs-string for numeric-backed columns.

**Bug 2 (timestamp fields)**: `Libs_QuickForm`'s legacy `theme/default.css` has
a documented "§26" fix (flex layout on `.data.timestamp > div`, `order:-1` to
put the date first, `select[name$="[h/H/i/a]"]{display:inline-block;width:
auto}` for the hour/minute/am-pm selects) - but `Base_ThemeResolver::resolve()`
(`modules/Base/Theme/resolver.php`) picks exactly **one** file per module+
filename, no cascading. `Libs/QuickForm/theme_adminltedark/default.css`
already existed (for `#multiselect`/autocomplete/epesi-switch), so it fully
shadowed `theme/default.css` for this module under adminltedark - the §26 fix
never reached this theme at all. Every `'type'=>'timestamp'` RecordBrowser
field was affected (CRM_PhoneCall Date and Time, CRM_Tasks Deadline, CRM_Mail
First/Last Date, Utils_Attachment Edited on, CRM_Roundcube thread dates) -
CRM_Meeting's own Date looked fine only because it's built from two separate
`'date'`+`'time'` fields and never goes through this component at all, which
made the bug look Meeting-specific/already-solved when it wasn't.

**A verbatim port of the §26 CSS into the adminltedark file still wasn't
enough** - `View_entry.css` has rules that happen to match this same markup
with equal-or-higher specificity than the legacy fix's plain selectors:
`.data select{display:block}` (2 classes+1 type) beats a bare `select[name$=
...]` (1 type+1 attribute); `.timestamp>div>div{float:right}`/`:last-child{
margin-right:130px}` tie **exactly** with a bare `.data.timestamp>div>div`
(both 2 classes+matching types). Had to prefix every selector in the ported
block with an explicit `div` type selector (`div.data.timestamp > div`, `div.
data.timestamp select[name$="[h]"]`, etc) - CSS specificity compares column-
by-column (classes before types), so adding one type selector without adding a
class breaks an exact tie in the port's favor without needing `!important`.

**How to apply**: (1) if a module's `theme_adminltedark/<file>` already exists
and its `theme/<file>` counterpart later gets a fix, the resolver will **not**
carry that fix over - grep both files for the touched selectors whenever
either theme's copy of a shared CSS/JS file changes, and port to the other
side explicitly. (2) When porting CSS between the two theme's files, verify
against `View_entry.css`'s (or whichever shared ancestor file's) actual
specificity for the same elements live in a browser (`getComputedStyle`), not
just by reasoning about the selectors - a verbatim copy can silently lose a
specificity fight the original never had to fight, because the two themes'
"generic field" rules aren't specified identically. (3) A per-id or per-
container CSS patch for one field's `epesi-switch`/similar shared-class
styling issue is a sign the fix belongs in the shared component's own file
instead - and if it's a class meant to render identically **regardless of
which of dozens of unrelated modules' own CSS happens to also target the same
bare tag/attribute** (`input[type=checkbox]`, `<select>`, etc), scoping the
fix to that class in a container-specific file (RecordBrowser's `View_entry.
css`) still isn't enough - every *other* container can independently write
its own conflicting rule with no idea the class exists, as ActivityReport did
here after Meeting. For a component-identity class like this, `!important` on
the component's own base rule (not spread across every container that happens
to embed it) is the actual permanent fix, not a workaround to avoid. A second
module hitting the same-shaped bug is confirmation to escalate the fix's
scope, not to write one more container-specific patch.

## `Utils_PopupCalendar` rendering off-screen: `clonePosition()` has no viewport awareness, and the popup wrapper can't be measured directly

Found 2026-08-07 on Tasks' "Deadline" date field (`Utils/PopupCalendar/
datepicker.php`) - clicking a date field near the right edge of a narrow
container (a two-column AdminLTE edit form) opened the calendar mostly
off-screen to the right, forcing horizontal page scroll to see it.

**Root cause, part 1**: `PopupCalendarCommon_0.php::create_href()` positions
the popup purely by cloning the trigger element's own position
(`jQuery(popup).clonePosition(triggerEl, {offsetTop: triggerEl.offsetHeight})`
- see `include/epesi.js`'s `jQuery.fn.clonePosition`, a faithful port of
Prototype's `Element#clonePosition`). Prototype's original never did viewport
clamping either, so this isn't a migration regression - just a pre-existing
gap in a widget nobody had opened this close to a viewport edge before.

**Root cause, part 2 (the part that makes a simple clamp non-trivial)**: the
popup wrapper `create_href()` renders is deliberately `style="width:1px"`
(`#datepicker_<name>_calendar`), and both themes' actual visible chrome is a
**child** of that wrapper with its own explicit width -
`theme_adminltedark/default.tpl`'s `.utils-popupcalendar-card` (240px) or the
classic theme's `theme/default.tpl` `.layer` div (220px, plus a `margin-left:
-70px`). A block child with an explicit width isn't width-clipped by a
too-narrow parent - it just overflows the parent's box - so the parent
(`popup`) itself still measures `offsetWidth`/`getBoundingClientRect().width`
≈ 1px. Any overflow-detection code that measures `popup` directly (the
obvious first instinct) silently never fires, because the number it's
comparing against isn't the real rendered size.

**Fix**: added `Utils_PopupCalendar.clampToViewport(popup)` (duplicated in
both `js/main2.js` and `theme_adminltedark/main2.js`, matching this file's
existing "identical API, different markup" convention) that measures
`popup.firstElementChild`'s `getBoundingClientRect()` - the actual card/layer,
not the wrapper - and if its right edge is past `window.innerWidth`, shifts
`popup`'s own `left` (which the child inherits its position from, being
normal in-flow content) leftward by the overflow amount. Wired into
`create_href()`'s shared `onClick` handler, after `toggle()`, guarded by
`jQuery(popup).is(':visible')` so it only runs when the click just *opened*
the popup, not when it closed one.

**How to apply**: (1) any future geometry check on this widget must measure
`popup.firstElementChild`, never `popup` itself - the 1px wrapper width is
deliberate (unclear original purpose, but both themes depend on child-only
sizing) and will silently defeat naive `offsetWidth`-based logic again. (2)
This fix only covers `create_href()`'s own popup (used by both `datepicker`/
`timestamp` QuickForm fields and any direct `Utils_PopupCalendarCommon::show()`/
`create_href()` caller). Other `clonePosition()` consumers - `Utils_Calendar`/
`Utils_CalendarBusyReport`'s "move event" popups, `GenericBrowser/
table_overflow.js`, `TabbedBrowser/theme/default.js` - share the same
no-viewport-awareness gap in the underlying primitive and were deliberately
left alone here (different popups, not reported broken); if one of them turns
up the same off-screen symptom, that's confirmation to lift `clampToViewport`
into a shared helper rather than re-deriving it a third time.

## Fixing one caller of a shared tooltip helper left a second caller of the *same underlying data* unfixed

Found 2026-08-07 making the Watchdog/RecordBrowser "what changed" tooltip
(`Utils_RecordBrowserCommon::watchdog_label()`'s `changes_list.tpl` output,
"Field/Old value/New value") render as a real table instead of a flattened
`" | "`/`": "`-joined line. First fix touched only `Utils_WatchdogCommon::
ajax_subscription_tooltip()`, the callback behind the per-row "watching" icon's
`Utils_TooltipCommon::ajax_open_tag_attrs()` call - verified the isolated
string-transform logic worked, shipped it, and the reported tooltip (the
Watchdog **applet**'s info icon) still rendered exactly as before, on two
browsers, after a hard refresh and a full Apache restart. Neither of those
was the problem: the applet builds its info icon through a **completely
different** path - `Watchdog_0.php`'s `applet()` → `RowObject::add_info()` →
`GenericBrowser_0.php`'s row-action renderer → `Utils_TooltipCommon::
open_tag_attrs()` (the *synchronous* tooltip helper, not the ajax one) - which
still called the old, always-flattening `to_safe_html($tip)` with no way to
opt out. Same source data (`watchdog_label()`'s HTML), two independent
rendering call chains reaching `Utils_TooltipCommon` by different routes; only
one of them got the fix.

**Fix**: threaded a `$keep_table` flag through **both** chains -
`Utils_TooltipCommon::to_safe_html($tip, $keep_table)` / `open_tag_attrs(...,
$keep_table)` / `ajax_open_tag_attrs(..., $safe_html)` for the ajax
callback, and `GenericBrowser_0.php::__add_row_action()` /
`RowObject::add_info(..., $keep_table)` for the synchronous one - defaulting
to `false` everywhere so every *other* tooltip (view/edit/delete action
tooltips, `format_info_tooltip()`-based ones) keeps its existing flattened
rendering untouched, and set it `true` only at the two call sites that
actually pass `changes_list.tpl` content (`WatchdogCommon_0.php`'s ajax
callback and `Watchdog_0.php`'s `add_info()` call).

**How to apply**: when a fix targets "content X renders wrong," grep for
*every* place that content reaches the screen before declaring done - the
same underlying data/callback can be wired through more than one independent
rendering path (sync vs. ajax, a dashboard applet vs. a plain list row), and
a plausible-looking negative result (hard refresh, cache clear, server
restart, all with no change) is itself a strong signal that the executing
code genuinely hasn't changed for *that specific call site* - i.e. you fixed
a different path than the one being looked at - rather than a caching
problem to chase.

## Dashboard applet header losing its assigned color in adminltedark's light mode: an exact specificity tie, broken by source order

Found 2026-08-07, reported as "in dark mode applets use their assigned color
scheme, but in light mode all applets render grey, ignoring the titlebar."

`Base/Dashboard/theme_adminltedark/default.css` defines 10 per-color rules
like `.epesi-applet-red .epesi-applet-header { background-color: #BD7B7B;
color: #fff; }` (2 classes = specificity (0,0,2,0)). The light-mode toggle
work (see `adminlte-theme.md`'s light/dark section) later appended an
auto-generated fallback at the end of the same file: `[data-bs-theme="light"]
.epesi-applet-header { background-color: #f8f9fa; ... }` (1 attribute + 1
class = also (0,0,2,0)) - meant for applets with **no** color class at all,
but written as a bare `.epesi-applet-header` selector with nothing excluding
colored ones. Neither rule uses `!important`; on an exact specificity tie,
the last-declared rule wins regardless of which one is "more specific in
spirit" - and the generated fallback, appended after all 10 color blocks, is
always last. Every colored applet's header silently lost its background in
light mode, and since the fallback never touches `color`, headers whose
color used `color:#fff` (red/blue/black/green-dark/etc) were left with
**white text on a near-white background** - not merely "greyed out" but
genuinely unreadable, while the grey-text colors (green/yellow/dark-yellow)
looked merely flat. This is the same shape already documented in this file
for `.data select`'s `height` fight and the `epesi-switch` checkbox fight:
a later same-specificity rule silently winning by source order alone.

**Fix**: added `!important` to the header `background-color`/`color` and the
`.epesi-applet-actions a` link colors in all 10 per-color blocks - matching
the `border-color`/`box-shadow` rules directly above them in the same file,
which had already needed (and gotten) `!important` for this exact reason
when the light-mode card-body override was added.

**How to apply**: any time an auto-generated or later-appended
`[data-bs-theme="light"]` (or `"dark"`) override file/block is added *after*
a set of existing per-variant color rules targeting the same element, check
whether the override's selector is actually scoped to exclude the colored
variants - if it isn't, and the specificities tie, the override always wins
regardless of how many color-specific classes the "more specific" rule has.
`!important` (already the established pattern for this file's card
border/shadow) is the fix, not selector reshuffling, since the light-mode
block is regenerated by a script (`gen_light_override.js`) that doesn't know
about the color blocks it might clobber.

## Native `<select>`/`<textarea>` text goes white-on-white inside a popup pinned to fixed-light chrome under dark mode

Found 2026-08-09, twice independently in the same session, in two different
Leightbox popups both recently converted to the fixed grey/black sidebar
scheme (see `adminlte-theme.md`'s "Leightbox popups" entry): `CRM_Followup`'s
Follow-up popup (Status/Note fields) and `Premium_Projects_Tickets`' "Change
Status" popup (Resolution/Note fields). Reported as the closed dropdown
showing no visible text at all, and the opened option list showing every row
blank except whichever one was keyboard-highlighted.

**Root cause**: pinning a popup's own CSS to always-light (`background-color:
#fff` on its `<select>`/`<textarea>`) doesn't change what the *browser* thinks
the page's color scheme is. `Base_ThemeCommon`'s dark-mode toggle sets
`colorScheme='dark'` on `<html>` (`ThemeCommon_0.php`), and browsers use that
- not the popup's own authored colors - to pick native form-control text/
option-list colors for anything left unstyled. Both popups set an explicit
white `background-color` on the field but never set an explicit `color`, so
the closed control's text (and, independently, its native dropdown popup's
option text) fell back to the dark-scheme UA default: white text, now on a
white background instead of the dark one it was designed against.

**Fix**, on both fields' shared CSS rule:
```css
color: #000;
color-scheme: light;
```
`color` alone can fix the closed control's own text; `color-scheme: light` is
what's actually needed for the *dropdown option list*, since that's native
browser chrome largely outside CSS's normal reach - author `color` on
`<option>` is unreliable across browsers, but `color-scheme` scoped to the
`<select>` (or an ancestor) reliably flips the native popup to light-scheme
colors regardless of the page's own dark mode.

**How to apply**: any Leightbox (or other container) pinned to a fixed-light
chrome regardless of theme - the pattern `adminlte-theme.md` documents as
"per request, same fixed grey/black scheme as the sidebar" - needs this same
`color` + `color-scheme: light` treatment on every native `<select>`/
`<textarea>`/`<input>` it contains, not just a light `background-color`. Two
unrelated popups hit this independently in one session; before styling a
*third* one this way, check whether it holds any native form control and add
both properties up front instead of waiting for it to be reported as
"invisible text."

## Status field's "quick shortcut" click bypassed the follow-up prompt entirely

`CRM_Tasks`/`CRM_PhoneCall`/`Premium_Projects_Tickets`/`CRM_Meeting`'s
`display_status()` (Meeting: split across `get_status_change_leightbox_href()`
+ `display_status()`) all special-cased the *first* status value (Open/New,
`$v==0` or `$v<=0`): instead of opening the same Follow-up/Change-Status
leightbox prompt every other status value opens (`class="lbOn"
rel="..._followups_leightbox"`), clicking it fired a hardcoded `onclick` that
submitted the underlying form directly with a made-up action
(`set_in_progress`/`set_next_stage`), auto-advancing straight to "In Progress"
with no prompt, no note field, no choice of any *other* status. Reported as
unintuitive: a user clicking "Open" expecting the same status-choice popup
every other status shows instead got silently reassigned to a specific status
they may not have wanted.

**Fix, identical shape in all four**: delete the special-cased branch and let
`$v==0`/`$v<=0` fall through to the same leightbox-opening return every other
status already uses - the leightbox's own status dropdown (`closecancel`)
already offers every status including "In Progress", so nothing was actually
gained by the shortcut. The now-unreachable `action=='set_in_progress'`/
`'set_next_stage'` handling deeper in each function (confirmed via a
whole-repo grep for both strings - no other caller in any of the four, before
*or* after fixing Meeting) was deleted too rather than left dead.

**How to apply**: any `display_status()`/similar field-formatter that branches
on the *current* value to decide between "prompt" and "silently mutate and
reload" for what's supposed to be one clickable status field is the same bug
shape - the prompt should be unconditional, not skipped for whichever value
happens to be first. All known instances of this pattern in the codebase are
fixed as of 2026-08-10 - if a *new* module adds its own status field this way,
it's copying the pre-fix pattern from one of these four, not a fresh design.

## An uncaught exception in a `document`-level handler silently eats the click that triggered it — invisible on mobile, no devtools

Found 2026-08-11, reported simply as "tapping Save on a phone does nothing" -
icon shows the tap registered (plain CSS `:hover` sticking, unrelated to
whether anything actually ran), then nothing: no request in the access log,
no visible error, screen never leaves edit mode. Took a temporary
`window.onerror` handler in `epesi.js` (still present, kept intentionally for
now per user request - remove once no longer needed) to surface what was
actually happening, since a phone has no visible console.

**Bug 1**: `modules/Libs/CKEditor/ck.js`'s `e:submit_form` handler
unconditionally calls `.destroy()` on every tracked instance in the global
`ckeditors` map. That map is only ever populated by `CKEDITOR.replace(key,
value)` (in the `e:load` handler), which **can return `null`** if CKEditor's
own init fails for any reason - a known CKEditor 4 quirk, more likely on
mobile/touch browsers (this app has an open, not-yet-started plan to replace
CKEditor with Quill - see `ckeditor-to-quill-migration.md` - which this class
of fragility is part of the motivation for). Nothing here ever guarded
against that `null`, so a single failed init poisoned the map, and the *next*
Save's `e:submit_form` handler threw a `TypeError` trying to call `.destroy()`
on it.

**Bug 2** (a second, independent bug the same investigation uncovered *after*
fixing Bug 1 - hidden until then because the diagnostic above only showed the
*first* error per page load): `modules/Utils/Shortcut/js/Shortcut.js`'s
global keydown listener has an `opt['disable_in_input']` guard that only
checks `element.tagName == 'INPUT' || 'TEXTAREA'` - it doesn't recognize
CKEditor's `contenteditable` iframe body as a form field, so it runs on every
keystroke typed into any RecordBrowser rich-text field too. It reads
`e.keyCode`/`e.which` into an un-`var`-declared global `code`, then
unconditionally does `String.fromCharCode(code)` right after - if a keyboard
event has *neither* property set (some Android virtual-keyboard/IME-generated
key events don't), `code` was never assigned at all, and referencing it threw
`ReferenceError: code is not defined`.

**The shared shape**: both are `jQuery(document).on(...)` handlers bound once
at page-load and re-invoked on every matching event thereafter. jQuery does
not wrap user callbacks in try/catch - an uncaught exception inside one
propagates out through jQuery's internal dispatch and **aborts whatever
called `.trigger(...)`**, which for `e:submit_form` specifically is the
ActionBar Save button's own raw inline `onclick` attribute, executed
synchronously, outside the ajax layer entirely: the exception aborts that
`onclick` before it ever reaches `_chj()`/`Epesi.request()`. Zero network
activity, zero visible feedback, and (critically) no console on a phone -
this is why it read as "tapping the button does literally nothing" rather
than as an error.

**Fix**: guarded both - `ck.js` now checks a tracked instance is truthy
before calling `.destroy()`/reading `.config` on it anywhere (and uses
`!ckeditors[key]` instead of `typeof ckeditors[key]=="undefined"` so a
previously-failed `null` entry gets *retried* on the next `e:load` cycle
instead of being permanently stuck, since `typeof null` is `"object"`, not
`"undefined"`); `Shortcut.js` now returns early if neither `keyCode` nor
`which` is set, instead of falling through to reference the unset `code`.
Also (defense in depth, not a fix for either specific bug): `epesi.js`'s
`Epesi.request()` now wraps `eval(responseText)` in try/catch too, so a bug
in a *response*'s generated JS can no longer permanently leak
`Epesi.procOn` and silently wedge every future click in the session the same
way (a related but distinct failure mode from the two above - that one would
manifest as a stuck "Loading..." indicator instead of true silence).

**How to apply**: any `jQuery(document).on('e:...', ...)` handler in this
codebase (there are several - GenericBrowser, RecordBrowser, other field
types) runs for *every* matching trigger app-wide, including ones fired by
totally unrelated code (any `Epesi.href()`/form-submit call triggers
`e:submit_form`/`e:loading`/`e:load` globally, not scoped to whichever module
"owns" the click). Before trusting that a value read from a shared
module-level map (`ckeditors`, or similar caches elsewhere) is always
populated, or that a browser API property (`keyCode`/`which`, or anything
else increasingly deprecated in favor of newer equivalents) is always set,
check what happens on mobile/touch input specifically - it's the environment
most likely to violate assumptions that hold reliably on desktop. And when a
report says a button "does nothing" with no error and no network activity at
all, suspect an uncaught exception inside that button's own click-triggered
event-handler chain before suspecting the network/server - there is no
built-in visibility into that on a phone, so it takes deliberately adding a
`window.onerror` (or equivalent) to see it.

## A record's own "identity" field linking/tooltipping to itself on its own View entry page

Found 2026-08-13, reported independently six times in one session as each
module was checked: Contacts' Last/First Name, Meeting's Title, Tasks'
Title, PhoneCall's Subject, Premium_Projects' Project Name, and
Premium_Projects_Tickets' Title all rendered as a clickable link *and/or* a
hover tooltip pointing back to the exact same record already on screen.
Harmless (even desirable) everywhere else these fields' display callbacks
run - Browse list rows, Attached-to/reference pickers in *other* records -
but redundant and slightly confusing on the record's own single-record
View/history page. The last two were found proactively by grepping for the
same shape across the whole `modules/` tree (`grep -rln
"TooltipCommon::open_tag_attrs"` combined with `description` - see
`premium-modules-gitignored.md`: this class of sweep must use plain `grep`,
not the Grep tool, or it silently misses `modules/Premium/`) rather than
waiting for a seventh screenshot - worth doing that sweep again if this
pattern resurfaces, since it's cheap and has a 100% hit rate so far.

**Root cause**: `Utils_RecordBrowserCommon::get_val()`/`call_display_callback()`
doesn't know or care *where* it's being called from - a field's display
callback (`CRM_ContactsCommon::contact_lastname_format_default()`,
`CRM_MeetingCommon::display_title()`, `CRM_TasksCommon::display_title()`,
`CRM_PhoneCallCommon::display_subject()`) always builds a link+tooltip to
`($tab, $record['id'])` regardless of whether that happens to be the very
record currently being displayed. The single-record View screen
(`RecordBrowserCommon_0.php::QFfield_static_display()`, used by every 'text'-
type field's default renderer) is the *only* code path where "the record
being formatted" and "the record on screen" are always the same record - grid/
Browse-row rendering (`RecordBrowser_0.php` line ~808, a separate `get_val()`
call site) never has this problem since a list shows many records at once.

**Fix**: added `Utils_RecordBrowserCommon::is_self_view($tab, $id)` - checks
a private static `$self_view_context` set to `($rb_obj->tab, $rb_obj->record['id'])`
only for the duration of `QFfield_static_display()`'s own `get_val()` call, then
restored. Wired into the three shared link/tooltip primitives every display
callback ultimately funnels through - `record_link_open_tag_r()`,
`record_link_open_tag()`, `create_record_tooltip()` (plus
`create_default_linked_label()`'s own separate tooltip check) - so any field
anywhere linking back to the record already on screen silently renders as
plain text instead. Five modules (Meeting, Tasks, PhoneCall,
Premium_Projects, Premium_Projects_Tickets) additionally build a *second*,
bespoke tooltip independent of those primitives (wrapping the linked label in
their own `<span {tooltip attrs}>` to show the record's Description/long-text
field, and for Projects/Tickets also Start/Due Date, as a preview) - those
needed their own explicit `!Utils_RecordBrowserCommon::is_self_view(...)`
guard added at each call site, since the shared primitives' fix doesn't reach
hand-rolled tooltip spans. Tickets' `display_title()` has a second, unrelated
tooltip right below the fixed one (`Blocked due to following tickets`/`Blocks
ticket #N`) - that one was deliberately left alone since it describes *other*
ticket records, not a self-reference, and stays useful on the ticket's own
view page.

**How to apply**: if a *new* module's own field-formatter builds a link back to
its own record (the `create_linked_label`/`create_linked_text`/
`create_linked_label_r` family, or a raw `Utils_TooltipCommon::open_tag_attrs()`
span like Meeting/Tasks/PhoneCall's Description preview) and that field is
ever shown on the record's own View/history page, check whether it already
goes self-link-aware for free via the shared primitives (it does, if it's
`create_linked_label`/`create_linked_text`/`create_linked_label_r`/
`create_default_linked_label`) - if it's a hand-rolled tooltip span instead,
it needs an explicit `is_self_view($tab, $id)` guard added at that call site,
matching Meeting/Tasks/PhoneCall's fix.

## `eval_js_once()` inside `Base_Box`'s own shell template assumed the shell renders once; it doesn't

Found 2026-08-13, reported as "viewed an e-mail on mobile, worked the first
time; on a later visit the toolbar looks broken and a 'Logout' link starts
showing in the navbar" - confirmed by the user to reproduce on a real phone
but *not* by merely resizing a desktop browser narrow, which pointed away
from a pure CSS-breakpoint bug.

**Root cause**: `Epesi::go()` (`include/epesi.php`) hardcodes
`self::$content[$path]['span'] = 'main_content'` for the **root** module
(`Base_Box`) exactly like `display_module()` does for any child - meaning
Box's *entire* rendered shell (navbar, sidebar, `#top_bar`, `#ActionBar`, the
whole `.epesi-adminlte.app-wrapper`) is one wholesale-replaceable
`Epesi.text(..., 'main_content')` unit, not just the inner `{$main}`
content area. Box's own output legitimately changes across ordinary
cross-module navigation (e.g. `$moduleindicator`'s embedded text differs),
so the *whole shell* - not merely the screen content - gets torn down and
replaced with a fresh DOM subtree far more often than "once per session."
Several one-time DOM-setup scripts in `Base_Box/theme_adminltedark/
default.tpl` assumed the opposite - explicit comments claimed "the navbar/
sidebar footer are shell chrome, not re-rendered by ordinary AJAX navigation"
- and were wrapped in `eval_js_once()`, whose dedup is a **server-side,
whole-session** flag (`$_SESSION['client']['__evaled_jses__'][md5($js)]`,
`include/misc.php`): it fires the JS to the client on the very first call
ever and never again, regardless of how many times the DOM it operates on
is actually recreated. Concretely: the script that moves `.logged_as`/
`.logout_css3_box` out of the navbar into the sidebar footer only ever ran
once, so every later shell replacement left a fresh, un-relocated Logout
link stuck in the navbar; the `ResizeObserver`-based `--epesi-header-height`/
`--epesi-actionbar-height` sync only ever attached to the *first* `#top_bar`/
`#ActionBar` nodes, so later ones (now taller, e.g. from the stuck Logout
text) were never tracked, letting the fixed bars end up mis-sized against
real content ("invisible toolbar"). Mobile-only in practice because the
navbar is more likely to actually change height there (off-canvas sidebar,
narrower search/filter wrapping) and a desktop-narrow test that doesn't
chain through two genuinely different module views never triggers the
"Box's own content differs" replacement in the first place.

**Fix**: switched the shell-scoped setup scripts (`--epesi-header-height`/
`--epesi-actionbar-height` watch, the `#ActionBar`-empty `MutationObserver`,
the `#MenuBar` close-sidebar-on-nav click listener, and the Logout/username
relocation) from `eval_js_once()` to plain `eval_js()`, so they re-run every
time `Base_Box::body()` executes (i.e. every request) instead of once ever.
To avoid piling up duplicate `ResizeObserver`s/listeners on the (far more
common) requests where the shell *isn't* actually replaced and the same DOM
nodes persist, each script now self-guards with a marker property set on the
element itself (`el.__epesiHeightWatched`, `bar.__epesiEmptyWatched`,
`bar.__epesiCloseBound`) checked before attaching anything - cheap on a
no-op re-run, correct on a fresh node. The relocation script needed no such
guard: re-inserting an already-relocated node at the position it's already
in is a harmless no-op.

## `load_js()`'s "already sent" session flag is set before the response is actually flushed

Found 2026-08-13 as a live `Uncaught ReferenceError: EpesiAutocompleter is
not defined` on an autocomplete field. Same family as the `eval_js_once()`
entry just above - server-side, per-client-session "don't resend this asset"
bookkeeping that isn't actually tied to the asset having reached the
browser - but triggered by request *abortion* rather than DOM re-render
frequency.

**Root cause**: `Epesi::load_js()`/`load_css()` (`include/epesi.php`) write
`$_SESSION['client']['__loaded_jses__'][$u] = true` the instant they're
called, then queue the actual `Epesi.load_js(...)` call into an in-memory
array that only gets embedded into the response string later, inside
`get_output()`. That's fine on the normal path - `get_output()` reads the
queued calls into `$ret` *before* calling `clean()`. But
`ErrorHandler::notify_client()` (`include/error.php`), reached whenever a
fatal error/uncaught exception occurs anywhere else in the same request,
calls `Epesi::clean()` directly to discard everything queued so far and
replaces the response with just the error alert - silently dropping any
`load_js()`/`load_css()` calls already made this request without ever
sending them, while the session flag they set remains. `HTML_QuickForm_autocomplete::toHtml()`
calls `load_js('.../autocomplete.js')` then unconditionally
`eval_js('new EpesiAutocompleter(...)')` on every render; once the session
flag is stuck "loaded" without the file ever having reached the browser,
every later render of that field (same client/tab, until a full page reload
issues a fresh client id) skips re-queuing the script but still runs the
`eval_js()` that depends on it - permanent `ReferenceError` for the rest of
that tab's session, triggered by a one-time, possibly unrelated fatal error
earlier in some other request.

**Fix**: added `Epesi::discard()`, which releases the session flags for
whatever's still pending in `self::$load_jses`/`$load_csses` before calling
`clean()`; `ErrorHandler::notify_client()` now calls that instead of
`Epesi::clean()` directly. Only the abort path changed - the normal
`get_output()` flush still uses plain `clean()`, since by that point the
queued calls are already captured in the returned string.

**How to apply**: any code that discards `Epesi`'s queued-but-not-yet-flushed
output (calling `Epesi::clean()`, or reimplementing the same "wipe and
replace" shape) needs to release the matching session flags first, not just
in `error.php`'s already-fixed call site - the same leak would reproduce
anywhere else that pattern gets added.

**How to apply**: any script in a `theme_adminltedark`/`theme_adminlte`
shell template (`Base_Box`'s own `default.tpl`, or any other file whose
`{php}` block runs as part of the *root* module's `body()`) that does
one-time DOM setup on an element inside the shell and is wrapped in
`eval_js_once()` under the assumption "this only renders once" is suspect -
`main_content` is Box's own span, so the whole shell can legitimately be
replaced mid-session. Prefer `eval_js()` + a client-side idempotency marker
on the target element over `eval_js_once()`'s server-side "sent once ever"
gate for anything touching shell chrome, not just the four scripts fixed
here - the same trap will reproduce for any *new* one-time shell script
written the old way.

## Watchdog "type label" callback indexes a record that doesn't exist for the generic (no-`$rid`) call

Found 2026-08-13, first real-world hit after pulling `jtylek/epesi`'s `jasiek`
branch fresh and loading the Dashboard: **blank page**, nothing in
`data/logs/php_errors.log` beyond a single `E_WARNING`. `Base_Dashboard`'s
Watchdog applet-settings path (`Dashboard_0.php:569` `get_default_values()` →
`applet_settings()`) calls every module's `*_watchdog_label($rid = null, ...)`
callback with **no `$rid`**, purely to get the generic display name for the
"what to watch" picker - not for any specific record.
`Premium_KnowledgeBase_Thread::thread_watchdog_label()`
(`modules/Premium/KnowledgeBase/Thread.php:143-146`) doesn't account for this:
it unconditionally calls `Utils_RecordBrowserCommon::get_record(...,$rid)`
(returns `null`/`false` when `$rid` is `null`) then immediately does
`$record['category']` to run a category-ACL check via
`Premium_KnowledgeBase_Category::check_permission()`. Under PHP 8 that's
`E_WARNING: Trying to access array offset on value of type null` - and since
`REPORT_ALL_ERRORS` is on for this instance (see `CLAUDE.md`'s Error handling
section, [[environment-gotchas]]), the *first* warning anywhere blanks the
whole rendered output, not just that one applet. Sibling `*_watchdog_label`
implementations (`ContactsCommon_0.php`'s `contact_watchdog_label`/
`company_watchdog_label`) don't hit this at all - they have no per-record
permission gate to begin with, so they're safe to call with `$rid = null`.
KnowledgeBase's category-ACL check is genuinely needed for *real* per-record
calls (hiding watchdog event labels for threads in categories the viewer
can't see), just not written to tolerate the record-less generic call.

**Fix**: `if($record && !Premium_KnowledgeBase_Category::check_permission($record['category'])) return;`
- skip the ACL check entirely when there's no record to check (the generic/
`$rid === null` case), keep it for real per-record calls.

**How to apply**: any `*_watchdog_label()` implementation that does its own
extra permission/lookup work beyond delegating straight to
`Utils_RecordBrowserCommon::watchdog_label()` needs to explicitly handle
`$rid === null` / a not-found record, since the Dashboard's applet-settings
UI calls every registered watchdog-label callback that way on every load
whether or not the module has any records at all. A blank page with exactly
one `E_WARNING` in the log and no fatal is the signature of this whole class
of bug under `REPORT_ALL_ERRORS` - check the warning's own file/line first,
it's almost always an unguarded array/property access, not a real crash.

## Legacy-format access-rule crits with non-string keys: a PHP 7.4→8.2 migration artifact, not a fresh bug

Found 2026-08-13 via the error-log monitor during the PHP 7.4→8.2/data migration, on
a `premium_invoice_customer_templates` ACL rule - not scoped to that one table, just
the first one whose data happened to trip it.

`Utils_RecordBrowser_Access::getRuleCrits()` → `parseAccessCrits()` unserializes each
access rule's stored `crits` DB column and, if not already an object, hands the raw
array to `Utils_RecordBrowser_CritsBuilder::build_from_array()`/`build_single()`
(`Crits.php`). Both methods `foreach` over the array expecting **every key to be a
field-name string** (with optional leading modifier chars: `!`/`"`/`<`/`>`/`~`/`(`/
`|`/`^`) and immediately index `$k[0]` to read the first modifier char. One stored
rule's crits unserialized to a plain **indexed** array (`[0 => "customer"]` -
`a:1:{i:0;s:8:"customer";}`) instead of the expected `['field' => value]` shape - a
legacy/malformed data shape that PHP 7.4 tolerated silently (`$k[0]` on an int key
just evaluated to `null`, no notice) but PHP 8.2 flags as `E_WARNING: Trying to
access array offset on value of type int`, tripping `REPORT_ALL_ERRORS` and blanking
the whole module.

**Fix**: added `if (!is_string($k)) continue;` in both `build_from_array()`'s and
`build_single()`'s per-key loop (right after `build_single()`'s existing
`CritsInterface` instanceof check, which already legitimately uses non-string keys
for plain indexed arrays of sub-`Crits` objects) - skip the malformed entry rather
than warn, deliberately not touching the underlying stored data since altering ACL
rule rows isn't a call to make unprompted.

**How to apply**: this is a specific instance of a general migration-era shape worth
watching for anywhere old serialized/array-shaped data reaches PHP 8 code that
indexes into a key or value without checking its type first - PHP 7.4's looser
type-juggling silently absorbed shapes that 8.2 warns on. If another table's access
rules (or any other `unserialize()`-fed array consumer) throws a similar "access
offset on value of type int/bool" warning, suspect old/malformed stored data before
assuming fresh code introduced the bug - and prefer a defensive skip over "fixing"
the data directly unless the data's correct shape is actually known.

## An already-fixed JS bug "coming back" in one browser window but not incognito: stale client state, not a regression

Reported 2026-08-14: the `alert('Invalid date - clearing')` popup from
[MIGRATION_NOTES.md §64](MIGRATION_NOTES.md) (fixed 2026-07-24 - an
untouched, optional date field failed the datepicker's validation regex on
blur) appeared to resurface in a normal browser window. Before assuming the
fix had regressed: `datepicker.js`'s source still had the fixed regex, and
every relevant bundle already on disk in `data/cache/minify/` - including one
regenerated minutes earlier - also had it. Server-side, there was nothing to
fix. Confirmed the actual explanation by asking whether the same window in an
**incognito/private session** showed the bug too - it didn't - which pins the
cause on stale client state, not stale server output.

**Why this happens here specifically.** Two things compound: (1) `serve.php`
sends `Cache-Control: max-age=31536000` (one year -
[serve.php:54](../serve.php)) on the combined JS/CSS bundle, so a browser that
already has a given bundle URL cached won't even ask the server again until
that URL changes or the cache is forced clear; (2) Epesi's module tree is an
old-style AJAX-push SPA (`process.php` returns JS that patches the existing
DOM - see `CLAUDE.md`'s Architecture section), so a browser tab can stay
"open" and interactive for a long session without ever reloading `index.php`
- meaning whatever JS was in memory at that tab's *original* load keeps
running indefinitely, untouched by any later fix, until an actual page
reload happens. (The bundle URL itself *is* cache-busted correctly - see
`Minify_Build::uri()` appending `max(filemtime())` across the bundle's source
files, [libs/minify/Minify/Build.php:76-101](../libs/minify/Minify/Build.php)
- so this isn't a server-side versioning bug either; the browser just never
asks again while the tab/session stays alive.)

**How to apply**: before investigating an already-documented-as-fixed bug as
a regression, check whether it reproduces in a fresh incognito/private
window (or after an explicit hard refresh) first. If incognito is clean,
the fix is intact and the report is stale client state in one browser
profile/tab - no code change needed, just tell the reporter to hard-refresh
or start a new session. Only chase it as a real regression if a *fresh*
session/tab also reproduces it. This is the same underlying `serve.php`
caching behavior already hit once before from the opposite direction (§67
in `MIGRATION_NOTES.md`: a stale cached bundle masked a *newly introduced*
bug in non-incognito windows while incognito correctly showed it broken) -
worth recognizing either direction: aggressive caching can hide a real bug
just as easily as it can appear to resurrect a fixed one.

## "Invalid date - clearing" recurring *again*, surviving a hard reload and a brand-new tab: browser autofill, not stale JS

Reported again 2026-08-14, same day as the stale-tab entry just above - but
this time the reporter did a real `Ctrl+Shift+R` **and** opened a brand-new
tab, and it got *worse* (3 alerts instead of 1). That combination rules out
every page-JS-staleness theory at once (stale cached bundle, stale in-memory
tab JS, the `load_js()` session-dedup flag documented earlier in this file -
a fresh tab gets a fresh `client_id`/session bucket, per `init_js.php`, so
none of those can survive a genuinely new tab). Confirmed live by fetching
`serve.php?f=modules/Utils/PopupCalendar/datepicker.js` directly with curl:
the served content already had the real regex fix. Something else was
depositing a non-empty, wrong-format value into the field *without the user
typing it*.

**Real root cause**: [modules/Utils/PopupCalendar/datepicker.php](../modules/Utils/PopupCalendar/datepicker.php)'s
`HTML_QuickForm_datepicker::toHtml()` rendered the input with no
`autocomplete` attribute at all, and a plain semantic `name`/`id` (e.g.
`birth_date` on Contacts) - exactly what browser/password-manager autofill
heuristics key off, on a form that also has name/address/phone fields (i.e.
any "Add Contact"-shaped form). Autofill has no idea this app expects one
specific configured `date_format()` (e.g. `%m/%d/%Y`); it fills in whatever
shape it stores internally (commonly ISO `YYYY-MM-DD`). `validate_blur()`
then does exactly what it's supposed to - rejects the mismatched format and
clears the field - except the value it's wiping was never something the user
typed, so every autofill of a date field silently loses the value the
browser/password manager just filled, with no obvious cause from the user's
side. Reproduced conclusively via Playwright: `el.value = '1990-05-20';
el.blur()` on a completely fresh page load fired the exact same alert.

**Fix (partial)**: `toHtml()` now sets `autocomplete="off"` on the field
(unless already set) right after the `placeholder` default. Applies to every
date field app-wide (shared QuickForm element type), pure code change,
reaches existing installs automatically. Follow-up:
`validate_blur()` (datepicker.js) also now reparses/reformats an unambiguous
ISO `YYYY-MM-DD` value instead of rejecting it, verified in isolation against
the live-served JS (six cases: three format×ISO-input combos all reformat
correctly, empty still passes, genuine garbage still alerts+clears, an
already-valid value passes through unchanged).

**Outcome: still not fully resolved in the wild.** Both fixes are real and
verified correct for what they target, but re-enabling Birth Date and
retesting with the reporting user's actual (Chrome-profile-synced) autofill
data reproduced the alert again - autocomplete="off" is a deliberate
Chromium no-op for fields it strongly believes are profile data (not a bug,
a documented Chrome product decision), and whatever value Chrome is actually
inserting isn't the plain ISO shape the reformat fallback handles. Rather
than keep guessing at real-world autofill formats with no way to reproduce
the user's actual synced-profile behavior locally, the field was disabled
instead - see `deliberate-removals.md`'s "Contacts Birth Date field
disabled" entry. Keep both code fixes (they're correct and harmless, and
help in browsers/situations where autocomplete="off" *is* honored) but don't
present this as fully solved if it comes up again.

**How to apply**: when a bug is reported as "already fixed" yet survives a
hard reload *and* a brand-new tab, stop looking at page JS/session state
entirely - both are guaranteed fresh at that point (see `init_js.php`'s
`num_of_clients` counter, which mints a new per-tab session bucket every full
`index.php` load). Look instead at browser-profile-level state that persists
across tabs/reloads: autofill, saved passwords, extensions. Any custom-
formatted text input whose `name`/`id` reads as a real-world field
(birthdate, address, phone, email) and has no explicit `autocomplete` is a
candidate - `autocomplete="off"` is the standard mitigation, not an attempt
to parse every possible autofill format.

## `ModuleInstall::simple_setup()` declared non-static silently drops the module from the Setup screen entirely

Found 2026-08-18, immediately after cloning `Premium-SalesOpportunity` as a new
nested repo (`modules/Premium/SalesOpportunity/`, matching the existing
`Premium-Import` pattern - see `README.md`'s index). Reported as "module
`/Premium/Import` does not have simple setup" - it (and, once checked, several
core modules) had silently stopped appearing in Base_Setup's Simple view
list, with no error, no log entry, nothing to blank the page - it just isn't
in the list.

**Root cause**: `Base_Setup_0::simple_setup()` discovers each module's install
hook by class name string, not an instance -
`is_callable(array($module_install_class, 'simple_setup'))` /
`call_user_func($func_simple)` - because at this point there's no live
`ModuleInstall` object to call it on, only the class name from
`get_module_dirs()`. PHP 7 tolerated calling a non-static method this way
(deprecation notice only); **PHP 8 does not** - `is_callable` on a
class-name-string + non-static-method pair simply returns `false`. Since
`Setup_0.php` treats a non-callable hook identically to one that returned
`false` (`if ($simple_module===false) continue;`), the module is silently
skipped from the package listing - not shown as "unavailable," not erroring,
just absent, indistinguishable from a module that intentionally opts out.

This is the exact bug shape `MIGRATION_NOTES.md` already documents for
`PEAR::isError()`/`PEAR::raiseError()` (§76, §275) and openpsa's
`registerElementType()` (§12.6) - "non-static method called statically" - just
manifesting as a silent UI omission instead of a fatal error, since this
particular call site was already defensively wrapped in `is_callable()`.

Swept the whole tree for the same declaration shape
(`public function simple_setup()` instead of `public static function
simple_setup()`) and found it in 14 more places, all one-line trivial bodies
with no `$this` usage (confirmed safe to make `static` with zero behavior
change other than becoming callable again): `Base/Box`, `Base/Error`,
`Base/Help`, `Data/Countries`, `Data/TaxRates`, `Develop/MiscUtils`,
`Develop/ModuleCreator`, `Develop/ModuleEditor`, `Develop/TableBrowserCreator`,
`Develop/Translations`, `Utils/BBCode`, `Utils/Calendar`,
`Utils/RecordBrowser`, `Utils/Tray`, plus `Premium/Import`. The ones that
already returned `false` had no user-visible effect either way; the ones
returning a real package name (Box, Error, Help, Countries, BBCode,
RecordBrowser) are mostly always-installed "EPESI Core" modules so their
absence from the *installable* list wasn't obviously wrong - but `Base_Error`
specifically loses its "Error reporting" sub-option entirely this way, and any
non-core module (like `Premium/Import`) becomes impossible to install or
uninstall from Setup's Simple view at all.

**Fix**: changed all 15 to `public static function simple_setup()`. Pure code
fix, no stored/seed data involved, so no upgrade patch needed - reaches
existing installs the moment the file is deployed.

**How to apply**: any *new* module (Premium or core) whose `*Install.php`
declares `simple_setup()` as an instance method instead of `static` will
reproduce this exact silent omission - it won't throw, won't log, won't
render an error; it will just never appear in Base_Setup → Simple view, which
reads as "the module doesn't support simple setup" rather than "this is a
bug." Check `simple_setup()` is declared `static` as part of scaffolding any
new module's `*Install.php` (also relevant to `Dev-Tutorial.md`'s install
lifecycle section and the `dev:create:module` console scaffold, if either
generates a template `simple_setup()` stub - worth checking they emit
`static`). See the "`modules/Premium/` is invisible to every PHP 8.2
migration tool" entry further down for the broader pattern this is one
instance of.

## One specific Bootstrap Icons glyph (`bi-square`, `\f584`) silently never paints in this app's vendored font - and `getComputedStyle` can't be trusted to diagnose it

Found 2026-08-18 while adding the first `theme_adminltedark` coverage for
`Premium_Import` (a module with no template of its own - see the module-icon
glyph-swap technique already established in `Utils_RecordBrowser/
theme_adminltedark/View_entry.css` and `Utils_GenericBrowser/
theme_adminltedark/default.css`'s own action-icon rows). Picked `bi-square`
(`content: "\f584"`, an empty checkbox outline) for the "Add to queue"
action's unchecked state - the codepoint is correctly declared in
`libs/bootstrap-icons-1.13.1/bootstrap-icons.min.css` and the vendored
`.woff`/`.woff2` are full, unsubsetted builds (~130-180KB, no evidence of
trimming) - yet the icon rendered as a genuinely blank box, confirmed by
screenshot, not just "looks off." Every other glyph tried in the same rule
slot (`\f341` eye, `\f26d` check-square, `\f3d9` folder2, `\f451` keyboard,
`\f5b2` tags, `\f4fd` plus-square) rendered correctly on the first try, in
the same selector, same file, same session - ruling out the selector/CSS
mechanism, cascade, or file-loading as the cause. No root cause chased
further (not worth the time for one glyph); worked around by picking
`bi-plus-square` (`\f4fd`, "add" reads at least as well for an "Add to
queue" action as a bare empty checkbox does) instead.

**The much bigger time-sink**: `getComputedStyle(el, '::before').content`
(and `CSSStyleRule.cssText`/`.style.content` read back through Playwright's
`page.evaluate`) reported `content: ""` for *every* hex-escape `content`
value tested in this session - including the ones that were, confirmed by
screenshot moments later, rendering completely correctly (GenericBrowser's
own long-shipped `\f341`, `\f5de`, etc). Chased this as a real bug for a
long time: rewrote the selector prefix (a legitimate fix, kept - see below),
added `!important`, re-fetched the CSS file with `cache: 'no-store'` to rule
out `serve.php`'s year-long `Cache-Control` masking an edit, injected the
freshly-fetched text into a brand-new `<style>` tag to bypass the `<link>`
entirely - the computed-style reading stayed `""` throughout, on rules that
a same-second screenshot proved were painting fine. The unreliable reading
was the reporting path itself, not the page: don't trust
`getComputedStyle`/`cssText` for a `content` value containing a Private-Use-
Area escape in this environment - a live screenshot is the only trustworthy
check for whether a `content: "\fXXX"` glyph is actually rendering.

**The legitimate fix found along the way, still correct and worth keeping**:
the first attempt at this file scoped its rules to plain
`.Utils_GenericBrowser__actions a:has(...)::before`, which - per the
`getComputedStyle` red herring above - looked like it was failing the same
way as everything else, but was *also* independently wrong for a real
reason: `Utils_GenericBrowser/theme_adminltedark/default.css`'s own base
`a::before` reset uses `:is(.epesi-gb .Utils_GenericBrowser,
#epesi-gb-actions-menu)` as its selector prefix, and per spec `:is()`'s
specificity is the *highest*-specificity branch in its argument list
regardless of which branch actually matches a given element - here that's
the `#epesi-gb-actions-menu` ID, so the reset carries ID-level specificity
everywhere it applies, table row or floating overflow-menu clone alike. A
selector scoped to a plain class can never outrank that no matter how many
`:has()` conditions it adds. Rewriting to reuse that exact same `:is(...)`
prefix (matching every other per-icon override already in that file) fixes
this properly, at equal specificity plus the `:has()` tie-breaker, with no
`!important` needed - confirmed live by removing `!important` again after
switching prefixes and the icon still rendered.

**How to apply**: (1) any future custom action-icon glyph added to a
`Utils_GenericBrowser` row (in any module) must reuse the `:is(.epesi-gb
.Utils_GenericBrowser, #epesi-gb-actions-menu)` prefix, not a scoped-down
class selector, for the same two reasons documented in
`Utils_GenericBrowser/theme_adminltedark/default.css`'s own header comment
(ID-level specificity tie, and reach into the overflow-menu clone) - this
entry is a second, independent confirmation of that file's own warning, hit
by not reading it closely enough the first time. (2) If a newly-picked
Bootstrap Icons glyph refuses to render with no console error and a
seemingly-correct selector, don't trust `getComputedStyle`/CSSOM to
diagnose it in this environment - do a cheap live swap to a different,
already-proven codepoint and screenshot; if the different codepoint renders
in the exact same rule, the mechanism is fine and the *specific glyph* is
the problem, not your CSS. `bi-square` (`\f584`) specifically is confirmed
bad as of this date; treat any other single glyph the same way if it turns
up silently blank.

## `modules/Premium/` is invisible to every PHP 8.2 migration tool - old-syntax bugs there only surface at runtime

Found 2026-08-18 during a live error-log monitoring session: four unrelated
PHP 8-incompatibility fatals surfaced from `modules/Premium/` in under an
hour, each a syntax/signature shape the core migration sweep had already
cleared out of tracked code:

- `Utils_RecordBrowserCommon::set_quickjump()` call left in
  `Premium/SalesOpportunity/SalesOpportunityInstall.php:38` after the method
  was deleted codebase-wide (see `deliberate-removals.md`'s quickjump entry,
  which now also documents this gap) - fatal on module install/upgrade via
  the Store admin screen.
- Curly-brace array offset syntax (`$columns{$id} = ...`), removed outright
  in PHP 8.0, in `Premium/Import/Temp/Worksheet.php:199`.
- Two `PhpOffice\PhpSpreadsheet\Reader\IReadFilter` implementers
  (`Premium/Import/File/FirstRowFilter.php`,
  `Premium/Import/File/ChunkReadFilter.php`) with bare untyped `readCell()`
  signatures, incompatible with the interface's typed
  `readCell(string, int, string = ''): bool` - fatal the moment Import's
  filtered-read path runs.
- The already-documented `simple_setup()` declared-non-static bug just above
  this entry also hit `Premium/Import` alongside 14 core modules.

**Root cause**: `phpstan.neon` and `rector-php82.php` both list `paths:
[include, modules]`, which *would* include `modules/Premium` - but Premium
is a separately-licensed tree, gitignored and each module its own nested git
repo (see main `CLAUDE.md`), so it's simply not present in CI's checkout at
all. Every migration/static-analysis pass that already swept core code for
these exact syntax shapes (removed curly-brace offsets, non-static
callback-by-classname calls, deleted API surface) never had a chance to see
Premium's copies of the same shapes. Unlike a clean miss, these don't show
up as PHPStan findings to triage later - they're silent until the exact
code path executes in production and throws a fatal.

**How to apply**: don't treat "PHPStan/Rector are clean" or "the migration
sweep is done" as covering Premium - it isn't and can't be, structurally.
When a Premium module throws a fatal that looks like a PHP 7.4-vs-8.2
compatibility shape (curly-brace offsets, `{}`/dynamic-property notices,
non-static-called-statically, interface/parent signature mismatches, removed
PEAR/openpsa APIs per `MIGRATION_NOTES.md`), first check whether the same
shape was already fixed in core (`git log -S'<pattern>'` or grep
`deliberate-removals.md`/this file) - if so it's almost certainly this gap,
not a new bug class, and the fix is the same mechanical change core already
got. If you're touching a Premium module for any reason, a quick manual grep
for these known-bad shapes (`{$`, `function simple_setup()` missing
`static`, interface implementations with untyped params) is worth doing
opportunistically since no automated pass will ever flag it. There is no
CI-side fix available (Premium can't be added to a public CI checkout) -
this stays a manual-sweep-on-touch problem indefinitely.

## `eval_js_once()` permanently skips a script that needs to re-run every time its target element is freshly (re-)rendered, not just once ever

Found 2026-08-18, reported as "when you run Playwright it renders light mode
with a black background" - screenshots showed a fully light-themed sidebar/
navbar/page background with every Bootstrap `.card` (Admin panel tool
tiles, a GenericBrowser table row) rendering solid black regardless.

**Root cause**: `Base_Box`'s shell wrapper (`.epesi-adminlte`,
`theme_adminltedark/default.tpl:977`) is server-rendered with
`data-bs-theme` hardcoded from the *installed theme name*, not the user's
actual color-mode preference: `{if $theme_name=='adminltedark'}dark{else}
light{/if}` - since `adminltedark` is the only AdminLTE-family theme now
(see this file's own `adminlte-theme.md` history), this is unconditionally
`"dark"` on every fresh render of that markup. `Base_ThemeCommon::
load_theme_assets()` queues a client-side correction
(`epesiSyncThemeShell()`) that's supposed to immediately re-sync
`.epesi-adminlte`'s attribute to match `localStorage['lte-theme']`, with a
"self-healing" retry loop for the case where the script runs before the
element exists yet.

That correction was wrapped in `eval_js_once()`, which dedupes by
`md5($js)` in `$_SESSION['client']['__evaled_jses__']` - a **server-side,
PHP-session-scoped** flag, not cleared by logout. `load_theme_assets()` is
called from `Box_0.php`'s construct(), which runs again on every AJAX-push
login (logging in patches Box's whole shell into the DOM without a real
page navigation - see `ThemeCommon_0.php`'s own pre-existing comment on
this exact race, which assumed the retry loop made it self-healing).
Confirmed live by patching `Element.prototype.setAttribute` and
`Storage.prototype.getItem` before a logout+login cycle: on the *second*
login within the same browser session, **zero** calls to either were
intercepted - the entire sync script, including its retry loop, was
silently skipped by `eval_js_once()` because the exact same script content
had already been marked "sent" earlier in the session (e.g. the original
full-page login). The freshly-rendered `.epesi-adminlte` from the second
login was left stuck on its SSR-hardcoded `"dark"`, forever, with nothing
left to correct it - `<html>`'s own attribute (set once on the original
page load by AdminLTE's own separate, non-Epesi color-mode script, and
never touched again by a mere AJAX login) happened to still show the
*correct* value, which is why the shell chrome looked right while the
content area's cards/tables didn't: AdminLTE/Bootstrap's dark-mode CSS uses
`[data-bs-theme=dark] .card`-style selectors matching *any* ancestor, and
`.epesi-adminlte` sits directly above nearly every card/table in the app.
A plain full page reload (not a re-login) always "fixed" it, since that
re-runs the whole PHP request from scratch including a fresh
`load_theme_assets()` call with a session where the dedup flag genuinely
hadn't been set yet in *that* browser tab's history - which is exactly why
this had gone unnoticed: normal manual testing tends to reload rather than
logout/login repeatedly, while an AI driving the browser via repeated
session-expiry-triggered logins (as happened organically this session)
hits the buggy path constantly.

**Fix**: split the one `eval_js_once()` call into two. The seed +
immediate `<html>` update + `epesiSyncThemeShell()` retry IIFE now goes
through plain `eval_js()` - safe to resend on every `load_theme_assets()`
call since it's a handful of idempotent DOM queries/attribute sets with no
cross-invocation state (the retry counter lives inside each IIFE's own
closure). The `document.addEventListener('changed.lte.color-mode', ...)`
registration stayed in its own `eval_js_once()`, unchanged - registering
that one repeatedly *would* be a real (if minor) accumulating-listener
problem, unlike the sync logic itself. Verified live across two separate
logout+login cycles (both correctly synced `.epesi-adminlte` to `"light"`)
and the real color-mode toggle in both directions (still correctly flips
both `<html>` and `.epesi-adminlte` together, confirming the split
listener still works).

**How to apply**: `eval_js_once()`'s guarantee is "this exact script body
runs at most once per PHP session" - correct for one-time setup (event
listener registration, injecting a modal template once) but wrong for any
script whose job is to *re-assert current state onto a DOM element that
can be freshly (re-)rendered more than once per session* (a shell wrapper,
a widget re-inserted by AJAX, anything server-rendered with a
request-time-computed default that a client-side script is meant to
correct). Before wrapping a state-sync script in `eval_js_once()`, ask
whether the element it targets is guaranteed to exist exactly once for the
lifetime of the session - if it can be re-rendered (this app's AJAX-push
navigation model makes that more common than a traditional multi-page app,
per `CLAUDE.md`'s Architecture section), the sync logic needs plain
`eval_js()` (or its own re-triggering hook), while any one-time-only side
effect nested inside the same block (listener registration, etc.) should
be split into its own separately-keyed `eval_js_once()` call so it doesn't
get needlessly re-run just because the sync logic now correctly does.

## An addon `*_related` admin grid only shows rows added through itself — not addon wiring done directly by other modules' `Install.php`

Found 2026-08-21 investigating why the "Administration: Attachments" screen
(`Utils_Attachment::admin()`, `AttachmentCommon_0.php`'s `admin_caption()` —
the "Features Configuration" section's Attachments panel) rendered as an
empty grid with just a "Recordset" column header, despite the Notes addon
clearly being active on `company`/`contact`/`task`/`phonecall`/`crm_meeting`
records in the running app.

**Root cause**: there are two independent, non-syncing places an addon's
wiring can live. `Utils_RecordBrowserCommon::new_addon($tab, $module, $func,
$label)` (`RecordBrowserCommon_0.php:1175`) is the **real, live registry** —
it writes straight into the `recordbrowser_addon` table (`tab`, `module`,
`func`, `label`, `pos`, `enabled`) and is what actually makes an addon render
on a recordset. `Utils_AttachmentCommon::new_addon()`/`delete_addon()` are
thin wrappers around it, called directly from ~50+ places across the
codebase — `CRM_Tasks`/`CRM_PhoneCall`/`CRM_Contacts` (company+contact)/
`CRM_Meeting`/`Tests_Bugtrack`'s own `Install.php` files, plus a long list of
Premium/Custom recordsets seeded in `modules/Base/patches/notes_addons.php`.
None of these ever touch a **second**, separate table: `utils_attachment_
related_data_1`, a plain `Utils_RecordBrowser` recordset (added by the
`20260808_features_configuration.php` patch) whose sole purpose is to let an
admin add/remove the addon at runtime via a CRUD grid — `Utils_Attachment
Common::processing_related()` is its `add`/`edit`/`delete` callback, and
itself just calls the same `new_addon()`/`delete_addon()` wrappers above.
The grid displays **only** rows that exist in this second table — i.e. only
recordsets an admin explicitly added through the grid itself — so every
recordset wired up the other way (directly, at install time) is fully active
but invisible to this screen. Same two-mechanism split exists for `CRM_
Tasks`' `task_related`/`CRM_Meeting`'s `crm_meeting_related` and likely any
other module built on this "Features Configuration addon" pattern — check
before assuming any one of these grids reflects live addon state.

**How to apply**: don't trust a `*_related`-table-backed "Features
Configuration" admin grid to show which recordsets currently have an addon —
query `recordbrowser_addon WHERE module=... AND func=...` directly for
ground truth. If a grid like this needs to actually list everything, it
needs to be seeded/reconciled from `recordbrowser_addon`, not just from its
own table's rows.

**Fixed for Attachments 2026-08-21**: `modules/Utils/Attachment/patches/
20260821_seed_related_from_addon.php` backfills a `utils_attachment_related`
row for every recordset `recordbrowser_addon` already has wired to
`Utils_Attachment`'s `body` addon but with no matching row yet, via a plain
`Utils_RecordBrowserCommon::new_record()` call per missing recordset (same
precedent already used by `CRM_Roundcube`'s 2015 `rc_related` patch). That
insert re-enters `processing_related()` → `new_addon()`, which harmlessly
re-wires the already-existing `recordbrowser_addon` row (only its `pos`
shifts) rather than duplicating it — confirmed via a rolled-back dry run
before applying for real. Applied to the dev DB directly (bypassing
`update.php`'s full app-wide patch sweep) via `PatchUtil::list_for_module()`
+ a single `Patch::apply()` call, since `PatchUtil::start_timing()` is
private and has to be invoked (via `ReflectionMethod`) before calling
`apply()` standalone, or every patch immediately throws
`NotEnoughExecutionTimeException` (`self::$start_time`/`$deadline_time` are
still null) and reports `STATUS_TIMEOUT` instead of running. `CRM_Tasks`'
`task_related`/`CRM_Meeting`'s `crm_meeting_related` still have the
underlying gap unaddressed - same fix shape would apply there if either is
ever reported empty despite active wiring.
