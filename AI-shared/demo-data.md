# Demo data generation via `console.php`

> **Status:** REFERENCE - the demo:generate:* console commands and their gotchas.

Five commands, `modules/../console/Demo/Generate*Command.php` (namespace `Epesi\Console\Demo`,
registered in `console.php`), use `Faker` to seed a dev install with realistic-looking records:

- `demo:generate:contacts [--count=N] [--create-company] [--employees=N]`
- `demo:generate:phonecalls [--count=N]`
- `demo:generate:meetings [--count=N]`
- `demo:generate:tasks [--count=N]`
- `demo:generate:shoutbox [--count=N]`

**There is no `demo:generate:companies`** — companies are created only as a side effect of
`demo:generate:contacts --create-company`. (Step 3 below named one for a while; it never existed.)

**The generated data is not reproducible.** `\Faker\Factory::create()` is called with no seed, so every
run produces different records. Fine for eyeballing a dev install, not fine for anything asserting on
specific values — adding a `--seed=N` option is the obvious fix if that ever matters.

## Realism constraints on generated records (2026-09-01)

Faker's raw output is not shaped like real CRM data, so three commands constrain it. Both helpers are
traits in `console/Demo/`, used by meetings/phonecalls/tasks:

- **`BusinessHours`** — Faker's `dateTimeBetween()` gives a useful *date* but a time-of-day drawn
  uniformly across 24h, which produced demo calendars full of 03:47 meetings, calls and deadlines.
  Times are now placed in a **09:00–20:00** window on **15-minute** boundaries. For meetings the
  duration is chosen first and the window holds the *whole* meeting, so a 3h meeting starts by 17:00.
- **`ShortTitle`** — `sentence(4)` routinely ran past 40 characters and wrapped in grid rows. Titles
  and subjects are capped at **30 characters**, trimmed on word boundaries so they still read as
  sentences rather than being cut mid-word.
- **Meeting durations** are 1h–3h in half-hour steps (`GenerateMeetingsCommand::DURATIONS`), up from
  the old 30min/1h/2h.
- **Employee `title`** uses Faker's `jobTitle` ("Sales Manager"), not `title` ("Prof.") — the contact
  recordset's Title is a free-text position field, not an honorific. The customer-contact path still
  uses `title`; worth changing too if demo customers ever need to look right.

Both traits use **static properties rather than constants**: constants in traits require PHP 8.2 and
this app supports 8.1+ (see `environment-gotchas.md` on the floor).

All four run as user id 1 (`Acl::set_user(1)`) so `Utils_RecordBrowserCommon::new_record()`'s
`created_by` binding has a real value in a CLI context, which never has a session.

## Employees vs. Customers - these tools never create employees

Found 2026-08-27 generating phonecalls/meetings/tasks against a fresh install seeded with
100 random demo contacts/companies: every generated record's **Employees** field showed a
crossed-out-eye icon next to each name in the UI - present, but not actually valid.

**Root cause.** `CRM_PhoneCallCommon`/`CRM_MeetingCommon`/`CRM_TasksCommon::employees_crits()`
restrict the real "Employees" picker to contacts belonging to *your own* company - specifically
`f_company_name = X OR f_related_companies LIKE '%__X__%'`, where `X` is
`CRM_ContactsCommon::get_main_company()` (your own contact's `company_name`, i.e.
`CRM_ContactsCommon::get_my_record()['company_name']`). The three generator commands originally
picked "Employees" from the same random pool used for "Customers" (any demo contact, regardless
of company) - technically insertable via `new_record()` (which does no crits validation, only the
real QuickForm picker does), but not a contact the app itself considers an employee.

**The fix, and the intended workflow it assumes**: the three generators now query for that same
`company_name`/`related_companies` match before generating anything, and **hard-fail** with a
clear message if it comes back empty - they never fall back to "any contact," and never create an
employee themselves. The expected setup order on a fresh install is:

1. Create your own company and your own contact by hand (or through normal first-run setup).
   Still manual: `get_main_company()` derives your company from *your own contact's*
   `company_name` (the contact whose `login` field is your user id), so nothing can create it
   until that record exists. Automating it in FirstRun has been proposed but not built.
2. `demo:generate:contacts --employees=10` (added 2026-09-01) fills the employee pool - contacts
   with `company_name` set to your own company, which is the only thing `employees_crits()`
   actually requires. This replaces the old "clone your own contact by hand" step; RecordBrowser's
   **Clone** feature still works if you prefer it.
3. *Then* run `demo:generate:contacts --create-company` (customers - any company, unrelated to yours)
   and `demo:generate:phonecalls`/`:meetings`/`:tasks` (which will now only ever assign employees
   from step 2, picking 1-2 at random per record).

`--employees=N` and `--count=N` are independent, so `--count=100 --employees=10` seeds both pools in
one run. `--employees` on its own generates *only* employees - it does not also emit the single
customer contact that a bare `demo:generate:contacts` still defaults to.

Re-running the generators later (e.g. after adding more employees) picks up the larger pool
automatically - the query runs fresh every time, nothing is cached.

**Demo contacts must never create real logins.** `demo:generate:contacts` used to have a
`--create-user` flag (QuickForm's `login`/`username`/`set_password` keys on the `contact`
recordset, which `CRM_ContactsCommon::submit_contact()` turns into a real `base_user` row) - this
has been **removed outright**, not just left unused, because a demo/customer contact getting a
real login account is a security-relevant mistake, not a cosmetic one. `--create-company` is still
fine to use for customer contacts - it only creates a `company` record (a client, not an
employee-eligible one), no login involved.

## `phone` on a `phonecall` record is a *selector*, not a phone number

`CRM_PhoneCallCommon::display_phone()` reads the `phonecall` recordset's `phone` field (type
`integer`) as an index into whichever contact/company `customer` points at - **not** digits to
render directly:

| `phone` value | source field                              |
|---|---|
| 1 | `customer`'s `mobile_phone` (contact only) |
| 2 | `customer`'s `work_phone` (contact only)   |
| 3 | `customer`'s `home_phone` (contact only)   |
| 4 | `customer`'s own `phone` (company only)    |

If the selected field on the actual customer is empty, `display_phone()` falls back to `---`. The
demo generator picks a customer/`phone` pair together (never a selector the chosen customer
doesn't actually have data for) by first fetching each candidate contact's three phone columns (or
a company's one `phone` column) and only offering selectors backed by a non-empty value.

## Checkbox fields: pass `0`/`1`, never a PHP `bool`

`Utils_RecordBrowserCommon::new_record()` calls `trim()` on every non-array field value before its
`is_bool()`-to-`0`/`1` coercion runs (`RecordBrowserCommon_0.php` ~line 1425) - `trim(false)`
casts to `''` first, so the later `is_bool()` check never fires and `''` gets bound against the
column's `%d` placeholder, throwing `Argument N is not number(%d)`. Passing the checkbox as a
plain `0`/`1` integer sidesteps the coercion order entirely. Hit adding `task`'s `longterm` field
to `demo:generate:tasks` - leaving it unset instead (relying on `new_record()`'s
"skip missing/empty keys" behavior) is *also* wrong, but silently: the column's own DB default
turned out to be `1`, so every generated task showed "Longterm: Yes" regardless of intent.

## Other conventions

- Generated dates (Phonecalls' `date_and_time`, Meetings' `date`/`time`, Tasks' `deadline`) are
  randomized across **today ± 30 days**, not clustered around "now" - deliberate, so demo lists
  have a mix of past/present/future records to browse/sort/filter by.
- Meeting's `time` field is a *second*, independent DATETIME column from `date` (not a derived
  display of it) - store it as `'1970-01-01 H:i:s'` (the exact convention
  `CRM_MeetingCommon::crm_event_update()` itself uses), not a bare `H:i:s` string.
- `Utils_RecordBrowserCommon::new_record()` bypasses QuickForm entirely, but **not** each
  recordset's registered processing callback (`submit_contact`/`submit_phonecall`/
  `submit_meeting`/`submit_task` - registered via `register_processing_callback()`) - it's called
  once with `mode='add'`. Before adding a new field to any of these generators, read that
  recordset's `submit_*($values, $mode)` `case 'add':` (and anything it falls through from) to
  make sure nothing there silently rewrites/requires a key your raw `$values` array doesn't set -
  e.g. `submit_meeting()`'s `'add'` case only recomputes `date`/`time` when `$values['modded']` is
  set, which direct `new_record()` calls never do, so raw values pass straight through unchanged
  (safe here, but wouldn't be for every field on every recordset).
