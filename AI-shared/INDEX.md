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
- [deliberate-removals.md](deliberate-removals.md) — features removed on
  purpose (quick-jump, login-audit purge, autofocus, legacy mobile system,
  `data/` theme+lang storage). Don't silently reintroduce.
- [standalone-entrypoints.md](standalone-entrypoints.md) — `admin/`,
  `update.php`, `check.php`, `setup.php` PHP/view split; the `anonymous_setup`
  access-control hardening pass; Smarty 2 gotchas hit doing this work.
- [legacy-js-migration.md](legacy-js-migration.md) — Prototype.js/
  script.aculo.us/old-jQuery inventory, elimination plan, and progress so far.
- [bug-patterns.md](bug-patterns.md) — already-fixed bugs whose root-cause
  shape (raw-record-vs-form-submission, strtotime() date parsing, settings
  override chains, legacy-theme `<select>` sizing across four stacked CSS
  bugs, a shared timeout/timer applied to a context it wasn't tuned for) is
  likely to recur elsewhere; plus one still-open bug (Shoutbox delete UI).
- [environment-gotchas.md](environment-gotchas.md) — DB/server issues that
  looked like application bugs (CLI scripts hitting the live DB, silent ADOdb
  failures from `max_allowed_packet`, MariaDB manifest corruption, access.log
  vs error.log, outbound SMTP port 25 blocked from this machine).
- [known-todos.md](known-todos.md) — full-repo `TODO`/`FIXME`/`XXX` audit
  (2026-08-04): every marker in Epesi's own code individually re-verified as
  still-open, none stale. Two flagged as worth prioritizing (a dead param, an
  escaping hack with a working fix already nearby in the same file).
- [TODO.md](TODO.md) — follow-up work deferred during AI-assisted sessions
  (started 2026-08-05): a real fix shipped now, with a known limitation
  recorded to come back to later. First entry: the mobile multiselect
  checklist fallback needs an autoselect/search switchover for large option
  counts, not yet implemented/testable in this dev install.

Last reorganized: 2026-08-05.
