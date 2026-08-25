# PROPOSAL — a small, high-ROI functional test suite for Epesi

**Status:** proposal for Jasiek's decision (architecture / investment level — not a mechanics change).
**Not an RC blocker.** The PHP 8.2 RC (`v20260701-rc1`) is already tagged; this is a quality/credibility
investment best started *during* the RC→stable window, in parallel with cross-platform testing.

## Why

Epesi has historically been criticised for "no tests". The reality in the repo confirms it:
- `codeception.yml` exists and `codeception/codeception` is in `composer` — but it's an **empty skeleton**:
  no `*Cest.php` / `*Cept.php` / `*.feature` anywhere.
- `modules/Tests/*` (220 files) are **demo/example modules** (Attachment, Bugtrack, RecordBrowser…),
  **not** automated tests.

This migration added the **first real automated quality gate — PHPStan in CI** (lint 8.2/8.3 + Rector +
PHPStan enforce). Static analysis already paid off: it forced the fix of a genuine hidden runtime fatal
(`$arr[] .= …` "Cannot use [] for reading" in the A–Z quick-jump — invisible to `php -l`) and cleaned up
PHP 8.1+ covariance. **But static analysis ≠ tests.** PHPStan checks whether the code *makes sense*; it can't
tell whether saving a contact actually persists, whether mail sends, or whether a permission truly blocks.
Only running the app catches that. This proposal adds the **runtime** gate.

## Approach — functional/acceptance first, not unit

Epesi's design (no PSR-4 autoload, heavy global state, module singletons) makes **unit testing expensive**
(hard to isolate a unit) but makes **black-box functional testing a natural fit** — drive the running app
like a user and assert on the result. So:

1. **Start with acceptance tests** (Codeception, already bundled): boot a real Epesi against a test DB and
   exercise critical user flows through the browser (headless Chrome via WebDriver, or `PhpBrowser` for
   non-JS flows).
2. **Unit tests later**, only for pure logic that's already isolatable (e.g. RecordBrowser crits building,
   RegionalSettings maps, date/utility helpers). Lower priority.

Note: `codeception/aspect-mock` was removed (§51) — it's irrelevant to acceptance tests, which don't mock.

## Scope — the first ~12 tests (the credibility jump)

These mirror the manual smoke we already run by hand — so writing them **automates work we do anyway**:

1. Fresh install + FirstRun completes.
2. Login with valid credentials → dashboard loads.
3. Login with bad credentials → rejected.
4. Logout.
5. Contact: create → appears in list.
6. Contact: edit → change persists.
7. Contact: delete → removed from list.
8. Company: create.
9. Task: create.
10. RecordBrowser A–Z quick-jump: click a letter / "All" / "123" → list filters (guards the §53 fix).
11. Search / filter (critsvalue) returns expected rows.
12. Roundcube mail module opens without fatal (send/receive if a test mailbox is feasible).

~12 green acceptance tests = a disproportionate credibility gain versus the effort.

## CI integration (staged — heavier than the static jobs)

Functional tests need a **running Epesi + MySQL + headless browser**, so they're a bigger lift than
`php -l`/PHPStan (which analyse files statically). Suggested staging:
- **Phase 1:** runnable **locally** (`vendor/bin/codecept run acceptance`) against a throwaway test DB —
  developers + reviewers run them by hand. No CI change.
- **Phase 2:** a **nightly** (or manual-dispatch) CI job that stands up MySQL + Chrome on the runner, installs
  Epesi, runs the suite. Nightly (not per-push) keeps per-push CI fast and avoids flakiness gating every commit.
- **Phase 3 (optional):** promote the stable subset to per-PR once it's proven non-flaky.

## Effort (rough)

- Harness bring-up (flesh out `codeception.yml`, acceptance suite config, DB fixture/dump, install bootstrap):
  ~1–2 focused days.
- First ~12 tests: ~1–2 days once the harness boots.
- Nightly CI job (MySQL + Chrome + install): ~1 day.
Total: roughly a week of focused work for a genuine, demonstrable test suite — then it grows incrementally.

## Decision points for Jasiek

1. **Go / no-go**, and **when** (parallel with RC testing, or after stable?).
2. **Scope** of the first cut (the 12 above, or a different critical set he'd prioritise).
3. **Gating policy:** advisory nightly only, or eventually block merges on the functional suite?
4. **Tooling confirm:** Codeception acceptance (matches the existing skeleton) vs. another framework.

## Not a blocker for

- The **RC** (already out; readiness established by the real-upgrade validation + green static CI).
- **Merging `experiment/php8-hardening` → main** (that only needs a light manual smoke of this session's
  changes — login + A–Z quick-jump).
- The **public release** — tests strengthen it but aren't a precondition; the RC feedback period is exactly
  when to decide how much test investment the public launch warrants.
