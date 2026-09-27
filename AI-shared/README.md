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
- [concurrent-session-tests.md](concurrent-session-tests.md) — running tests while several
  sessions work in one checkout: what two test runs share (fake disks, compiled views, the
  working tree) and what they don't, telling the other sessions before and after a test run,
  how to run tests meanwhile, and two ways to make runs independent (a token per run, or a
  lock).
- [conventions.md](conventions.md) — UI and code conventions applied across every resource in
  this app (navigation, page actions, terminology, custom fields).
- [cron.md](cron.md) — the one cron entry (`cron.php` every minute) that runs epesi's
  scheduled tasks: what runs and what stops without it, setting it up on Linux, shared hosting,
  the cron URL for hosts that can only call an address, Windows Task Scheduler and a development
  machine, Administration → Cron, the traps (`proc_open` under `schedule:run`, stale locks,
  maintenance mode), adding and testing a task in a module, the files, and how Epesi's
  `cron.php` maps here.
- [Custom-fields.md](Custom-fields.md) — administrator-added fields on any recordset
  (Administration → Fields): why each one is a real database column rather than a JSON blob or
  EAV, adding/editing/removing a field from the browser, turning a field off versus dropping its
  column and data, "Repair columns", the per-recordset field limit, and the types withheld from
  the admin screen (relations, shared lists).
- [Customer-portal.md](Customer-portal.md) — the beginning of a customer portal: a separate
  panel (`/portal`) where a login with the `customer` role sees only its own contact. Covers
  why it isn't the main panel with one more role, the page that opens in View mode and edits
  personal details only, what is left off the form on purpose, e-mailed password links for a
  customer, the reset page's fix for a link opened while signed in as someone else, and what
  isn't built yet.
- [Dashboard.md](Dashboard.md) — the Dashboard (Epesi's `Base/Dashboard`): each user's tabs of
  applets, adding one more than once, per-applet settings and the gear, how to write an applet
  (the `Applet` contract and `IsApplet`), the Agenda, Tasks and Phone Calls applets, and what
  isn't ported.
- [Demo-mode.md](Demo-mode.md) — `DEMO_MODE`, a public installation anyone can try: the
  "Log in as" login page, what is closed or says "Unavailable in demo mode" and where, guarding a
  module's own action with `Demo::guard()`, the nightly `demo:reset` that keeps the login audit
  (and continues after one that stopped halfway), `demo:audit`, and setting a demo up.
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
- [Menu-Search.md](Menu-Search.md) — the sidebar's "/" quick switcher (jump to any page by
  name) and its navigation groups: how a resource or page joins one (`$navigationGroup`),
  why there's no central registry, the CRM group as the working example, the icon constraint
  (a group or its items can have icons, never both), and the compact spacing between groups.
- [Mail-server-settings.md](Mail-server-settings.md) — Administration → Mail Server (legacy's
  Mail server settings): the `.env` keys and the one class that writes them, "This server's mail
  system" as php.ini's mail settings (a Windows XAMPP PC reaching Papercut), the Test button,
  a new user or Reset Password left blank e-mailing a link to choose a password, trying it on a
  Windows PC, and what isn't done.
- [Notes-Files-storage.md](Notes-Files-storage.md) — the files on a note (the `Epesi/Attachments`
  module): kept once in the shared file storage, the pill per file with its View, Download and
  Get link buttons, the two routes (signed-in download and preview, and the signed link that
  needs no login), which types can be previewed and why SVG can't, how Epesi's file popup maps
  here, what isn't built (e-mailing a file, a download log), the traps (a row link can't wrap a
  file link; the pills' CSS lives in the plugin), and the note's History tab: its labels, a word
  diff of any long text instead of two copies of it, files by name, the Show modal with Restore,
  and why the history is kept.
- [Priority List](../modules/Epesi/PriorityList/README.md) — documented in the module's own
  README: each user's list of up to 10 next actions (GTD), the flag on View pages, the
  dashboard applet, why a hidden record takes no place, how finished records leave every
  list, and adding a record type.
- [Setup-wizard.md](Setup-wizard.md) — how a new installation is set up: `epesi:install` (the
  port of `setup.php`), the `/setup` wizard and its install steps (FirstRun), the module setup
  pages after installation and how a module adds one, scripted installs, security, recovering
  from a failed install, and testing.
- [User-management.md](User-management.md) — the Users screen in the Administration panel: a
  user is made from a contact (any contact, for a customer portal), Reset Password and Change
  Username, the history of a user, and "Log in as user" (legacy's "Log as user"): who may use
  it and why that isn't a policy ability, how the session changes hands and the
  `AuthenticateSession` trap, the bar that switches back, what Login Audit and record history
  record, and what differs from legacy.
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

## Writing these docs

- **Present tense, current behaviour only.** A doc describes the framework as it is today, not
  how it got there — git history is the changelog, this isn't. A rule can be born from a bug,
  but only the rule survives; the story of the bug (when, which module, who found it) doesn't.
- **No dates in prose.** Git blame already carries "when". Exceptions: patch/migration
  filenames, version numbers, and attributing a design decision to the person who made it.
- **One fact, one file.** If a rule could plausibly live in two files, pick the owning one and
  link to it instead of restating it. Where two files genuinely overlap, say so explicitly
  (e.g. one file for what to read before writing code, another for after something misbehaves).
- **Two-part shape, in one file, for a topic that needs both.** A short plain-language section
  for a developer first, the deeper mechanics after — not a separate file split by audience.
  Filing by audience instead of topic has no natural stopping point and is how a folder like
  this one overgrows.
- **No `file:line` citations.** They rot the moment code moves, and a wrong line number costs
  more time than none. Name the function or file, never the line.
- **Nothing account-specific, install-specific or confidential.** No credentials, hostnames,
  customer names, or anything true of only one machine or deployment.
