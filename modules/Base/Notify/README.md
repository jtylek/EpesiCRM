# Base/Notify

Pops up OS tray/desktop notifications for the user, including Telegram delivery. It creates the
`base_notify` table (token, cached payload, last-refresh timestamp, single-cache user id, and a
`telegram` flag) used to track and dedupe pending notifications, and seeds a `Base_Notify/Timeout`
CommonData option list (Disable/Manually/10s/30s/1min) that lets users choose how often the
client polls for new notifications.

Part of the **Epesi Core** package on the admin Setup screen — bundled with core, not a
separately installable option.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
