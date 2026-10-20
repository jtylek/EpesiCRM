# Sorting a list by a collection column

Design only; nothing here is built. Summary and the reason it is not built yet: "Still open" in
[Epesi-optimization.md](Epesi-optimization.md). Check against `Field.php` (lazy select options) before building.

## Problem

A Collection field's column (e-mail, phone, address) sorts through `Field::collectionSortQuery()`:
`ORDER BY (SELECT <shown column> FROM <items> WHERE owner_id = x.id AND owner_type = ? AND field = ?
ORDER BY position, id LIMIT 1)`. That is one correlated subquery per owner row, about 1-1.5 s of
database time at 100,000 companies, and an index on the items table changes it by about a tenth
(see "Still open" in Epesi-optimization.md). Showing the column stays cheap, since it is a
page-sized eager load. Only the sort scans.

## Proposal: a stored primary value on the owner row

Keep the primary item's displayed value in a plain column on the owner table, so the sort is
`ORDER BY <col>` on an indexable column, like any other field.

- **Column.** `cs_<field>` (e.g. `cs_phones`, `cs_emails`, `cs_addresses`), a nullable string of
  fixed length (191 or 255 so a MySQL index key stays short; `string(191)` is enough for sorting),
  plus index `(deleted_at, cs_<field>)` named explicitly, within 64 characters. Created by a
  migration in the module that owns the owner table; for custom collection fields added by an
  administrator, by the same DDL path `customfields:sync` uses for `cf_<id>` columns (this makes a
  collection field "need DDL" only when it is made sortable, which today's "needs no DDL" note in
  Epesi-custom-fields.md must then say).
- **Value.** The sort key of the item with the lowest `position` then `id`: exactly what the
  subquery picks today, so the result is identical. It is the shown column's value
  (`collectionListField()`), lower-cased/trimmed for text, not the formatted summary. A phone
  sorts by its stored digits; an address by the list column (City or the first shown item
  field); a field with no items stores NULL (sorts first or last as the current subquery does).
- **Writer.** One place: `HasCollections::syncCollection()`, inside its existing transaction,
  after items and positions are final, from `$kept` (first element by position). Written with
  `saveQuietly`/`updateQuietly` on the owner (no second History entry; `touchQuietly` already
  runs there). Two other places change the primary: owner restore (nothing to do, items stay)
  and force-delete (row gone). Soft-delete leaves the value in place; the index includes
  `deleted_at` as for the default-sort indexes. Today `syncCollection()` is the only code that
  writes items (the importers, the portal e-mails and the setup wizard go through it), so one hook
  suffices; a test should assert that, by failing when a model other than the trait writes items.
- **Backfill.** A migration computes the column for existing rows in chunks:
  `UPDATE owners SET cs_x = (SELECT value ... LIMIT 1)` per chunk of ids (one pass, a few seconds
  per 100,000 rows), and `epesi:update` re-runs it idempotently. `recordset:check` gains a rule:
  a sortable collection field with a missing column fails; a stale column is found by a
  `--strict` comparison of a sample against the subquery. Legacy import calls `syncCollection()`
  so it fills the column as it goes; `import:legacy` needs no extra step.
- **Where it hooks into `Field`.** `collectionSortQuery()` returns
  `orderBy($owner.cs_<name>, $direction)` when the column exists (`Schema::hasColumn` memoized
  with `RequestMemo`, not queried per request), and falls back to today's subquery when it does
  not, so an owner without the migration still sorts. Opting in is an explicit
  `Field::collection(...)->sortStored()` on the resources that want it (Companies, Contacts); the
  default is unchanged. F4's change to `Field` (lazy `options()`) does not touch this method, but
  rebase over it.
- **Custom-field semantics.** A collection added in Administration → Fields is sortable only after
  an administrator ticks "Sortable": that creates the column and runs the backfill, the same way
  adding a `cf_<id>` column does, and unticking drops the column. Its `Field` is built from the
  custom-field row, so `sortStored()` is set from that setting instead of by a resource. Renaming
  or retyping the item field that is shown (`collectionListField()` changing) must rerun the
  backfill; the settings save does that.

## Alternative: switch the sort off above a size

If a stored column is unwanted: `collectionSortQuery()` returns null when the owner table has more
than a threshold of rows (count from the cached table stats, not `COUNT(*)`), so the header is not
clickable on a big installation. Zero schema change, but a feature disappears exactly when a
customer has most data. The stored column is the recommended route; this is the fallback for custom
collections where nobody wants DDL.

## Costs and risks

- One extra owner-row write per collection save, only when the primary value changed (compare
  before writing).
- Drift if code ever writes items without `syncCollection()`; the `recordset:check` sample and a
  test on the trait guard it.
- MySQL vs SQLite: `string(191)` index and the correlated `UPDATE` backfill work on both; keep the
  backfill portable by chunking in PHP rather than `UPDATE ... JOIN`.
- Measure before/after on a 100,000-row throwaway copy, with Apache and OPcache, against the
  1-1.5 s baseline, before keeping it.
