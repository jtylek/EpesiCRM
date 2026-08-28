# CRM/Fax

## What it does

A standalone fax screen under the CRM menu for sending and receiving faxes,
cross-referenced against your Contacts and Companies. It doesn't implement fax transport
itself — it's a thin abstraction layer over pluggable "fax provider" modules, and shows
nothing ("No fax providers installed or configured.") until at least one is installed
and configured. Once a provider is available, `body()` shows tabbed Received / Current
Queue / Sent lists sourced from that provider, and every file's Attachment toolbar gains
a "Fax" action (`attachment_getters()`) that opens a send form letting you pick
recipients from Contacts or Companies by their fax number.

## Why it exists

Some industries (legal, healthcare, government) still rely on fax for compliance or
interoperability reasons. This module lets Epesi send/receive faxes from the same
screens users already work in — attachments, contacts, companies — instead of a
separate fax application, as long as a provider module is configured.

## Files

| File | Purpose |
|---|---|
| `FaxInstall.php` | Adds the "Fax - Browse" / "Fax - Send" ACL permissions, installs the module theme, and creates the data directory. `uninstall()` removes the permissions and theme. |
| `Fax_0.php` | `body()` — the tabbed Received/Queue/Sent screen; `send_file_tab()` and related methods handle sending. |
| `FaxCommon_0.php` | `menu()` adds the CRM sidebar entry (gated on "Fax - Browse"); `attachment_getters()`/`fax_file()` wire the "Fax" action into the generic Attachment toolbar. |

## Installing / removing

Standard module lifecycle via the admin Setup screen or `console.php`. Requires
`CRM_Contacts` (to resolve fax numbers to contacts/companies), `Base_Lang`, and
`Libs_QuickForm` — plus a working fax provider module to actually send or receive
anything. Uninstalling removes the two ACL permissions and the module theme; provider
data is unaffected, since this module itself keeps no fax records of its own.
