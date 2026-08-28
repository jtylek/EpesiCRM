# Utils/Watchdog

A generic change-notification/audit engine that other modules plug into. Modules register categories (with a display callback) and log events against a category plus an internal record id in `utils_watchdog_event`; users can subscribe to a whole category or to individual records (`utils_watchdog_category_subscription` / `utils_watchdog_subscription`), and a notification queue table tracks which events still need delivering. Installs the "Watchdog - subscribe to categories" ACL permission for employees and managers.

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
