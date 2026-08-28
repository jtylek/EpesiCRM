# Base/Setup

**Epesi** is an open-source, web-based CRM/ERP framework: a modular platform where every screen, tool, and
business feature — Contacts, Calendar, custom line-of-business apps, and everything else — is a self-contained
module plugged into a shared core, rather than one monolithic application.

**Epesi Core** is that shared core: the bundle of foundational modules every installation needs regardless of
which business modules are layered on top, and the only package on this screen that can't be uninstalled. It
includes, among others:

- **Utils/RecordBrowser**, built on the underlying `GenericBrowser` engine — the generic data-grid/CRUD
  framework almost every list/search/filter screen in Epesi (Contacts, Companies, Tasks, and dozens more) is
  built on top of, rather than each module rolling its own.
- **Base/Admin** — the Administration screen every module plugs its own admin section into.
- **Base/User** (with Login, Settings and Administrator) — User Management: accounts, sessions, and per-user
  preferences.
- **Base/Acl** — the permission/access-control checks behind every module's screens and actions.
- **Base/Theme** — the shared theming system (including the AdminLTE UI) every module renders through.
- Module install/upgrade/uninstall management (this module), plus Cron, Dashboard, Menu, Search, Regional
  Settings, and the rest of the always-on infrastructure the modules above depend on.

This specific module, **Base/Setup**, backs the admin "Setup" screen itself — the module list/package browser
used to install, upgrade, and uninstall other modules. On install it creates the `available_modules` tracking
table and sets the `anonymous_setup`/`simple_setup` variables that control the screen's mode. It's also the
module that defines the "Epesi Core" package card's own version number, icon, and homepage link, and marks it
as the core package that cannot be uninstalled.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
