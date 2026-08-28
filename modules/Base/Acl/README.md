# Base/Acl

Implements Epesi's access-control system: permissions (`base_acl_permission`), the rules attached to them
(`base_acl_rules`), and the clearance strings each rule requires (`base_acl_rules_clearance`), resolved
through pluggable clearance callbacks registered in `base_acl_clearance` (seeded with
`Base_AclCommon::basic_clearance`). Every module that needs to gate a feature behind a permission or check
whether the current user is logged in relies on this module.

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
