# Known TODO/FIXME/XXX markers (audited 2026-08-04)

> **Status:** AUDIT, dated 2026-08-04 - every TODO/FIXME/XXX marker in Epesi's own code, re-verified as still open at that date.

A full-repo scan for `TODO`/`FIXME`/`XXX:` in Epesi's own code (excluding vendored
third-party code — `vendor/`, `modules/Libs/RoundCube/RC/`, `modules/Libs/TCPDF/vendor/`,
`libs/adodb/`, `libs/minify/`, `modules/Libs/Codepress/`, `modules/Base/Mail/class.phpmailer.php`).
Every marker found was individually re-checked against current code — **none were
stale; all still describe a real, unresolved condition as of the audit date.** Recorded
here so a future pass doesn't have to redo the same verification from scratch — just
confirm these are still true before relying on this list, since code moves on.

**Trap hit during the scan**: `modules/Base/Notify/js/desktop-notify.js` has six
`TODO`s (`actions`/`badge`/`noscreen`/`renotify`/`sound`/`vibrate` properties) that look
like ours at a glance — the file lives under `modules/Base/...`, not `vendor/`. It's
actually a vendored third-party polyfill (`HTML5 Notification` v3.0.0 by Tsvetan
Tsvetkov, Apache-2.0, per its own header comment) that was just copied in-tree rather
than pulled via Composer/npm. Check a JS file's own header comment for a
license/version banner before treating its TODOs as ours to fix.

## Two worth prioritizing if anyone picks this list up

- **`modules/Base/Box/BoxCommon_0.php:40`** — `create_href_array($parent_module, ...)`'s
  `$parent_module` param is confirmed genuinely dead (never read anywhere in the
  function body); `create_href()`/`create_href_js()`/`location()` all thread it through
  for nothing. Safe, mechanical cleanup — just needs call-site signature updates.
- **`modules/Utils/Calendar/Calendar_0.php:326`** and the identical hack in
  **`modules/Utils/CalendarBusyReport/CalendarBusyReport_0.php:261`** — both build the
  "Add event" href with `str_replace('"','\'',$jshref)` and a `// TODO: regular escape
  didn't work` comment. `Calendar_0.php:308`, a few lines above, in the **same
  method**, already does proper escaping via `Epesi::escapeJS(...)` for a sibling href —
  so a working replacement already exists nearby and this may be an easy real fix, not
  just a stale comment.

## Full inventory (for reference, not all need action)

Stub/incomplete feature:
- `modules/CRM/Calendar/Event/EventCommon_0.php:38` — `get_event_days()` returns `array()`
  unconditionally. Confirmed still called from `MonthView_0.php:82` and
  `Calendar_0.php:984`, so CRM's month-view day markers are genuinely dead, not
  theoretical.
- `modules/CRM/Contacts/Activities/Activities_0.php:37-38` — recurring events not
  factored into the activity list; commented-out old SQL nearby confirms nothing
  replaced it.

Known workarounds/hacks, unchanged:
- `modules/Utils/RecordBrowser/RecordBrowserCommon_0.php:1397` and `:1474` — duplicated
  regex hack (`preg_match('/^[a-z]+(\([0-9]+\))?$/i', $desc['param'])`) validating
  calculated/hidden field params; the `<tab>_field` table exists but doesn't store this
  validation info yet.
- `modules/Utils/RecordBrowser/CritsValidator.php:50` and `:57` — `decode_multi()`
  detection still relies on a `str_starts_with($v, '__')` string heuristic.
- `include/module.php:919` — `reserve_form_name()` still uses a static counter for
  uniqueness (comment notes it can fail if a preceding form is inside an `if`).
- `modules/Base/Setup/Setup_0.php:469` — store module names shown as-is, not localized.
- `modules/Base/User/Login/LoginCommon_0.php:166` — IP-restricted login exists but has
  no admin GUI, `Variable`-only.
- `modules/Libs/QuickForm/QuickForm_0.php:300` — `add_table()` grouping question.
- `modules/Libs/QuickForm/FieldTypes/autocomplete/autocomplete.php:55` — hide-callback
  JS injection "needs to extend the function automatically".
- `modules/Libs/QuickForm/FieldTypes/multiselect/multiselect.php:486` and
  `modules/Libs/QuickForm/FieldTypes/automulti/automulti.php:339` — identical bug
  #4465/#5269 workaround in `onQuickFormEvent()`, copy-pasted between the two field
  types instead of pushed to a shared base.
- `modules/Utils/LeightboxPrompt/LeightboxPrompt_0.php:227` — manual `unset()` of a
  QuickForm-generated submit key by reconstructing its md5'd name.
- `modules/Utils/Path/Path_0.php:19` — `$_string` property flagged for cleanup, still
  present/used as-is.
- `modules/Utils/CurrencyField/currency.php:111` — open question on float vs. string
  representation for currency values.
- `modules/Utils/Comment/Comment_0.php:110` — current-page pagination link styling
  flagged as provisional.
- `modules/Base/Lang/Administrator/AdministratorInstall.php:46` — open question on
  whether a module dependency is actually needed.
- `modules/Utils/Calendar/calendar-jq.js:1` — file-level note that ajax/iPad-dblclick
  handling is still Prototype-based; confirmed still uses `Event.observe(...,
  'dblclick', ...)` and `new Ajax.Request(...)` (ties into
  [legacy-js-migration.md](legacy-js-migration.md)).
