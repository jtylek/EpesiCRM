# EPESI

Free, open source CRM and a base for building custom ERP applications. Self-hosted, MIT licensed,
no per-seat fees.

**Website:** https://epesicrm.com

Epesi stores business information in one place and shares it between the people in a company or
organization: contacts, companies, tasks, meetings, phone calls, a shared calendar, and an
integrated e-mail client. Records are secure, organized and easy to find, so day-to-day
communication and workflow get simpler.

Epesi 2 is a ground-up rewrite of the original Epesi (2006), built on **Laravel 12**,
**Filament 5** and **Livewire**.

## Features

- **Core CRM**: Companies, Contacts, Phone Calls, Tasks and Meetings, with a shared Calendar
  merging events from every module.
- **Row-level permissions**: records are visible and editable when they are public, yours, or
  your company's. Roles: super admin, manager, employee.
- **Full record history**: every change to every field is tracked.
- **Custom fields**: administrators add their own fields to any record type.
- **E-mail**: the Roundcube webmail embedded in Epesi (optional, downloaded on request).
- **Modules**: features are installed, enabled and disabled from the administration panel, with
  no code deploy. Modules can be browsed and installed from the Epesi Store.
- **Administration panel**: users, roles, modules, login audit, database update.
- **Languages**: English and Polish; more can be imported from the old Epesi translations.
- **Migration**: import your data, with its full edit history, from a legacy Epesi installation.

## Requirements

- PHP 8.2 or newer (the setup wizard checks the extensions)
- MySQL or MariaDB
- Apache or nginx; shared hosting works
- A modern web browser

No Composer, Node or command line is needed to install from the release zip.

## Installation

1. Download the latest `epesi-<version>.zip` from this page.
2. Unpack it into a folder on your web server, e.g. `htdocs/epesi`.
3. Create an empty database (e.g. in phpMyAdmin).
4. Open the site in a browser. The setup wizard asks for a one-time setup code (found in
   `storage/app/setup-code.txt`), checks the server, creates the tables, and sets up the
   administrator account, optional demo data and e-mail.

See `INSTALL.md` in the zip for details.

## Updating

Back up the folder and the database, unpack the new release over the old files, then open
Administration → Database update (or run `php artisan epesi:update`).

## Support and source

- Website and demo: https://epesicrm.com
- Source code: https://github.com/jtylek/EpesiCRM (branch `laravel`)
- Questions and bug reports: https://github.com/jtylek/EpesiCRM/issues

Epesi is released under the MIT license.
