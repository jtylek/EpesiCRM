# Base/Admin

Provides the administration screen: the menu of admin sections built from every module's `admin()`/
`admin_caption()` (and optional `admin_access()`) callbacks, grouped into fixed sections (Administration,
User Management, Features Configuration, Data, Regional Settings, Server Configuration) and gated per module/
section via the `base_admin_access` table. It requires Acl (for permission checks) and the Theme system to
render the selected admin sub-module.

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
