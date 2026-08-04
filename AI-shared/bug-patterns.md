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

## Known, still-open issue: Shoutbox delete UI doesn't work

The History tab's delete icon (`Apps_Shoutbox::delete_msg()`/
`can_delete_msg()`) does not actually delete a message on this install — not
yet root-caused (could be `can_delete_msg()`'s permission logic, or something
broken in GenericBrowser row-action rendering here). Workaround: soft-delete
directly —

```sql
UPDATE apps_shoutbox_messages SET deleted=1 WHERE id=<id>;
```

Never hard-delete; the module's own design keeps all messages in history.
