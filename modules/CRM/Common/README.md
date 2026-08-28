# CRM/Common

**CRM** is Epesi's customer-relationship-management bundle — installing this module (or any of its
dependents) puts one package card, labeled **CRM**, on the admin Setup screen:

- Calendar
- Contacts
- Tasks
- PhoneCall
- Mail
- Meeting
- Fax
- LoginAudit
- Followup
- Filters
- Roundcube

**Calendar** is a shared scheduling/events organiser. **Contacts** manages contact and company records — the
heart of the CRM. **Tasks** is a to-do/checklist module. **PhoneCall** logs phone calls against contacts.
**Mail** provides mail accounts and an archive applet for sent/received mail. **Meeting** schedules meetings.
**Fax** is a fax send/receive abstraction layer. **LoginAudit** keeps a login audit log. **Followup** lets you
schedule a follow-up phone call, meeting, or task directly from another record, cross-linking notes between
the two. **Filters** adds the shared "My records" quick filter and saved filter presets used across CRM's
browsers. **Roundcube** bridges Epesi to a Roundcube webmail install.

**CRM/Common** is shared infrastructure for the package — not a screen of its own. It
seeds the `CommonData` lookup lists every CRM module's forms draw from (`CRM/Priority`: Low/Medium/High,
`CRM/Access`: Public/Public Read-Only/Private, `CRM/Status`: Open/In Progress/On Hold/Closed/Canceled), and
provides shared helpers used across CRM modules: the phone-dialing integration (`method`/`callto` user
setting plus any installed dialer module), the record status filter dropdown reused by
Calendar/Tasks/PhoneCall-style browsers, and the default record priority/permission user settings.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
