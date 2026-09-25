# Architecture

This app is a from-scratch reimplementation of Epesi's modules on **Laravel 12 + Filament
5 + Livewire**, not a line-for-line port of the old PHP. Epesi's own philosophy — "a module
declares a recordset's fields and gets a full CRUD UI for free" — is carried forward through
Filament rather than Epesi's original EAV-style storage engine, which isn't reused.

## Ownership-based ACL

Row-level visibility and edit rights are enforced through a reusable Eloquent global scope,
`Epesi\Modules\RecordBrowser\Models\Concerns\HasOwnershipVisibility`, applied to every
CRM-facing model. A record is visible if it's marked public, if the current user created it,
or if a model-specific hook says it's "yours" some other way (e.g. your own company, or a
record tied to your company). `manager`/`super_admin` roles bypass the scope entirely.
Policies (`spatie/laravel-permission` + `bezhansalleh/filament-shield` roles:
`super_admin`/`manager`/`employee`) layer create/update/delete gates on the same logic.

This generalizes Epesi's per-module ACL crits (e.g. "visible if not-Private, OR you created
it, OR it's your company") into one shared trait instead of re-deriving the logic per model.

## RecordBrowser engine

`modules/Epesi/RecordBrowser` is a core module (cannot be disabled or uninstalled) that
builds a full Filament experience — List, View, Create, Edit, filters, search, an activity
History tab, and page registration — from a single `fields()` declaration on a model. An
administrator can add further fields at runtime from Administration → Fields, and those
fields reach every screen through the same code path a first-party field does.

Shared base pages (`ListRecords`/`ViewRecord`/`CreateRecord`/`EditRecord`) and the
`HasOwnershipVisibility` trait live in this module so any resource — first-party or
module-provided — can build on the same foundation instead of reimplementing it.

Every polymorphic relationship in the app uses **morph aliases** (short strings like
`contact`, `user`, registered centrally) rather than fully-qualified class names, so a model
can move between namespaces (e.g. into a module) without breaking existing polymorphic
references. Anything used polymorphically must register an alias, or it throws.

## Module system

A module is a plain directory (`modules/<Vendor>/<Name>/`, with a `module.json` and PSR-4
`src/`), distributed and installed as a zip archive — the same distribution model Epesi used,
deliberately not Composer packages, so modules can be installed/enabled/disabled from the
Admin GUI with no code deploy. A `ModuleRegistry`, read at boot from a generated cache file,
drives which modules' service providers and Filament plugins are active; both Filament panels
build their plugin list from the registry instead of a static array.

The path may nest one level deeper so related features group the way old Epesi's tree did:
the five CRM recordsets live at `modules/Epesi/CRM/{Contacts,Companies,Tasks,Meetings,
PhoneCalls}`, where `CRM` is a plain directory owning no manifest and no namespace. Each of
those owns its model, policy, Filament resource and calendar provider; shared vocabulary,
migrations and the legacy importers stay in the core app.

This is not just how optional features ship — it is where the application's own core lives.
A module marked `"core": true` cannot be disabled or uninstalled, which is what lets
first-party functionality move out of `app/` without becoming switch-off-able. What keeps
that move safe is the morph map: history rows, role assignments and custom-field definitions
all store a short alias rather than a class name, so a model can change namespace without
orphaning its data.

## Administration panel

A separate Filament panel (`/administration`), gated to the `super_admin` role specifically
(the main CRM panel is open to all three roles). Holds cross-cutting, infrastructure-level
concerns that don't belong in the day-to-day CRM UI: login/session audit history, module
management, and role administration.

## Calendar

A pluggable, multi-source calendar page merges events from every registered
`CalendarEventProvider` (tracked in a `CalendarRegistry`). Each first-party model that has a
notion of a date (Meetings, Tasks, Phone Calls) contributes its own provider; a future module
adds events to the calendar by registering one more provider — nothing in the Calendar page
itself needs to change.

## Setup (first run)

A fresh install has no users, and every panel sends every request to the setup wizard at
`/setup` (a small panel of its own) until one exists — the port of Epesi's FirstRun. The server
half (`.env`, database, tables) is `php artisan epesi:install`, since Laravel can't serve a page
without a working `.env`. The wizard's install step registers every core module plus the chosen
profile's modules (`config/setup.php`) in dependency order through the ordinary
`ModuleInstaller`, creates the super_admin, and writes the system mailer settings to `.env`.
"Installed" is a marker file written at that point, with "any user exists" as the fallback for
installs that predate the wizard or were built with `migrate --seed` / `import:legacy`.

The pages after installation belong to the modules: a module registers a `SetupStep` (form
fields plus a handler) with `SetupSteps::register()` from its service provider, the port of a
module's `post_install()` / `post_install_process()`. They run on the request after
installation because a module's provider only loads once the module is registered.
Step by step, with options and failure handling: [Setup-wizard.md](Setup-wizard.md).

## File storage

Every file a note or an e-mail carries is kept once, however many times it is attached: the
port of Epesi's Utils/FileStorage, in the core app (`App\Services\FileStorage`) because
more than one module uses it. Two tables, as in Epesi:

- `stored_file_contents` (`utils_filestorage_files`): one row per distinct content, keyed by its
  sha512. The bytes are on the private `filestorage` disk under Epesi's layout, the first five
  hex digits one directory each and the rest as the file name. A legacy
  `data/Utils_FileStorage` tree names the same bytes the same way.
- `stored_files` (`utils_filestorage`): one use of a content under a name. A module keeps these:
  `epesi_attachments.files` is a list of their ids, and `epesi_mail_attachments.stored_file_id` is one.

Storing bytes that are already there adds only a `stored_files` row. Deleting the last
`stored_files` row of a content deletes the content and its file too. Epesi left that to an
administrator. So a module deletes its StoredFiles when the owner goes: a file taken off a note,
a force-deleted note or message. Forgetting to do so only keeps a file longer than needed; it
never deletes one still in use. Files are served only through a module's controller, which
first checks that the user may see the note or message holding the file.

## Legacy data import

A generic importer reads any RecordBrowser-shaped table directly out of a live legacy Epesi
database (field catalog + data rows + edit-history rows) and imports it into this app's
schema, reconstructing full field-level edit history into the same activity log every
first-party model already uses. Import is upsert-by-legacy-id and CLI-driven
(`php artisan import:legacy`), not a Filament UI — this is a developer-run cutover operation,
not an end-user feature.
