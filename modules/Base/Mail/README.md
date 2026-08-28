# Base/Mail

Holds the system's outgoing-mail configuration as instance variables: sender address/name, reply-to
handling, delivery method, SMTP host/user/password/security and whether SMTP auth is required. These
`mail_*` variables are what the rest of the codebase reads to actually send email. It depends on QuickForm
for its settings form and on Acl/Admin/Theme/Lang for its admin presence.

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
