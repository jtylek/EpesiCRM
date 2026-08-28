# Base/HomePage

Manages the list of candidate home-page modules a user can land on after login, each with an ordering
priority and one or more clearance requirements (`base_home_page` / `base_home_page_clearance`); modules
register their own home-page option via `Base_HomePageCommon::set_home_page()`, and the first one the current
user has clearance for is loaded. It depends on Box, User and ActionBar to actually display the chosen page.

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
