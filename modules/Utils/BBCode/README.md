# Utils/BBCode

A lightweight BBCode-to-HTML parser used by other modules for user-entered rich text. It installs the `utils_bbcode` table mapping bracket tags (`[b]`, `[i]`, `[u]`, `[s]`, `[url]`, `[color]`, `[img]`) to their rendering callbacks in `Utils_BBCodeCommon`, and other modules can register additional tags into the same table the same way.

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
