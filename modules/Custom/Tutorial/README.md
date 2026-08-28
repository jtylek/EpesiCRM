# Custom/Tutorial

A reference/example Epesi module, built alongside `AI-shared/Dev-Tutorial.md` as the
**complete, working companion** to that write-up. It's tracked in git (unlike
`modules/Premium/`) since it's referenced directly from the tutorial and is meant to
travel with the repo. Read the tutorial doc section by section and this module's files
side by side — that's the fastest way to internalize the conventions.

## What it does

It's a small "task/ticket" style app built on `Utils_RecordBrowser`, Epesi's generic
data-grid/CRUD framework. Two tables:

- **`tutorial`** — the main record set (sidebar leaf "Records"). Deliberately exercises
  *every* RecordBrowser field type in one real, installable table: text, long text,
  `commondata` (fixed dropdown, incl. a chained Country/Zone pair), `select`/
  `multiselect` (pointed at the category table below), `crm_contact` (a registered
  datatype wired to CRM's own contact picker), integer, float, currency, checkbox,
  date, timestamp, time, `autonumber`, `file`, `hidden`, and `calculated`.
- **`tutorial_category`** — a small lookup table (sidebar leaf "Categories") that the
  main table's Category/Related Categories fields point at. It also carries a
  RecordBrowser *addon tab*, shown when viewing a category record, listing every
  Tutorial record filed under it.

## Why it exists

Not a feature for end users — it's a teaching aid for developers. Each field/class in
`TutorialInstall.php`, `Tutorial_0.php`, and `TutorialCommon_0.php` is commented with
*why* it's built that way (which helper to use, which trap it avoids), and cross-
references the relevant section of `AI-shared/Dev-Tutorial.md`. When in doubt about how
to wire up a new RecordBrowser field, a display callback, a processing callback, a
crits callback, or an addon tab, copy the pattern used here rather than inventing one.

## Files

| File | Purpose |
|---|---|
| `TutorialInstall.php` | Schema for both tables, ACL, the `Tutorial_Priority` CommonData list, module dependencies (`requires()`), and `simple_setup()` (Setup screen entry). |
| `Tutorial_0.php` | The instantiable module: `body()` (Records), `categories()`, and `category_records_addon()`. |
| `TutorialCommon_0.php` | Sidebar `menu()`, the AdminLTE icon, display/processing/crits callbacks used by the fields above. |
| `theme/package-icon.png` | Icon shown for this package on the Setup screen (`'icon'=>true` in `simple_setup()`). |

## Installing / removing

Standard module lifecycle — install/uninstall via the admin Setup screen (Simple or
Advanced view) or `console.php`. `uninstall()` in `TutorialInstall.php` reverses
`install()` completely (drops both tables in dependency order, removes the
`Tutorial_Priority` CommonData array), so it's safe to install and remove repeatedly
while experimenting.
