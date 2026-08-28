# Base/User/Login

Handles authentication for `Base_User` accounts. On install it creates the `user_password`,
`user_autologin`, `user_login_ban`, and `user_reset_pass` tables backing password storage,
"remember me" autologin tokens, password-reset requests, and failed-login tracking, and seeds
the `host_ban_time`/`host_ban_nr_of_tries`/`host_ban_by_login` variables that drive the
brute-force login-ban protection (temporarily banning a host, or a login, after too many failed
attempts).

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
