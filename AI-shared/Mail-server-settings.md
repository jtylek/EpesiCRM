# Mail server settings and new-account e-mail

Covers **Administration → Mail Server** (legacy Epesi's *Mail server settings*) and what
happens when an administrator creates a user. Legacy references are paths inside the legacy
epesiCRM checkout: `modules/Base/Mail/Mail_0.php` and `MailCommon_0.php` (the settings page,
`test_mail_config()`), and `modules/Base/User/Login/LoginCommon_0.php` (`add_user()`,
`send_mail_with_password()`).

## Why

Password recovery and new accounts both depend on e-mail. On an install whose `.env` says
`MAIL_MAILER=log`, Laravel writes every message to `storage/logs/laravel.log` and opens no SMTP
connection, so "Forgot password?" reports success and nothing arrives. Before this, the only way to
change how epesi sends mail was to edit `.env` by hand (the setup wizard said "Change this later in
.env"), and nothing told an administrator that mail did not work.

## Design

The settings stay where the setup wizard already puts them: the mailer's `MAIL_*` keys in `.env`,
written with `App\Support\Setup\EnvFile`, followed by `config:clear`. There is no new table, package
or migration. Setup and the new page write through one class, `App\Support\Mail\MailConfig`.

### MailConfig (`app/Support/Mail/MailConfig.php`)

- `envValues(...)` turns the choices (method, host, port, security, login, password, from address,
  from name) into `.env` keys. It is the `match` that used to sit in `Installer::configureMail()`:
  SMTP gives `MAIL_MAILER/SCHEME/HOST/PORT/USERNAME/PASSWORD` (`smtps` for SSL, port 465, else 587),
  the log and sendmail methods give `MAIL_MAILER` alone; `MAIL_FROM_ADDRESS/NAME` always.
- `write($values)` checks `EnvFile::writable()`, writes, clears the config cache. On a host where
  `.env` is read-only it changes nothing and returns the text saying what to set by hand.
- `current()` reads the running configuration back into the form's shape.
- `apply($values)` uses values for the rest of the request only, so a configuration can be tried
  before it is kept.
- `Installer::configureMail()` is now a thin caller; its behaviour is unchanged.

### "This server's mail system" (`app/Support/Mail/ServerMailTransport.php`)

This is legacy's *local php.ini settings*: the way PHP's own `mail()` reaches a mail server.
Laravel's stock `sendmail` mailer ignores php.ini and runs one fixed Linux command
(`/usr/sbin/sendmail -bs -i`). On a Windows PC that fails with "Connection to process
/usr/sbin/sendmail -bs -i has been closed unexpectedly", and XAMPP, where `sendmail_path` is empty
and `SMTP=localhost`, `smtp_port=25` point at a mail catcher such as Papercut, never got any mail.

`config/mail.php`'s `sendmail` mailer now has `transport => native`, which
`ServerMailTransport::register()` (called from `AppServiceProvider::boot()`) provides. It picks,
in order:

1. `MAIL_SENDMAIL_PATH`, when set: a sendmail command of your own;
2. php.ini's `sendmail_path` (a command with neither `-bs` nor `-t` is skipped: PHP would run it,
   Symfony can't talk to it);
3. on Windows only, php.ini's `SMTP` and `smtp_port` (implicit TLS only on port 465), as PHP's
   `mail()` does;
4. the old `/usr/sbin/sendmail -bs -i`, for a Linux host whose php.ini sets nothing (the official
   PHP Docker images, say).

The `MAIL_MAILER=sendmail` value, the wizard and the form are unchanged.

### Administration → Mail Server (`app/Filament/Administration/Pages/MailServer.php`)

A regular sidebar page, registered in `AdministrationPanelProvider::pages()` (a page isn't
discovered), open to super_admin as the whole panel is. Its form mirrors the wizard's Mail step,
with the same choices and labels:

- **How epesi sends mail**: this server's mail system / an SMTP server / don't send e-mail.
- **Administrator e-mail address** and **Send e-mails from name** (the From header).
- For SMTP: server, port, security (STARTTLS, SSL/TLS, None), login, password.

The password field is pre-filled with the stored value, as legacy's was, so saving without retyping
never wipes it. The page is behind super_admin, who can read `.env` anyway.

**Save** writes through `MailConfig::write()` and reloads the page (the request that saved has
already read the old `.env`).

**Test** (legacy `test_mail_config()`) sends a message to the administrator who pressed it, with a
10-second connect timeout so a wrong host or port fails in seconds. Unlike legacy, which tested the
saved settings, it uses what the form says now, saved or not, so a configuration is tried before it
is kept. With "don't send e-mail" chosen it says nothing was sent instead of reporting success.

Not ported: legacy's *Reply-To* address. Laravel has no such setting.

Known limit, inherited from the wizard: STARTTLS and None both write `MAIL_SCHEME=smtp`, so the
form cannot tell them apart when it reads the settings back and shows STARTTLS.

### New users get a link, not a password

Legacy generated a password when the field was left blank and e-mailed it in the clear. Here a
user is made from a contact (see [User-management.md](User-management.md)), and:

- The **password fields on Create User are optional** (`UserForm`).
- Typed: used as it is, and nothing is sent.
- Blank: `CreateUser::mutateFormDataBeforeCreate()` stores a random 40-character password (the
  `hashed` cast keeps only its hash; nobody sees it) and `afterCreate()` calls
  `App\Support\Auth\NewAccountMailer::sendSetPasswordLink()`. The user receives the same e-mail
  "Forgot password?" sends, with a link to choose a password. No password travels by e-mail.
- If the e-mail cannot be sent the user is created anyway, and a warning points at Mail Server and
  at Reset Password (which sets a password by hand).
- **A user with no role gets no e-mail.** The reset page refuses an account that can't open the
  panel (`canAccessPanel()`), and "Forgot password?" sends nothing to one, so a link would only
  fail. The user is created, and a warning says they can use "Forgot password?" once they have a
  role, or be given a password with Reset Password.

`NewAccountMailer` does what `App\Filament\Auth\RequestPasswordReset` does: it forces the
notification onto the `sync` queue connection (epesi runs no queue worker, see
[cron.md](cron.md)) and goes through the password broker, so the token and the throttling are the
ones "Forgot password?" uses. A transport failure is reported and returns false. The link is
built for the **main** panel, whichever panel the account was created in: users are created in
Administration, which most of them can't open, and after choosing a password they land on the
main login.

**Reset Password** on the Users View page (a contact no longer has one; see
[User-management.md](User-management.md)) works the same way: typed, it sets that password;
left blank, it e-mails the user the same link through `NewAccountMailer`. An account that can't
sign in gets none, a second link within the minute is held back, and a failing mail server is
reported, each with its own message.

## Trying it on a Windows PC (XAMPP and a mail catcher)

Checked by hand on a XAMPP PC with Papercut SMTP: a message sent from a script and one sent by the
Test button both arrived in Papercut. To repeat it:

1. Start the catcher. Papercut listens on port 25, which is where php.ini's `SMTP=localhost`,
   `smtp_port=25` (XAMPP's default) already points, so nothing else needs configuring.
2. Administration → Mail Server, choose **This server's mail system**, fill in the Administrator
   e-mail address, press **Test**. The catcher gets "E-mail configuration test": "If you are
   reading this, it means that your e-mail server configuration at *the site's address* is working
   properly." It goes to the signed-in administrator's own address. Press **Save** to keep it.
3. Or choose **An SMTP server** and give the catcher's host and port (Papercut: `127.0.0.1`, 25,
   security None), for one that isn't on php.ini's address.

What the failures mean:

| Test says | Cause |
| --- | --- |
| Connection to process /usr/sbin/sendmail -bs -i has been closed unexpectedly | Before `ServerMailTransport`: Laravel ran a Linux program that a Windows PC doesn't have. Now only when php.ini has no mail settings at all and the host isn't Windows |
| Connection could not be established / refused | Nothing listens at that host and port: the catcher isn't running, or php.ini's `SMTP`/`smtp_port` (or the form's) name another address |
| Nothing was sent | The method is "Don't send e-mail" (`MAIL_MAILER=log`): messages only go to `storage/logs/laravel.log` |

Don't point a development PC at XAMPP's own `sendmail\sendmail.exe`: its `sendmail.ini` ships set
to `mail.mydomain.com`, so it fails or sends to the wrong place. The Windows PC needs no
`sendmail_path` here, because the transport reads `SMTP` and `smtp_port` instead.

To see what a running install would send without opening the page, in `php artisan tinker`
(`--execute`, with `< /dev/null` so it doesn't wait at its prompt), `Mail::raw('test', fn ($m) =>
$m->to('you@example.test')->subject('test'))` uses the saved settings, and
`app('mail.manager')->mailer('sendmail')->getSymfonyTransport()` prints the transport picked
(`smtp://localhost` for XAMPP's defaults).

## Files

- `app/Support/Mail/MailConfig.php`: the `.env` keys, writing, reading back, trying.
- `app/Support/Mail/ServerMailTransport.php`, `config/mail.php`: "This server's mail system".
- `app/Filament/Administration/Pages/MailServer.php`: the page.
- `app/Support/Auth/NewAccountMailer.php`: the set-password link for a new account.
- `app/Filament/Administration/Resources/Users/Pages/CreateUser.php`, `Schemas/UserForm.php`.
- `app/Providers/Filament/AdministrationPanelProvider.php`: lists the page.
- `app/Services/Setup/Installer.php`: `configureMail()` calls `MailConfig`.
- `lang/pl.json`: the Polish strings; `TranslationsTest` fails if one is missing.

## Tests

`tests/Feature/MailServerTest.php` redirects `.env` to a scratch directory as `SetupTest` does
(`useEnvironmentPath`). It covers:

- the page opens for super_admin and not for a manager, and shows the running configuration;
- Save writes the expected keys for SMTP (including quoting a password with a space), the port
  defaults (465 for SSL, 587 otherwise), sendmail and log need no SMTP details, an SMTP server
  needs a host, and an unwritable `.env` gives the set-it-by-hand warning and no redirect;
- Test sends to the signed-in administrator from the form's values without saving them (the test
  turns the `sendmail` mailer into an array one), reports a transport failure, and says nothing
  was sent when the method is log;
- Create User: blank password → random one, link e-mailed to the right address and not queued
  (`queue.default=database` in the test), and that link really sets the password; no role → no
  e-mail and a warning; typed password → used, nothing sent, still has to be confirmed; a failing
  mail server → the user is still created and the warning shows.

`tests/Feature/ServerMailTransportTest.php` covers the choice above with php.ini's values passed
in (`sendmail_path` can't be changed at runtime), so it gives the same result on any OS.

## Not done

- Reply-To (legacy `mail_use_replyto`).
- A "send the link again" action on a user. Until then, "Forgot password?" is the way.
- Telling STARTTLS from None when the settings are read back (see above).
