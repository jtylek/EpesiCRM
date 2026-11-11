# Tax Rates

VAT and other tax rates, each with a code. It is a core module: it is always installed and can't
be switched off. Accounting's invoice, expense and reusable lines and Inventory's items take
their rate from it, and an installation without Accounting has it too.

## For administrators

### Administration → Data → Tax Rates

- A new installation starts with VAT 23%, VAT 8% and VAT 5%, and three 0% codes: **0% (no
  tax)**, **Exempt** and **Reverse charge**. Rated taxes differ by country: change or add them
  to match yours.
- Each rate has a name, a code, the rate in percent, a **tax type** (Standard, Reduced, Zero
  rated, Exempt, Reverse charge), an optional country code and a description. The 0% types are
  kept apart because tax returns report them apart.
- Switch a rate off to stop offering it on new lines. A line copies the rate when it is saved,
  so changing a rate doesn't change lines already saved (until a draft's line is saved again);
  issued documents can't be edited at all.
- History records every change.

## For developers

- Model `Epesi\Modules\TaxRates\Models\TaxRate`, table `tax_rates`, morph alias and activity
  log name `tax_rate`. Columns: `name`, `code`, `rate` (`decimal(8,4)`), `tax_type` (a key of
  `TaxRate::TYPES`), `country_code`, `description`, `active`, `legacy_id`, `created_by`, soft
  deletes.
- Point at a rate with a nullable `tax_rate_id` column (a foreign key to `tax_rates` with
  `nullOnDelete()`) and a `belongsTo(TaxRate::class)`, and copy its `rate` onto the record when
  the record is saved, as Accounting's lines do. List `"epesi/taxrates"` in your module's
  `requires`.
- In a recordset resource: `Field::relation('tax_rate_id', TaxRate::class, 'taxRate')`. The
  resource is also registered in the main panel (without a menu entry), so the rate shows by its
  name and links to its page there.
- No custom fields: it is a lookup table.

## Importing from legacy Epesi

`php artisan import:legacy invoice_tax_rates` copies legacy Data → Tax Rates (`data_tax_rates`)
with its history, keeping the legacy ids that legacy invoices, expenses and items point at.
`import:legacy all` runs it before the other tabs, and Accounting's `invoices` and `expenses`
tabs run it themselves.

Tests: `tests/Feature/Modules/TaxRatesTest.php`.
