# CRM/Contacts/County

## What it does

Adds a **County** field (Company and Contact) and a **Home County** field (Contact),
each a cascading dropdown chained to the existing Country → Zone/State fields via
Epesi's `commondata` reference data. A custom form-field callback (`QFfield_county`)
keeps the county list scoped to whichever country/zone is currently selected, the same
way the built-in Zone field works. The fields are inserted right next to their matching
Zone/Home Zone fields, and install also nudges the "Birth Date" field's position so
everything lands in a sensible order on the Contact form.

## Why it exists

Epesi's core Contact/Company address fields stop at Country and Zone/State; some
countries (the US foremost) organize addresses one level further, by county. This
module adds that level for organizations that need it, without forcing it on everyone
else.

## Files

| File | Purpose |
|---|---|
| `CountyInstall.php` | Registers the three `commondata` fields (`company.County`, `contact.County`, `contact.Home County`) chained to Country/Zone; reorders `contact_field` positions on install. `uninstall()` removes the three fields. |
| `CountyCommon_0.php` | `QFfield_county()` — the form-field callback that renders the county dropdown scoped to the selected country/zone. |

## Installing / removing

Standard module lifecycle via the admin Setup screen or `console.php`. Requires
`CRM_Contacts`. Uninstalling deletes the three fields — along with any County/Home
County data already stored on records — with no separate schema table or ACL
permissions to clean up.
