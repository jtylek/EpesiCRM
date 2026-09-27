# Demo mode

The port of Epesi's `DEMO_MODE`: a public installation anyone can try without an account. The
login page offers the demo accounts instead of a password. Anything that would spoil the demo
for the next visitor is either gone or says **"Unavailable in demo mode"**. Every night the demo
goes back to freshly installed with the demo data, and its login audit is kept, so it still shows
who used the demo and from where.

## Switching it on

`DEMO_MODE=true` in `.env` (`config/demo.php`, read through `App\Support\Demo::enabled()`). The
same config file holds:

| Key | Default | What |
|---|---|---|
| `users` | `manager@example.com`, `employee@example.com` | the accounts the login page offers, with a label and a one-line description each (legacy `$demo_users`). `DemoDataSeeder` creates both. |
| `reset_at`, `timezone` | `03:00`, `Europe/Warsaw` (`DEMO_RESET_AT`, `DEMO_TIMEZONE`) | when the scheduler runs `demo:reset` |
| `keep_tables` | `login_audits` | what the reset saves and puts back |
| `admin_email` | `admin@example.com` (`DEMO_ADMIN_EMAIL`) | the administrator the reset creates, with a new random password every time |
| `analytics_id` | empty (`DEMO_ANALYTICS_ID`) | a Google Analytics 4 measurement ID; empty means no analytics (see "Google Analytics" below) |

## What changes

**Login.** `App\Filament\Auth\Login` is the login page of the main and settings panels. Outside
demo mode it is Filament's page, unchanged. In demo mode it shows one **Log in as** select
("Manager — sees and edits every record") and no password, "Remember me" or password reset.
Below the select it says that the data is reset every day (no time: it would read CEST in summer
and CET in winter) and that the visitor's IP address and browser are recorded. That notice is there
because the login audit is personal data.

`authenticate()` accepts only an e-mail from `demo.users`, whatever the request says. It refuses a
user with `super_admin` even if the list is edited to offer one. Then it logs in with
`Auth::login()`, regenerates the session and keeps Filament's login rate limit.

**Language.** Above **Log in as** there is a **Language** select. The choice is kept in a cookie
(`Demo::LOCALE_COOKIE`), and `SetLocale` puts it ahead of the account's own language
(`Demo::locale()`). It isn't kept in the account's settings, because every visitor shares the
account, and it isn't kept in the session, which ends when a visitor logs out to try the other
account. Saving a language under Settings → Regional Settings updates the cookie as well, so that
choice isn't overridden by the one made at login.

**Closed entirely:**

| What | How |
|---|---|
| The Administration panel (users, roles, modules, the store, database update, demo-data removal, translations, common data, custom fields, login audit, "Log in as user") | `App\Http\Middleware\DisabledInDemo` answers 404 on every route of the panel, its login page included. `User::canAccessPanel()` also refuses it, and the user-menu link is hidden. |
| The setup wizard (`/setup`) | the same middleware |
| Password reset ("Forgot password?") | The login page has no link to it, since it has no password field. `App\Filament\Auth\RequestPasswordReset` answers 404 in every panel, so no reset link can be asked for. |
| Settings → Mail accounts | `MailAccountResource::canAccess()`. Every visitor shares the demo accounts, so a mailbox password typed in would be everyone's. "Test" and "Fetch now" would also connect the server to any host a visitor names. |

**"Unavailable in demo mode":**

| What | Where |
|---|---|
| Bulk delete, permanent delete (`DeleteBulkAction`, `ForceDeleteAction`, `ForceDeleteBulkAction`) | `AppServiceProvider::lockDownDemoMode()`, for every resource |
| File uploads (every `FileUpload` is disabled with the message as its hint) and images dropped into the rich editor | the same |
| The contact's linked-login field (Reset Password and Change Username are on Administration → Users, which demo mode doesn't have) | the Contacts module |
| Sending from the compose page; "Archive .eml" | the Mail module |

**Always on in demo mode:**
- **No e-mail leaves the server.** A `MessageSending` listener cancels every message sent through
  Laravel's mailer: reminders, watching, notifications. Sending from a mail account goes through
  the Mail module's own transport, which is closed above.
- **Livewire's temporary upload endpoint is capped at 1 MB.**
- **A bar** at the bottom of every page (`App\Filament\Support\DemoNotice`) says the data is reset
  daily and that e-mail isn't sent and files can't be uploaded.

Everything else works as usual: creating, editing, cloning, single (soft) delete and restore,
follow-ups, reminders, watching, notes, the shoutbox, per-user settings. The nightly reset repairs
whatever visitors do. Per-user settings are shared by every visitor on the same demo account; the
language is the exception, kept per visitor (see "Language" above).

## Guarding a module's action

```php
use App\Support\Demo;

return Demo::guard(Action::make('dangerous')->schema([...])->action(...));
```

`Demo::guard()` does nothing outside demo mode. In demo mode the button stays, but clicking it
only shows the danger notification. It removes the action's form and confirmation first, because
Filament validates a form before any `before()` hook runs. It also turns off the action's own
success message. Filament never calls the original closure, so a hand-made Livewire request can't
run it either: `callMountedAction()` only ever reaches the replacement.

For code that isn't an action, check `Demo::enabled()` and call `Demo::unavailable()`, as
`ComposeMail::send()` does. For a field, use `->disabled(fn (): bool => Demo::enabled())` with a
hint, as the contact's linked-login field does. Anything new that is closed in demo mode also
belongs in the tables above and in `tests/Feature/DemoModeTest.php`.

## The nightly reset: `php artisan demo:reset`

`App\Console\Commands\ResetDemo` wraps `App\Services\DemoReset`:

1. It refuses unless demo mode is on, because it deletes everything, and asks first in production
   unless given `--force`.
2. It puts the site into maintenance mode with its own page (`epesi.demo-resetting`, which
   reloads itself), unless something else, such as a deploy, took it down already.
3. It writes the `keep_tables` rows to `storage/app/private/demo/keep-<time>.json` **before**
   dropping anything. It also records that file in `demo/pending`.
4. It drops every table and runs `migrate`, then `Installer::install()`: the default profile, the
   demo data, and mail set to `log`. The install writes production settings to `.env` only when
   `APP_ENV` is already production. It marks the module setup pages done.
5. It puts the kept rows back with their own ids. The demo data is deterministic, so the demo
   users come back with the same ids. A reference to a row the new install doesn't have, such as
   a visitor-created user, becomes `NULL`; `login_audits.login` still names who it was.
6. It deletes uploads (the file storage, `livewire-tmp`, the mail upload folders), keeps the last
   seven keep-files, removes `demo/pending` and brings the site back up.

If a reset stops halfway, `demo/pending` stays. The next reset restores from that file instead of
saving the now-emptied tables again. The site stays in maintenance mode meanwhile, because a
half-installed demo only shows errors.

It drops the tables itself rather than calling `db:wipe`. On MySQL that is the same thing. On
SQLite, `db:wipe` also runs `VACUUM`, which fails inside the tests' transaction.

**Schedule** (`routes/console.php`, only while demo mode is on): daily at `reset_at` in
`timezone`, plus every 15 minutes while a reset is pending. Both run even in maintenance mode,
since an interrupted reset leaves the site down. This needs the scheduler's cron line, as on any
installation:

```
* * * * * cd /path/to/epesi && php artisan schedule:run > /dev/null 2>&1
```

See [cron.md](cron.md) for setting it up and checking it runs.

## Reading the audit: `php artisan demo:audit`

There is no Administration panel in demo mode, so the login audit is read from the command line.
It prints every session in the last `--days` (30 by default): start time in the demo's timezone,
minutes, account, IP address, reverse-DNS host and device. Then it prints sessions and different IP
addresses per day. `--csv` prints the sessions as CSV instead.

The IP address is the connection's own (`$request->ip()`, in `App\Support\ClientInfo`). A
forwarded address counts only from a proxy listed in `trustProxies()` in `bootstrap/app.php`,
which a host behind Cloudflare or a load balancer needs to set. Legacy Epesi took `X-Real-IP` or
`X-Forwarded-For` from any request. The port did too, until the first demo deploy showed a
browser could record any address it liked.

## Google Analytics

With `DEMO_ANALYTICS_ID` set, `App\Filament\Support\DemoAnalytics` adds Google Analytics 4 to
every page of the demo, the login page included. It is set up the same way as on the epesicrm.com
site around the public demo: Consent Mode v2, basic. Two render hooks do it:

- `HEAD_END` sets Google's tag with everything denied. Google's script is fetched only after the
  visitor accepts.
- `FOOTER` adds a "Cookie settings" link to every page. It also adds the cookie bar, which shows
  until the visitor accepts or declines. Declining later also deletes the `_ga` cookies.

The choice is kept in `localStorage` under `epesi-analytics`, the site's own key. The demo is at
the site's address, so a visitor who already answered on the site isn't asked again. Their Google
Analytics ID carries over too, so a visit from the site into the demo is one journey. A signed-in
visit also sends the demo account (`Manager` or `Employee`) as the `demo_account` user property.
Reporting on it needs a user-scoped custom dimension of that name in Google Analytics.

It complements the login audit rather than replacing it. Google Analytics sees only visitors who
accept, but adds where they came from, what they opened, and a map. The login audit counts every
session. Nothing is added outside demo mode, whatever `.env` says: an ordinary installation is a
company's own CRM.

## Setting up a demo

1. Install a release as usual (see [Epesi-Laravel-distro.md](Epesi-Laravel-distro.md)), or unpack
   it and write `.env` by hand.
2. Set `DEMO_MODE=true` and `APP_DEBUG=false`. `MODULES_INSTALL_ENABLED=false` is worth setting
   too.
3. Run `php artisan demo:reset --force`. On an empty database this is the installation itself.
4. Add the scheduler's cron line.

## Tests

- `tests/Feature/DemoModeTest.php`: the login page and who can be chosen, no password reset, the
  closed panels, the banner, bulk delete, the contact login actions, uploads, mail accounts and outgoing mail.
- `tests/Feature/DemoResetTest.php`: refusing outside demo mode, a reset keeping the audit
  (same ids, orphaned references emptied, uploads gone), and a reset continuing after one that
  stopped halfway. It calls the service, not the command, because the command's maintenance mode
  would be this checkout's own.
- `tests/Feature/DemoAnalyticsTest.php`: Google Analytics only on a demo with an ID, nothing
  fetched from Google before consent, and the demo account sent with a signed-in visit.
