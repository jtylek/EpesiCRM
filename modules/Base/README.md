# Base

The root/meta module of the Base pack: it installs nothing itself, but its `requires()` list pulls in the
always-installed core of Epesi — Admin, ActionBar, Cron, Dashboard, Help, Support, Setup, EpesiStore,
Lang/Administrator, Menu (and QuickAccess), MainModuleIndicator, RegionalSettings, StatusBar, Search, Print,
HomePage, Theme/Administrator and User/Administrator. Every other admin module in the pack depends on this
set being present, directly or transitively.

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
