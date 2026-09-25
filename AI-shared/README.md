# AI-shared/

## Purpose

Project-specific knowledge about this Laravel port of Epesi, shared with any AI agent or
developer working in this repo. Distilled, reusable rules and architecture — not confidential
facts, not incident narratives, not in-progress plans. If something is account-specific,
security-sensitive, or still only partly built, it doesn't belong here.

**Every file added here must be listed below.** Update this index whenever a file is added,
removed, or its purpose changes.

## Index

- [architecture.md](architecture.md) — the port's major building blocks: Filament resources,
  the ownership-based ACL scope, the RecordBrowser engine, the module system, the
  Administration panel, Calendar, and the legacy data importer.
- [Common-data.md](Common-data.md) — the design for the reference-data engine that replaces
  legacy `Utils_CommonData`: schema, facade API, the drill-down administration UI built on
  stock Filament, and how the `commondata` field type wires into the `Field` DSL. The module is
  built, the legacy importer runs, and the `commondata` field type is wired into the DSL.
- [conventions.md](conventions.md) — UI and code conventions applied across every resource in
  this app (navigation, page actions, terminology, custom fields).
- [Epesi-CRM-Laravel-port.md](Epesi-CRM-Laravel-port.md) — the five CRM recordsets as modules
  under `Epesi/CRM`: what a CRM module owns, how a recordset is declared, the escape hatches
  the `Field` DSL needed and why, the traps that come with models living outside `app/`, and
  how to verify a recordset without a test suite.
- [Epesi-Laravel-Roundcube.md](Epesi-Laravel-Roundcube.md) — the design for embedding the
  Roundcube webmail as a full IMAP client (the `Epesi/Roundcube` module): downloading and
  upgrading Roundcube, why it lives in `storage/`, the generated config, single sign-on through one-time tickets, the
  `epesi_*` Roundcube plugins (archive button, CRM address book), the Mailbox page, logout,
  the web-server requirements, and two Windows traps: recursive deletes follow junctions, and
  fresh directories can't be renamed straight away.
- [Epesi-Laravel-distro.md](Epesi-Laravel-distro.md) — the downloadable epesi zip: how
  `epesi:package` builds it (and how to keep `vendor/` to release contents when Composer
  clones packages), how the browser wizard takes it from an unpacked folder to a running
  system, a step-by-step setup on shared hosting (database, document root, setup code, cron,
  production settings), installing the Roundcube webmail there, and what publishing on
  SourceForge or through Softaculous still needs.
- [Epesi-Laravel-Translations.md](Epesi-Laravel-Translations.md) — the interface in several
  languages (Epesi's `Base/Lang`): JSON translations keyed by the English text, module
  `lang/` directories, the global hooks that translate Filament labels, how each user's
  language is chosen, notifications in the recipient's language, the Polish translation,
  adding a language with `lang:import-epesi`, the tests that catch untranslated strings, the
  Administration → Translations page for custom translations, and the problems found and
  fixed while building it.
- [Setup-wizard.md](Setup-wizard.md) — how a new installation is set up: `epesi:install` (the
  port of `setup.php`), the `/setup` wizard and its install steps (FirstRun), the module setup
  pages after installation and how a module adds one, scripted installs, security, recovering
  from a failed install, and testing.
- [User-management.md](User-management.md) — the Users screen in the Administration panel,
  and "Log in as user" (legacy's "Log as user"): who may use it and why that isn't a policy
  ability, how the session changes hands and the `AuthenticateSession` trap, the bar that
  switches back, what Login Audit and record history record, and what differs from legacy.
- [Watchdog_and_Notifications.md](Watchdog_and_Notifications.md) — the `Epesi/Watchdog`
  module (Epesi's `Utils/Watchdog`) and the bell. Covers:
  - watching records and whole record types, and how a change becomes a notification;
  - the Watched page;
  - how the bell and the Watched page share one read state: Watchdog's subclass of
    Filament's bell, why it is set in the plugin's `boot()`, and the refresh events;
  - making a record type watchable, and what isn't covered.
- [filament-fields.md](filament-fields.md) — the field types Filament actually ships (form,
  layout, infolist, table), how they are extended, and how the RecordBrowser `Field` DSL and
  its `FieldType` enum map onto them; which of the two to extend when a field type is
  missing; and a legacy-parity pass naming every Epesi field type that is done, that still
  needs building, and why a closed enum blocks module-provided types.
- [Epesi-Moonshine-Laravel.md](Epesi-Moonshine-Laravel.md) — evaluation of MoonShine as an
  alternative to Filament, and the phased plan that would get there: where it genuinely fits
  Epesi's engine, where it doesn't, and why the `Field` DSL is what makes the question
  answerable.
