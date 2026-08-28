# CRM/Contacts/AccountManager

## What it does

Adds an **Account Manager** field to Company records: a contact/employee picker
(`crm_contact` type) restricted to employees of your own company and its related
companies. It shows up as a normal filterable field/column on the Companies list and
detail view — no separate screen. When a user browses the Companies list,
`browse_mode_details()` defaults new records' Account Manager to "myself", and if the
user setting *"Account Manager - default set to Perspective"* is enabled, it pre-filters
the list to companies where the current user is the account manager.

## Why it exists

Once a company list grows past a handful of accounts, someone needs to own the
relationship with each one. This module gives every company a designated point of
contact within your own organization, and a one-click way for each account manager to
see just "their" companies — without building a separate ownership/territory system.

## Files

| File | Purpose |
|---|---|
| `AccountManagerInstall.php` | Registers the "Account Manager" field on `company` and the `browse_mode_details` callback; `uninstall()` removes both. |
| `AccountManager_0.php` | `browse_mode_details()` — defaults new company records to the current user and applies the "Perspective" filter default. |
| `AccountManagerCommon_0.php` | `crits_accountmanager()` restricts the picker to your own company's employees; `user_settings()` adds the "Perspective" toggle. |

## Installing / removing

Standard module lifecycle via the admin Setup screen or `console.php`. Requires
`CRM_Contacts`. Uninstalling deletes the field and the callback registration — no schema
table or ACL permissions are added, so there's nothing else to clean up.
