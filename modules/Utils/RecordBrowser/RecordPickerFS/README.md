# Utils/RecordBrowser/RecordPickerFS

The full-screen counterpart to Utils/RecordBrowser/RecordPicker: instead of an inline widget, it pushes a `Utils_RecordBrowser` instance onto the page as a full box overlay for selecting records, then pops back to the caller (preserving the previous selection on cancel). Used where record selection needs the full browse/filter/search experience rather than an inline picker. Has no schema of its own — install/uninstall are no-ops.

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
