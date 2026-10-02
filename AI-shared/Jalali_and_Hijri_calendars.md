# Jalali and Hijri calendars

## Goal

Let each user choose Gregorian, Jalali (Solar Hijri), or Hijri dates without changing the
calendar system used to persist CRM data. The initial Hijri choices are Umm al-Qura and
tabular/civil Hijri. Local moon-sighting calendars are not included: they require a defined
regional authority or maintained observation data, not just a different conversion algorithm.

Calendar choice is independent of interface language. Persian (`fa`) language support and
right-to-left layout are related rollout requirements, but neither should silently select a
calendar for a user.

The one exception is demo mode (`DEMO_MODE`), where only demo visitors get an automatic
switch: choosing Arabic gives the Umm al-Qura Hijri calendar and choosing Persian gives Jalali.
The demo accounts are shared by all visitors, so this is applied per request in
`RegionalSetting::effective()` (`DEMO_CALENDARS`) and never stored. Real installations are
unaffected.

## Current implementation status

### In place

- Regional settings provide per-user Gregorian, Jalali, or Hijri selection, with Umm al-Qura
  and tabular/civil Hijri variants. Existing and system-default settings remain Gregorian.
- `CalendarDateConverter` converts between Gregorian storage values and the selected calendar
  using PHP `IntlDateFormatter`. Shared RecordBrowser date fields convert on display and form
  submission; dates remain Gregorian in storage.
- The CRM FullCalendar supports alternate-calendar month navigation, labels, and date entry.
  Its browser-side picker uses canonical ASCII `YYYY-MM-DD` values, accepts Persian and
  Arabic-Indic numerals on entry, and maps dates back to Gregorian for event operations.
- The shared PHP date converter also normalizes Persian and Arabic-Indic numerals before parsing
  alternate-calendar input. Focused PHP and JavaScript tests cover both digit sets.
- Meeting date-time forms, fixed-time reminders, and follow-up scheduling use calendar-aware
  date-time inputs in alternate-calendar modes and retain Gregorian/UTC persistence. Reminder
  dates in the addon table and dashboard widget also use the user's regional date formatter.
- Mail date filters accept the selected calendar and dehydrate to Gregorian query dates; mail
  list and detail dates use the user's calendar and timezone.
- Umm al-Qura is explicitly limited to Hijri years 1300-1600, matching
  [ICU's tabulated data](https://github.com/unicode-org/icu/blob/release-71-1/icu4c/source/i18n/islamcal.cpp).
  ICU otherwise falls back to civil arithmetic outside those years; PHP conversion rejects that
  fallback, and the browser picker rejects out-of-range dates and displays a range notice.
- Jalali and tabular/civil Hijri conversion is limited to Gregorian storage dates from
  1000-01-01 through 9999-12-31, matching the MySQL/MariaDB `DATE` range; Gregorian-mode
  conversion is unchanged. PHP and browser tests cover both endpoints.
- Attachment Notes date-range filters accept the selected calendar and dehydrate to Gregorian
  query dates; the table-level inclusive range test passes with the PHPUnit root URL override.
- Filament's Persian translation sets the document direction to RTL at the shared layout root.
  This confirms the framework hook is present, but does not verify page-level RTL behavior.
- Focused PHP and JavaScript tests pass for conversion, regional settings, and browser-side
  round trips. PHP and browser ICU results also matched on six sampled Gregorian dates. This
  sample does not establish the full supported range or parity across deployment ICU versions.
- Additional PHP and browser regression cases now cover Jalali leap-year transitions, Hijri
  month boundaries, invalid month days, and a date where Umm al-Qura differs from civil Hijri.
  They pass on local PHP ICU 71.1 and Node ICU 78.3; deployment ICU versions still need checking.
- A direct Edge 154 browser probe matched all 13 Jalali, Umm al-Qura, and civil Hijri reference
  fixtures, including the supported storage endpoints and Umm al-Qura table bounds. This verifies
  the local browser runtime only; deployed browser/ICU combinations still need checking.
- A regional-timezone regression confirms date-only Jalali values retain their stored day while
  timestamps render on the user's local day and time.
- Focused tests now pass for Attachment Notes date filtering, mail date filters, Meeting and Task
  calendar-aware inputs, fixed reminders, and Jalali follow-up meeting scheduling. The test 404 was
  caused by the inherited `APP_URL` pointing at a subdirectory; PHPUnit now forces the root URL via
  a server variable. Calendar-aware custom validators use Laravel's `ClosureValidationRule` so
  Filament does not evaluate them as component closures.
- The most recent full-suite run completed 615 tests successfully with 2 incomplete and 24
  failures, concentrated in password-reset/notification flows reporting rate limits or missing
  items. No calendar-focused test was listed among the failures.
- Recent-visit timestamps, shared record-info timestamps, History timestamps, and Shoutbox
  timestamps now use the selected calendar. Reminders and CRM date fields already used the
  regional formatter. Watchdog timestamps are relative; no human-readable report/export date
  surface was found in the current modules.
- A browser-only RTL-direction preview of Regional Settings at desktop (1600px) and mobile
  (390px) showed mirrored navigation, labels, fields, and Save placement without horizontal
  overflow. The account remained English, so this is not a Persian-locale or translation review.
- The CRM calendar was also previewed with an in-memory Persian locale, Jalali calendar, and RTL
  document direction before FullCalendar initialized. Week headers, ordering, and controls rendered
  RTL at desktop and mobile without page overflow or overlapping controls. Event text truncates in
  narrow mobile columns; surrounding app navigation and copy remained English, so native Persian
  translation review is still needed.
- The Companies list and create form were previewed with RTL document direction at desktop and
  mobile sizes. Columns and form labels/controls mirror without viewport overflow; the mobile list
  keeps its own horizontal table scrolling. Copy remained English, so this is structural RTL
  coverage, not a Persian translation review.

### Remaining

- Verify the defined ranges against the ICU versions used in deployment; local coverage now
  includes endpoints, leap-year transitions, month boundaries, and invalid dates for all modes.
- Persian (`fa`) is an available interface language (community strings imported from old Epesi,
  the rest DeepL machine translations in `fa.machine.json`; needs a native-speaker review).
- Verify actual Persian-locale layout across the remaining panels and surfaces, especially dialogs
  and tooltips, and review the mobile calendar's truncated event labels. Calendar locale/Jalali
  settings were injected into its response without changing the account language. Keep language
  and calendar preferences independent.
- Verify localized-digit normalization across date-entry surfaces outside the shared RecordBrowser
  fields, CRM calendar picker, Meeting form, reminders, follow-up action, and mail filters.
- Continue checking any new date-entry/display surfaces. Attachment Notes filters convert input
  to Gregorian query dates; notification timestamps are relative, and no human-readable report
  or export date surface was found. Date-only timezone stability now has regression coverage.
- Have a Persian speaker review the translated experience in the running application at desktop
  and mobile sizes.

## Preferences and date semantics

- Add a per-user calendar preference with Gregorian, Jalali, and Hijri options. When Hijri is
  selected, a second preference selects Umm al-Qura or tabular/civil Hijri.
- Keep all persisted dates and timestamps Gregorian. Parse a user's selected-calendar input
  into Gregorian values before validation and persistence; format Gregorian values for display.
- Apply the user's timezone to timestamps as today, then render their date in the selected
  calendar. Date-only values are calendar days and must not shift across timezones.
- Keep machine-readable exports and integrations in their existing Gregorian representation.
  Human-readable reports may use the user's selected calendar where appropriate.
- Define and test the supported conversion range. Do not silently fall back to Gregorian or
  another Hijri variant for dates outside it.

## Implementation plan

1. **Prove the libraries and calendar UI.** Verify that the chosen conversion implementation
   produces Jalali, Umm al-Qura, and tabular/civil dates consistently in PHP and JavaScript, or
   define one authoritative conversion boundary. Check supported years, leap rules, and
   deployment requirements. Prototype alternate-month navigation in the existing FullCalendar
   page and confirm that Filament date inputs can be adapted without persisting localized
   strings. This is the main feasibility risk; locale formatting alone does not add another
   calendar system.
2. **Add a conversion boundary and tests.** Centralize Gregorian-to-calendar formatting and
   calendar-to-Gregorian parsing. Cover fixed reference dates, round trips, month/year changes,
   leap boundaries, invalid dates, and supported-range behavior for all three calendar modes.
3. **Add user preferences.** Extend RegionalSettings for calendar and Hijri variant, including
   defaults and a user-facing selector. Preserve Gregorian as the default for existing users.
4. **Adapt shared date entry and display.** Integrate conversion through shared RecordBrowser
   field customization and Filament date inputs, not isolated per-resource fixes. Cover form
   defaults, validation messages, table columns, filters, record views, and user-facing date
   summaries. Keep the serialized form values Gregorian at the persistence boundary.
5. **Adapt the CRM calendar.** Make its month and year navigation, date labels, date selection,
   and event create/reschedule flows operate correctly in the selected calendar while retaining
   Gregorian event data and existing timezone behavior.
6. **Complete Persian and regression coverage.** Add Persian (`fa`) through the normal
   translation workflow and verify right-to-left rendering separately from calendar selection.
   Audit reminders, notifications, reports, and exports; test Gregorian users and all three
   calendar modes.

## Verification criteria

- A user can choose Gregorian, Jalali, Umm al-Qura, or tabular/civil Hijri without changing
  another user's preference.
- A date entered in any supported calendar is stored as the corresponding Gregorian date and
  displays back as the same entered calendar date.
- Date-time values remain the same instant across timezone conversion; date-only values remain
  the same day.
- Calendar navigation and event create/reschedule flows work across month and year boundaries
  in each mode.
- Existing Gregorian behavior, APIs, and machine-readable exports remain unchanged.
- Tests cover reference conversions, leap/month boundaries, invalid inputs, supported ranges,
  settings persistence, and the shared date entry/display paths.

The initial planning estimate is three to five developer weeks, subject to the feasibility spike
for the conversion implementation, FullCalendar navigation, and Filament date inputs.
