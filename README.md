<p align="center">
  <img src="/images/logo.png" alt="Epesi Logo" width="360">
</p>

<p align="center">
  <b>An open-source PHP framework and CRM that generates its own UI — so you can build
  business modules by describing them, not by hand-coding views.</b>
</p>

<p align="center">
  <a href="https://sourceforge.net/projects/epesi"><img src="https://img.shields.io/sourceforge/dt/epesi.svg" alt="SourceForge downloads"></a>
  <img src="https://img.shields.io/badge/PHP-8.0%2B-777bb4" alt="PHP 8.0 or newer (8.2 recommended)">
  <img src="https://img.shields.io/badge/license-MIT-green" alt="MIT License">
</p>

---

## What is Epesi?

Epesi is a **module framework with a full CRM built on top of it** — a Progressive Web App
you install on your own server, then extend. Out of the box you get Contacts, Companies,
Calendar, E-mail, Tasks, Phonecalls and a dashboard you can rearrange
per user. Because it's a framework underneath, that CRM is also a starting point: add the
modules your business actually needs and Epesi grows into a full ERP — inventory,
invoicing, project tracking, whatever your domain requires — without you starting from an
empty repo.

**The core idea:** a module author declares *what a record looks like* — field names,
types, relationships — in a plain PHP array, and the framework (`Utils_RecordBrowser`)
generates the add/edit/view/browse/search/filter/ACL screens for it automatically. No
HTML, no CSS, no JavaScript to write for the common case — the framework produces a
working, responsive UI from the declaration. See
[`AI-shared/design-philosophy.md`](AI-shared/design-philosophy.md) for the reasoning
behind this, straight from the framework's creator.

## Gallery

<table>
  <tr>
    <td width="50%"><img src="/images/screenshots/dashboard.jpg" alt="Dashboard with configurable widgets"></td>
    <td width="50%"><img src="/images/screenshots/dashboard-darkmode.jpg" alt="Dark mode theme"></td>
  </tr>
  <tr>
    <td align="center"><sub>Configurable per-user dashboard</sub></td>
    <td align="center"><sub>Built-in dark mode</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src="/images/screenshots/contacts.jpg" alt="Contacts browser"></td>
    <td width="50%"><img src="/images/screenshots/companies.jpg" alt="Companies browser"></td>
  </tr>
  <tr>
    <td align="center"><sub>Contacts — generated list, search &amp; filters</sub></td>
    <td align="center"><sub>Companies — same generated CRUD, different table</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src="/images/screenshots/calendar.jpg" alt="Calendar"></td>
    <td width="50%"><img src="/images/screenshots/roundcube.jpg" alt="Roundcube webmail integration"></td>
  </tr>
  <tr>
    <td align="center"><sub>Calendar — day/week/month/agenda views</sub></td>
    <td align="center"><sub>Built-in webmail (Roundcube) embedded in the UI</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src="/images/screenshots/mobile-contacts-portrait.jpg" alt="Contacts browser on a phone, portrait"></td>
    <td width="50%"><img src="/images/screenshots/mobile-contacts-landscape.jpg" alt="Contacts browser on a phone, landscape"></td>
  </tr>
  <tr>
    <td align="center"><sub>PWA on a phone — same generated grid, responsive layout</sub></td>
    <td align="center"><sub>Landscape</sub></td>
  </tr>
</table>

## New: AdminLTE theme, mobile-friendly out of the box

The UI has been rebuilt on top of [AdminLTE](https://adminlte.io/)/Bootstrap
(`adminltedark`, now the default theme for new installs) — a modern sidebar layout,
built-in dark mode, and consistent styling across every screen, replacing the
framework's older default theme.

That rebuild included a **generic mobile/responsive pass across the whole framework**,
not a per-module patch: the same data-grid component every `Utils_RecordBrowser`/
`Utils_GenericBrowser` list uses (Contacts, Companies, Login Audit, any module you
build) reflows into a compact two-line-per-row layout below the tablet breakpoint,
non-essential columns (row actions, favorites/watchdog toggles) collapse into a kebab
menu, and the sidebar, search, and filter bars all adapt to portrait/landscape phone
viewports — see the mobile gallery shots above, taken straight from a phone. Because
this lives in the shared grid/theme layer, a module author gets the mobile layout for
free the same way they get the desktop one: by declaring fields, not by writing
responsive CSS. See [`AI-shared/adminlte-theme.md`](AI-shared/adminlte-theme.md) and
[`AI-shared/generic-browser-responsive-tables.md`](AI-shared/generic-browser-responsive-tables.md)
for the implementation history.

## Why developers like it

- **No boilerplate CRUD.** Declare a table's fields once and get a full data grid with
  search, filtering, sorting, CSV export, printing, and ACL for free.
- **Batteries included.** CRM out of the box (Contacts, Companies, Calendar, Tasks,
  Phonecalls), plus webmail, Watchdog notifications, Shoutbox chat,
  and a Telegram integration — all real modules you can read as reference implementations.
- **PWA, zero client install.** Runs in any modern browser, installable to a home screen,
  no desktop client to distribute or update.
- **Ordinary LAMP stack.** PHP 8.0+ (8.2 recommended) + MySQL or PostgreSQL. No build
  step — `modules/` and `theme*/` are served directly, nothing to compile or bundle.
- **AI-agent ready.** This repo is set up to be productively worked on by an AI coding
  agent from the first clone — see below.

## Build modules in plain English with an AI agent

This repository ships pre-trained for AI-assisted development. [`AI-shared/`](AI-shared/)
is a git-tracked knowledge base — architecture rationale, the module-authoring tutorial,
known bug patterns, deliberate design decisions that look like bugs but aren't — written
specifically so an AI agent (or a new developer) starting from a fresh clone doesn't have
to rediscover the framework's conventions from scratch. Combined with
[`CLAUDE.md`](CLAUDE.md) at the repo root, an agent can go from a plain-English feature
request to a working module respecting this codebase's actual conventions, not generic
PHP idioms.

**Recommended setup:**
1. Clone this repo.
2. Open it in [VS Code](https://code.visualstudio.com/).
3. Install the [Claude Code](https://claude.com/claude-code) extension and point it at
   the checkout.

From there, describe the module you want in plain English. The agent reads
`CLAUDE.md` and `AI-shared/` first, so it already knows the module anatomy
(`Install`/`Common`/`Main` classes), `Utils_RecordBrowser` field types, the patch system
for upgrading existing installs, and where AdminLTE theming does and doesn't apply.

**See it for yourself:** [`modules/Custom/Tutorial/`](modules/Custom/Tutorial/) is a
complete, working module built entirely by Claude, from a plain-English brief, following
[`AI-shared/Dev-Tutorial.md`](AI-shared/Dev-Tutorial.md). It demonstrates every
`RecordBrowser` field type, a lookup table, and an addon tab in one real example —
read it side by side with the tutorial to see the pattern an agent follows for any new
module.

## Getting started

**If you just want to run Epesi:**
- Easiest — the [Softaculous installer](https://epesi.org/get-started/softaculous),
  available via most hosts' cPanel.
- Or download a release directly from
  [SourceForge](https://sourceforge.net/projects/epesi/).

**If you want to develop your own modules:**

```bash
git clone https://github.com/jtylek/epesi.git
cd epesi
composer install            # the app itself
composer install -d tools   # once: PHPStan + Rector, used by CI and by `php -l`-adjacent checks
```

Requires a LAMP-style stack — PHP 8.0+ (8.2 recommended) and MySQL or PostgreSQL.
Point your web server's document root at the checkout and open it in a browser to run
the setup wizard.

**Why the second command:** the app's own `vendor/` is committed to the repo, so a
deployment needs no Composer run at all. The static-analysis tools are a different
matter — they're ~68 MB, they're dev-only, and they'd end up in every release package —
so they live in their own project under [`tools/`](tools/) with a gitignored
`tools/vendor/`. `tools/composer.lock` *is* committed, so everyone gets the same pinned
versions. Run it once per clone and then:

```bash
tools/vendor/bin/phpstan analyse -c phpstan.neon
tools/vendor/bin/rector process --dry-run --config rector-php82.php
```

Both also run in CI on every push and pull request
([`.github/workflows/ci.yml`](.github/workflows/ci.yml)), alongside `php -l` over all
first-party code. See [`CLAUDE.md`](CLAUDE.md) for the full architecture, local commands,
and environment notes.

## Documentation map

| File | What it's for |
|---|---|
| [`CLAUDE.md`](CLAUDE.md) | Architecture, bootstrap chain, module system, commands — read first |
| [`AI-shared/`](AI-shared/) | Living notes: feature status, tutorials, bug patterns, environment gotchas |
| [`AI-shared/Dev-Tutorial.md`](AI-shared/Dev-Tutorial.md) | How to write a module from scratch, paired with `modules/Custom/Tutorial/` |
| [`AI-shared/design-philosophy.md`](AI-shared/design-philosophy.md) | The founding principle: business logic in PHP, view generated for you |
| [`AI-shared/MIGRATION_NOTES.md`](AI-shared/MIGRATION_NOTES.md) | PHP 7.4 → 8.2 migration log — root causes and decisions |

## Support

- User manual: https://epesi.org/
- Developer tutorial: https://epesi.org/devtutorial
- Bugs and feature requests: open an issue on this repo
- More about the project: https://epe.si/

## License

Epesi is released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek

<sub>[LinkedIn: Janusz](https://www.linkedin.com/in/jtylek/) ·
[LinkedIn: Karina](https://www.linkedin.com/in/ktylek/)</sub>
