# Utils/Attachment

Adds a reusable file/note attachment capability that other modules' record pages can hook onto. It defines the `utils_attachment` recordset (title, rich-text note, uploaded files, sticky/crypted flags, and a permission level) and a companion `utils_attachment_related` admin table listing which recordsets have the Attachments addon wired onto them. View/edit/delete access is governed per record by its permission level and creator, and changes are logged through Watchdog.

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
