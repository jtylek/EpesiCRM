# How to add a proper mouseover tooltip to a RecordBrowser field

> **Status:** REFERENCE - recipe for adding a RecordBrowser column tooltip.

Recipe distilled from three repeat requests in one day (2026-08-12): Companies Browse's
"Company Name" column, Premium Tickets Browse's "Ticket ID" column, Contacts Browse's
"Last Name" column. All three had the exact same root cause and the exact same fix
shape — so before hand-rolling anything for a new field, check whether it fits this
pattern first. Full narrative/history is in [adminlte-theme.md](adminlte-theme.md)'s
Tooltips section; this file is the condensed "do this" version.

## Recognize the symptom

A RecordBrowser/GenericBrowser column (usually a recordset's "title" column — the one
that links to the full record) shows **no tooltip at all** on hover, while other
references to the same kind of record elsewhere in the app *do* show a rich card popup
(phones/email/address/...).

## Find the root cause

Check the field's `display_callback` in the module's `*Install.php`:

- If it's the generic `array('Utils_RecordBrowserCommon', 'display_linked_field_label')`
  — that's it. That function calls `create_linked_label_r()`, whose `$tooltip` parameter
  defaults to `false`. It renders the linked label with no popup, by design (it's the
  generic fallback, not wrong for fields nobody's asked about).
- Other generic-formatter callbacks can have the same gap — e.g. Ticket ID's
  `display_ticket_id()` called `create_linked_label_r()` the same way. Grep the
  callback's body for `create_linked_label_r(` / `create_linked_text(` and check whether
  a `$tooltip` arg is actually being passed.

## Fix it: reuse or add a `*_get_tooltip($record)` builder

Look for an existing one first — `CRM_ContactsCommon::company_get_tooltip()` /
`contact_get_tooltip()` already exist and are reused by every other company/contact
reference in the app (`ContactsCommon_0.php`). Don't build a second copy of the same
field list; a new field-specific tooltip (like Ticket ID's curated
Type/Date/Priority-only `ticket_id_get_tooltip()`) is only warranted when the existing
one's field list is wrong for this context.

A tooltip builder follows this shape:

```php
public static function xxx_get_tooltip($record) {
    if (!$record[':active']) return '';
    if (!Utils_RecordBrowserCommon::get_access('<tab>', 'view', $record)) return '';
    return Utils_TooltipCommon::format_info_tooltip(array(
        __('Label') => $record['field'],
        // ...
    ));
}
```

`format_info_tooltip()` already skips any row whose value is null/empty after
`strip_tags()`+`trim()` — **don't** hand-roll empty-field filtering in the caller, it's
handled once, centrally (fixed app-wide 2026-08-12; before that, blank `Fax:`/`Email:`
rows showed everywhere).

## Fix it: point the column at it

Add (or reuse) a display-callback wrapper that renders the column's own text but
attaches that tooltip via `create_linked_text()`'s `$tooltip` param as a
`array(callback, args)` pair instead of a plain bool:

```php
public static function xxx_format_default($record, $nolink=false) {
    if (is_numeric($record)) $record = self::get_record($record);
    if (!$record || $record=='__NULL__') return null;
    return Utils_RecordBrowserCommon::create_linked_text($record['field'], '<tab>', $record['id'], $nolink,
        array(array('Module_Common','xxx_get_tooltip'), array($record)));
}
```

(`CRM_ContactsCommon::company_format_default()` / `contact_lastname_format_default()`
are working examples of exactly this shape — copy one instead of writing from scratch.)

## Wire it up in two places — this is the step most likely to get skipped

The field's `display_callback` is **stored in the DB**, not read live from
`*Install.php`, in a table named `<tab>_callback` (`field`/`callback`/`freezed`
columns) — populated once at install time. Editing `*Install.php` alone only reaches
**fresh installs**.

1. **`*Install.php`** — change the field definition's `display_callback` to the new
   wrapper. Reaches fresh installs and this dev DB *if it gets reinstalled*, which it
   normally won't.
2. **A same-day patch** (see [[patches-identified-by-filepath-not-content]]) — a
   matched-old-value `UPDATE`, guarded so a value a user has since customized isn't
   clobbered:
   ```php
   DB::Execute('UPDATE <tab>_callback SET callback=%s WHERE field=%s AND freezed=1 AND callback=%s', array(
       'Module_Common::xxx_format_default',
       'Field Display Name',
       'Utils_RecordBrowserCommon::display_linked_field_label',
   ));
   ```
   This is what actually reaches this (and every other) existing install.

**Don't confuse this with the *other* callback-storage mechanism** hit the same day:
a `crm_contact`/`crm_company`-type *field* (a foreign-record picker, e.g. "Employees",
"Account Manager") stores its `format` callback pre-serialized inside `<tab>_field`'s
`param` string (built by `Utils_RecordBrowserCommon::new_record_field()` at install
time), not in a `<tab>_callback` row. That needs a *different* patch shape — a
matched-old-value `UPDATE <tab>_field SET param=... WHERE field=... AND param=...`
against the whole serialized string. If a patch's guarded `UPDATE` runs but `affected
rows == 0` on a dev DB you expect it to hit, check which of the two mechanisms actually
applies before assuming the patch itself is wrong — a drifted/atypical stored value on
one install is also possible (seen once: two fields on this dev DB had an empty label
segment where the pattern expected one).

## Judgment call: which fields actually warrant this

"Important fields" ⇒ in practice, so far: a browse table's own title/identifier column
that links to the full record (Company Name, Ticket ID, Last Name) — the column a user
is most likely to hover *instead of* opening the record. Not every column, and not
every `crm_contact`/`crm_company` reference field elsewhere in the app — those already
get a tooltip by default via `contact_format_no_company()`/`contact_format_default()`
unless deliberately suppressed (staff-picker fields like Employees/Account Manager/
Ticket Owner — see [adminlte-theme.md](adminlte-theme.md) and
[[patches-identified-by-filepath-not-content]]). When in doubt, ask which specific
column before building anything — this has been a one-field-at-a-time, user-named
request every time so far, not a "do it everywhere" ask.
