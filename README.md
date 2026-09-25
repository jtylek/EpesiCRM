# Epesi → Laravel

A from-scratch rewrite of [Epesi](https://github.com/jtylek/epesiCRM) — an open-source CRM
and a kickstarter for custom ERP systems.

Built on **Laravel 12 + Filament 5**.

This is not a migration of the old codebase; it's a new application that reimplements
Epesi's modules and behavior on modern foundations, built module by module against the real
legacy source rather than from general Laravel/Filament conventions alone.

## Why a rewrite, and why this stack

Epesi's rendering model (`process.php` → server returns JS that patches the DOM; no REST/JSON
API) has no direct Laravel equivalent, so the front end has to be replaced wholesale rather
than adapted. That pushed the choice toward **Livewire**: Epesi's UI is already conceptually
server-driven, and **Filament** — itself built on Livewire — is the natural successor to
Epesi's own **RecordBrowser** pattern ("a module declares a recordset's fields and gets a full
CRUD UI for free"). RecordBrowser's storage engine (EAV-style `<tab>_field`/`<tab>_data_1`
tables) isn't worth porting, but its philosophy is exactly Filament's own model, so the
field-type catalog and per-module ACL semantics are the real spec being carried forward.

## What's built

- **Core CRM**: Companies, Contacts, Phone Calls, Tasks, and Meetings, each a full Filament
  resource with row-level ownership ACL (visible/editable if it's public, yours, or your
  company's — ported from Epesi's real `install_permissions()` rules, not reinvented),
  activity history, and a Calendar view merging events from every module.
- **RecordBrowser engine** (`modules/Epesi/RecordBrowser`): a core module that builds List,
  View, Create, Edit, filters, search, and history for any recordset from a `fields()`
  declaration — plus admin-defined custom fields that reach every screen through the same
  code path.
- **Module system + Store**: first-party modules ship as zip archives extracted into
  `modules/`, discovered and enabled/disabled at runtime with no code deploy — the same model
  Epesi used, deliberately not Composer packages.
- **Administration panel**: a separate, `super_admin`-only panel (login/session audit,
  module management, roles) kept apart from the day-to-day CRM panel.
- **Languages**: English and Polish, chosen per user under Settings → Regional settings,
  with the system default set during setup. Any of Epesi's other languages can be imported
  from its translation files with `php artisan lang:import-epesi`. See
  [AI-shared/Epesi-Laravel-Translations.md](AI-shared/Epesi-Laravel-Translations.md).
- **Legacy data import**: a generic importer that reads any RecordBrowser table straight out
  of a live legacy Epesi database — records, relationships, and full field-level edit
  history — into this app's schema.

See [AI-shared/](AI-shared/) for the shared, non-confidential technical notes behind all of
this (architecture decisions, conventions, what's next).

## Getting started

**From a release zip** (no Composer, Node or command line needed; how to build one is below):

1. Create a folder in the web root, e.g. `C:\xampp\htdocs\epesi`, and unpack the zip into it.
2. Create an empty database, e.g. in phpMyAdmin.
3. Open the site (`http://localhost/epesi/`). The **setup wizard** asks first for a one-time
   setup code: open `storage/app/setup-code.txt` in the epesi folder and copy it. Then the
   wizard checks the server, asks for the database and creates the tables, and continues with
   Epesi's FirstRun: the setup type (which modules), the administrator account, how to send
   e-mail, and whether to download the Roundcube webmail. Roundcube is a separate GPL-3.0
   project, so it is only downloaded if you say yes; you can also add it later from the
   Mailbox page or Administration → Modules. After installing, the wizard shows each
   installed module's own setup page (your company and your name, regional defaults).

**From a git checkout:**

```bash
composer install
npm install && npm run build
php artisan epesi:install
```

`epesi:install` runs the server half of the wizard on the command line. It checks the server
(PHP version, extensions, writable folders), creates `.env` and the application key, asks for
the database connection and creates the tables. It then prints the setup code and the address
to open in the browser for the rest. Skipping the command and opening the site straight away
works too.

**Building a release zip:** from a checkout installed with
`composer install --no-dev --optimize-autoloader` and `npm run build`, run
`php artisan epesi:package`. The zip lands in `storage/app/private/releases/`.

Every step, option and failure case is described in
[AI-shared/Setup-wizard.md](AI-shared/Setup-wizard.md). Building the zip, and setting epesi up
on a shared hosting account (plus Roundcube), step by step:
[AI-shared/Epesi-Laravel-distro.md](AI-shared/Epesi-Laravel-distro.md).

For scripted installs, the same can be done without the browser:

```bash
php artisan epesi:install --db-connection=mysql --db-database=epesi --db-username=epesi \
    --admin-name="Jan Kowalski" --admin-email=jan@example.com --admin-password=... --profile=crm
```

For development with demo data instead:

```bash
php artisan migrate --seed
php artisan serve
```

and log in with one of the seeded demo accounts (`admin@example.com` / `manager@example.com` /
`employee@example.com`, password `password`) at `/login`. The setup wizard's "Load demo data"
option creates the same records.

## License

MIT — see [LICENSE](LICENSE). Third-party packages keep their own licenses; the optional
Roundcube webmail (GPL-3.0) is downloaded separately and is not part of epesi.
