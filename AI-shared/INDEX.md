# Index

- [design-philosophy.md](design-philosophy.md) — the founding principle behind
  Epesi (from the framework's creator): developers write pure business logic in
  PHP, the framework generates view/CSS/JS automatically. The test to apply to
  any future redesign work.
- [Dev-Tutorial.md](Dev-Tutorial.md) — how modules are built in Epesi: class
  hierarchy, install/uninstall lifecycle, RecordBrowser field-type vocabulary,
  ACL, patches, translations. Paired with `modules/Custom/Tutorial/`, a
  complete working example module exercising every field type.
- [adminlte-theme.md](adminlte-theme.md) — `adminlte`/`adminltedark` theme
  status (what's themed, what's not) + recurring CSS/JS architecture traps.
- [how-menu-works.md](how-menu-works.md) — sidebar/left menu internals: tree
  construction and session caching (`Base_MenuCommon::get_menus()`), the AdminLTE
  vs. default-theme render split, `build_menu_html()`'s DOM shape (Bootstrap
  collapse, not AdminLTE's own classes), and the `#MenuBar` shell/JS-rebind
  convention. Written 2026-08-14 ahead of planning a menu search/filter feature.
- [menu-search-plan.md](menu-search-plan.md) — approved 2026-08-14 plan for an
  AdminLTE sidebar search/filter box: client-side only, AdminLTE-family scope only,
  cascading auto-expand of ancestor folders on match, Bootstrap Collapse API for
  expand/collapse. Implementation follows in the same session.
- [deliberate-removals.md](deliberate-removals.md) — features removed on
  purpose (quick-jump, login-audit purge, autofocus, legacy mobile system,
  `data/` theme+lang storage). Don't silently reintroduce.
- [standalone-entrypoints.md](standalone-entrypoints.md) — `admin/`,
  `update.php`, `check.php`, `setup.php` PHP/view split; the `anonymous_setup`
  access-control hardening pass; Smarty 2 gotchas hit doing this work.
- [legacy-js-migration.md](legacy-js-migration.md) — Prototype.js/
  script.aculo.us/old-jQuery inventory, elimination plan, and progress so far.
  Step 7 (2026-08-13): `modules/Premium/` was the whole migration's actual
  blind spot — gitignored, so every "zero callers remain" grep-based claim in
  steps 1-6 silently never checked it. 13 real leftover call sites found and
  fixed, plus a second undetected bug class (`$(id).property` raw-DOM access
  broken by `$` now meaning jQuery).
- [ckeditor-to-quill-migration.md](ckeditor-to-quill-migration.md) — planned CKEditor→
  Quill swap (MIT vs. non-MIT license, retiring an old dependency): verified scope (4
  call sites, one shared element/lifecycle-JS), the HTML-vs-Delta storage decision,
  step-by-step plan, ~1-2 day estimate. Not started — needs its own branch.
- [generic-browser-responsive-tables.md](generic-browser-responsive-tables.md) —
  fix for `Utils_GenericBrowser`/`Utils_RecordBrowser` list tables squeezing unreadable
  on narrow viewports instead of scrolling/wrapping: root cause (div-based CSS
  table-display grid, fixed 100% width), the CSS-grid 2-line-per-row mechanism,
  alternatives considered and rejected. Implemented on the `mobile-gb` branch
  (`GenericBrowser/theme_adminltedark/default.tpl` + `default.css`), confirmed to
  reach RecordBrowser's Browse mode; not yet visually verified or merged.
- [bug-patterns.md](bug-patterns.md) — already-fixed bugs whose root-cause
  shape (raw-record-vs-form-submission, strtotime() date parsing, settings
  override chains, legacy-theme `<select>` sizing across four stacked CSS
  bugs, a shared timeout/timer applied to a context it wasn't tuned for, a
  `*_watchdog_label()` callback indexing a record that doesn't exist for the
  generic no-`$rid` call, `eval_js_once()`'s session-wide dedup assuming a
  shell template renders once when it doesn't, `load_js()`'s "already sent"
  session flag surviving a request abort that never actually delivered the
  asset) is likely to recur elsewhere; plus one still-open bug (Shoutbox
  delete UI).
- [environment-gotchas.md](environment-gotchas.md) — DB/server issues that
  looked like application bugs (CLI scripts hitting the live DB, silent ADOdb
  failures from `max_allowed_packet`, MariaDB manifest corruption, access.log
  vs error.log, outbound SMTP port 25 blocked from this machine, hardcoded
  `EPESI_URL` redirecting off `localhost` to the real production domain,
  stale `data/cache/` after a wholesale code swap, clearing logs before a
  migration pass, this machine's broken port-443 SSL vhost causing
  browser-side http→https auto-upgrade to look like a 403), plus a dev-tooling
  entry on driving a real browser against this app (no `chromium-cli`,
  Playwright's own Chromium never downloaded — use the `channel: 'msedge'`/
  `'chrome'` option instead; the app's AJAX-push SPA shape means no
  deep-linkable URLs, so verification means click-through navigation); plus
  `modules/Custom/` being only partly gitignored (only `Tutorial` is tracked
  in the main repo — every other Custom module is meant to be its own nested
  git repo, same as Premium, now actually encoded in `.gitignore`).
- [log-monitoring.md](log-monitoring.md) — example log-monitoring setup from one
  developer's machine (app error log, php.ini error_log, Apache error/access.log,
  noise filters, dedicated-window habit). Log paths/config vary per machine/dev —
  use as a starting template, not a prescribed standard.
- [known-todos.md](known-todos.md) — full-repo `TODO`/`FIXME`/`XXX` audit
  (2026-08-04): every marker in Epesi's own code individually re-verified as
  still-open, none stale. Two flagged as worth prioritizing (a dead param, an
  escaping hack with a working fix already nearby in the same file).
- [dependency-upgrades.md](dependency-upgrades.md) — composer dependency-upgrade findings
  (2026-08-24): Symfony capped at `^7.4` (8.x needs PHP ≥8.4), Symfony Console 7's
  `execute(): int` breaking change (fixed across all 25 `console/*Command.php` files),
  phpdocumentor/reflection-docblock v6 API rewrite, and `tecnickcom/tcpdf` 7.x's
  font-packaging gap — reverted to 6.x, don't re-attempt without reading this first.
- [TODO.md](TODO.md) — follow-up work deferred during AI-assisted sessions
  (started 2026-08-05): a real fix shipped now, with a known limitation
  recorded to come back to later. First entry: the mobile multiselect
  checklist fallback needs an autoselect/search switchover for large option
  counts, not yet implemented/testable in this dev install.

Last reorganized: 2026-08-05.
