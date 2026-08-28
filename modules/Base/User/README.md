# Base/User

The core user-identity module. On install it creates the `user_login` table (id, login, active,
admin flags) that every other user-related module — login credentials, permissions, per-user
settings — keys off of via `user_login_id`. It depends only on `Base_Acl`.

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
