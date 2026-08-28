# Base/Lang

The base language module: on install it records the full set of shipped languages as the `installed_langs`
variable and sets `default_lang` to English. It has no other module dependencies and is itself a prerequisite
for most other Base modules, since anything that needs `__()` translation or per-instance language settings
builds on it.

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
