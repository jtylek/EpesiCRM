# Utils/CommonData

A generic hierarchical key/value data store, backed by the single `utils_commondata_tree` table (parent/child rows keyed by name, holding an arbitrary value, an optional read-only flag, and a sort position). Other modules use it via `Utils_CommonDataCommon`'s array/tree helpers to seed and manage nested reference data — for example Data/Countries' country and state lists, or RecordBrowser's CSV export settings.

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
