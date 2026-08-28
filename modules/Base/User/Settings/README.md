# Base/User/Settings

A generic per-user preference storage framework. It creates the `base_user_settings` table
(per-user values keyed by module + variable name) and `base_user_settings_admin_defaults`
(system-wide admin-set defaults used when a user hasn't overridden a value), and registers the
"Advanced User Settings" ACL permission. Other modules — e.g. `Base_RegionalSettings` — build
their own preference screens on top of it rather than storing user preferences themselves.

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
