# Currencies

The currencies an installation works in, its home currency, and daily exchange rates. It is a
core module: it is always installed and can't be switched off. RecordBrowser's **currency field
type** (`Field::currency()`) and Regional Settings' default currency are built on it.

Developer reference with more detail: `AI-shared/Epesi-currencies.md` in the repository.

## For administrators

### Administration → Currencies

- USD, EUR, GBP and PLN are set up on install. **New currency** adds any ISO 4217 currency in
  circulation. Its name and decimal places are filled in for you (JPY 0, KWD 3, most others 2).
- Switch a currency off to stop offering it on new records. Records that already use it keep it.
- **Set home currency** is the currency the books are kept in.
  - **With a date**, the change applies from that date on. Earlier records keep the home
    currency they had, so moving the company to another country doesn't rewrite its history.
  - **Without a date**, it applies to all dates. Use this on a new installation, or to correct a
    mistake.
- The home currency can't be switched off. A currency that is or ever was the home currency can't
  be deleted.

### Administration → Exchange Rates

- Daily rates come from one of two sources, chosen under **⋮ → Settings**:
  - **ECB** (European Central Bank, through api.frankfurter.dev): the usual choice. It is free
    and needs no key.
  - **NBP** (National Bank of Poland, table A): for Polish VAT, which converts at the NBP rate
    of the business day *before* the document date. Epesi applies that rule automatically.
- Rates are fetched every day at 17:00 UTC, which needs the server's cron (see Administration →
  Cron). **Fetch now** fetches them straight away. **Fetch history from** (in Settings) sets how
  far back the first fetch goes.
- **⋮ → Add custom rate** is for a currency the chosen source doesn't publish. Epesi uses it only
  when the source has no rate.
- **Currency Converter**: type an amount and it shows it in another currency, at the rate a document dated on the chosen day would get, and where the rate came
  from. Every user can also add it to the Dashboard as an applet (Add applet → Currency
  Converter); its gear sets the currencies it starts with.

### Each user's default currency

User settings → Regional Settings → **Default currency** is the currency new amounts start in.
Administration → Regional Settings sets the default for everyone. With neither set, new
amounts start in the home currency.

## The currency field

A field that holds **an amount and the currency it is in**, such as "1,500.00 EUR". It is
legacy Epesi's `currency` field type, but stored so the database can sort, filter and add up the
amount.

### What users see

| Where | What |
|---|---|
| Form | The amount and a currency list side by side. The currency starts at the user's default currency. Once there is an amount, a currency is required. Only active currencies are offered, plus the one a record already has. |
| View page | The amount as money in its own currency, in the user's language: `€1,500.00`, `PLN 1,500.00`. |
| List | The same, aligned right. Sorting goes by the number alone, so 100 EUR and 100 PLN sit together. |
| Filter | "From" and "until" amounts, plus a currency. Pick a currency for an exact comparison: without one, "over 1,000" means 1,000 of whatever each row is in. |
| History | One line for both parts: `Budget: 10.00 PLN → 12.00 EUR`. |

Nothing is converted between currencies. A record keeps the amount and currency it was entered
with.

### Adding one as an administrator

Administration → Recordsets → pick a recordset → add a field of type **Amount with currency**,
and choose its decimal places. This creates two columns on the recordset's table: `cf_N` for the
amount and `cf_N_currency` for the currency. The field's **Drop column** action removes both,
with their values. An
existing *Decimal* or *Integer* field can be changed to *Amount with currency*: its amounts stay,
and their currency starts empty.

### Using it in a module

Declare the field in your recordset resource's `fields()`:

```php
use Epesi\Modules\RecordBrowser\Recordset\Field;

public static function fields(): array
{
    return [
        Field::text('name')->required()->inTable(),
        Field::currency('total_budget')->inTable()->filterable(),
        Field::currency('price_jpy', decimals: 0),
    ];
}
```

Create **two columns** in your migration: the amount, with the same number of decimals, and
its currency code, named `<name>_currency`. Both are nullable.

```php
$table->decimal('total_budget', 15, 2)->nullable();
$table->char('total_budget_currency', 3)->nullable();
```

Add both columns to the model's `$fillable`. The amount's cast (`decimal:2`) comes from the
field. Then run `php artisan recordset:check`, which reports a currency field whose
`_currency` column is missing.

In your module's `module.json`, list `"epesi/currencies"` in `requires` if your code calls this
module's services directly. RecordBrowser already requires it.

Values store the **ISO code** (`EUR`), never an id, so they read the same on every installation
and in plain SQL.

A currency field can't be part of a collection, such as an address.

### Importing legacy values

Legacy Epesi stores a currency value as one string, `amount__currencyid` (for example `60__2`).
`App\Services\LegacyImport\LegacyMoney::parse($raw)` splits it into the amount and the ISO code,
reading the codes from the legacy `utils_currency` table. A value with no currency gets the
legacy default currency.

```php
[$amount, $code] = LegacyMoney::parse($row->f_total_budget);   // ['60', 'PLN']
```

`php artisan import:legacy currencies` copies the legacy currency list itself, and makes the
legacy default currency the home currency.

## For developers: services

All in `Epesi\Modules\Currencies\Services`. Get them from the container (`app(...)`).

| Call | Returns |
|---|---|
| `CurrencyRepository::active()` / `options()` | Active currencies, `code => name` / `code => "CODE (Name)"` for a select. |
| `CurrencyRepository::defaultCode()` | The currency a new amount starts in: the user's, then the system's, then the home currency. |
| `CurrencyRepository::home($date = today)` | The home currency **on that date**. Read it as of a document's own date, never today's. |
| `CurrencyRepository::decimals($code)` | The currency's decimal places. |
| `CurrencyRepository::format($amount, $code)` | `"€1,500.00"` in the app's language. Without the PHP `intl` extension it falls back to `"1,500.00 EUR"`. |
| `RateResolver::resolve($from, $to, $date, $manualRate = null)` | A `ResolvedRate` with `rate`, `source` and `rateDate`. `to_amount = from_amount × rate`. |

`RateResolver` decides a rate in this order:

1. A rate typed on the document (`manual`).
2. 1 for the same currency (`same`).
3. The chosen source (`ecb` / `nbp`), with cross rates worked out through its base currency. A
   rate more than 7 days old is not used.
4. An administrator's custom rate (`custom`).
5. Otherwise `none`, with a null rate.

It never substitutes today's rate for a historical one. A document that books at a rate should
store the rate, its source and the home currency when it is issued, and not look them up again.

Tests: `tests/Feature/Modules/CurrenciesTest.php` and `tests/Feature/Modules/CurrencyFieldTest.php`.
