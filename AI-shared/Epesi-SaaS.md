# Epesi SaaS on epesi.cloud — plan

> **Status: PLANNING ONLY, nothing built yet.** Written 2026-09-08 after a discussion of whether the
> DirectAdmin reseller account already used for `ess.epe.si` could also drive automatic provisioning
> of new, fully-hosted Epesi CRM instances. Account-specific facts (credentials, server names, IPs)
> deliberately live in `AI-private/` (see the cross-references below) — this file is the durable
> architecture/plan record, kept in `AI-shared/` at the user's explicit direction even though it
> borders on business planning, so keep it free of anything that shouldn't be world-readable if this
> repo's `AI-shared/` boundary is ever crossed.

## Goal

Offer "Epesi CRM as a service" on `epesi.cloud` — a fully hosted, ready-to-use instance — for users
who lack the technical skill or time to self-host. **This is explicitly a different product from ESS**
(`ess.epe.si`, the Epesi Store Server): per the user (2026-09-08), "Epesi Store server is just a part
of the puzzle — for Epesi users who run Epesi on their own servers they can still purchase Premium
modules." ESS's order/payment/invoice pipeline (`Custom_ESS` + `Premium/Payments`, PayPal Orders v2 —
see `AI-private/ESS-checkout.md`, `AI-private/ess.epe.si.md`) sells add-on modules to already-running,
self-hosted installs and is **not** being reused as this product's billing system. The two are
complementary: self-hosters keep using ESS for modules; non-technical users get a turnkey instance
through this SaaS product instead of self-hosting at all. Whether a *provisioned* SaaS instance should
still be able to reach ESS for further Premium-module purchases later, the same way any self-hosted
install can, is an open question, not assumed either way here.

## Current state of `epesi.cloud` (already real, not hypothetical)

- Same DirectAdmin reseller account as `ess.epe.si` — a hostbrr.com-provisioned account (see
  `AI-private/DirectAdmin-sync.md` and `AI-private/cPanel-hostbrr.md` for the account-specific
  credentials/SSH details; not repeated here).
- `epesi.cloud` already hosts multiple **named client instances**, each its own DirectAdmin domain
  entry with its own docroot (`~/domains/<name>/public_html/`) and own DB: `bim.epesi.cloud`,
  `kancelaria.epesi.cloud` (see `AI-private/bim-epesi-cloud-sync.md`, `AI-private/Old-Epesi-Upgrade.md`).
  Both were manually upgraded 2026-09-08 from a **Softaculous-installed EPESI 1.9.1** (rev `20220911`)
  to this repo's `2.0` build, via the rsync-over-SSH + `update.php` pipeline already established for
  `ess.epe.si`.
- So the isolation model already in production is **one DirectAdmin domain-account per client**, not
  a shared multi-tenant single install — Epesi itself has no built-in multi-tenancy, and building one
  would be a much larger change than anything considered here. This plan assumes account/domain-per-
  customer throughout; a shared-install alternative is out of scope, noted only to rule it out
  explicitly.
- Known gotcha that any automated provisioning must account for (documented in full in
  `AI-private/DirectAdmin-sync.md`'s `ess.epe.si` section): this account's PHP version is settable in
  **three independent, disconnected places** (`cloudlinux-selector` CLI — unreliable here;
  domain-level "PHP Settings"; per-subdomain "Subdomain Management", which is the one that actually
  matters and does **not** inherit from the domain level). A new customer's instance will silently run
  on a stale default PHP version unless the provisioning flow explicitly sets it via whichever of these
  the DirectAdmin API actually exposes (not yet looked up — see Open Items).

## What "already doing before" means, and what's new here

The user has previously run **WHMCS + cPanel** integration to auto-deploy Epesi (against a different,
unrecorded reseller account/setup — not yet detailed in this repo's docs). What's new in this plan is
doing the equivalent against *this* DirectAdmin/hostbrr reseller account, using **Softaculous** (bundled
into DirectAdmin, confirmed supporting cPanel, DirectAdmin, and InterWorx) as the app-install piece —
plus reconciling that with the fact that Softaculous's own EPESI listing is badly out of date (next
section).

## The Softaculous–EPESI compatibility gap (the one hard blocker)

Verified 2026-09-08 directly against [softaculous.com/apps/erp/EPESI](https://www.softaculous.com/apps/erp/EPESI):
Softaculous's own EPESI script is **version 1.9.1, released 2022-09-07** — the exact pre-migration
revision (`20220911`) both `bim.epesi.cloud` and `kancelaria.epesi.cloud` were running before this
session's manual upgrades. **A stock Softaculous auto-install today would deploy an app that fatals on
modern PHP** (`get_magic_quotes_gpc()`, `each()` — both removed in PHP 8, both documented as live
breakage against real ESS customers in `AI-private/ess.epe.si.md`'s "PHP8 `each()` outbreak scan," 18
of 183 sampled installs hit exactly this). **Do not point a WHMCS Softaculous-install hook at the
stock EPESI script as-is.**

Softaculous supports server-admin-added **"Custom Scripts"**
([docs](https://www.softaculous.com/docs/admin/adding-custom-scripts/)) — package the app, then add it
via the root Softaculous Admin panel (Custom Scripts → Add Custom Scripts). This is the mechanism to
register this repo's own `2.0` build as the installable script for this account, superseding the stale
stock listing. **Not yet investigated:**
- The exact package/manifest format (the docs point to a separate "Making Custom Package" guide not
  yet read).
- Whether it needs Softaculous Enterprise/white-label licensing at this reseller tier — HostBrr's own
  plan page ([hostbrr.com/directadmin-reseller.html](https://hostbrr.com/directadmin-reseller.html),
  fetched 2026-09-08) doesn't mention Softaculous tier, API access, or WHMCS/Blesta compatibility either
  way; likely a standard DirectAdmin-platform feature rather than something the host special-cases, but
  not confirmed against this specific account.
- Whether a custom script's install action has any hook to also set the new domain's PHP version (the
  gotcha above), or whether that's necessarily a separate step regardless of path chosen.

## The non-interactive install mechanism (verified in this codebase, 2026-09-08)

Read `setup.php` and `check.php` directly rather than assuming. Findings:

- `setup.php`'s wizard is only two real HTTP round-trips once you know the shape: a **GET** carrying
  the four license-agreement checkboxes (`tos1..tos4=1`) plus `license=1&htaccess=1`, then a **POST**
  of the DB form (`host`, `port`, `engine` = `mysqli`/`postgres`, `user`, `password`, `db`, `newdb` =
  `0`/`1`, `direction`). The POST handler (`setup.php` ~line 274-378) validates the DB connection,
  optionally runs `CREATE DATABASE`, and calls `write_config()` — which writes `data/config.php`. **A
  provisioning script can just POST these fields directly; nothing about the wizard requires a real
  browser or JS.**
- The header-documented `installation_config.php` mechanism (drop a file with a `$CONFIG` array before
  hitting `setup.php`) only **pre-fills and freezes** the DB form's fields for a human clicking through
  the wizard (`setup.php` ~line 260-270) — it is a convenience, not a requirement, and **not needed**
  for a scripted flow that already knows the values it wants to POST.
- Once `data/config.php` exists, `setup.php` itself refuses to run again (`file_exists(DATA_DIR.'/config.php')`
  → error page, ~line 161) — so a provisioning script's next step is the normal app entry point, not a
  second setup.php call. **Not yet confirmed empirically**: whether hitting `index.php`/`update.php`
  next is sufficient to trigger the module system's fresh-install path (`ModuleManager` installing every
  module's schema/default data via its `*Install.php` class, per `CLAUDE.md`'s module-system section)
  and to create the initial super-admin account, or whether an explicit additional step is needed. Verify
  this by actually running the sequence against a scratch DB before relying on it for real provisioning
  — flagged as an open item below, not assumed.

This closes the main technical unknown from the earlier discussion: there is **no need for a
`console.php` CLI installer** (none exists — `module:install` is per-module, post-bootstrap only) or
for Softaculous's own install.xml machinery to know anything special — a plain HTTP client replicating
these two requests against a freshly-created empty DB is enough to reach the same end state
`setup.php`'s wizard reaches by hand.

## Prior art: the ESS Hosting module (deleted)

Per the user (2026-09-09): ESS previously had a **Hosting** module that automatically provisioned new
Epesi instances in a multi-tenant hosting mode. **Deleted** because it ran every tenant's instance
under **one shared Linux user** — an isolation model the user was not satisfied with (one compromised
or misbehaving tenant's files/processes are only as separated as file permissions within a single
account allow, not enforced at the OS-account level). Not present in this checkout (`Custom/ESS` isn't
even checked out here) and not findable in this repo's own git history — it presumably lived, and was
removed, in the separately-repo'd `Custom-ESS` nested repo (see `[[project_custom_ess_nested_repo]]`
auto-memory). This section records the user's account of it, not a verified code read.

**Hard requirement carried forward from this history, for every path below**: each tenant needs a
genuinely separate Linux user/account, not a domain or subdomain layered onto one shared reseller
account. This sharpens the multi-tenancy open item further down: `bim.epesi.cloud`/`kancelaria.epesi.cloud`'s
current shape (domain entries under the single `epesicrm` account) is fine for a handful of
hand-migrated clients, but is the *same* shared-account shape the old Hosting module used — it should
**not** be the template for self-serve SaaS signups. Every path below should provision through
DirectAdmin's actual reseller **account-creation** API (a real new user, own home directory, own
package assignment), not the domain-creation API used for the manually-managed sites so far.

## Three implementation paths

### Path A — WHMCS + Softaculous custom script (closest to "already doing before")

1. Package this repo's `2.0` build as a Softaculous custom script for this account (open item: package
   format, see above).
2. WHMCS's DirectAdmin provisioning module creates the new domain/account on signup — the DirectAdmin
   analogue of whatever the cPanel provisioning module did before.
3. WHMCS's **Softaculous Auto Install** hook (`Setup → Products/Services`, module settings tab) installs
   the custom EPESI script into the new account automatically as part of account creation.
4. **Still needed as a step outside Softaculous's own flow**: explicitly setting the new domain's PHP
   version (the three-selectors gotcha) — Softaculous's installer writes app config, it does not reach
   into DirectAdmin's PHP-version selection.

### Path B — Custom WHMCS module / direct DirectAdmin API, bypassing Softaculous

1. A custom WHMCS module (PHP) calls DirectAdmin's HTTP API directly (`CMD_API_DOMAIN` or the
   account-creation equivalent, depending on the isolation-model decision below) to create the domain +
   DB on signup.
2. The same module deploys this repo's `2.0` codebase into the new docroot — reusing the exact
   rsync-over-SSH method already proven working in `AI-private/DirectAdmin-sync.md` /
   `AI-private/bim-epesi-cloud-sync.md` (including its Windows/PowerShell execution gotchas if the
   provisioning script itself runs from a Windows box, or a plain server-side copy/extract if it runs
   as a script on the DirectAdmin server instead).
3. POSTs the two `setup.php` requests above with freshly generated DB credentials, then confirms the
   post-`write_config()` bootstrap actually completed (see the open item above).
4. Sets the new domain's PHP version via the DirectAdmin API in the same flow — closing the gap Path A
   leaves open.
5. Sends credentials to the customer via WHMCS's normal welcome-email templating.

**Path B is more to build and maintain, but gives full control over the two things Softaculous's own
flow doesn't reach** (PHP version; guaranteeing the real `2.0` build rather than depending on a
custom-packaged script staying in sync with this repo as it evolves). Path A is less new code *if* the
custom-script packaging turns out to be simple — but its one unresolved integration question (does
install.xml have any hook for the PHP-version step, or does Path A need a bolted-on Path-B-style step
regardless) means it may partially collapse into Path B anyway.

### Path C — native to ESS: landing page + existing PayPal integration + DirectAdmin API, no WHMCS

Raised 2026-09-09: build this directly on infrastructure ESS already runs, instead of introducing
WHMCS as a second billing/customer system.

1. A signup landing page (open item: hosted where — a new page on `epesi.cloud`'s own root domain, or
   a new tab/module inside the existing ESS Manager on `ess.epe.si` — not decided) presents the hosting
   plans/tiers and takes payment through **ESS's already-verified-working PayPal integration**
   (`Premium_Payments_Plugins_Paypal_PluginExternal`, the Orders v2 REST rewrite confirmed live
   end-to-end 2026-09-03 — real captures, idempotent double-confirm, cancel all verified, see
   `AI-private/ess.epe.si.md`). This reuses the hardest-already-solved part (OAuth2 client-credentials,
   order create/capture, idempotent confirm) instead of re-integrating payment from scratch or through
   WHMCS.
2. On a successful capture, a provisioning script calls DirectAdmin's reseller **Create Account** API,
   assigning the new account to one of a set of **predefined DirectAdmin Packages** (reseller-defined
   resource tiers — disk/bandwidth/DB/domain limits) matching whatever plan the customer paid for.
   **This is the step that fixes the old Hosting module's flaw** (see "Prior art" above): each customer
   gets a genuinely separate DirectAdmin-created user account, not a domain bolted onto one shared
   account.
3. Within that new account, the same script creates a MySQL database for Epesi (a follow-up DirectAdmin
   API call, or covered by account creation directly, depending on how the chosen Package is
   configured).
4. Deploys **the most recent Epesi release** into the new account. This repo already has the tool for
   producing that artifact: `console.php dev:dist:create` — "Create a distributable EPESI package (zip)
   with an empty `data/` directory" (confirmed via `console.php list`, 2026-09-08). A release pipeline
   would rebuild this zip whenever the release branch moves, stage it somewhere the provisioning script
   can reach, then push it into the new account — deliberately not the same rsync-from-dev-checkout
   method used for `ess.epe.si`/`bim.epesi.cloud`, since those are maintained checkouts kept in sync,
   not fresh one-time installs.
5. Runs the same two-request non-interactive `setup.php` sequence documented above against the new DB,
   then verifies the fresh-install bootstrap actually completed (same as open item 4 below — this
   hasn't been confirmed empirically for any path yet).
6. Sets the new account's PHP version explicitly — open item: whether a DirectAdmin Package can pin PHP
   version at account-creation time (which would close this cleanly, unlike the per-domain override
   problem `ess.epe.si` hit) or whether it still needs an explicit post-creation API call regardless.
7. Steps 2-6 take real time (account creation, DB, file transfer, install) — the landing page needs an
   async "your instance is being set up" flow, not a synchronous redirect from payment straight to a
   working login. A queued job + status page + completion email, not a single request/response.

**Path C needs no WHMCS at all** — billing, signup, and provisioning all live on infrastructure ESS
already runs and this team already maintains. Its cost is the mirror image of Path A's: no vendor
billing platform to configure, but a fully custom "your instance is ready" flow (queueing, status page,
plan/package catalog, receipts) that WHMCS would otherwise provide out of the box.

**Recommendation**: given the PayPal integration and the release-packaging command already exist and
are proven, Path C is likely the least *net-new* infrastructure of the three — it avoids both WHMCS
licensing/integration work (Paths A/B) and the Softaculous custom-script packaging question (Path A)
entirely. Its own open questions (landing page location, async provisioning UX, Package definitions —
open items 9-11 below) are product/UX work rather than unresolved technical integration risk, which is
a meaningfully different kind of open item than Path A's items 1-2.

## Open items

1. Softaculous custom-script package format for this account/license tier — the "Making Custom
   Package" companion doc hasn't been read yet.
2. Whether install.xml (or whatever Softaculous's custom-script action format actually is) has any
   reach into DirectAdmin's PHP-version selection, or whether that's unavoidably a separate step —
   decides how much of Path A actually differs from Path B.
3. DirectAdmin API call(s) for setting a domain's PHP version programmatically — only found via the
   panel UI so far (see `DirectAdmin-sync.md`), not via the API docs.
4. Confirm empirically (scratch DB, not assumed) that hitting `index.php`/`update.php` after
   `write_config()` is sufficient to complete the fresh-install bootstrap (schema + default data + an
   initial super-admin account), and capture exactly what that initial-admin flow looks like — this is
   the one piece of the non-interactive install path not yet traced end to end.
5. What "already doing before" (WHMCS + cPanel) actually looked like in enough detail to reuse — which
   WHMCS modules/hooks were configured, whether a custom Softaculous script already existed for a
   pre-migration Epesi build, whether any of that setup or its docs still exist. Ask before assuming
   Path A's steps 2-3 have to be built from scratch.
6. Whether `setup.php`/`check.php` should be deleted or blocked (`.htaccess`/`DirectoryIndex`-style)
   after a successful install, given `setup.php` already self-refuses once `config.php` exists but
   `check.php` stays reachable (gated to super-admin login, per its own code) — decide whether that's
   an acceptable steady state for a customer-facing instance or whether provisioning should lock it
   down further.
7. **Billing/signup platform decision is now genuinely three-way** (WHMCS per "already doing before,"
   vs. building Path C natively on ESS) rather than a re-confirmation of a foregone conclusion — worth
   an explicit choice rather than assuming WHMCS carries over just because it was used previously on a
   different account.
8. Per-customer resource/quota planning against the hostbrr reseller plan's limits (accounts are
   "unlimited" per the plan page, but storage/CPU/RAM are not) — not modeled here at all yet. Applies
   whichever path is chosen, but is more directly Path C's concern since Path C is the one defining the
   DirectAdmin Packages itself rather than reusing whatever packages a prior WHMCS setup already had.
9. Where Path C's landing page actually lives (`epesi.cloud` root vs. a new ESS Manager tab on
   `ess.epe.si`) — affects branding/DNS and whether it shares a session/login with the existing ESS
   Manager admin UI or needs to be fully public-facing and separate from it.
10. Path C's DirectAdmin **Packages** need to be defined (names, resource tiers, price mapping) before
    "assign the new account to one of the predefined plans" is anything more than a placeholder — not
    started.
11. Path C's async provisioning UX (queue mechanism, status page, failure handling if account creation
    or install fails partway through — e.g. does a failed provisioning attempt need to tear down a
    half-created DirectAdmin account automatically, or is that a manual cleanup step) — not designed.
12. Whether DirectAdmin's Create-Account API (the one Path B/C both need, as opposed to the
    domain-creation API `bim.epesi.cloud`/`kancelaria.epesi.cloud` used) supports the automation this
    plan assumes at this reseller tier, and what its actual parameter shape is (`CMD_API_ACCOUNT_USER`
    or current equivalent — DirectAdmin's API surface has moved to a newer versioned API in recent
    releases per its own changelog, not yet checked against what this specific hostbrr account exposes)
    — not yet looked up, same category of unknown as items 1-3 but for account creation instead of
    domain/PHP-version management.
