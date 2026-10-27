# Epesi 3.0 CRM

Free, open-source CRM built on **Laravel 12 + Filament 5**, and a kickstarter for your own ERP
system.

[![SourceForge downloads](https://img.shields.io/sourceforge/dt/epesi.svg)](https://sourceforge.net/projects/epesi)
![PHP 8.3 to 8.5](https://img.shields.io/badge/PHP-8.3%20%7C%208.4%20%7C%208.5-777bb4)
![MIT License](https://img.shields.io/badge/license-MIT-green)

- **Website:** <https://epesicrm.com>
- **Live demo:** <https://epesicrm.com/demo/>
- **Download:** <https://sourceforge.net/projects/epesi/>

## What is Epesi CRM

Epesi keeps a company's business information in one place and shares it between the people who
work there: companies, contacts, tasks, meetings, phone calls, notes, a shared calendar and
e-mails.
It is self-hosted, MIT licensed and has no per-seat fees.

Epesi 3.0 is a complete rewrite of the original [Epesi CRM](https://github.com/jtylek/epesi-legacy)
on Laravel. It keeps the old Epesi's behavior and its
[design principles](#design-principles). Because it is modular and
generates most of its screens itself, it also works as a starting point for a custom ERP
system: add modules for whatever your business needs.

## Functionality

- **Core CRM:** Companies, Contacts, Tasks, Meetings and Phone Calls, with a shared calendar
  that merges events from every module.
- **Permissions:** a record is visible and editable when it is public, yours or your company's.
  Roles: super admin, manager, employee.
- **Full history:** every change to every field is recorded.
- **Custom fields:** administrators add their own fields to any record type, from the browser.
- **Notes and attachments** on every record, **reminders**, **follow-ups**, **watching** a record
  to be notified of changes, and a personal **priority list**.
- **E-mail:** IMAP/SMTP accounts, an e-mail archive linked to contacts and companies, and the
  optional Roundcube webmail built in.
- **Dashboard** with applets: shoutbox, sticky notes, unread mail, priority list.
- **Currencies** with daily exchange rates (ECB or NBP).
- **Customer portal:** a separate area where your customers log in.
- **Modules:** install, enable and disable features from the administration panel, or browse
  the Epesi Store.
- **Administration panel:** users, roles, modules, login audit, server check, database update.
- **Languages:** English plus 12 more (some machine-translated); each user picks their own,
  along with timezone and date format.
- **Migration:** import your data, with its full edit history, from an old Epesi installation.

## How to get it (end users)

Download the ready-to-run package from **SourceForge**:
<https://sourceforge.net/projects/epesi/>. You don't need Composer, Node or the command line.

**Requirements:** PHP 8.3, 8.4 or 8.5, MySQL or MariaDB, and Apache or nginx. Shared hosting
works. The setup wizard checks the PHP extensions it needs.

1. Unpack the zip into a folder on your web server, e.g. `htdocs/epesi`.
2. Create an empty database, e.g. in phpMyAdmin.
3. Open the site in a browser. The setup wizard asks for a one-time setup code (in
   `storage/app/setup-code.txt`), checks the server, creates the tables, and then sets up the
   administrator account, optional demo data, e-mail and the optional Roundcube webmail.

**Updating:** back up the folder and the database, unpack the new release over the old files,
then open Administration → Database update.

To try Epesi before installing it, use the [live demo](https://epesicrm.com/demo/).

## Design principles

1. **Modular architecture.** Almost everything is a module in `modules/<Vendor>/<Name>/`: a
   folder with a `module.json` manifest and PSR-4 code. That includes the CRM itself. Modules
   declare their dependencies and are installed, enabled and disabled at runtime. Modules
   marked `"core": true` can't be switched off.
2. **Modules (mostly) don't need Composer.** A module ships as a zip archive and is installed
   from the administration panel or the Epesi Store, with no `composer require` and no
   redeploy, the same way the old Epesi worked.
3. **Browser-based, wizard-driven installation.** Unpack, open the site, follow the wizard.
   Modules can add their own setup steps to it.
4. **RecordBrowser does the tedious work.** For record-based features, a module declares its
   fields once and gets consistent list, view, create and edit screens, filters, search and
   change history. Administrator-added custom fields use the same path and appear alongside
   built-in fields.

## How to install (developers)

**You need:** git, PHP 8.3–8.5 with Composer, Node.js with npm, and MySQL or MariaDB.

On Windows, [PHP Windows DevStack](https://github.com/jtylek/PHP-windows-devstack) gives you
PHP, Apache and MariaDB ready for Epesi development. Its setup for Epesi is described in
[AI-shared/DEVSTACK-SETUP.md](AI-shared/DEVSTACK-SETUP.md).

```bash
git clone https://github.com/jtylek/EpesiCRM.git epesi
cd epesi
composer install
npm install
npm run build
php artisan epesi:install
```

`epesi:install` checks the server, creates `.env` and the application key, asks for the
database connection and creates the tables. It then prints the setup code and the address to
open in the browser to finish setup. You can also skip it and open the site in the browser
straight away: the wizard does everything.

For a development database filled with demo data instead:

```bash
php artisan migrate --seed
composer run dev
```

`composer run dev` starts the web server, queue listener, log tail and Vite together. Log in
at `/login` as `admin@example.com`, `manager@example.com` or `employee@example.com`, all with
password `password`.

Run the tests with `php artisan test`. They use an in-memory SQLite database, so they don't
touch your development database.

**Tip:** [ScreenCapture](https://github.com/jtylek/ScreenCapture) is a handy utility for sending
screenshots to AI coding agents while you work on the application.

## Extending Epesi

Create a module and add a record type to it:

```bash
php artisan make:epesi-module Acme/Invoices
php artisan make:epesi-recordset Acme/Invoices Invoice
php artisan module:register Acme/Invoices
```

A record type is a resource built on `RecordsetResource` that declares its fields with the
`Field` DSL; the screens follow from it:

```php
public static function fields(): array
{
    return [
        Field::text('company_name')->required()->maxLength(128)->inTable(),
        Field::commonData('groups', 'Companies_Groups', multiple: true)->filterable(),
        Field::longText('memo')->notInTable(),
    ];
}
```

Use `CompanyResource` in [modules/Epesi/CRM/Companies](modules/Epesi/CRM/Companies) as the
reference example. `php artisan module:package Acme/Invoices` builds the zip that other
installations install.

### RecordBrowser or Filament?

RecordBrowser is Epesi's shared recordset engine, built on top of Filament. It is not a
replacement UI framework: Filament supplies the components, panels and Livewire integration;
RecordBrowser supplies Epesi's conventions and the reusable machinery for business records.

Use **RecordBrowser** when a feature is centered on a collection of records that people create,
find, view and edit. Extending `RecordsetResource` and declaring fields once gives a developer:

- Generated list, view, create and edit pages, with table columns, filters and search derived
  from the fields declaration.
- A shared `Field` DSL for common field types and their form, table, filter and view behavior,
  with customization hooks when a field needs special handling.
- Change history and custom fields that follow the same display and editing path as built-in
  fields, including fields administrators add later.
- Shared record pages, linked-record behavior, related-data addons, and the engine's common
  ownership visibility rules, so each resource does not have to recreate those patterns.

Use **Filament directly** for pages and workflows that are not recordsets: dashboards, calendars,
settings, setup wizards, reports, one-off tools, or a specialized interaction that does not fit a
record's list/view/create/edit lifecycle. Filament remains available for custom schemas, tables,
actions and pages, and can also be used to customize a RecordBrowser resource where its hooks
are sufficient. Start with RecordBrowser for a normal business record; use Filament directly
when the feature's shape is a page or workflow rather than a reusable record type.

Read more:

- [AGENTS.md](AGENTS.md): the rules for working in this repository, for people and AI agents
  alike.
- [AI-shared/](AI-shared/README.md): technical notes on the architecture, conventions, fields,
  themes, translations, setup and more.
- [AI-shared/Epesi-custom-fields.md](AI-shared/Epesi-custom-fields.md): the `Field` DSL,
  administrator-added custom fields, collections and related records.
- [AI-shared/AI-agent-module-development.md](AI-shared/AI-agent-module-development.md): a
  module-building and validation workflow for AI agents.
- [AI-shared/Epesi-Laravel-distro.md](AI-shared/Epesi-Laravel-distro.md): building a release zip
  (`php artisan epesi:package`) and installing on shared hosting.

## License

Copyright by Janusz Tylek (c) 2026

[MIT License](LICENSE)

Third-party packages keep their own licenses. The optional Roundcube webmail (GPL-3.0) is
downloaded separately and is not part of Epesi.
