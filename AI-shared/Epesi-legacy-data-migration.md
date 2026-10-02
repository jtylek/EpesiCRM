# Legacy data migration: full production cutover

How `php artisan import:legacy` is actually run for a real cutover — clearing this app's own
CRM/communication data first so the legacy database's current state lands cleanly, without
touching settings (modules, Dashboard, roles, regional settings, login audit). The importer
architecture itself (`App\Services\LegacyImport\*`, one `Importer` subclass per tab) is documented
in code, not here — this doc is the operational recipe plus what a full re-run actually touches.

## Before running anything

**`.env`'s `LEGACY_DB_DATABASE` goes stale whenever the legacy install is renamed.** The legacy
checkout at `C:\xampp82\htdocs\bim.epesi.cloud` (renamed 2026-09-14 from `bim.epe.si`) had its
local MySQL database renamed to match on 2026-09-24 — `bim.epe.si` no longer exists as a
database. Check `SHOW DATABASES;` before trusting `.env` if it's been a while since the last
cutover.

**`LEGACY_DATA_DIR` must point at the legacy install's `data/` directory** (e.g.
`C:\xampp82\htdocs\bim.epesi.cloud\data`) for CRM/Mail's attachments and encrypted account
passwords, and for the Attachments module's files (below), to import. Without it, both importers
still run — they just skip files and report it.

## What `import:legacy all` refuses to run against

`ImportLegacyData::dirtyTables()` checks `users`/`companies`/`contacts`/`phone_calls`/`tasks`/
`meetings` for any row with `legacy_id IS NULL` — a record created directly in this app, not by a
previous import. If any exist, the whole `all` run (or any core tab) refuses, since importing on
top of them would misalign ids. This is a real safety net, not just a dev-fixture guard: a 2026-09
run found three real portal logins (see below) blocking it on a database that had been live for
weeks.

**This does not mean `migrate:fresh`.** That drops every table, including `modules`,
`dashboard_applets`/`dashboard_tabs`, roles/permissions, regional settings and the login audit —
it's for a throwaway dev database, not a live install. A real cutover truncates only the
CRM/communication tables and leaves settings alone.

## Rounding imported activity times

Time selectors default to five-minute increments. The importer rounds current Meetings,
Phone Calls and timed Task deadlines to the nearest five minutes; a meeting rounded past
midnight moves to the following date. Date-only task deadlines, history values and audit
timestamps retain their original values.

For already-imported records, `php artisan import:round-times` previews counts without
writing. `php artisan import:round-times --apply` saves original and replacement values under
the local storage disk's `legacy-time-rounding/` directory, then applies changes in a
transaction. Only records with `legacy_id` are considered, including soft-deleted records.
The adjustment preserves record timestamps, records changes in activity history and updates
relative reminders through the normal model events. A repeated run makes no further changes.

## The full-wipe recipe

1. **Back up first.** `mysqldump` the whole app database somewhere outside the checkout (e.g.
   `C:\DirectAdmin-backup\epesicrm_laravel_backup_<date>.sql`) before touching anything — the
   only real rollback path once records are truncated.
2. **Fix `.env`** (above) if needed.
3. **Resolve any non-legacy rows in `users`/`companies`/etc. individually first.** These are real
   local work (a manually-granted portal login, a hand-entered test record), not migration
   plumbing — inspect each one (`SELECT * FROM users WHERE legacy_id IS NULL`, etc.) before
   deciding whether to delete it. A portal login with no legacy counterpart (checked via the
   legacy contact's `f_login` column) has to be deleted to unblock the guard and recreated after
   import — record its role and linked contact first.
4. **Truncate the CRM/communication tables** — not `users` itself (Dashboard, regional settings
   and the login audit all FK to it), just the business data:
   - Core: `companies`, `contacts`, `tasks`, `meetings`, `phone_calls`, and their pivots
     (`company_contact`, `task_customer`, `task_customer_company`, `task_employee`,
     `meeting_customer`, `meeting_customer_company`, `meeting_employee`, `phone_call_employee`)
   - Mail: `epesi_mails`, `epesi_mail_accounts`, `epesi_mail_threads`, `epesi_mail_addresses`,
     `epesi_mail_attachments`, `epesi_mail_links`, `epesi_mail_account_folders`,
     `epesi_roundcube_tickets` (FKs to mail accounts)
   - Projects/Tickets: `epesi_projects`, `epesi_tickets` and their pivots
     (`epesi_project_customer_companies`, `epesi_project_customer_contacts`,
     `epesi_project_employees`, `epesi_ticket_assignees`, `epesi_ticket_customer_companies`,
     `epesi_ticket_customer_contacts`, `epesi_ticket_required`)
   - Attachments: `epesi_attachments`, `epesi_attachment_links` (no legacy importer covered these
     until this session — see below)
   - Record-scoped convenience/derived data that goes stale the moment ids change: RecordBrowser
     `epesi_recordbrowser_favorites`/`_recent`, Watchdog `epesi_watchdog_subscriptions` (**not**
     `epesi_watchdog_category_subscriptions` — that's category-level, not tied to a record),
     `epesi_reminders`/`epesi_reminder_recipients`, `epesi_priority_list_entries`
   - `activity_log`, filtered to `subject_type IN ('company','contact','task','meeting',
     'phone_call','mail','attachment')` — **not** a blanket truncate, since `module`/`user`/
     `custom_field` history is infrastructure, not CRM data
   - Wrap in `SET FOREIGN_KEY_CHECKS=0` / `=1` (or Laravel's
     `Schema::withoutForeignKeyConstraints()`), `TRUNCATE` rather than `DELETE` so auto-increment
     resets and ids come out low and clean again.
   - **Left alone:** `modules`, `dashboard_applets`/`dashboard_tabs`, `roles`/`permissions`/
     `model_has_*`, `epesi_regional_settings`, `common_data`, `custom_fields` (field
     *definitions*, not values), `login_audits`, the embedded Roundcube webmail's own `rc_*`
     tables (a live mailbox client, unrelated to the archived-mail import), Store Server data,
     `epesi_shoutbox_messages` (not record-scoped), `stored_files`/`stored_file_contents` (orphaned
     blobs are harmless; not swept automatically).
5. **Run `php artisan import:legacy all`.** Runs `commondata` first, then the six core tabs, then
   whatever modules registered (`mail`, `projects`, `tickets`, `attachments` as of this session) —
   see `ImportLegacyData::importers()`.
6. **Run `php artisan import:legacy attachments` again, on its own, after step 5.** The
   Attachments importer needs `projects`/`tickets` already imported to link notes attached to a
   Project or Ticket, but `ImporterRegistry` only orders tabs `first`-before-core vs.
   after-core — it doesn't order the after-core tabs relative to each other, and that order
   depends on which module's service provider happens to `boot()` first. The importer is
   idempotent (`links()->delete()` then recreates every run), so a second pass after `all` has
   finished costs little and resolves anything the first pass's ordering missed. It reports how
   many links it couldn't resolve, so this is checkable rather than assumed.
7. **Recreate any portal logins deleted in step 3**, same email and role, linked back to the
   re-imported contact (`contacts.user_id`). Don't invent a password for a real external
   account — use the app's own password-reset flow.
8. **Spot-check**, the same way every importer session before this one has: row counts against
   the legacy tables, a couple of `information_schema`-level checks, the actual Filament UI for a
   record that has history, mail, and attachments.
9. **Check the real staff logins still have their real email addresses** — see the
   `UsersImporter` bug below. Fixed 2026-09-27, but re-verify after any future run touching the
   `users` tab, since this is exactly the kind of regression that looks fine until someone can't
   log in.

## `UsersImporter` clobbered real logins with `@imported.invalid` (found and fixed 2026-09-27)

`users` is a `MIGRATED_TABLES` core tab, upserted by `legacy_id` like everything else — but
unlike every `Importer`-subclass tab, `UsersImporter` doesn't extend `Importer`, and before this
fix it unconditionally recomputed **both** `name` and `email` from the legacy row on every run,
update included. Legacy's `user_password.mail` was blank for most real staff logins on this
install — their working emails (`j@epe.si`, `k@epe.si`, `abukowski@telaxus.com`, …) had been set
by hand in this app after the very first import, never sourced from legacy at all. A later
re-import silently overwrote all of them with the `<login>@imported.invalid` fallback (and
`name` down to the bare login slug, e.g. "Janusz Tylek" → "jasiek") — a real login-lockout risk
that only surfaced because a concurrent session's live testing happened to print an unexpected
`@imported.invalid` address in an unrelated check right after this run.

**Fixed** in `app/Services/LegacyImport/Importers/UsersImporter.php`: `name` and `password` are
now set only when the user is newly created; `email` is only replaced when legacy actually has a
non-blank `mail` to offer, never merely because the local value doesn't match what legacy would
compute — the same "don't let an import downgrade a better local value" principle
`Importer::withoutTakenValues()` already applies to unique columns elsewhere. Recovering the
already-clobbered emails this session was a manual restore from the pre-cutover `mysqldump`
backup (imported into a throwaway database, `UPDATE ... JOIN`, dropped after) — there was no
other copy of the real addresses anywhere.

## The Attachments importer (built 2026-09-27)

`modules/Epesi/Attachments/src/LegacyImport/AttachmentsImporter.php`, tab `attachments`. Ports
the legacy `utils_attachment` recordset (title/note/files/"Attached to", Epesi's generic
note-and-file attachment — CRM/Mail's own attachments are a separate thing, already covered by
`import:legacy mail`). Extends the shared `App\Services\LegacyImport\Importer`, so it gets
upsert-by-`legacy_id` and edit-history replay for free; `files` and `attached_to` are import-only
(no history), matching every other importer's pivots.

**Files**: `f_files` is a multiselect of `utils_filestorage.id`s. Resolved through
`utils_filestorage`/`utils_filestorage_files` to a sha512 hash, read from
`<LEGACY_DATA_DIR>/Utils_FileStorage/<FileStorage::pathFor($hash)>` — confirmed the legacy
on-disk layout matches this app's own `App\Services\FileStorage::pathFor()` exactly (both are the
same Utils_FileStorage design), then copied in via `FileStorage::putFile()`, same as CRM/Mail's
attachments.

**Links**: `f_attached_to` is a dual-recordset multiselect (`"company/5"`, sometimes several
tokens on one note — Follow-up leaves the same note on both ends of a trace). Only recordsets this
app has ported get a real link (`contact`, `company`, `task`, `crm_meeting`, `phonecall`,
`premium_projects`, `premium_tickets` — the legacy recordset names, not this app's own morph
aliases). Confirmed live against the real `bim.epesi.cloud` dump (3,854 attachments): **roughly
two-thirds are attached to recordsets not ported yet** —

| Legacy recordset | Count | Ported? |
| --- | ---: | --- |
| `premium_expense` | 1,686 | no |
| `premium_tickets` | ~709 | **yes** |
| `premium_invoice` | 515 | no |
| `company`/`task`/`phonecall`/`contact`/`crm_meeting` | 604 combined | yes |
| `premium_payments_entries` | 140 | no |
| `premium_salesopportunity` | 102 | no |
| `premium_projects` | ~80 | **yes** |
| `premium_knowledgebase_thread` | 27 | no |
| `premium_vacation` | 8 | no |
| `premium_vehicles_trip` | 1 | no |

A note attached only to an unported recordset is still imported in full (title, note, files) —
there's simply nowhere to link it yet, since `epesi_attachment_links.attachable_id` isn't
nullable. Counted per recordset name in the run's warnings rather than silently dropped, so this
table is checkable directly from `import:legacy attachments`'s own output, not just from here.
Revisit once any of Expenses/Invoice/Payments/Sales Opportunity/Knowledge Base/Vacation/Vehicles
gets ported — nothing about the importer needs to change, just add its morph alias to
`AttachmentsImporter::TARGETS`.

**Dry-run verified 2026-09-27 against the real `bim.epesi.cloud` dump**: all 3,854 rows import
cleanly (`created 3854, updated 0, history rows 4852`), one file missing from the legacy data
directory (reported, not fatal), 805 links unresolved on that run because `projects`/`tickets`
hadn't been imported yet in the same dry run — expected, see step 6 above.
