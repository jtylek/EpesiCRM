---
name: monitor-error-logs
description: Start tailing this repo's error/warning logs as persistent background watches
---

Read `AI-private/log-monitoring.md` in the repo root — it's the source of truth for which logs
to watch, their paths, and filters (not this skill; if it's missing or its paths are stale
for this machine, fix that file directly rather than improvising here).

Steps:

1. Check `git status` for uncommitted changes in this working tree. If there are any, and this
   session hasn't already been confirmed as the dedicated monitoring window earlier in this
   conversation, flag it once and ask whether to proceed here anyway or that monitoring should
   run in a separate window instead — per the "dedicated window" preference documented at the
   bottom of `AI-private/log-monitoring.md`.
2. Confirm the log paths in the doc's "Quick start" block are still valid for this machine.
   Skip re-verifying if already confirmed earlier in this same session/repo checkout — only
   re-check a path that a monitor actually errors on.
3. Check for and cancel any stale one-off monitor left over from an earlier ad-hoc request in
   this session, so it doesn't duplicate the live watch.
4. Launch one persistent `Monitor` tool watch per log path from the doc's quick-start block
   (one call per path, not one shell juggling multiple tails), so each log's events surface
   independently.
5. Report back a one-line confirmation of what's now being watched.
