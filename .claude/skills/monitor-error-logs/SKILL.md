---
name: monitor-error-logs
description: Start tailing this repo's error/warning logs as persistent background watches
---

# Monitor this checkout's logs

Four logs matter for Epesi development. Their paths vary by machine, OS and Apache
configuration, so **derive them rather than assuming** — the checks below take a few
seconds and save chasing a monitor that silently watches the wrong file.

## 1. Locate the four logs

| # | Log | What it catches | How to find it |
|---|---|---|---|
| 1 | `data/logs/php_errors.log` | Epesi's own application errors/warnings/notices, written by `include/error.php`'s `epesi_log()` | always repo-relative — no lookup needed |
| 2 | the php.ini `error_log` | PHP-engine errors (fatals, parse errors in included files) that never reach Epesi's logger | `php -i \| grep error_log`, using the **real** PHP binary for this project (see `CLAUDE.md` — the bare `php` on PATH is often the wrong version) |
| 3 | Apache's error log | server-level failures | XAMPP default: `<xampp>/apache/logs/error.log` (Windows) or `<lampp>/logs/error_log` (Linux) |
| 4 | Apache's access log, filtered to 4xx/5xx | **missing static assets** — a broken `<img>` or CSS `url()` leaves no trace in any error log | same directory as #3: `access.log` (Windows) / `access_log` (Linux) |

Note the Linux/Windows naming difference: XAMPP on Linux uses `error_log`/`access_log`
with no dot, Windows uses `error.log`/`access.log`. Getting this wrong looks like a log
that never produces output.

**Two things to check before trusting #2 and #4:**

- **The php.ini `error_log` is shared by every vhost on the instance.** Unrelated projects
  on the same machine interleave their own fatals and warnings into it. Filter by this
  checkout's path (`grep -Ev '<other-checkout-name>'` or equivalent) rather than triaging
  another project's errors as if they were this repo's.
- **A vhost may have its own `CustomLog`.** If this checkout is served from a named vhost,
  the generic `access.log` may carry none of its traffic. Check the vhost config, or tail
  both side by side for a moment and see which one moves. Prefer the vhost-specific file
  when one exists.

Verify each path exists (`ls` / `Test-Path`) before launching. Once confirmed for this
machine and checkout, treat that as settled for the rest of the session — only re-derive a
path whose monitor actually errors.

## 2. Cancel stale monitors

Check for, and cancel, any one-off monitor left over from an earlier ad-hoc request in this
session, so it doesn't duplicate the live watch.

## 3. Launch one watch per log

Use a persistent `Monitor` watch **per log path** — one call each, not a single shell
juggling four `tail -f`s — so each log's events surface independently. Tail with `-f -n0`
so you get new lines only, not a backlog dump.

```
tail -f -n0 data/logs/php_errors.log
tail -f -n0 <php.ini error_log path>
tail -f -n0 <apache logs>/error.log
tail -f -n0 <apache logs>/access.log | grep -E --line-buffered '" [45][0-9]{2} ' | grep -Ev --line-buffered 'favicon\.ico|com\.chrome\.devtools\.json|\.(js|css)\.map'
```

The access log needs both filters. Raw, it is dominated by routine Notify/Shoutbox polling
every few seconds and can run to tens of MB, so tailing it unfiltered is a firehose. The
4xx/5xx filter reduces it to real failures; the second filter drops noise that recurs on
most machines and is never an app bug — favicon probes, Chrome DevTools' auto-probe, and
source-map lookups for files that aren't shipped. Expect an analogous but not identical set
of benign 404s on any given box (stray traffic from another site sharing the same Apache
instance is the usual extra) and extend the exclusion rather than triaging them repeatedly.

## 4. Report

Confirm in one line what is now being watched.

## A note on where monitoring runs

Some developers keep log monitoring in its own window, separate from active development, so
neither interrupts the other's context; others prefer one window doing both. Neither is a
repo convention. If you are asked to start monitoring in a window that already has
uncommitted work, it is worth asking once which the user wants rather than assuming.
