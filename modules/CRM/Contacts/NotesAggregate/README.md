# CRM/Contacts/NotesAggregate

## What it does

Adds a **Related Notes** section (a RecordBrowser addon) to Contact, Company, and Sales
Opportunity records, pulling together the notes/attachments of everything related to
that record. For a Company, that means its own contacts plus any Meetings, Tasks, Phone
Calls, and Sales Opportunities linked to it (each check only runs if that module is
actually installed); Contact and Sales Opportunity records aggregate their own set of
related records the same way. Everything shows up as a single multi-group attachment
browser, so you don't have to open each related record just to read its notes. A user
setting ("Include Record Notes in Aggregate" / `show_all_notes`) can also fold the
record's own native notes into the same view.

## Why it exists

Notes about a company are often actually attached to its contacts, meetings, or deals
rather than the company record itself, which normally means clicking into each one just
to catch up. This module gives a single "everything anyone has written about this
account" view.

## Files

| File | Purpose |
|---|---|
| `NotesAggregateInstall.php` | Registers the three "Related Notes" addons (`contact`, `company`, `premium_salesopportunity`). `uninstall()` removes all three. |
| `NotesAggregate_0.php` | `contact_addon()` / `company_addon()` / `salesopportunity_addon()` — gather the relevant attachment groups from related records and display them via `Utils_Attachment` in multi-group mode. |

## Installing / removing

Standard module lifecycle via the admin Setup screen or `console.php`. Requires
`Utils_RecordBrowser` and `CRM_Contacts`. Uninstalling just removes the three addons —
no data is deleted, since the module only aggregates notes that already live on other
records.
