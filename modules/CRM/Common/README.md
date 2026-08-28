# CRM/Common

Shared infrastructure for the CRM package — not a screen of its own. It seeds the
`CommonData` lookup lists every CRM module's forms draw from (`CRM/Priority`: Low/Medium/High,
`CRM/Access`: Public/Public Read-Only/Private, `CRM/Status`: Open/In Progress/On Hold/
Closed/Canceled), and provides shared helpers used across CRM modules: the phone-dialing
integration (`method`/`callto` user setting plus any installed dialer module), the record
status filter dropdown reused by Calendar/Tasks/PhoneCall-style browsers, and the default
record priority/permission user settings.

This is also the module whose `simple_setup()` labels the whole group **CRM** on the admin
Setup screen — install it and its dependents (Calendar, Contacts, Tasks, PhoneCall, Mail,
Meeting, Fax, LoginAudit, Followup, Filters, Roundcube, ...) share that one package card.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
