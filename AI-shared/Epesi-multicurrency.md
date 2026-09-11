# Multicurrency support

Currency master data lives in `Utils_CurrencyField` (`modules/Utils/CurrencyField/`), admin screen
Administration → *Currencies* (`Utils_CurrencyFieldCommon::admin_caption()`, section "Regional
Settings"). This is about that module's own tables — not about `RBO_Field_Currency`/the `'currency'`
RecordBrowser field type, which is how *other* modules attach a currency amount to their own records
(see below).

## Tables

- **`utils_currency`** — one row per currency: `symbol`, `code` (ISO-ish, e.g. `USD`), `decimal_sign`,
  `thousand_sign`, `decimals`, `pos_before` (symbol before/after the amount), `active`, and
  `default_currency` (exactly one row should have this set — it's the fallback in
  `Utils_CurrencyFieldCommon::get_values()` and the default for `Base_User_Settings`).
- **`utils_currency_rate`** — daily exchange rates: `currency_id`, `target_currency_id`, `rate_date`,
  `rate`, `source`, `fetched` (unix timestamp), unique on `(currency_id, target_currency_id, rate_date)`.
  Both id columns are FK-shaped (point at `utils_currency.id`) but there's no DB-level foreign key —
  nothing but `delete_currency()` (see Deleting a currency, below) cleans up rows here.

Rates are pulled from `api.frankfurter.dev` (free, ECB-backed, no key) by
`Utils_CurrencyFieldCommon::fetch_daily_rates()`, wired to the "Update currencies exchange rates" admin
action and gated by the `CURRENCY_RATE_AUTO_FETCH` config constant. It backfills from
`get_rate_backfill_start()` (default: one year back, overridable via the `utils_currency_rate_backfill_start`
Variable) through today, for every pair of *active* currencies.

After a fetch, it also re-triggers amount recalculation in whichever optional Premium modules are
installed (`Premium_Accounts`, `Premium_Expenses`, `Premium_Timesheet`, `Premium_Vehicles`), each via its
own `recalculate_missing_amounts()` hook, guarded by `ModuleManager::is_installed(...)` — the established
pattern in this codebase for a core module to reach into optional Premium modules without a hard
dependency on them being present.

## How a currency amount is stored on an arbitrary record

A RecordBrowser field of type `'currency'` (raw array-based `'type'=>'currency'`, or the OOP
`RBO_Field_Currency` wrapper — both go through the same `Utils_RecordBrowserCommon::actual_db_type()`
and end up as one `VARCHAR(128)` column) stores its value as a single string:

```
<amount>__<currency_id>
```

produced by `Utils_CurrencyFieldCommon::format_default()` / parsed by `::get_values()`. **The suffix is
`utils_currency.id`, not `code`** — a currency's code/symbol can be edited freely without touching stored
records, but its numeric id is load-bearing everywhere data has already been saved with it. If a value has
no `__<id>` suffix at all, `get_values()` falls back to the user's configured default currency.

## Finding every place a currency is actually used

Because RecordBrowser field metadata is itself DB-driven, "is currency X used anywhere" is answerable with
SQL alone — no source-code grep needed, which matters because that would silently miss
`modules/Premium/*` (gitignored, see root `CLAUDE.md`) even though those modules are real, installed
consumers of currency fields in production:

1. `SELECT tab FROM recordbrowser_table_properties` — every recordset, core and Premium alike.
2. For each `tab`, `SELECT field FROM <tab>_field WHERE type='currency'` — that tab's currency-typed
   fields, if any (most tabs will have none).
3. Each such field's column in `<tab>_data_1` is `f_<slug>`, where
   `<slug> = Utils_RecordBrowserCommon::get_field_id($field_name)` (lowercase, non-alnum → `_`) —
   derivable without a DB lookup.
4. A currency is in use there if any row has `f_<slug>` ending in `__<currency_id>`. Match the suffix
   carefully: `LIKE '%__<id>'` needs the two underscores escaped as literals (they're normally
   single-char wildcards), but no further escaping of `<id>` is needed — amounts are numeric-only, so
   `__` can't otherwise appear before it, and a plain suffix match won't false-positive id `5` against
   id `15` (`'...__15'` does not end in the literal `'__5'`).

## Deleting a currency

The Currencies admin list does have a delete action (`Utils_CurrencyField::delete_currency()`), but it's
deliberately hard to reach, because a currency id can be referenced from an unbounded number of recordset
tables — including ones this checkout can't even see, in `Premium` — and a delete with no usage check
would silently corrupt stored amounts (orphaned `__<id>` suffix, which formats to nothing per
`Utils_CurrencyFieldCommon::format()`'s empty-currency early return). The row action is disabled (icon
present, grayed out, tooltip explains why) unless all of the following hold:

- the currency is **not** `active` — deactivate it first; this is enforced so delete is only ever
  exercised on something nobody's actively transacting in, never used as a substitute for turning it off,
- it's **not** the `default_currency` row,
- `Utils_CurrencyFieldCommon::is_currency_used()` (the scan described above) finds no recordset value
  ending in `__<id>` anywhere. `is_currency_used()` is a thin wrapper over
  `find_currency_usage($currency_id, $limit)`, which also backs the "Show usage" row action (always
  available, not just when delete is blocked).

"Show usage" is two screens, both real GenericBrowser reports (filterable/sortable/paged, not dumped
lists):

1. `Utils_CurrencyField::show_usage()` - a summary: one row per recordset that uses this currency, with a
   match count (`Utils_CurrencyFieldCommon::count_currency_usage_by_tab()`, a `COUNT(DISTINCT id)` per
   tab rather than fetching every id just to count them) and a "Show usage" action per row.
2. `show_usage_detail($id, $tab)` - the actual records for one recordset (Recordset / Field / Record,
   the last a link via `create_linked_label()`/`create_linked_text()`). `$tab` is only the *initial*
   selection (whichever summary row was clicked); a "Recordset" filter stays on screen (defaulting to
   that tab, with an "All" option) so the user can switch between recordsets without going back to the
   summary. Picking a specific tab scopes `find_currency_usage()`'s scan to just that recordset's own
   currency fields - only "All" pays for a full scan across every recordset.

Since matches come from an arbitrary number of unrelated recordset tables/columns, there's no single
real query for GenericBrowser's own `query_order_limit()` (the mechanism `show_rates()` also uses) to run
against directly. `show_usage_detail()` materializes its scan into a **connection-scoped temporary table**
(`tmp_currency_usage`, recreated on every call) and browses that instead - same pattern as any other
GenericBrowser-backed report, just against synthesized rather than persistent data. `find_currency_usage()`
takes an optional `$limit` (used by `is_currency_used()` to stop at the first match) and `$tab_filter`, but
the detail screen calls it uncapped - GenericBrowser's own paging handles display size, so there's no
reason to truncate the underlying result. Picking "All" still means materializing every match on every
request (every page click included, since nothing persists across requests) - acceptable for an on-demand
admin diagnostic, but worth knowing if it ever feels slow on a very large install.

All three are re-checked server-side in `delete_currency()` itself, not just in the UI (the confirm-href
could in principle be replayed from an already-open tab). A successful delete also purges the currency's
rows from `utils_currency_rate` (`WHERE currency_id=%d OR target_currency_id=%d`) in the same transaction,
since that table has no FK and nothing else cleans it up.

This doesn't fully close the check-then-delete race (a currency could theoretically be used by a
still-open form between the scan and the delete) — closing that would need row-level locks across an
unbounded set of arbitrary recordset tables, which isn't practical. Deactivation being a prerequisite is
what actually keeps this safe in practice: an inactive currency isn't offered in `get_currencies()` (the
list new records draw from), so new usage after deactivation is exactly one already-stale form away, not
an ongoing possibility.
