# FullCalendar 6.1.21 — Standard Bundle (MIT)

Vendored from the `fullcalendar` npm package (https://www.npmjs.com/package/fullcalendar),
version 6.1.21, `index.global.min.js` only — a single self-contained UMD/global bundle,
no build step required to use it (matches this project's own no-build-step convention).

**License: MIT** (see `LICENSE.md`, verbatim from the npm package). The `fullcalendar`
meta-package bundles exactly these MIT-licensed plugins, confirmed against its
`package.json` at vendoring time:

- `@fullcalendar/core`
- `@fullcalendar/interaction`
- `@fullcalendar/daygrid`
- `@fullcalendar/timegrid`
- `@fullcalendar/list`
- `@fullcalendar/multimonth`

**Deliberately NOT vendored**: `fullcalendar-scheduler` or any `@fullcalendar/resource*`/
`@fullcalendar/timeline` package. Those "Premium"/"Scheduler" plugins are under a separate,
non-free license (commercial/non-profit/AGPL — see https://fullcalendar.io/license) and
are out of scope for this project, which only uses the open-source standard bundle.

No CSS file ships with this version — v6's standard bundle injects its own base styles
from JS at runtime. Project-specific override CSS lives alongside the Epesi module that
uses this library (`modules/Utils/Calendar/theme*/fullcalendar.css`), not here.

Pinned at 6.1.21 rather than tracking `latest` deliberately: FullCalendar 7.x
restructured around a `preact`-based rendering core and a separate `@full-ui/
headless-calendar` package, and reintroduced multiple required CSS files — a
meaningfully bigger dependency footprint for a vendored, no-build-step file drop than
6.x's single dependency-free script. Revisit only as a deliberate migration, not an
incidental upgrade.
