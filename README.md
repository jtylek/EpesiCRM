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

Epesi is designed for businesses that need consistent records, administrator customization,
optional modules and affordable self-hosting. Its architecture puts common behavior in shared
engines so that adding a business feature does not mean rebuilding the same infrastructure.

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
5. **Relational data, including customization.** Business records use ordinary SQL tables.
   Administrator-added fields are real columns on the record's table, so filtering, sorting
   and reporting can use normal database queries. The old rendering and EAV storage engines
   are not carried forward.
6. **Preserve business behavior while modernizing the implementation.** Existing Epesi
   workflows guide the rewrite; framework defaults do not define the product. Inherited
   behavior can still be reviewed and changed deliberately.

### Why this architecture fits CRM and ERP

Epesi is a **modular monolith**: business capabilities have explicit ownership while running
in one application. A module owns its models, policies, resources and migrations. Shared
features such as the calendar, dashboard and setup wizard accept contributions through
registries and contracts, keeping module-specific decisions out of their shared pages.

RecordBrowser addresses a recurring CRM/ERP problem: companies, contacts, tasks and invoices
all need many of the same capabilities. Maintaining separate implementations of forms,
lists, search, history and custom fields makes them drift apart. A shared field declaration
keeps those surfaces aligned, and an engine correction reaches all recordsets. Custom fields
are especially important here: administrators can extend a record without each screen needing
a separate mechanism to display and edit the new field. Field and schema hooks accommodate
exceptions; pages and workflows outside the record lifecycle can use Filament directly.

Access is explicit at several levels: panel admission, record visibility, action permissions
and business invariants. Permissions belong to roles rather than individual users. Hiding a
field is not authorization, and a business rule such as preventing changes to a posted record
must also apply to super administrators.

Deployment choices follow the same practical priorities. Release packages include built
assets and dependencies, modules install through the browser, and cron is the baseline for
scheduled work. Customers can operate Epesi on supported PHP hosting without needing
Composer or Node on the server.

### Tradeoffs and responsibilities

- **A shared engine adds a learning curve and increases the reach of mistakes.** Developers
  must understand the field declarations and extension hooks. Changes to RecordBrowser need
  broad regression testing, and its abstractions must remain focused enough that unusual
  requirements stay understandable.
- **Runtime modules require lifecycle discipline.** Dependency compatibility, upgrades,
  installation and data removal are platform responsibilities. Module boundaries organize
  code; they do not sandbox installed PHP. Install modules only from trusted sources.
- **Custom-field columns introduce operational costs.** Schema changes can lock tables or
  fail on production databases. MySQL/MariaDB behavior needs direct validation; SQLite tests
  alone cannot establish migration safety or production performance.
- **The framework stack remains an important dependency.** Filament and Livewire supply much
  of the interface infrastructure, so their lifecycle and upgrade behavior affect Epesi's
  shared engine and modules.
- **Access rules must hold across every entry point.** Separate visibility and permission
  layers help express the rules, but forms, actions, exports and background work must enforce
  the applicable boundaries and business invariants consistently.

The architecture earns its complexity when a new business record receives consistent
capabilities with little bespoke code, a shared correction improves every consumer, and
exceptions remain easy to understand. Those are the standards by which it should evolve.

## How to install (developers)

**You need:** git, PHP 8.3–8.5 with Composer, Node.js with npm, and MySQL or MariaDB.

On Windows, [PHP Windows DevStack](https://github.com/jtylek/PHP-windows-devstack) gives you
PHP, Apache and MariaDB ready for Epesi development.

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
touch your development database. Check a change's code style with `php vendor/bin/pint --dirty`.

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

- [PORTING.md](PORTING.md): which parts of the old Epesi exist here, and which don't yet.
- [INSTALL.md](INSTALL.md): installing a release zip, and fixing common server problems.
- `php artisan recordset:check --strict` checks your record types' field declarations.
- `php artisan epesi:package` builds a release zip with its dependencies and built assets.

## License

Copyright by Janusz Tylek (c) 2026

[MIT License](LICENSE)

Third-party packages keep their own licenses. The optional Roundcube webmail (GPL-3.0) is
downloaded separately and is not part of Epesi.
