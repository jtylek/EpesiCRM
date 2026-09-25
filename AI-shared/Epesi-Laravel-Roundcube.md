# Roundcube webmail — the `Epesi/Roundcube` module

**Status: built.** Covered by `tests/Feature/Modules/RoundcubeTest.php`. Ticket login, folder
listing and the identity have also been checked against a real IMAP server. The browser
checks listed under Testing have not been run yet.

The design for embedding the Roundcube webmail in the app as a full IMAP client, the port of
legacy Epesi's `CRM/Roundcube` module and its `epesi_*` Roundcube plugins.

## Why

`Epesi/Mail` is an e-mail **archive**, not a mail client. It holds mail that was:
- filed into the account's archive IMAP folder (default "CRM Archive"),
- sent from the CRM, or
- uploaded as `.eml`.

It cannot show a mailbox's INBOX or its IMAP folders. Legacy Epesi filled that gap by
embedding Roundcube in an iframe. This module does the same with the real Roundcube, rather
than rebuilding a webmail client in Livewire. That choice brings:
- every Roundcube feature from the first day;
- about 20 years of hardening in rendering untrusted HTML mail;
- a large maintainer team, compared with a one-person IMAP library.

## Epesi's version, and how it maps here

| Epesi | Here |
|---|---|
| Roundcube 1.7.1 committed under `modules/Libs/RoundCube/RC` | Roundcube **downloaded** by `php artisan roundcube:install` into `storage/roundcube/` (never committed) |
| `RC/config/config.inc.php` booted part of Epesi and read Epesi's PHP session | Generated `config.inc.php`, which never boots Laravel; login goes through a one-time **ticket** |
| `epesi_autologon` + `epesi_autorelogon` (every screen open re-logs-in) | `epesi_sso`, which also re-logs-in whenever the page issues a new ticket |
| `epesi_init` identity name, global signature | `epesi_sso` (`user_create`, `message_outgoing_body`) |
| `epesi_archive` wrote into Epesi's `rc_mails` from inside Roundcube | `epesi_archive` moves messages to the archive folder, and `MailFetcher` archives them |
| `epesi_addressbook`: CRM Contacts / CRM Companies address books | `epesi_addressbook`: one read-only "CRM" address book |
| `epesi_mailto` (compose in Roundcube from a CRM record) | not ported; the CRM's own `ComposeAction` covers it |
| Hard-coded `des_key`, TLS certificate checks off | Per-install derived keys; TLS verification on by default |

## Design at a glance

```
Mailbox page (Filament, main panel)
  └─ issues a one-time ticket (DB row, 60 s, token stored hashed, credentials encrypted
     with a key derived from APP_KEY)
  └─ <iframe src="{app}/roundcube/index.php?_task=login&_epesi_ticket=…">
        Roundcube 1.7.x, served straight by the web server (not through Laravel)
          ├─ epesi_sso: redeems the ticket → IMAP login, per-account SMTP, identity;
          │             password login disabled
          ├─ epesi_archive: "Archive" button → move to the account's archive folder
          │                 → tell the parent page → MailFetcher
          └─ epesi_addressbook: read-only "CRM" address book (contacts, companies,
                                extra addresses, filtered by ownership visibility)
```

The module is `modules/Epesi/Roundcube/`. Its manifest has `id` `epesi/roundcube`, namespace
`Epesi\Modules\Roundcube\`, `requires: ["epesi/mail"]` and `panels: ["main"]`.

## Installing Roundcube

Roundcube can't ship inside the module, for two reasons. It is GPLv3 and this repo is MIT.
And the module zip validator (`config/modules.php` → `zip`) rejects its file types (`.html`
templates, `.inc`, `.sql`, `.sh`) and its file count. Installing or registering a module
only runs its migrations; there's no post-install hook. So the download is a separate step.

Because it is someone else's GPL software from the internet, it is only downloaded after an
explicit yes. Every place that offers it shows the same notice first
(`App\Services\Setup\RoundcubeSetup::notice()`): what Roundcube is, its license (linked), and
where it comes from. There are four ways in, all ending in the same `RoundcubeInstaller`:

- **The setup wizard's Webmail step** (see [Setup-wizard.md](Setup-wizard.md)). A required
  yes/no. Yes installs the module with the others and downloads Roundcube last. A failed
  download is a warning, and setup still finishes.
- **Download and install Roundcube** on the Mailbox page, in place of the mail client while
  it isn't installed. Only a super_admin sees it; everyone else is told to ask their
  administrator.
- **The same button under Administration → Modules**, until Roundcube is installed. This
  covers a "no" in setup, since without the module there is no Mailbox page. It turns on the
  module and whatever it requires (Mail) first.
- **`php artisan roundcube:install`** on the command line, which is also how Roundcube is
  upgraded.

`RoundcubeSetup` (core app) does the module part: it registers or enables `Epesi/Roundcube`
and its requirements (`ModulePlan`), then loads them into the running request
(`ModuleLoader`), because the button is clicked in a request that started without the
module. It then calls the installer by class name, since the module may not have been
loaded when the core code was compiled. The page reloads afterwards so the menu shows
Mailbox.

Two things differ when the installer runs from a web page instead of the command line.
`RoundcubeInstaller` handles both:

- **`PHP_BINARY` isn't PHP.** Under Apache's mod_php (XAMPP) it is `httpd.exe`. Roundcube's
  `bin/initdb.sh` is a PHP script, so `RoundcubeInstaller::php()` runs it with `PHP_BINARY`
  only on the CLI. Elsewhere it looks for `php`/`php.exe` in `PHP_BINDIR` and next to the
  loaded `php.ini` (`C:\xampp82\php\php.exe`), then falls back to the `PATH`.
- **No CA certificates.** XAMPP's PHP ships without `curl.cainfo`, so an HTTPS download fails
  certificate checks. The download verifies against
  `Composer\CaBundle\CaBundle::getSystemCaRootBundlePath()`: the system's bundle when there is
  one, otherwise Mozilla's, bundled in `composer/ca-bundle`. Verification stays on.

The installer:

1. Downloads the release pinned in `config/epesi-roundcube.php`: the version and the
   sha256 from roundcube.net. It uses the `-complete` tarball from GitHub releases, which
   bundles Roundcube's own Composer dependencies. The pin is currently **1.7.4**, released
   2026-09-06, which needs PHP 8.1–8.5.
2. Verifies the checksum and refuses on a mismatch. It then extracts with `PharData` into
   `storage/roundcube/`.
3. Links `public/roundcube` → `storage/roundcube/public_html`, with `Filesystem::link()`.
   That is a directory junction on Windows, so no admin rights are needed.
4. Links each plugin directory under `modules/Epesi/Roundcube/roundcube-plugins/` into
   `storage/roundcube/plugins/`, so the plugins stay live with the module's code. Roundcube
   only loads plugins from its own `plugins/`. `static.php` serves plugin assets through the
   link because it checks an allowed path prefix, not the resolved real path.
5. Writes `config/config.inc.php` (see below).
6. Creates the `rc_*` tables with Roundcube's own `bin/initdb.sh --dir=SQL`, or upgrades them
   with `bin/updatedb.sh` when they already exist.

Two Windows traps the installer handles, which any other code touching `storage/roundcube`
has to handle too:

- **Never delete `storage/roundcube` recursively with the links still inside.** On Windows,
  `Filesystem::deleteDirectory()` follows a junction and empties its target. It would delete
  the module's own plugin sources through `storage/roundcube/plugins/epesi_*`. PHP doesn't
  report a junction as a link, but `readlink()` resolves it. `rmdir()` removes a junction and
  leaves its target alone; `unlink()` does the same for a Unix symlink. The installer removes
  the links first (`deleteInstall()`).
- **A freshly extracted directory inside the project can't be renamed for a second or two**
  ("Access is denied" while a file watcher or virus scanner still has it open). The move
  into place retries for up to 30 seconds. `Filesystem::moveDirectory()` and
  `Filesystem::link()` return quietly on failure, so the installer checks the result of every
  move and link itself.

Running the command again is safe, and it is how Roundcube is upgraded: bump the pinned
version and checksum, then run it. `--configure-only` only rewrites the config, for example
after a database password or `APP_KEY` changes. `--release=` together with `--sha256=`
installs another version.

## Why `storage/roundcube`

Roundcube is downloaded at install time, so it needs a directory outside git (see above for
why it can't be committed). `storage/` is the only place in the project that fits:

- **Not the module's own directory.** Installing a module zip over an existing version
  moves the old directory aside, moves the new one in and deletes the old one
  (`ModuleInstaller`). Uninstalling deletes the directory. A copy under
  `modules/Epesi/Roundcube/` would disappear with every module update.
- **Not `public/`.** Only `public_html` has to be reachable from the web. Everything else
  must not be served. `config/config.inc.php` holds the database password and the derived
  keys. `vendor/`, `bin/`, `SQL/` and `installer/` are code and scripts, not web pages. So the
  whole copy sits in `storage/`, and `public/roundcube` is only a link to its `public_html`.
  That is the layout Roundcube is built for: `public_html` as the one document root.
- **Writable.** The installer creates, replaces and deletes the whole tree on every install
  and upgrade. `storage/` is the directory the app is expected to write to, on any
  deployment. Roundcube's own runtime files go to Laravel's directories too, set in the
  generated config: logs to `storage/logs/roundcube/`, temp files to
  `storage/framework/roundcube/`. The `logs/` and `temp/` directories inside
  `storage/roundcube/` stay empty.

The cost is that `storage/` normally holds data, not code. A backup of `storage/` includes
the Roundcube copy, and anyone clearing out `storage/` has to know it is there. On Windows,
clearing it out also runs into the junction trap above.

## Generated configuration

It holds nothing that can't be derived again from Laravel's own configuration, so it can be
rewritten at any time:

| Setting | Value |
|---|---|
| `db_dsnw` | From Laravel's default connection: the **same database**, as in legacy |
| `db_prefix` | `rc_`, as in legacy |
| `des_key` and the SSO key | HMAC-SHA256 of fixed labels keyed with `APP_KEY`. `APP_KEY` itself never lands in Roundcube, and rotating it only ends open Roundcube sessions |
| `session_name` | `epesi_roundcube_sessid` |
| `session_path` | The app's base path, so the cookie reaches Laravel's logout request |
| `session_lifetime` | Laravel's `session.lifetime` |
| `plugins` | `epesi_sso`, `epesi_archive`, `epesi_addressbook`, `markasjunk`, `zipdownload` |
| `skin` | `elastic` |
| `x_frame_options` | `sameorigin` |
| `imap_conn_options` / `smtp_conn_options` | Certificate checks follow `epesi-mail.imap_validate_cert`, which is on by default. Legacy had them off |
| `enable_installer` | `false` |
| `log_dir` / `temp_dir` | Inside the Roundcube tree under `storage/` |

## Single sign-on: tickets

Roundcube runs as its own PHP application and never boots Laravel. Laravel's session cookie
is encrypted with `APP_KEY`, so the two can't share a session. Login therefore goes through a
one-time ticket in the table `epesi_roundcube_tickets`, which has the columns `token`,
`user_id`, `account_id`, `payload` and `expires_at`.

1. On every load, the Mailbox page's `TicketIssuer` does three things:
   - deletes expired tickets;
   - stores a new one: a random token kept only as its sha256, and a 60-second expiry;
   - puts the plain token in the iframe URL.

   The payload is encrypted with `new Encrypter($ssoKey, 'aes-256-gcm')->encryptString(json)`
   and holds:
   - the IMAP and SMTP settings as Roundcube host URIs (`ssl://host:993`, `tls://host:587`),
     with their logins and passwords;
   - the e-mail address, display name, archive folder and `archive_on_sending`;
   - the global signature;
   - for the address book: the user id, whether the user sees every record (super_admin or
     manager), and the user's company id.

   Credential fallbacks are the same ones the rest of `Epesi/Mail` uses:
   - an empty IMAP login means the e-mail address (`MailAccount::imapLogin()`);
   - empty SMTP credentials mean the IMAP ones (`MailAccount::smtpLogin()` /
     `smtpPassword()`).
2. `epesi_sso` redeems the ticket in two steps:
   - it selects the row by hash where it hasn't expired, deletes it, and decrypts the payload
     in Laravel's `{iv, value, mac, tag}` format;
   - it logs in with that account's IMAP settings through the `startup` and `authenticate`
     hooks. A ticket in the URL first kills any existing Roundcube session, which covers
     switching accounts and a different Epesi user in the same browser.

   The redeem-and-decrypt code (`epesi_sso_ticket.php`) has no Roundcube dependency, so
   PHPUnit tests it directly against Laravel's encrypter.
3. **Without a ticket, Roundcube's login is aborted.** It can't be used as a separate
   password login. When its session has expired inside the iframe, the login page posts
   `login-required` to the parent page, which issues a fresh ticket. Opened on its own, the
   login page says to open Mailbox from Epesi.

## Plugins (`modules/Epesi/Roundcube/roundcube-plugins/`)

They are MIT like the rest of the repo: Roundcube's license exempts plugins that only call
its API.

**Language.** The ticket carries the Epesi user's `language`, and `epesi_sso` passes it to
`load_language()` at login, so Roundcube's whole interface follows the language chosen in
Epesi. The plugins' own labels come already translated in the ticket's `labels`, from `__()` and
the module's `lang/pl.json`, like every other string in the app. They are registered with
`load_language(null, [...])`, not with `localization/*.inc` files, because the module zip
validator rejects `.inc` files. The only untranslated text is the "Open Mailbox in Epesi"
page, which is shown before any login, when there is no ticket to read a language from.

- **`epesi_sso`** handles:
  - ticket login;
  - the first-login identity (`user_create`: name and e-mail);
  - per-account SMTP (`smtp_connect`, with the password kept in the Roundcube session
    encrypted by `$rcmail->encrypt()`);
  - the global signature (`message_outgoing_body`, as `MailSender` appends it);
  - the `login-required` message described above.
- **`epesi_archive`** has four parts:
  - An **Archive** button on the message list and the message view. It is disabled in the
    archive folder and in Drafts, as it was in Epesi. The archive folder is created and
    subscribed on login if it's missing.
  - **The checks, as in Epesi, in Epesi's order.** The button first posts the selection to
    the plugin action `plugin.epesi_archive`, which uses `epesi_archive_matcher`:
    1. **Already archived, by anyone?** A message whose Message-ID is in the archive is left
       where it is, with Epesi's "Message already archived". The archive is shared, so a
       message is kept once however many people file it. A deleted copy doesn't count; see
       No duplicates below.
    2. **Linked to anything?** The action reads each remaining message's From, To and Cc
       (Epesi checked only From and To). Any contact or company with one of those
       addresses counts, your own contact included, as in Epesi's `look_contact()`. If a
       message matches nothing, the action shows Epesi's own warning: "Matching contact or
       company not found. Click again to force archive without contact association." The
       Polish is also Epesi's. It remembers the warned messages in the Roundcube session
       (Epesi's `force_archive`), so the next click archives them anyway. One warning
       covers every unmatched message in the selection; Epesi warned one message at a time.
    3. **Otherwise** it answers with the `plugin.epesi_archive_move` callback. The client
       moves exactly the messages it names into the archive folder and posts `archived` to
       the parent page.
  - **Linking.** The parent runs `MailFetcher::fetch($account)`, so filing reuses the whole
    existing pipeline:
    - **The contact of whoever archives is always linked**, whether or not their address is
      on the message. That is what the archive must tell first, especially for a shared
      mailbox (support@…), whose addresses say nothing about who filed it. It is also the
      `employee` of the archived mail. Epesi listed a message on its employee's contact
      too; `MailImporter` links imported mail to its employee, and a migration added the
      link to mail archived before.
    - `MailArchiver` links every contact and company whose address is in From, To or Cc,
      through `ContactMatcher`.
    - Attachments and threads.
    - An outgoing direction when the sender is one of your own addresses, including your
      contact's extra addresses.

    `epesi_archive_matcher` has to give the same answer as that linking: no visibility
    rule, deleted records left out, and the own contact counted. It is plain PHP, and
    `RoundcubeTest::test_the_archive_check_agrees_with_what_archiving_links` runs it and
    `ContactMatcher` over the same records. Auto-archive (INBOX/Sent) is the one place where
    your own contact doesn't count: "involves a known contact or company" means someone
    other than you, because your address is on every message in your mailbox.
  - A compose toggle, **Archive this message after sending**, that defaults to the account's
    `archive_on_sending`. It stores the sent copy in the archive folder (`_store_target`),
    and the parent fetches on the next page load. It never warns, like Epesi's archiving on
    send.

  **No duplicates.** Every way into the archive goes through `MailArchiver`: the Archive
  button, fetching, `.eml` upload, sending from the CRM and the legacy import. It keeps one
  copy per message:
  - It looks the message up by Message-ID across all users. A message without one is
    looked up by sender, recipients, subject and date.
  - A deleted copy is restored rather than stored again.
  - The lookup and the insert run under a cache lock per message
    (`epesi-mail-archive:<hash>`). A scheduled fetch and the Archive button, or two
    colleagues filing the same message, can't both find it missing and both store it.
  - Archiving an existing message again adds none of the second person's links. Only
    explicit links, such as the record a message was sent from, are added.
- **`epesi_addressbook`** is a read-only `rcube_addressbook` named "CRM", which is also used
  for compose autocomplete.
  - It is a UNION of three sources: `contacts`, `companies`, and `epesi_mail_addresses`
    joined to their owning record. All use `deleted_at IS NULL`.
  - It repeats `HasOwnershipVisibility` in SQL for users other than super_admin or manager:
    - contacts: `permission <> 2 OR created_by = :uid OR user_id = :uid`
    - companies: `permission <> 2 OR created_by = :uid OR id = :company`

    **A change to that trait's rule has to be repeated here.** The plugin's comment points
    back to it.

## The Mailbox page

`Epesi\Modules\Roundcube\Filament\Pages\Mailbox` is in the main panel.

- **Sidebar:** "Mailbox" with `Heroicon::OutlinedInbox`, sort 59, just above E-mails (60).
- **Header and access:** it uses `HasPageIconBreadcrumb` and `HidesPageHeading` (see
  [conventions.md](conventions.md)), and `canAccess()` allows the same roles as
  `MailPolicy::viewAny`.
- **Account switcher:** a header action, shown when the user has more than one account with
  IMAP. Switching issues a ticket for the other account.
- **Layout:** the iframe fills the rest of the window height. It uses inline styles because
  the panel's precompiled stylesheet has no classes a module adds.
- **Empty states:**
  - no account: a link to Settings → Mail accounts;
  - Roundcube not installed (no `public/roundcube/index.php`): "run `php artisan
    roundcube:install`".
- **Messages from the iframe:** an Alpine `message` listener checks the origin and that the
  source is the iframe, then handles two messages. `archived` runs the fetcher and shows a
  toast. `login-required` reloads the iframe with a new ticket.
- **Dark mode:** before the iframe loads, the page sets Roundcube's `colorMode` cookie from
  Filament's theme, so Elastic's dark mode follows the app. With no cookie, Elastic follows
  the OS setting.

## Logout

A Logout listener (`EndRoundcubeSession`) deletes the `rc_session` row named by the
`epesi_roundcube_sessid` cookie. That cookie is in `EncryptCookies::except()`, because Laravel
would otherwise fail to decrypt a cookie it didn't write and pass on null.

## Web server requirements

Roundcube runs outside Laravel, so the web server must serve `public/roundcube/` as real files.
That includes two kinds of URL:
- its directory URLs (`…/roundcube/?_task=mail`), because Roundcube builds its links from the
  directory;
- path-info asset URLs (`…/roundcube/static.php/skins/…`).

Setups differ:
- **Apache with Laravel's stock `public/.htaccess`:** already works, since it passes existing
  directories and files through.
- **nginx:** needs `index index.php` and a PHP location that splits path info (`location ~
  [^/]\.php(/|$)` with `fastcgi_split_path_info`), as Roundcube's own docs describe.
- **`php artisan serve`:** not supported for Mailbox. The built-in server's router sends
  path-info URLs to Laravel.
- **Local XAMPP (this project's development setup):** the vhost in
  `apache/conf/extra/httpd-vhosts.conf` rewrites only existing *files* to `public/`. It needs
  one extra rule in both the `:80` and `:443` blocks, right after `RewriteEngine On` and before
  the existing rules. Apache then needs a restart from the XAMPP Control Panel; it runs as a
  console process there, not as a Windows service.
  ```
  RewriteRule ^/epesi-laravel/roundcube(/.*)?$ "C:/xampp82/htdocs/epesi-laravel/public/roundcube$1" [L]
  ```

`.gitignore` covers `/storage/roundcube` and `/public/roundcube`.

## Deliberately not carried over

- The `mailto:` protocol handler and composing in Roundcube from a CRM record. The CRM's own
  `ComposeAction` (New / Reply / Reply all / Forward) covers these.
- Multi-window `RCWIN_` sessions.
- `epesi_init`'s syncing of date formats from Regional Settings.
- The "no matching contact — click again to force archive" prompt. The archive folder
  already means "archive everything filed here".
- Creating a CRM contact from Roundcube's "Add to address book".
- A separate "CRM Archive Sent" folder. Archived sent mail goes to the one archive folder,
  and the archiver records its direction.

## Files

- `modules/Epesi/Roundcube/`:
  - `module.json`, `config/epesi-roundcube.php`
  - `database/migrations/…_create_epesi_roundcube_tickets_table.php`
  - `src/RoundcubeServiceProvider.php`: config, migrations, views, the command, the Logout
    listener and the cookie exception
  - `src/RoundcubePlugin.php`
  - `src/Roundcube.php`: install paths, the URL, the cookie path, the table prefix and the
    keys derived from `APP_KEY`
  - `src/Filament/Pages/Mailbox.php` + `resources/views/mailbox.blade.php`
  - `src/Services/TicketIssuer.php`, `RoundcubeInstaller.php`, `RoundcubeConfigWriter.php`
  - `src/Console/InstallRoundcubeCommand.php`
  - `src/Listeners/EndRoundcubeSession.php`
  - `roundcube-plugins/epesi_sso/`, `epesi_archive/`, `epesi_addressbook/`
- `modules/Epesi/Mail/src/Models/MailAccount.php`: `smtpLogin()` / `smtpPassword()`, moved
  out of `SmtpTransportFactory`.

## Testing

`tests/Feature/Modules/RoundcubeTest.php` covers:
- A ticket is stored hashed with an expiry, and its payload decrypts through
  `epesi_sso_ticket` to the account's credentials, including the blank-login fallback.
- A redeemed ticket can't be reused, and an expired one is refused (SQLite PDO).
- The page renders the iframe for a user with an account, and each empty state otherwise.
- `archived` runs the fetcher (`FakeMailbox`).
- Logout deletes the `rc_session` row.
- The config writer produces the expected DSN, prefix, `session_path` and stable derived
  keys.
- The installer refuses a checksum mismatch (`Http::fake`).

`PageHeaderTest` picks up the Mailbox page automatically.

Roundcube itself is only exercised by hand, in the browser:
- Mailbox opens with no login prompt, and shows INBOX and every IMAP folder;
- dark mode follows the app;
- sending goes through the account's SMTP server;
- Archive moves a message and it appears under E-mails, linked to its contact;
- CRM contacts autocomplete in compose;
- after logging out, `…/roundcube/` asks to be opened from Epesi.
