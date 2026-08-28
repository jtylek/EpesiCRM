# Base/Setup

Backs the admin "Setup" screen itself: the module list/package browser used to install,
upgrade, and uninstall other modules. On install it creates the `available_modules` tracking
table and sets the `anonymous_setup`/`simple_setup` variables that control the screen's mode.
This is also the module whose `simple_setup()` defines the "Epesi Core" package card's own
version number, icon, and homepage link, and marks it as the core package that cannot be
uninstalled.

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
