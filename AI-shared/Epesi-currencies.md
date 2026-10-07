# Currencies and exchange rates

`modules/Epesi/Currencies` is a core module (`"core": true`) on the Administration panel. It
holds the currencies an install uses, the home currency, and a cache of daily exchange rates.
Other modules read all of this through its services, never through the tables.

RecordBrowser (the `currency` field type, `Field::currency()`; see
[Epesi-custom-fields.md](Epesi-custom-fields.md), Step 11) and RegionalSettings (each user's
default currency) require it. So should anything else that records money.

## The `currency` field type

- `Field::currency($name, $decimals = 2)` uses two columns: `{name}` `decimal(15, $decimals)`
  and `{name}_currency` `char(3)`. A module's migration creates both, and `recordset:check`
  reports a missing currency column. An administrator's field gets `cf_N` and `cf_N_currency`
  (`CustomFieldSchema`). A Decimal or Integer field can be changed to Currency, which adds the
  code column, empty.
- **Form:** a `FusedGroup` of the amount and a currency select (active currencies). It starts at
  `CurrencyRepository::defaultCode()`: the user's Regional Settings currency, then the system
  one, then today's home currency. A currency is required once there is an amount.
- **View and list:** `CurrencyRepository::format()`, which uses intl's currency format in the
  app's language and falls back to "1,234.50 PLN" without intl. The list sorts by the number
  alone.
- **Filter:** an amount range plus a currency.
- **History:** one line for both columns, "10.00 PLN → 12.00 EUR".
- Nothing converts between currencies; documents that book at a rate use `RateResolver` and
  freeze the rate themselves.
- Not available in collection items (an address can't have one).

## New core modules on update

`epesi:update` (and Administration → Database update) registers core modules that are on disk
but not in the `modules` table, in dependency order, before migrating
(`SystemUpdate::newCoreModules()`). Currencies came after the first installs; without this,
an updated install would not have it.

## What changed from legacy `Utils/CurrencyField`

| Legacy | Here |
|---|---|
| Values reference `utils_currency.id` (`"60__2"`) | Values reference the ISO 4217 code |
| One `default_currency` flag; changing it re-bases every old document | The home currency is **dated** (`currency_home_periods`) |
| Symbol, separators and position stored per currency | Not stored; formatting will come from the locale |
| Rate cache holds every pair, N×(N−1) rows a day | Only what the provider publishes: N rows a day, cross rates derived on read |
| Rates fetched by hand (admin button) | Daily cron task plus "Fetch now" |
| One source (Frankfurter) | Pluggable providers: ECB (Frankfurter) and NBP |

## Tables

- **`currencies`**: `code` (char 3, unique), `name`, `decimals` (ISO minor units), `active`,
  `position`. The migration seeds USD, EUR, GBP and PLN. The "New currency" form picks from
  `resources/iso4217.php`, which lists the codes in circulation with ISO minor units. These are
  not ICU's display digits: HUF and IDR have 2, ISK has 0.
- **`currency_home_periods`**: `currency_code`, `effective_from` (unique). The home currency on
  date D is the row with the latest `effective_from` on or before D. It is seeded as USD from
  1970-01-01. "Set home currency" either adds a period from a date, or, with no date, replaces
  them all.
- **`currency_rates`**: `provider` (`ecb`, `nbp`, `custom`), `base`, `quote`, `rate_date`,
  `rate` (a double, never rounded), `fetched_at`. ECB rows are EUR → X and NBP rows are
  X → PLN, exactly as published. `custom` rows are pairs an administrator typed in.
- **`epesi_currency_settings`** (single row): `rate_provider`, `auto_fetch`, `backfill_from`,
  and the last fetch's time and result. `rate_provider` defaults to `auto`: NBP when the home
  currency is PLN, ECB otherwise, decided each time it's read (`RateProviders::currentId()`),
  so it follows a home currency set after the row was made. Choosing ECB or NBP in Settings
  fixes it.

Index names are given explicitly. Every timestamp is nullable, to avoid MySQL's
`ON UPDATE CURRENT_TIMESTAMP` on the first NOT NULL timestamp.

## Services (`src/Services/`)

- **`CurrencyRepository`** (singleton):
  - `active()` and `options()` for selects;
  - `isActive()`;
  - `decimals($code)`: this install's value, then ISO, then 2;
  - `home($date = today)`;
  - `setHome($code, $from = null)`;
  - `isHomeInAnyPeriod()`.

  Read the home currency **as of a document's date**, never today's. A company that moves
  country changes it, and old documents keep theirs.
- **`RateResolver::resolve($from, $to, $date, $manualRate = null, $provider = null)`** returns a
  `ResolvedRate` with `rate`, `source` and `rateDate`. The direction is
  `to_amount = from_amount × rate`, and there is no reciprocal anywhere else. The order:
  1. `manual`: a rate typed on the document. It always wins, because a bank's rate isn't the
     central bank's.
  2. `same`: the two currencies are the same, so the rate is 1.
  3. The configured provider (`ecb` / `nbp`), derived through its pivot. For the ECB,
     USD → PLN = (EUR → PLN) / (EUR → USD). It takes the latest rate on or before D. For the
     NBP it takes the latest rate *strictly before* D: Polish VAT (Art. 31a) uses the NBP
     table of the last business day before the tax point. A rate older than
     `MAX_AGE_DAYS` (7) is ignored, because it means the cache stopped being filled.
  4. `custom`: an administrator's rate for the pair, in either direction, the latest on or
     before D. These cover currencies the provider doesn't publish.
  5. `none`: `rate` is null. Callers leave it empty and never put today's rate in its place.
- **`RateFetcher::fetch($provider = null)`** works per active currency the provider publishes.
  It fetches from the day after that currency's last cached rate (or `backfill_from`) through
  today, and upserts the rows. It returns `fetched`, `skipped` (codes the provider doesn't
  publish) and `error`. A provider failure is reported, not thrown.
- **`RateProviders`** maps a provider id to its class. A new provider is one class implementing
  `Providers\RateProvider` (`id`, `pivot`, `strictlyBefore`, `supported`, `fetch`), plus an
  entry here and in `CurrencyRate::providers()`.
  - `EcbProvider`: `api.frankfurter.dev`, no API key, fetched in chunks of up to 365 days. These
    are mid-market reference rates.
  - `NbpProvider`: `api.nbp.pl` table A, fetched in chunks of 90 days (the API caps a range at
    93). A 404 from it means "no table in this range", not an error.
- **`RateFormatter::format($rate)`** shows a rate by significant digits, not fixed decimals:
  0.0000578, 25.34, 1.00.

## Cron, UI, import

- **Cron task:** "Currencies: fetch daily exchange rates" runs `dailyAt('17:00')` UTC when
  `auto_fetch` is on (see [cron.md](cron.md)).
- **Administration → Currencies:** a single list with modals: new, edit, active toggle,
  reorder, and "Set home currency". The home currency can't be deactivated. A currency that
  is, or ever was, home can't be deleted.
- **Administration → Exchange Rates:** the cached rates with filters for provider, currency
  and date. Header actions: "Fetch now", "Converter" (shows what the resolver gives a document)
  and a "⋮" menu with "Add custom rate" and "Settings" (provider, daily fetch, history start).
  Fetched rows are read-only; custom ones can be edited.
- **`import:legacy currencies`** (registered `first`) copies `utils_currency` into `currencies`,
  matched on code, and makes the legacy default currency home for every date. The legacy rate
  cache is not imported: refetch from the backfill start instead.
- Tests: `tests/Feature/Modules/CurrenciesTest.php`, with the rate APIs faked with `Http::fake`.
