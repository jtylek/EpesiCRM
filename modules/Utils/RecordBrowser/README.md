# Utils/RecordBrowser

The generic data-grid/CRUD engine (`Utils_RecordBrowser`) that most business modules in Epesi — Contacts, Companies, Tasks, and dozens of others — are built on top of, rather than each rolling its own list/search/filter/CRUD screens. A "recordset" (one browsable table of records) is defined by installing rows into this module's schema: `recordbrowser_table_properties` for its tab name, template, caption, icon, and display options (favorites, recent, full history, jump-to-id, search inclusion/priority); `recordbrowser_datatype` for the field-type callbacks a recordset's columns use; `recordbrowser_addon` for extra panels/buttons other modules attach to a recordset's records; `recordbrowser_access_methods` and `recordbrowser_processing_methods` for per-recordset access rules and submit-time hooks; and `recordbrowser_search_index` for the full-text search index behind global search.

It composes several sibling modules to do this: Utils/RecordBrowser/Filters for the filter panel, Utils/RecordBrowser/RecordPicker and RecordPickerFS for inline/full-screen record selection widgets, and Utils/CommonData for reference data (it also seeds the CSV export defaults used by recordset exports). On install it also registers a print handler (`Utils_RecordBrowser_RecordPrinter`) so any recordset can be printed through Base/Print.

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
