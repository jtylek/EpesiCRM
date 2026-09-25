# The epesi distribution package

How the downloadable epesi zip is built, what happens when someone opens it in a browser for
the first time, and how a new user sets epesi up on a shared hosting account: no shell, no
Composer, no Node, only a file manager (or FTP), a database and a browser. It also covers
installing the Roundcube webmail.

This is the port of Epesi's own download: a zip you unpacked on any PHP host, then opened
`setup.php` in the browser. [Setup-wizard.md](Setup-wizard.md) has the code-level detail of
every wizard step. [Epesi-Laravel-Roundcube.md](Epesi-Laravel-Roundcube.md) covers the
Roundcube module in depth. This document ties them together from the release manager's side
and the new user's side.

## What is in the package

`epesi-2.0.zip`, about 15 MB, with `epesi-2.0.zip.sha256` next to it (see
[Version numbers](#version-numbers)). It holds:

| Part | Where from | Why |
|---|---|---|
| The application: `app/`, `bootstrap/`, `config/`, `database/`, `lang/`, `modules/`, `public/`, `resources/views/`, `routes/`, `storage/` skeleton, `artisan`, `composer.json`, `.env.example`, `.htaccess`, `LICENSE`, `VERSION` | the files git tracks (`git ls-files`), as they are on disk | exactly what is committed, so nothing local leaks in |
| `vendor/` | `composer install --no-dev` | the server needs no Composer |
| `public/build/` | `npm run build` | the server needs no Node |

It never contains:
- `.env`, logs, sessions, uploads or `storage/app/*` contents, because they aren't tracked by git;
- what only a developer uses (`PackageRelease::EXCLUDE`): `AI-shared/`, `.claude/`,
  `CLAUDE.md`, `PORTING.md`, `README.md`, the git and editor settings, `tests/` and
  `phpunit.xml`, the frontend's sources (`resources/css/`, `resources/js/`, `package.json`,
  `vite.config.js`) and the lock files. `composer.json` stays: Laravel reads the
  application's namespace from it;
- what packages ship in `vendor/` that nothing runs (`PackageRelease::isPackageClutter()`):
  their `docs/`, `tests/`, `.github/` and `art/` folders, Markdown files, source maps and
  tool settings (PHPStan, Psalm, PHP-CS-Fixer, Rector), `vendor/bin/`, Livewire's bundler
  builds and test images, and `vendor/filament/*/dist/`, which `filament:assets` already
  copied into `public/`. Their license files always stay. This takes the zip from 21 MB to
  16 MB. One consequence: `php artisan filament:assets` fails in an installed release, and
  nothing there needs it;
- Roundcube, which is GPL-licensed and downloaded on the server only when someone chooses it
  (see [Roundcube](#roundcube-webmail));
- development packages (PHPUnit, Faker, Pint, Tinker…), when it's built as described below.
  Tinker, the interactive console, is one of them (with psysh and PHP-Parser, 0.9 MB), so
  `php artisan tinker` isn't available on an installed release.

The files sit at the top of the zip with no wrapping folder: unpacking into
`public_html/crm/` gives `public_html/crm/public/index.php`. `--folder=epesi` wraps them in
a folder of that name instead.

## Building the package

`php artisan epesi:package` (`app/Console/Commands/PackageRelease.php`). Build it from a
**separate clean clone**, never from your development checkout: the release needs
`vendor/` without the development packages, and the test suite needs them.

```bash
git clone https://github.com/jtylek/epesi-laravel.git epesi-release
cd epesi-release
git checkout main                       # or the commit to release

composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction
npm ci && npm run build

cp .env.example .env && php artisan key:generate   # only so artisan can boot here;
                                                    # .env is not tracked, so it isn't packed
php artisan epesi:package
# → storage/app/private/releases/epesi-2.0.zip and epesi-2.0.zip.sha256
```

Options: `--out=<dir>` writes the zip elsewhere; `--folder=<name>` wraps the files in a
folder.

### Version numbers

The version lives in one place: the `VERSION` file at the top of the application, currently
`2.0.0` (`App\Support\Version`). People see it as **epesi 2.0**: under the sidebar, below the
login and setup pages, and in `php artisan about`. The full form is also the core version that
module manifests' `"epesi_core"` constraints are checked against. The bundled modules require
`^2.0`, and a test fails if a version bump leaves a module behind, because setup couldn't
install it.

`epesi:package` names the zip after it:
- **`epesi-2.0.zip`** when the commit is tagged `v2.0` (or `v2.0.0`), i.e. the release;
- **`epesi-2.0-dev.<date>.<commit>.zip`** otherwise, with a warning, so a test build is never
  mistaken for the release.

It also writes `<zip>.sha256` (`sha256sum -c epesi-2.0.zip.sha256` checks a download).

Releasing a version:
1. Set `VERSION` (e.g. `2.0.1`). A new major version also means updating the modules'
   `"epesi_core"` constraints.
2. Commit, and tag it: `git tag v2.0.1 && git push origin v2.0.1`.
3. Build the package from that tag, as above.

The command refuses to run without `vendor/autoload.php` or `public/build/manifest.json`. It
warns when `vendor/` still has PHPUnit, meaning it was installed with the dev packages. It
also warns when packages in `vendor/` are git clones (see the next section).

### Keep `vendor/` to release contents (21 MB, not 66 MB)

Composer normally downloads each package's **release archive** (its "dist"). That archive
leaves out whatever the package marks `export-ignore` in its `.gitattributes`: its tests,
docs, fixtures and CI files. When Composer can't download archives, it falls back to
**cloning** each package's git repository (its "source"). This happens in the cloud build
container, which gets "Could not authenticate against github.com" without a GitHub token, and
behind some proxies. A clone brings everything: `league/csv` alone carries 38 MB of test
files. Packed like that, the zip was **66 MB instead of 21 MB**, with the same working code.

The fix is to turn every cloned package back into what its release archive would have
contained, then rebuild the autoloader. `git archive HEAD` produces exactly that, because it
applies the package's own `export-ignore` rules:

```bash
for g in $(find vendor -mindepth 3 -maxdepth 3 -name .git -type d); do
    d=$(dirname "$g")
    mkdir "$d.__dist"
    git -C "$d" archive --format=tar HEAD | tar -x -C "$d.__dist"
    mv "$d" "$d.__src" && mv "$d.__dist" "$d" && rm -rf "$d.__src"
done
composer dump-autoload --optimize --no-dev
php artisan epesi:package
```

`vendor/` goes from 890 MB on disk to 114 MB, and the zip to about 15 MB. On a normal machine
with working dist downloads (a developer PC, CI with a token), none of this is needed:
`--prefer-dist` already gives release contents. You can tell which case you're in: the
command warns "N packages in vendor/ are git clones" when you need the loop above.

The command already skips `.git` directories. What makes a cloned `vendor/` large is
everything else the archive would have left out.

### Checking a package before publishing

```bash
mkdir /tmp/check && cd /tmp/check && unzip -q /path/to/epesi-*.zip
ls -a | grep -x .env            # must print nothing
ls vendor | grep phpunit        # must print nothing
php -r 'require "vendor/autoload.php"; var_dump(class_exists("Filament\\Panel"));'
```

Then do a real install from it, as in [Setting up on shared hosting](#setting-up-epesi-on-shared-hosting),
on XAMPP or a test hosting account.

## How the wizard works

Three stages, all in the browser. Each is a separate page, and each can be interrupted and
resumed.

### 1. The first request writes `.env` (`FirstBoot`)

`public/index.php` runs `App\Support\Setup\FirstBoot` before Laravel boots. It is plain PHP,
because Laravel can't render anything without an application key. While `.env` is missing or
has no `APP_KEY`, it:

- checks that the folder can take `.env`, that `storage/` and `bootstrap/cache/` are writable
  and that `.env.example` exists. If not, it shows a plain page listing what to fix;
- copies `.env.example` to `.env` and sets a fresh `APP_KEY`, with `SESSION_DRIVER=file`
  and `CACHE_STORE=file`, because there is no database yet;
- sets `APP_ENV=production` and `APP_DEBUG=false` in the `.env` it created.
  `.env.example` is set up for development, and with debug on, any error page would show
  configuration values (the database password among them) to whoever sees it. A
  developer's own `.env` that only lacks a key keeps its settings.

`index.php` also shows a plain message when `vendor/` is missing, which means a git checkout
rather than a release zip.

### 2. Server and database (`/setup/install`, Epesi's `setup.php`)

Every page redirects to `/setup/install` until epesi is installed. While the database has no
tables, the wizard shows three steps:

1. **Setup code.** Until setup is done, whoever opens `/setup` could make themselves the
   administrator, or point epesi at their own database. So the wizard first asks for a
   one-time code written to a file on the server, **`storage/app/setup-code.txt`** (twelve
   characters like `K7QM-2XRP-9HTD`). Only someone who can see the server's files can read
   it. `SETUP_TOKEN` in `.env`, if set, replaces it. The code is asked once per browser
   session, and the file is deleted when setup finishes.
2. **Server check.** PHP ≥ 8.2; the extensions `ctype curl fileinfo intl mbstring openssl pdo
   tokenizer xml zip`; at least one database driver (`pdo_mysql`, `pdo_pgsql` or
   `pdo_sqlite`); `storage/`, `bootstrap/cache/` and `.env` writable. A writable `modules/`
   is optional: it is only needed to install modules from the web page. **Next** stays
   blocked while a required row fails.
3. **Database.** Type (only the ones this PHP can open), server, port, database name, user
   and password, or a file path for SQLite. **Create the tables** connects first and shows
   the database's own error on failure. It then saves the connection to `.env`, sets
   `APP_URL` to the address the page was opened at, and runs the migrations. If an existing
   epesi database is detected, it goes straight to the login page.

`php artisan epesi:install` is the same stage for a host with a shell. It also prints the
setup code.

### 3. The system (`/setup/install`, Epesi's FirstRun)

Once the tables exist, the same address asks:

1. **Setup type.** A profile from `config/setup.php`:
   - **CRM installation**: contacts, companies, tasks, meetings, phone calls, calendar,
     notes and files, watching, follow-ups, reminders, e-mail archiving, shoutbox;
   - **Core only**: the CRM records and the calendar.

   Also **Load demo data**: about 100 companies and 100 contacts, 30 tasks, 30 phone calls and
   30 meetings, a shoutbox conversation, and two demo users (`manager@example.com` and
   `employee@example.com`, password `password`). See [Demo data](#demo-data).
2. **Administrator.** Name, e-mail (the login) and password.
3. **Mail.** How epesi sends its own e-mail (password resets, reminders): this server's mail
   system (sendmail, usually right on a hosted server), an SMTP server, or not yet
   (messages go to the log).
4. **Webmail.** Whether to install Roundcube; see [Roundcube](#roundcube-webmail).
5. **Install.** A summary of the modules. **Install** then:
   - takes a lock;
   - creates the roles;
   - installs the modules in dependency order and loads them into the running request;
   - creates the administrator (plus the demo data if chosen);
   - writes the mail settings to `.env`;
   - writes `APP_ENV=production` and `APP_DEBUG=false` to `.env`. This covers copies whose
     `.env` wasn't created by FirstBoot. `epesi:install --dev` keeps a developer's settings,
     and if `.env` isn't writable, setup finishes with a warning to change them by hand;
   - downloads Roundcube if asked;
   - writes the installed marker (`storage/app/epesi-installed.json`);
   - signs the administrator in.

### 4. Module pages (`/setup/finish`, FirstRun's `post_install`)

The newly installed modules ask for their own details: **Your company** (your company and
your name, from Contacts) and **Regional settings** (language, timezone, date and time
format, location: the defaults for every user). **Skip for now** is allowed. Then the
administrator lands on the dashboard.

## Setting up epesi on shared hosting

This is for a new user with an ordinary hosting account: cPanel, DirectAdmin, Plesk or
similar. It needs no shell access.

### What the host must offer

- **PHP 8.2 or newer**, with the extensions listed under [Server check](#2-server-and-database-setupinstall-epesis-setupphp).
  Most control panels have a "Select PHP version" or "PHP settings" page where extensions
  like `intl`, `fileinfo` and `zip` can be switched on.
- **A MySQL or MariaDB database** (PostgreSQL works too), plus a database user that has all
  privileges on it.
- **Apache with `.htaccess` and mod_rewrite**, which almost every shared host has. On an
  nginx-only host, see [Where to put the files](#2-where-to-put-the-files).
- **Cron jobs**, for mail fetching and reminders. Most panels have them.
- Optional, for Roundcube:
  - outbound HTTPS to github.com (the download);
  - symlinks allowed in the web space;
  - PHP's `proc_open` enabled, plus a PHP command-line binary. Roundcube creates its tables
    with its own script.

  Some hosts disable one of these. Then the download is reported as a warning, and setup
  still finishes.

### 1. Create the database

In the control panel's **MySQL Databases** (cPanel) or equivalent:

1. Create a database, e.g. `account_epesi`.
2. Create a user with a strong password.
3. Add the user to the database with **All privileges**.

Note the database name, user name and password. Shared hosts usually prefix both names with
the account name. The database server is normally `localhost`.

### 2. Where to put the files

The only folder that should be reachable from the web is epesi's `public/`. Pick one of
these:

**A. A (sub)domain whose document root is `public/` (best).**
1. In the file manager, create a folder next to `public_html`, not inside it, e.g.
   `/home/account/epesi`.
2. Upload the zip there and **Extract** it with the file manager. Uploading the 15 MB zip and
   extracting it on the server is far faster than uploading thousands of files over FTP.
3. In **Domains** / **Subdomains**, create e.g. `crm.example.com` and set its **document
   root** to `/home/account/epesi/public`.

The address is then `https://crm.example.com/`. Nothing outside `public/` can be reached,
whatever the server.

**B. A folder inside `public_html` (when the document root can't be chosen).**
1. Create `public_html/crm`, upload the zip into it, and extract it there.
2. The zip's top-level `.htaccess` rewrites every request into `public/` internally.

The address is then `https://example.com/crm/`, with no `/public` in it. Unpacked straight into
`public_html`, it is `https://example.com/`. Nothing outside `public/` can be reached:
`/crm/.env` becomes `public/.env`, which doesn't exist, so epesi answers "Not found".
`public/index.php` lines its base path up with the address (`App\Support\Setup\BasePath`).
Addresses with `/public` in them, from before, keep working.

> **Check after unpacking (option B):** open `https://example.com/crm/.env` in the browser.
> It must show a **"Not found"** page (or "Forbidden"). If it shows text, or downloads a file,
> the server ignores `.htaccess` (nginx, or `AllowOverride None`), and your settings would be
> readable by anyone once setup has written them. Stop, delete the files, and use option A.
> Without mod_rewrite, the `.htaccess` serves nothing at all ("Forbidden" everywhere).

In both cases, make sure the web server can write to `storage/`, `bootstrap/cache/` and the
epesi folder itself (for `.env`). On most shared hosts, PHP runs as your own account, so
freshly extracted files already are writable. If not, set those folders to 755 (or 775) in
the file manager.

### 3. Run the wizard

1. Open the address from step 2. The first request writes `.env`, then shows **Welcome to
   epesi**. If it shows "epesi can't start its setup yet" instead, it lists the folders to
   make writable. Fix them and reload.
2. **Setup code:** in the file manager, open `storage/app/setup-code.txt` in the epesi
   folder, and copy the code into the page.
3. **Server check:** every required row should say OK. A missing extension is fixed in the
   panel's PHP settings, then **Next** again.
4. **Database:** choose **MySQL**. Server `localhost`, port `3306`, then the database name,
   user and password from step 1. Click **Create the tables**. A wrong password shows the
   database's own message ("Access denied for user…").
5. Answer the system questions: setup type, administrator, mail, webmail. On shared hosting,
   **This server's mail system** usually works. If mail doesn't arrive, use **An SMTP
   server** with the mailbox login from the panel's Email Accounts. Click **Install**.
6. Fill in **Your company** and **Regional settings**, or skip them. You are now signed in as
   the administrator.

### 4. Check `.env`

Setup has already switched epesi to production mode (`APP_ENV=production`,
`APP_DEBUG=false`), so error pages show a plain message rather than configuration values.
Unless setup warned that `.env` wasn't writable, there is nothing to change. It's still
worth a look in the file manager:
- `APP_DEBUG` must be `false` on a public server;
- `APP_URL` is the address you opened during setup (e.g. `https://crm.example.com`), and
  links in e-mails use it.

To see error details while diagnosing a problem, set `APP_DEBUG=true` temporarily and set it
back afterwards. The details also go to `storage/logs/laravel.log` either way.

### 5. Add the cron job

Mail fetching (every 5 minutes) and reminders (every minute) run from Laravel's scheduler,
as Epesi's `cron.php` did. In the panel's **Cron Jobs**, add one entry that runs every minute:

```
* * * * * /usr/local/bin/php /home/account/epesi/artisan schedule:run >> /dev/null 2>&1
```

Use your account's path to the epesi folder. The PHP command-line path differs between hosts
(`/usr/bin/php`, `/usr/local/bin/php`, `/opt/cpanel/ea-php83/root/usr/bin/php`); the cron
page or the host's help usually names it. It must be PHP 8.2 or newer, which isn't always
the default `php`. Nothing else needs a background process: notifications and reminders are
sent without a queue worker.

### 6. Afterwards

- **Sign in** at the site address with the administrator's e-mail and password. Add users
  under **Administration → Users**.
- **Updating** to a new release:
  1. Back up the database and the epesi folder.
  2. Extract the new zip over the old files. The zip has no `.env` and no files in
     `storage/`, so your settings and uploads stay.
  3. Sign in as an administrator. While changes are waiting, epesi takes you straight to
     **Administration → Database update** (`RedirectToDatabaseUpdate`), because the CRM's
     pages would fail on tables that don't exist yet. The page lists the waiting changes by
     where they come from (epesi itself, or a module). The other Administration pages show a
     bar saying the changes are waiting.
  4. Click **Update the database**, and confirm.

  Until then, other signed-in users get a short "epesi is being updated, try again in a few
  minutes" page (HTTP 503) instead of an error. The login page keeps working. With a shell,
  `php artisan epesi:update` does the same (`--pretend` only lists).
- **If setup stopped part-way,** open the address again. The wizard continues where it
  stopped, and running **Install** again is safe.

## Demo data

`Database\Seeders\DemoDataSeeder` runs when **Load demo data** is ticked in the wizard
(`epesi:install --demo`), and for development with `php artisan migrate --seed`. It creates:
- a few hand-written records that tests and examples refer to: Acme Corp (the demo's own
  company), Wayne Enterprises with Bruce Wayne, and Stark Industries with Tony Stark;
- generated records up to 100 companies, 100 contacts, 30 tasks, 30 phone calls and 30
  meetings, spread over the three users as creators and over the permission levels. Past
  activities are mostly closed and upcoming ones open;
- 25 shoutbox messages, some of them private, when the Shoutbox module is installed.

The generated records come from word lists in the seeder, not Faker: Faker is a development
package, and the release zip leaves those out. The random generator has a fixed seed, so every
installation gets the same demo.

**Removing it.** Every row the seeder creates is recorded in the `demo_records` table
(`App\Support\DemoData`). While there are any, **Administration → Demo data** lists them by
type and offers **Remove the demo data**. That deletes exactly those rows for good:
- pivot rows go through the database's cascades;
- rows pointing at them polymorphically (history, notifications, roles, notes, watching,
  reminders) are found in every table with a `<name>_type`/`<name>_id` pair;
- the demo users' sessions go too.

What remains is the administrator's own account, the contact and company from the **Your
company** setup step, and anything added since. A link from your own record to a demo contact
disappears with that contact. Then the menu entry disappears.

## Troubleshooting

**"rename(…\storage\framework\views\….tmp, ….php): Access is denied (code: 5)"** (Windows,
XAMPP). Laravel stores each compiled Blade view by writing a temporary file and renaming it
over the target. Windows refuses that rename while another process has the target open. On a
fresh install that happens routinely: no view is compiled yet, and the dashboard sends several
Livewire requests at once that each compile the same views. A virus scanner opening the new
file does the same. `App\Support\RetryingFilesystem`, bound as the application's `files` in
`AppServiceProvider::register()`, handles it:
- it retries the rename for up to a second;
- it treats the write as done when the target already holds identical content, which is what
  another request compiling the same view produces;
- it fails as before otherwise.

Zips built before this fix lack it, including `epesi-2026-09-25-4c54979.zip`. There the error goes away on reload,
because the view is compiled by then.

**"epesi can't start its setup yet"**: lists the folders the web server can't write
(`storage/`, `bootstrap/cache/`, the epesi folder for `.env`). Fix the permissions and reload.

**"epesi is missing its vendor/ folder"**: a git checkout rather than the release zip, or an
upload that didn't finish. Upload and extract the zip again.

## Roundcube webmail

Roundcube is a separate open-source webmail program (GPL-3.0), so it is never inside the
epesi zip. It is downloaded on the server, from the Roundcube project's GitHub releases
(about 7 MB), only after someone says yes to a notice explaining exactly that. Installed, it
adds a **Mailbox** page: your own mailbox inside epesi, with one-click filing of messages
into the CRM. It uses epesi's database (its tables start with `rc_`). Opening Mailbox signs
you in automatically with a one-time ticket, so there is no second login.

### Four ways to install it

All four end in the same installer (`RoundcubeSetup` → `RoundcubeInstaller`):

1. **In the setup wizard:** the **Webmail** step, "Would you like to install Roundcube?" →
   **Yes**. The Roundcube module (and Mail, which it needs) is installed with the others, and
   Roundcube is downloaded last. A failed download is a warning, and setup still finishes.
2. **Later, from Administration → Modules:** a **Download and install Roundcube** button,
   shown to administrators until Roundcube is installed. It turns on the module first, so
   this is the way in after a "No" during setup.
3. **On the Mailbox page:** the same button in place of the mail client, while the module is
   on but Roundcube hasn't been downloaded (for example, after a failed download). Only an
   administrator sees it; everyone else is told to ask their administrator.
4. **On the command line:** `php artisan roundcube:install` (or `epesi:install --roundcube`
   during a scripted install). This is also how Roundcube is upgraded.

### What the installer does

1. Downloads the Roundcube release pinned in `config/epesi-roundcube.php` (currently 1.7.4)
   and verifies its SHA-256 checksum. It refuses on a mismatch.
2. Unpacks it into `storage/roundcube/`, outside the web root.
3. Links `public/roundcube` → `storage/roundcube/public_html`, and links epesi's own
   Roundcube plugins (single sign-on, filing to the CRM) into Roundcube's `plugins/`.
4. Writes Roundcube's configuration from epesi's `.env` (database, key).
5. Creates the `rc_*` tables with Roundcube's own `bin/initdb.sh`, run by the PHP
   command-line binary. It finds that binary itself, even under Apache's mod_php.

Downloading, unpacking and creating the tables takes a minute or two. Keep the page open.

### On shared hosting

- **Download blocked** (no outbound HTTPS, or a firewall): the button reports the error.
  Some hosts allow outbound connections only on request.
- **Symlinks not allowed:** step 3 fails. Ask the host to allow `FollowSymLinks` (most do).
- **`proc_open` disabled:** step 5 fails, because the tables can't be created. Ask the host,
  or use a plan with shell access and run `php artisan roundcube:install`.
- **Web server:** Apache with epesi's `.htaccess` serves `public/roundcube/` without extra
  configuration. On nginx, `public/roundcube/` needs `index index.php` and a PHP location that
  splits path info, as Roundcube's own documentation describes.
- Each user's Roundcube connects to their IMAP/SMTP mailbox as set under **Settings → Mail
  accounts**.

Installing again is safe: it replaces the earlier download and upgrades the tables.

## Publishing: SourceForge and Softaculous

The original epesi (1.x) is already listed on SourceForge, and Softaculous lists it among its
ERP applications. So epesi 2.0 is a new major version of an existing listing, not a new
application. One consequence applies everywhere: **1.x installations can't be upgraded in
place.** 2.0 is a rewrite with its own database. Its data comes over with
`php artisan import:legacy` from the old database, into a fresh 2.0 installation. Release
notes and listings must say so, and an installer's upgrade button must not offer 1.x → 2.0.

### SourceForge: ready

SourceForge only hosts downloads, and the zip is exactly what someone downloads, unpacks and
sets up in the browser. Publish 2.0 as a new release in the existing epesi project. What it
needs is in place:
- **`LICENSE`** at the top of the zip: MIT with epesi's copyright line, and a note that the
  packages in `vendor/` keep their own licenses and that Roundcube (GPL-3.0) is downloaded
  separately;
- **version numbers:** `epesi-2.0.zip` from the commit tagged `v2.0`, see
  [Version numbers](#version-numbers);
- **a checksum:** `epesi-2.0.zip.sha256`, written by `epesi:package`. Upload it next to the
  zip.

Still to write for each release: short release notes. They cover what changed, a reminder to
run **Administration → Database update** after updating a 2.x installation, and for 1.x users
the move with `import:legacy`.

### Softaculous (and Installatron): not yet

The following is our understanding of how such installers work in general, not a check against
Softaculous's current rules for adding an application.

Softaculous and similar hosting-panel installers don't run a web wizard. They unpack the
files, create the database, and run their own install script without asking the user
anything. They expect the application to work at the folder's own address
(`example.com/crm/`), and they upgrade it without anyone clicking. What epesi still lacks:

- ~~Working without `/public` in the address~~: done. Unpacked into `public_html/crm`, epesi
  answers at `…/crm/` (see option B above).
- **A fully automatic install.** `php artisan epesi:install` with all its options (database,
  administrator, profile, mail, `--demo`, `--roundcube`) already installs without questions
  from a shell. An installer that can't use a shell needs either:
  - a PHP entry point it can call with the same parameters, protected by the setup code; or
  - a ready-made database dump with placeholders for the administrator, plus a `.env`
    template with placeholders for the database settings.

  Either way it must also skip the setup code and the `/setup` wizard, and mark the system
  installed.
- **Automatic upgrades.** After unpacking new files, the installer must bring the database up
  to date. `php artisan epesi:update` fits, provided the installer can run it; otherwise it
  needs the same kind of callable entry point.
- **Cron.** The installer should create the `schedule:run` cron job (step 5 of the shared
  hosting setup). Installers of this kind can usually set up cron jobs for an application.
- **Requirements.** PHP 8.2+ and the extensions from the server check, declared in the
  installer's metadata.
- **Listing.** epesi is already in Softaculous's ERP category, so this means asking
  Softaculous to update the existing entry to 2.0, not applying as a new application. Send
  the package, the new requirements (PHP 8.2+), and a stable download URL per version (the
  SourceForge release). Point out that 1.x installations can't upgrade in place.

Done so far: the `LICENSE` file, version numbers and serving without `/public`. Next: the
non-interactive install and upgrade entry points, then asking Softaculous to update the
listing.

## Files

| File | Role |
|---|---|
| `app/Console/Commands/PackageRelease.php` | `epesi:package` |
| `.htaccess` (root) | rewrites into `public/` when unpacked into a web root, keeping everything else unreachable |
| `app/Support/Setup/BasePath.php` | lines the script path up with the address for that (and for `LARAVEL_BASE_PATH`) |
| `VERSION`, `app/Support/Version.php` | the version; `LICENSE` the license |
| `public/index.php` | missing-`vendor/` message, `FirstBoot`, optional `LARAVEL_BASE_PATH` |
| `app/Support/Setup/FirstBoot.php` | writes `.env` and `APP_KEY` on the first request |
| `app/Support/Setup/SetupCode.php` | the one-time setup code |
| `app/Services/Setup/Requirements.php` | the server check |
| `app/Services/Setup/DatabaseSetup.php` | connect, save to `.env`, migrate |
| `app/Filament/Setup/Pages/InstallWizard.php` | `/setup/install`, both halves |
| `app/Filament/Setup/Pages/FinishSetup.php` | `/setup/finish`, the module pages |
| `app/Services/Setup/Installer.php`, `ModulePlan.php`, `ModuleLoader.php` | the install itself |
| `app/Services/Setup/RoundcubeSetup.php` | Roundcube's module part and notice |
| `modules/Epesi/Roundcube/src/Services/RoundcubeInstaller.php` | download, links, config, tables |
| `app/Console/Commands/EpesiInstall.php` | the same setup with a shell |
| `config/setup.php` | profiles, setup code path, `SETUP_TOKEN`, marker path |
| `app/Services/Setup/SystemUpdate.php` | pending migrations (core and enabled modules) and running them |
| `app/Filament/Administration/Pages/DatabaseUpdate.php` | Administration → Database update |
| `app/Filament/Support/UpdateNotice.php` | the "database changes are waiting" bar on Administration pages |
| `app/Http/Middleware/RedirectToDatabaseUpdate.php` | sends administrators to the update, shows everyone else `resources/views/epesi/updating.blade.php` |
| `app/Console/Commands/EpesiUpdate.php` | `epesi:update` |
| `database/seeders/DemoDataSeeder.php` | the demo data |
| `app/Support/DemoData.php`, `app/Filament/Administration/Pages/DemoDataPage.php` | remembering and removing it (Administration → Demo data) |

## Known gaps

- **Publishing.** Nothing an installer like Softaculous can drive yet (non-interactive
  install and upgrade). See [Publishing](#publishing-sourceforge-and-softaculous).
- **Package size.** The warning catches cloned packages, but the command doesn't convert them
  itself. The loop in [Keep vendor/ to release contents](#keep-vendor-to-release-contents-21-mb-not-66-mb)
  is manual.
