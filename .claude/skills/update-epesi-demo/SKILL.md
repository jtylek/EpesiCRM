---
name: update-epesi-demo
description: Deploy epesi-laravel to the public demo (epesicrm.com/demo, DEMO_MODE) as the release zip, reset it, and check it; also its first deploy over the legacy Epesi demo, rollback, status, and the login-audit report. Use when asked to update, deploy, roll back or check the epesi demo, or to see who used it.
---

# Updating the epesi demo

The demo runs **the release zip itself**, the one `php artisan epesi:package` builds for download,
in demo mode (`DEMO_MODE=true`, see `AI-shared/Demo-mode.md`). Every deploy is therefore also a
live test of the distribution package.

```bash
.claude/skills/update-epesi-demo/scripts/deploy.sh --dry-run        # build + upload only
.claude/skills/update-epesi-demo/scripts/deploy.sh                  # main → live, reset, checks
.claude/skills/update-epesi-demo/scripts/deploy.sh <commit>         # a given commit
.claude/skills/update-epesi-demo/scripts/deploy.sh --no-reset       # swap and migrate, keep today's data
.claude/skills/update-epesi-demo/scripts/deploy.sh status           # deployed zip, maintenance, pending reset, cron
.claude/skills/update-epesi-demo/scripts/deploy.sh audit [days]     # who used the demo (demo:audit)
.claude/skills/update-epesi-demo/scripts/deploy.sh visitors [days]  # fetch + merge + render the visitor report
.claude/skills/update-epesi-demo/scripts/deploy.sh rollback         # previous release back
.claude/skills/update-epesi-demo/scripts/deploy.sh first-deploy     # once: replaces the legacy Epesi demo (--no-backup: skip the dump)
```

Run it from Git Bash in the checkout, with the Bash tool: the build uses the machine's PHP, Composer
and Node, and every ssh/scp goes through WSL.

## Before running

- **The target is private.** The host, SSH key, paths and PHP binary come from
  `AI-private/epesicrm-demo.env`. This skill is published with the code (`publish-epesicrm`), so
  none of that may ever be written here. No AI-private checkout means no deploy.
- **Only committed code is deployed.** The build is a separate clean clone
  (`%LOCALAPPDATA%\epesi-demo-build`), checked out at the commit. The script warns when the
  working tree has uncommitted changes. Other sessions may have their own dirty files in the
  checkout; they aren't yours to commit.
- **Every mode except `--dry-run`, `status`, `audit` and `visitors` changes a public site.** Confirm
  with the user before running it, unless they asked for exactly that mode. `first-deploy` and
  `rollback` always need an explicit yes.

## What a deploy does

1. **Build:** in the clone, `composer install --no-dev --optimize-autoloader --prefer-dist`
   (retried: Windows file locks), `npm ci && npm run build`, `php artisan epesi:package`.
2. **Upload:** the zip and its `.sha256` go to `<deploy>/incoming/` by WSL `scp`, and the checksum
   is verified there. One ~15 MB file, not thousands. Only the last three zips are kept.
3. **Swap** (outside the docroot, so there is never a second working copy in it): unzip into
   `<deploy>/staging`, copy the live `.env`, `artisan down`, copy the live `storage/` over the
   skeleton, then move live to `<deploy>/previous` and staging to live.
4. `artisan epesi:update`, then `demo:reset --force` (unless `--no-reset`), then `artisan up`.
5. **Checks:** the login page answers in demo mode ("Log in as"); `.env`, a log file,
   `vendor/composer/installed.json`, `composer.json`, `/administration` and `/setup/install` all
   answer 404/403; today's log has no errors.

**Read the check output.** A `FAILED` line means stop and look, and probably roll back. After a
live run, add a line to the deploy log at the end of `AI-private/epesicrm-demo.md`: the date, the
zip name (it carries the commit) and the check result. Do anything else that file lists for this
kind of run.

## first-deploy

Once, over the legacy Epesi demo. It reads the database credentials from the legacy
`data/config.php` **on the server**, so the password never leaves it. Then it:

1. backs up the legacy database (`mysqldump`) and files (`tar`) to `~/backups/`, unless given
   `--no-backup`;
2. writes a production `.env` for the demo (`DEMO_MODE=true`, `MODULES_INSTALL_ENABLED=false`,
   `QUEUE_CONNECTION=sync`, `LOG_CHANNEL=daily`), then runs `key:generate`;
3. moves the legacy files to `<deploy>/legacy-epesi` and the new release into place;
4. runs `db:wipe`, because the legacy `modules` table collides with the port's, then
   `demo:reset`.

It ends by printing the scheduler's cron line. **Adding it is a separate step, needing its own
yes:** without it there is no nightly reset. Afterwards, `status` should show the cron line.

## visitors

Who used the demo, kept locally across resets rather than re-read from the server by eye each
time (`demo:reset` never wipes `login_audits`, but nothing else did anything with that history
before this). `../demo-visitors/` (a private git repo, sibling of this checkout — same "lives
once, globally" pattern as `AI-private/`, see `login_audit_analysis.md` there for the design):

- `raw/login_audits-<UTC timestamp>.csv` — one immutable snapshot per run (`demo:audit --raw`,
  every column, unformatted), kept rather than overwritten.
- `login_audits.csv` — every snapshot merged and deduped by `id` (a later snapshot's row wins,
  since a session's `ended_at` moves on while it's active) — the actual accumulated history.
- `own-ips.txt` — IPs to treat as "you", one per line, `#`-comments allowed. `build-visitor-report.php`
  re-detects this machine's current public IP on every run and appends it if it isn't already
  listed, so a home/office IP that changes doesn't quietly start looking like a returning visitor.
  Never guessed from traffic patterns — a real returning visitor deserves to be seen, not silently
  folded into "own testing".
- `login_audit_report.html` — regenerated every run: per-day own/external split, external sessions
  table, "returning visitor" flag for an external IP seen on more than one day.

On demand only (`deploy.sh visitors`) — the demo's traffic doesn't justify a cron job yet.

## When something is wrong

- **The site shows "The demo is being reset" for more than a few minutes:** a reset stopped
  halfway. `status` shows `INTERRUPTED`. The scheduler retries every 15 minutes; running
  `demo:reset --force` on the server finishes it by hand from the saved login audit. The error is in
  `storage/logs/`.
- **The checks fail after a deploy:** `rollback`, then find out why on a copy, not on the demo.
- **ssh/scp hangs or fails from PowerShell or Git Bash:** that's why this goes through WSL. The
  key must be WSL's own copy with mode 600, not the Windows file through `/mnt/c`.
