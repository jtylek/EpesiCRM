# CRM/Contacts/ParentCompany

## What it does

Adds a **Parent Company** field to Company records — a self-referential company picker
(a company can't be set as its own parent), placed right after the Phone field — plus a
**Child Companies** addon on the Company detail view that lists every company whose
Parent Company points back to the one you're viewing. Together they give a simple,
one-level parent/subsidiary hierarchy between companies.

## Why it exists

Many organizations track companies that are branches, subsidiaries, or franchises of
another company. This module lets you record that relationship directly and browse it
from either direction — parent to children via the addon, child to parent via the field
— without modeling a full org chart.

## Files

| File | Purpose |
|---|---|
| `ParentCompanyInstall.php` | Registers the "Parent Company" field and the "Child Companies" addon on `company`. `uninstall()` removes both. |
| `ParentCompany_0.php` | `parent_company_addon()` — renders the embedded RecordBrowser of child companies. |
| `ParentCompanyCommon_0.php` | `parent_company_crits()` — excludes the current company from its own Parent Company picker. |

## Installing / removing

Standard module lifecycle via the admin Setup screen or `console.php`. Requires
`CRM_Contacts`. Uninstalling removes the addon and the field — along with any Parent
Company values already stored on Company records — with no schema table or ACL
permissions to clean up.
