# Base/RegionalSettings

Regional settings like currency, time, and locale. Its post-install wizard prompts the admin for
the default date/time display format, timezone, and default country/state, then stores the
choices as system-wide admin defaults via `Base_User_Settings` (`Base_User_SettingsCommon::save_admin`)
so every module can read a consistent regional configuration.

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
