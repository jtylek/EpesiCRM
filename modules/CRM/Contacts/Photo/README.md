# CRM/Contacts/Photo

## What it does

Lets you attach a photo to each Contact. Install swaps the Contact record's template for
one that reserves a spot for a photo/placeholder (`Utils_RecordBrowserCommon::set_tpl`)
and registers a processing callback that injects the photo — or a "click to change"
placeholder — into every Contact view. Clicking it opens a small upload screen
(`CRM_Contacts_Photo::body()`) built on `Utils_FileUpload`; uploaded images are
thumbnailed to 600x600 and stored in the module's own data directory, with the mapping
from contact to filename kept in a dedicated `contact_photos` table.

## Why it exists

A face next to a name makes a Contacts list easier to scan and recognize, especially for
sales/support teams juggling many contacts. This is an optional, low-overhead way to add
that without changing the core Contact schema.

## Files

| File | Purpose |
|---|---|
| `PhotoInstall.php` | Installs the module theme, swaps the Contact template, registers the processing callback, creates the data dir and the `contact_photos` table. `uninstall()` reverts the template and drops the table. |
| `Photo_0.php` | `body()` — the upload/clear screen; `submit_attach()` validates the extension, thumbnails and stores the file; `clear_photo()` removes it. |
| `PhotoCommon_0.php` | `submit_contact()` — the processing callback that injects `photo_src`/`photo_link` into the Contact template; `add_photo()`/`get_photo()`/`del_photo()` manage the `contact_photos` table. |

## Installing / removing

Standard module lifecycle via the admin Setup screen or `console.php`. Requires
`CRM_Contacts` and `Utils_Image`. Uninstalling reverts Contact records to the stock
template, drops the `contact_photos` table, and removes the module's data directory —
so any uploaded photo files go with it.
