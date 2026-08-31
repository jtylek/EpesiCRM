# Log monitoring — example setup

> **Status:** REFERENCE - one developer's setup. A template, not a standard; paths vary per machine.

Log paths, XAMPP/Apache config, and OS all vary by machine and developer, so there's no
single "correct" set of logs to tail — **this file documents one developer's working
setup as a worked example**, not a prescribed standard. If you're setting up monitoring
for the first time, use this as a starting point/template, then adjust paths and filters
to your own machine and confirm what's actually relevant there (e.g. re-run the
"does Apache's error.log carry PHP lines on this box" check below rather than trusting
the answer recorded here). Don't assume these exact paths exist on a different machine.

## Quick start (skip re-deriving this every session)

Once you've confirmed the four paths below are correct for **your own machine** (verify
once — `ls`/`Test-Path` each, plus `php -i | grep error_log` for the php.ini path — and
correct any that differ), treat that confirmation as durable for that machine rather than
re-verifying and re-reading this whole file from scratch on every future "start monitoring
error logs" request in the same repo checkout. Just launch the four tails directly. Only
re-verify if a monitor errors immediately (path moved, XAMPP reinstalled elsewhere, a log
got rotated to a new name, etc.) — fix the one broken path, not all four.

Confirmed working on jasiek's Windows/XAMPP box (`c:\xampp82\htdocs\epesigithub`) as of
2026-08-21 — adjust these four paths for your own machine, then this block is your
low-ceremony starting point too:

```
tail -f -n0 data/logs/php_errors.log
tail -f -n0 /c/xampp82/php/logs/php_error_log
tail -f -n0 /c/xampp82/apache/logs/error.log
tail -f -n0 /c/xampp82/apache/logs/access.log | grep -E --line-buffered '" [45][0-9]{2} ' | grep -Ev --line-buffered 'favicon\.ico|com\.chrome\.devtools\.json|\.(js|css)\.map'
```

Confirmed working on jasiek's Windows/XAMPP box for the `ess.epe.si` vhost checkout
(`c:\xampp82\htdocs\ess.epe.si\manage`, a separate checkout from `epesigithub` above, same
physical machine) as of 2026-08-25. Same php.ini `error_log` and Apache `error.log` as the
`epesigithub` entry (shared across all vhosts on this XAMPP instance), but the generic
`apache/logs/access.log` does **not** carry this vhost's traffic — this XAMPP instance gives
`ess.epe.si` (and other vhosts) their own dedicated `CustomLog`, so the generic access.log only
shows the default/other vhost's requests (confirmed by tailing both side by side: generic log
was full of unrelated `/newsetup/...` traffic while `ess.epe.si-access.log` had this vhost's
actual requests). Use the vhost-specific access log instead:

```
tail -f -n0 data/logs/php_errors.log
tail -f -n0 /c/xampp82/php/logs/php_error_log
tail -f -n0 /c/xampp82/apache/logs/error.log
tail -f -n0 /c/xampp82/apache/logs/ess.epe.si-access.log | grep -E --line-buffered '" [45][0-9]{2} ' | grep -Ev --line-buffered 'favicon\.ico|com\.chrome\.devtools\.json|\.(js|css)\.map'
```

If monitoring a different vhost checkout on a machine with per-vhost `CustomLog` directives,
check for and use that vhost's own access log the same way — don't assume the generic
`apache/logs/access.log` covers it.

Confirmed working on ktylek's Linux/XAMPP box (`/opt/lampp/htdocs/euroleader`) as of
2026-08-21. Note the Linux XAMPP log names differ (`error_log`/`access_log`, no dot before
the extension) — couldn't cross-check the php.ini `error_log` directive itself via
`php -i` here since `/opt/lampp/bin/php` fails to run in this session (see
`environment-gotchas.md`'s `libcrypt.so.1` entry); path taken from XAMPP's default layout
and confirmed present/actively-written via `ls`.

```
tail -f -n0 data/logs/php_errors.log
tail -f -n0 /opt/lampp/logs/php_error_log
tail -f -n0 /opt/lampp/logs/error_log
tail -f -n0 /opt/lampp/logs/access_log | grep -E --line-buffered '" [45][0-9]{2} ' | grep -Ev --line-buffered 'favicon\.ico|com\.chrome\.devtools\.json|\.(js|css)\.map'
```

An AI assistant should run each as its own persistent background watch (e.g. Claude Code's
`Monitor` tool, one call per line above) rather than one shell juggling four `tail -f`s, so
each log's events surface independently. Also worth a quick check for (and cancellation of)
any stale one-off monitoring job left over from an earlier ad-hoc request in the same
session, so it doesn't duplicate the live watch.

## Example: the four logs (one developer's Windows/XAMPP setup)

1. **`data/logs/php_errors.log`** — Epesi's own app-level PHP error log, written by
   `include/error.php`'s `epesi_log()`. This is the primary log for application-code
   errors/warnings/notices (see `CLAUDE.md`'s `REPORT_ALL_ERRORS` note — the first
   warning/notice anywhere blanks that module's rendered output, so this log is often
   the only trace of what happened).
2. **The php.ini `error_log` directive** — on this machine
   `C:\xampp82\php\logs\php_error_log`. Get the live path with
   `php -i | grep error_log` rather than hardcoding it; it can differ per machine/php.ini.
   Catches PHP-engine-level errors (fatals, syntax errors in included files, etc.) that
   never reach Epesi's own logger.
3. **`apache/logs/error.log`** — Apache's own server error log. Checked 2026-08-07 and
   found to contain zero PHP-tagged lines on this machine at the time (mod_php/PHP-CGI
   errors went only to the php.ini `error_log` above; this log was pure server-level
   noise — startup, worker-thread counts, an unrelated SSL cert-mismatch warning,
   "Unclean shutdown of previous Apache run" on restart). Included anyway per standing
   preference. If XAMPP's PHP handler config ever changes, re-verify relevance with
   `grep -ic "PHP " apache/logs/error.log`.
4. **`apache/logs/access.log`** — filtered to 4xx/5xx responses only
   (`grep -E '" [45][0-9]{2} '`). The raw file is dominated by routine polling
   (Notify/Shoutbox refresh every few seconds) and runs 30MB+, so tailing it unfiltered
   is a firehose. This is the log that catches missing static assets (broken `<img>`/CSS
   `url()`) — see `environment-gotchas.md`, they leave **no trace** in error.log.

## Known benign noise to exclude from access.log

The specific noise sources below (favicon requests from an old IE11-tagged client, a
particular LAN IP) are also this developer's machine — expect a different, but
analogous, set of recurring benign 404s on another box; the pattern (DevTools probes,
source-map lookups, stray non-Epesi traffic sharing the same Apache instance) is the
generally useful part, not the exact strings.

Even after the 4xx/5xx filter, these recur and are not app bugs:
- `/favicon.ico` — repeats every few minutes from an old Trident/IE11-tagged localhost
  client, some Windows system component, harmless.
- `/.well-known/appspecific/com.chrome.devtools.json` — Chrome DevTools auto-probe.
- `adminlte.min.{js,css}.map` — DevTools looking for source maps that aren't shipped.
- Requests whose path/referrer don't match `/epesi_php82/...` (e.g. a LAN-IP referrer
  requesting bare `/mobile.js` or `/css/searchicon.png`) — a different site sharing this
  Apache instance, not Epesi traffic.

Suggested combined filter:
```
tail -f -n0 apache/logs/access.log | grep -E '" [45][0-9]{2} ' | grep -Ev 'favicon\.ico|com\.chrome\.devtools\.json|\.(js|css)\.map'
```

All four logs are tailed with `-f -n0` (new lines only, no backlog dump) when monitoring
starts.

## Example workflow preference: dedicated window

This is this developer's personal working style, not a repo convention — treat it as an
example of *a* reasonable setup, not the expected one. Each developer should decide their
own workflow for where monitoring runs.

On this developer's machine, log monitoring runs in its **own dedicated chat
window/session**, kept separate from any window doing active development. Rationale:
running multiple sessions in parallel, wanting the monitoring stream isolated so neither
interrupts the other's context.

- If asked to monitor logs in a window that already has uncommitted dev changes, flag it
  once and ask (don't silently start elsewhere) — on this developer's setup, the
  exception (keep monitoring there rather than move it) has been confirmed before when
  explicitly requested for that window.
- Conversely, a window doing active feature/bug work on this developer's setup should
  assume a separate monitoring window already covers logs, rather than spinning up its
  own redundant tail. A different developer may prefer one window doing both — ask
  rather than assuming this preference applies to them.
