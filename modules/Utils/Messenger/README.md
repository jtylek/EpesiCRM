# Utils/Messenger

Delivers popup alert messages to specific users on behalf of other modules. Each message is stored in `utils_messenger_message` along with a callback (module, page context, and arguments) to invoke when it's actioned, its creator, and an optional scheduled `alert_on` time; per-recipient delivery/acknowledgement state (done, done on, follow) is tracked separately in `utils_messenger_users`. Installs the "Messenger Alerts" ACL permission for employees.

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
