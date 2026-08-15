# Toyota EU Service-Kit Database — Build & Sell Plan

Goal: build a local database mapping **Toyota model/generation → engine code → production years →
engine oil spec + oil filter + air filter + cabin filter + drain-plug seal**, bundle those four/five
parts into sellable "service kits," and list the kits on Allegro.pl with correct vehicle-compatibility
tagging.

Scope for v1 (per decision 2026-08-15): **Toyota only**, the handful of high-volume EU models
(Corolla, Yaris, Auris, RAV4, Avensis, Aygo, C-HR), schema left brand-agnostic enough to extend later
but no other-brand data loaded yet.

---

## 1. Why this is harder than "join two datasets"

Wikidata and the GitHub cars-dataset only get you the **vehicle backbone**: model, generation, body
code, production years, engine code, displacement, power, fuel type. Neither has a clue what oil
filter fits a `1ND-TV` versus a `2ZR-FE`. That fitment layer — the actual product you're selling — has
to come from a different kind of source entirely (parts catalogs, not vehicle catalogs), and it's the
part where a wrong answer has real consequences: a mis-matched oil filter or wrong oil viscosity can
damage a customer's engine, and Allegro's buyer-protection process will side against a seller who
shipped an incompatible part. **Treat fitment data as safety-critical, not just catalog data** — see
§5 (verification) before anything gets marked sellable.

---

## 2. Data model

Core entities:

```
Make            (Toyota)
Model            (Corolla, Yaris, RAV4, ...)
Generation       (model + generation code/name, body style, production_start, production_end, market = "EU")
Engine           (engine_code e.g. "1ND-TV", displacement_cc, fuel_type, power_kw, notes)
VehicleEngine    (generation_id, engine_id, production_start, production_end)
                 -- the join that actually matters: same engine code can appear across several
                 -- generations, and a generation can carry several engine codes over its run,
                 -- sometimes changing mid-cycle at facelift (which can also change the filter P/N)
OilSpec          (vehicle_engine_id, viscosity e.g. "0W-30", api_spec, acea_spec, ilsac_spec,
                  capacity_with_filter_l, capacity_without_filter_l, source_url, verified_at)
Part             (part_type: oil_filter|air_filter|cabin_filter|drain_plug_seal,
                  oe_number, brand, aftermarket_sku, notes)
Fitment          (vehicle_engine_id, part_id, effective_start, effective_end,
                  source, cross_checked_by, status: draft|needs_review|verified)
Kit              (name, vehicle_engine_id, oil_spec_id, part_ids[], margin/cost fields)
Listing          (kit_id, allegro_offer_id, allegro_compatibility_ids[], price, status)
```

The key discipline: **match at (Generation × Engine code), not at Model or Model-year.** A "2015
Corolla" isn't a fitment key — a 2015 Corolla could carry a `1.4 D-4D (1ND-TV)`, `1.6 Valvematic
(1ZR-FAE)`, or `1.33 Dual VVT-i (1NR-FE)`, each needing different everything. Engine code is the real
join key; model/year is only there to help a buyer find the right listing.

---

## 3. Sourcing plan — Phase 1: vehicle/engine backbone

**Wikidata SPARQL** (CC0, free to reuse) — query for Toyota models, instance-of "automobile model,"
pull generation, production date range, and any linked engine items. Usable as a seed/skeleton, but
Wikidata's automotive coverage is crowd-sourced and uneven — expect gaps and treat it as a starting
list to verify, not a source of truth for capacities or filter-relevant details.

**GitHub cars-dataset — evaluated, ruled out.** Checked `vbalagovic/cars-dataset` (2026-08-15): it is
**not MIT-licensed** — the repo's own README states "This dataset is proprietary. Sample data provided
for evaluation purposes only." What's actually on GitHub is a 37-row evaluation sample (Toyota gets 2
rows, both C-HR 1.2T trims); the real dataset is a paid product ($499 one-time for cars, $999 bundle,
or $149-299/mo API). More importantly, even the paid version wouldn't solve our actual problem: its
schema has no OEM engine code field (`1ND-TV`, `2ZR-FE`, ...) — only a free-text `engine_type`
description plus `cubic_capacity_cc`/`cylinders`/`power_kw`, which can't reliably distinguish two
different real engines that happen to share displacement and power. It also has no generation/
production-range field, only one row per (model, trim, year). It's built for spec-comparison and
marketplace-price use cases, not parts fitment — not worth buying for this project. (The free sample
also has an internal inconsistency — both Toyota C-HR "1.2T" rows list `aspiration: naturally
aspirated` — which wouldn't inspire confidence in the paid tier either.) **Dropped from the plan;
Wikidata + Toyota EU manuals remain the vehicle-backbone source.**

**Toyota Europe owner's manuals / technical data sheets** — the actual authoritative source for oil
viscosity + capacity per engine code, and a good cross-check for production year ranges. Worth treating
as the primary source for OilSpec even if Wikidata seeds the vehicle list.

---

## 4. Sourcing plan — Phase 2: fitment (filters + seal)

Two paths, since you weren't sure yet which to commit to:

**A. Free / manual.** Use filter makers' own "search by vehicle" tools — MANN-FILTER, Filtron (Polish
brand, strong trust signal for PL buyers), Bosch, MAHLE, Hengst — to look up each (generation × engine)
combination by hand and record the result. Zero data cost, fully clean to reuse (you're recording a
fact — "this SKU fits this engine" — not republishing their catalog). Slow: each of the ~15-20
generation×engine combos for a Toyota-only pilot needs 4-part lookups across at least two brands to
cross-check (§5), so budget real hours here. Scripting against these sites' search tools instead of
using them by hand risks their ToS (most don't offer a public API); if scripting, check robots.txt/ToS
first or stick to manual entry.

**B. Paid fitment database.** TecDoc is the industry-standard fitment database aftermarket parts
sellers build on, but it's licensed at a scale/price point aimed at parts retailers and workshops, not
easily bought standalone by a small seller. The realistic version of "paid" for your scale: **open a
wholesale/trade account with one or two Polish B2B parts distributors** (Inter Cars, Auto Partner/GK
Parts, Motointegrator/ProfiAuto are the common ones) — their dealer portals already have TecDoc-backed
"search by VIN/engine code" tools included with the account, *and* they can supply/dropship the
physical parts. That solves the data problem and the sourcing/logistics problem in the same step, which
is how most small Polish Allegro parts sellers actually operate — worth strongly considering over
building an independent fitment database from scratch, which is a lot of infrastructure for a
kit-bundling business.

**Recommendation:** start with (A) for your pilot engine list to get moving with zero upfront cost and
learn the domain, but open a distributor trade account in parallel — by the time you're ready to scale
past the pilot models, (B) will be both faster and will double as your supply chain.

---

## 5. Verification / QA discipline (do not skip)

Because a wrong fitment record can damage a customer's engine and trigger Allegro complaints:

- A `Fitment` row only reaches `status = verified` after **two independent sources agree** (e.g. two
  filter brands' own lookup tools, or one brand's tool + the Toyota parts catalog/manual).
- Disagreements get logged, not silently resolved by picking one — flag for manual review.
- Only `verified` fitment rows are eligible to be attached to a `Kit` that gets listed for sale.
- Spot-check your top-selling kits' oil filter physically against a purchased sample before your first
  real sale batch — cheap insurance against a source being subtly wrong (e.g. a facelift-year P/N
  change that a lookup tool missed).

---

## 6. Kit definition & supplier choice

A kit = one verified `VehicleEngine` + one `OilSpec` (viscosity/spec/quantity) + one each of oil
filter, air filter, cabin filter, drain-plug seal, all drawn from `verified` Fitment rows.

Decisions to make before pricing:
- **Brand tier per component** — OE/Toyota Genuine vs recognizable aftermarket (Filtron, Bosch, MANN)
  vs no-name. Polish Allegro buyers in the auto-parts category tend to trust recognizable aftermarket
  brands noticeably more than unbranded parts; factor that into margin vs conversion tradeoffs.
- **Oil brand** — own-sourced bulk oil vs a branded partner oil; must meet the ACEA/API/ILSAC spec
  pulled from the Toyota manual for that engine, not just "5W-30 in general."
- **Mid-cycle running changes** — track `effective_start`/`effective_end` on `Fitment`, not just the
  engine code, so a facelift year that swapped filter housings doesn't get bundled into the same kit as
  the pre-facelift years.

---

## 7. Allegro.pl integration

Allegro's automotive-parts categories use Allegro's own **"Dopasowanie do pojazdu"** (vehicle
compatibility) feature — you tag a listing against Allegro's own vehicle catalog entries, not free
text, so part of the pipeline is mapping your internal `VehicleEngine` records to Allegro's catalog IDs
via their compatibility endpoints.

- Allegro REST API (`api.allegro.pl`, OAuth2, sandbox environment available) supports offer creation,
  category-specific parameters, and attaching a compatibility list for moto/parts categories — this is
  what lets you push/update a kit listing programmatically instead of retyping every covered
  model/engine by hand on the listing form each time you add a generation to a kit's coverage.
- Recommend **one listing per kit** (i.e., per generation×engine, or per a family of engines that share
  identical fitment) with the full compatibility list attached, rather than one listing per exact
  model/trim — keeps the catalog size sane for a pilot of ~15-20 engine variants.
- Category: likely "Motoryzacja > Części samochodowe > Filtry" plus/or "Oleje, płyny i smary" depending
  on whether Allegro's rules let you bundle a multi-part-type kit under one category — needs a quick
  check against current category rules before finalizing listing structure.

---

## 8. Tech stack recommendation

This is a data-engineering project, not an EPESI feature, so it doesn't need to follow EPESI's PHP
module conventions unless/until you want kit sales flowing through the CRM for order management:

- **ETL/scripts:** Python (`SPARQLWrapper` for Wikidata, `pandas` for wrangling/cross-checking sources)
  — much less friction than PHP for this kind of one-off data cleaning.
- **Catalog DB during buildout:** SQLite — zero setup, easy to inspect, easy to hand-edit
  `status=needs_review` rows.
- **Later, if you want kit sales inside EPESI** (customers, orders, inventory): the schema above maps
  reasonably well onto a `RecordBrowser`-based module (`modules/Utils/RecordBrowser`), at which point it
  becomes an actual EPESI module and moves into MySQL/PostgreSQL alongside the rest of the app. Not
  worth doing until the pilot catalog and Allegro flow are proven — premature to build the CRM
  integration before you know the data pipeline works.

Proposed folder layout under `parts-kits/` (not created yet, pending your go-ahead):
```
parts-kits/
  PLAN.md              (this file)
  data/
    raw/               (Wikidata dumps, distributor exports, manual lookup notes)
    catalog.db          (SQLite working DB)
  scripts/
    fetch_wikidata.py
    import_fitment.py
    build_kits.py
    push_allegro.py
```

---

## 9. Phased roadmap

| Phase | Work | Rough effort |
|---|---|---|
| 0 | Confirm DB stack, get the GitHub dataset URL evaluated, pick pilot model list | this session |
| 1 | Vehicle/engine backbone for the ~7 pilot models via Wikidata + Toyota EU manuals | 1-2 weeks |
| 2 | Fitment sourcing for pilot engines (manual, 2-source cross-check) + open a distributor trade account | 2-3 weeks |
| 3 | Kit definition: pick brand tiers, pricing, margin per kit | few days |
| 4 | Allegro API integration: register app, map compatibility catalog, build offer-push script | 1 week |
| 5 | Launch 5-8 pilot kits, watch returns/complaints as a QA signal, then expand model coverage | ongoing |

---

## 10. Legal/licensing notes

- Wikidata: CC0 — free to reuse, no attribution required (attribution still good practice).
- GitHub cars-dataset: MIT per your description — free to reuse; still verify it actually covers the
  EU market before depending on it (see §3 open item).
- Using OE part numbers to state compatibility ("fits Toyota 1ND-TV") is standard nominative/comparative
  use across the aftermarket industry — not an issue by itself. Just don't imply your aftermarket kit
  *is* a genuine Toyota part or use Toyota's logo/branding on the listing unless you're actually selling
  genuine parts.
- Scraping filter manufacturers' vehicle-search tools programmatically may violate their ToS even though
  looking up individual parts by hand doesn't — check robots.txt/ToS per site, or stick to manual entry
  for phase 2.
- Allegro enforces compatibility-data accuracy on parts listings; repeated mis-fitment complaints can get
  listings pulled or the account penalized — another reason §5's verification gate matters.

---

## Open items before implementation starts

1. ~~Evaluate GitHub cars-dataset~~ — done, ruled out (see §3): proprietary, no engine-code field, not
   fit for purpose. Vehicle backbone stays on Wikidata + Toyota EU manuals.
2. Confirm the pilot model list (proposed: Corolla, Yaris, Auris, RAV4, Avensis, Aygo, C-HR — adjust if
   your actual sales focus differs).
3. Decide free-manual vs distributor-account path for fitment sourcing (or start (A) now, open a trade
   account in parallel, per the §4 recommendation).
4. Say the word and I'll scaffold `parts-kits/data/` and `parts-kits/scripts/` and write the first
   Wikidata SPARQL pull for the pilot models.
