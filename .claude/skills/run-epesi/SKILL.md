---
name: run-epesi
description: Launch and look at the running epesi app — signed-in screenshots of any page (dark or light), e.g. to check a UI change in the real app rather than in tests. Use when asked to run, open, screenshot or visually verify epesi-laravel.
---

# Running epesi and taking screenshots

The app is served by the web server the checkout sits under (XAMPP Apache on
the development machine) at `APP_URL` from `.env`, against MySQL. There is
nothing to start: check it answers, then drive it with Playwright.

```bash
curl -s -o /dev/null -w '%{http_code}\n' "$(grep -E '^APP_URL=' .env | cut -d= -f2-)/login"   # 200
```

## Signed-in screenshots

```bash
.claude/skills/run-epesi/scripts/shoot.sh meetings/13@History ''
.claude/skills/run-epesi/scripts/shoot.sh --light --user someone@example.com companies
```

Each page is a path under `APP_URL`; `@Tab` clicks that tab after loading, and
`''` is the dashboard. Screenshots land in `temp/screenshots/` in the
checkout, named after the page. `/temp` is git-ignored, and the root
`.htaccess` keeps it out of reach over HTTP. Never save them to the project
root or anywhere tracked; `--out DIR` or `EPESI_SCREENSHOTS` changes the place.
Dark mode by default. **Look at every screenshot** — a login page or a blank frame
means it did not work, whatever the exit code says.

What `shoot.sh` does, and why:

1. **Signs in without a password** (`scripts/session.php`): writes a session
   for the first `super_admin` (or `--user`) into the `sessions` table the way
   `SessionGuard::login()` and Filament's `AuthenticateSession` would, and hands
   the browser the encrypted cookie.
2. **Shoots** (`scripts/shoot.cjs` — `.cjs` because `package.json` says
   `"type": "module"`): finds a `playwright-core` whose Chromium is already
   downloaded. npx leaves several Playwright versions in its cache, each wanting
   its own browser build, and Google Chrome is not installed, so
   `channel: 'chrome'` fails. With no match it falls back to Edge
   (`channel: 'msedge'`). The theme goes in `localStorage.theme`, where Filament
   keeps it.
3. **Removes the session again**, on success or failure: the session row and
   the `login_audits` row its first request opened
   (`App\Http\Middleware\TrackLoginAudit`). Without that, every screenshot run
   shows up in Administration > Login audit as a login.

## Traps

- `php artisan tinker <file>` runs the file and then waits at its prompt, so
  the command hangs. Use `--execute="require '...';" < /dev/null`.
- If no Playwright is found at all: `npx -y playwright@latest install chromium`.
- Other Claude sessions may be editing the same checkout: a page can change
  between two screenshots without anything you did.
