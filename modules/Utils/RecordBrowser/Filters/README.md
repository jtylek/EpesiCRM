# Utils/RecordBrowser/Filters

Implements the filter panel used by RecordBrowser-based grids: given a `Utils_RecordBrowser` instance and a set of filterable field criteria, it builds the QuickForm filter controls, tracks which filters are active and their selected values, and applies both standard per-field filters and any custom filter callbacks a module has registered. It's a supporting UI component with no schema of its own — install just registers its default theme.

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
