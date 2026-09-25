# Setup wizard

How a new installation is set up: the port of Epesi's `setup.php` and `modules/FirstRun`.
This file covers what happens at each step, which code does it, and how a module adds its own
setup page.

## Epesi's version, and how it maps here

Epesi set itself up in two stages:

1. **`setup.php`**: Language → License → Database → Compatibility check. It wrote
   `data/config.php` and installed the base modules.
2. **FirstRun**: Setup type (a profile from `distros.ini`) → Administrator → Mail settings →
   Confirm. It installed the profile's modules, then showed a form for each installed module
   that defined `post_install()`. CRM/Contacts asked for your company and your name;
   Base/RegionalSettings asked for the default date/time formats, timezone and location.

| Epesi | Here |
|---|---|
| `setup.php` (database, compatibility) | the first half of `/setup/install`, or `php artisan epesi:install` |
| FirstRun wizard | `/setup/install` |
| `distros.ini` | `config/setup.php` → `profiles` |
| A module's `post_install()` / `post_install_process()` | a `SetupStep` registered with `SetupSteps::register()`, shown at `/setup/finish` |
| Language and License pages | not ported |

Both stages run in the browser. Laravel can't serve a page without `.env` and an application
key, so `public/index.php` writes those itself on the first request (see
[Step 0](#step-0-the-first-request--firstboot)). The server stage can also be run as a command,
`php artisan epesi:install`, on servers with a shell.

For the package itself and a user-level walkthrough on shared hosting, see
[Epesi-Laravel-distro.md](Epesi-Laravel-distro.md).

## Installing from a release zip

`php artisan epesi:package` (`app/Console/Commands/PackageRelease.php`) builds the zip a user
unpacks and sets up without Composer, Node or a shell: the files git tracks (minus `AI-shared/`, `tests/`
and `phpunit.xml`) plus `vendor/` and `public/build/`. Build it from a checkout prepared with
`composer install --no-dev --optimize-autoloader` and `npm run build`; it warns when `vendor/`
still has the development packages. It is written to `storage/app/private/releases/`
(`--out=`). Its files sit at the top of the zip, so they unpack straight into the folder it is
extracted into; `--folder=epesi` puts them in a folder of that name instead.

The user then:

1. creates a folder in the web root and unpacks the zip into it (XAMPP: `C:\xampp\htdocs\epesi`);
2. creates an empty database (XAMPP: phpMyAdmin → Databases);
3. opens the site (`http://localhost/epesi/`), reads the setup code from
   `storage/app/setup-code.txt`, and follows the wizard.

When the zip is unpacked straight into a web root, the root `.htaccess` rewrites every
request into `public/` internally, so epesi answers at the folder's own address, and
`.env`, `storage/` and `vendor/` can't be downloaded: they become paths in `public/` that
don't exist (see [Epesi-Laravel-distro.md](Epesi-Laravel-distro.md), option B). A server whose document root is `public/` never reads it. It needs mod_rewrite,
which XAMPP enables by default.

## Step 0: the first request — `FirstBoot`

`app/Support/Setup/FirstBoot.php`, called from `public/index.php` after the autoloader and
before Laravel boots, so it's plain PHP. While `.env` is missing or has no `APP_KEY`:

- it checks that `.env` (or the project folder), `storage/` and `bootstrap/cache/` are
  writable, and that `.env.example` exists. If not, it prints a plain HTML page listing what to
  fix, since Laravel's error page couldn't render either;
- copies `.env.example` to `.env` when there is none;
- sets a new `APP_KEY`, plus `SESSION_DRIVER=file` and `CACHE_STORE=file`, because
  `.env.example` keeps both in the database, which doesn't exist yet. They stay on `file`
  afterwards, which is fine for a single server; change them in `.env` if you want;
- in a `.env` it created itself, sets `APP_ENV=production` and `APP_DEBUG=false`, so a
  fresh copy on a public server doesn't show configuration values on its error pages. The
  installer writes the same two values when setup finishes (`Installer::configureEnvironment()`),
  unless `epesi:install --dev` was used.

Once `.env` has a key, this is one file read per request. `public/index.php` also prints a
plain message when `vendor/` is missing (a git checkout before `composer install`).

## Step 1: the server — in the browser, or `php artisan epesi:install`

**In the browser.** While the database has no tables (`SetupState::databaseReady()` is false),
`/setup/install` shows setup.php's pages instead of FirstRun's:

1. **Setup code.** See [Security](#security).
2. **Server check.** The requirements table below, plus whether `.env` is writable. **Next**
   refuses to continue while a required row fails.
3. **Database.** Type (only the ones this PHP has a PDO driver for), then server, port,
   database name, user and password; for SQLite, the file, which is created if missing. It
   defaults to MySQL on `127.0.0.1:3306`, database `epesi`, user `root` (XAMPP's defaults).
   **Create the tables** connects first and shows the database's own error if that fails,
   with nothing saved. Then it saves the `DB_*` keys to `.env`, sets `APP_URL` to the address
   the page was opened at if it is still `.env.example`'s `http://localhost`, and runs the
   migrations. Then the page reloads as FirstRun. If the database already holds an installed
   epesi, it goes straight to the main panel.

`App\Services\Setup\Requirements` and `App\Services\Setup\DatabaseSetup` do the work for both
this page and the command.

**On the command line.** `app/Console/Commands/EpesiInstall.php`. Run it once on the server,
from the project directory, after `composer install`.

1. **Requirements.** Prints a table: PHP 8.2+, the extensions `ctype curl fileinfo intl
   mbstring openssl pdo tokenizer xml zip`, and whether `storage/` and `bootstrap/cache/` are
   writable. Stops if anything fails, unless given `--force`. A non-writable `modules/` is
   reported but doesn't fail the check: it only matters for installing modules from the web page.
2. **`.env`.** Copies `.env.example` to `.env` if there is none, and generates `APP_KEY` if it
   is empty. `--app-url=` sets `APP_URL`.
3. **Database.** Asks for the connection type (MySQL, MariaDB, PostgreSQL, SQLite), then host,
   port, database, user and password, prefilled with the current settings. Each can be given
   as an option instead (`--db-connection`, `--db-host`, `--db-port`, `--db-database`,
   `--db-username`, `--db-password`). For SQLite, `--db-database` is the file path, and the
   file is created if missing. The connection is tested **before** anything is written; the
   `DB_*` keys are saved to `.env` only once it works.
4. **Tables.** Runs `php artisan migrate --force` (the core migrations; module tables come in
   step 2 below).
5. **Then**, one of:
   - if the database already has users: says epesi is already set up and stops;
   - if `--admin-email` was given: installs completely without the browser (see
     [Scripted installs](#scripted-installs));
   - otherwise: prints the URL to open, `<APP_URL>/setup`, and the setup code.

## Step 2: the wizard — `/setup/install`

`app/Filament/Setup/Pages/InstallWizard.php`, in a small Filament panel of its own
(`app/Providers/Filament/SetupPanelProvider.php`) with no login, sidebar or module plugins.

**Getting there.** On a system that isn't installed, every page of every panel (main,
administration, user settings) redirects to the wizard, including the login pages
(`App\Http\Middleware\RedirectToSetup`, prioritised ahead of authentication in
`bootstrap/app.php`). `/setup` itself redirects to `/setup/install` (`routes/web.php`).
If the database isn't configured or its tables don't exist yet, the page shows Step 1's
server and database pages first.

The pages:

0. **Setup code** — only if this browser session hasn't already given it on the database
   pages. See [Security](#security).
1. **Setup type** — a choice of profile from `config/setup.php`:
   - **CRM installation** (default): Regional Settings, Attachments, Watchdog, Follow-up,
     Reminders, Mail, Shoutbox.
   - **Core only**: Regional Settings.

   Both include every core module. There is also a **Load demo data** switch: sample
   companies, contacts, calls, tasks and meetings, plus two demo users (`manager@example.com`,
   `employee@example.com`, password `password`). Meant for evaluation only.
2. **Administrator** — name, e-mail (the sign-in address), password (at least 8 characters)
   and confirmation. With demo data on, the two demo addresses are refused.
3. **Mail** — how epesi sends its own e-mail (password resets, reminders):
   - *This server's mail system* → `MAIL_MAILER=sendmail` (Epesi's "local php.ini settings");
   - *An SMTP server* → host, port, security (STARTTLS / SSL/TLS / none), login, password;
   - *Don't send e-mail yet* → `MAIL_MAILER=log`.
4. **Webmail** — only when `modules/Epesi/Roundcube` is present. Explains in plain words what
   Roundcube is, that it is a separate project under the GPL-3.0 (linked), and that it is
   downloaded from roundcube.net (about 7 MB) only if chosen (`RoundcubeSetup::notice()`).
   The answer is required, with no default: **Yes, download and install Roundcube** or **No,
   not now**. Nothing GPL is downloaded without a yes.
5. **Install** — lists exactly the modules that will be installed, in order, and an
   **Install** button. With Roundcube chosen, it says the download follows and to keep the
   page open.

On **Install**, `App\Services\Setup\Installer` does, in order:

1. takes a lock (`Cache::lock('epesi-setup')`), so two browsers can't install at once, and
   re-checks that the system isn't installed;
2. creates the roles (`RoleSeeder`: super_admin, manager, employee);
3. registers the modules through the ordinary `ModuleInstaller::registerExisting()`, which runs
   each module's migrations. The list is every `"core": true` module plus the profile's,
   with anything they `require` pulled in, sorted so a module always comes after what it
   requires (`App\Services\Setup\ModulePlan`). A module listed in a profile but missing from
   `modules/` is skipped, as FirstRun did. The modules are then loaded into the running
   process (their PSR-4 namespaces and service providers, `Installer::load()`), because this
   request started with none installed. Without that, saving the administrator's demo
   records failed on the CRM morph aliases that the modules' providers register. The test
   suite can't catch this, since it boots every module from its manifest;
4. creates the administrator with the `super_admin` role and, if asked, runs
   `DemoDataSeeder` for them (one transaction);
5. writes the mail settings to `.env` (`MAIL_MAILER`, and for SMTP `MAIL_SCHEME` (`smtps` for
   SSL/TLS), `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`), plus
   `MAIL_FROM_ADDRESS` = the administrator's e-mail and `MAIL_FROM_NAME` = `APP_NAME`. If
   `.env` isn't writable, setup still finishes and shows a warning naming the keys to set by
   hand;
6. writes the **marker file** (`config('setup.marker_path')`, by default
   `storage/app/epesi-installed.json`) recording the time, the profile, the installed modules
   and `finish_pending: true`, and deletes the setup code file;
7. if Roundcube was chosen: the `Epesi/Roundcube` module was installed with the others in
   step 3, and now `App\Services\Setup\RoundcubeSetup::download()` downloads and sets up
   Roundcube itself (see [Epesi-Laravel-Roundcube.md](Epesi-Laravel-Roundcube.md)). This comes
   last because it needs the internet and takes a while, and setup is complete without it. If
   it fails, the failure is a warning, not an error: everything else stays installed, and the
   warning says to use **Download and install Roundcube** on the Mailbox page.

The administrator is then signed in and sent to `/setup/finish`.

## Step 3: module pages — `/setup/finish`

`app/Filament/Setup/Pages/FinishSetup.php`. FirstRun's `post_install` loop: one wizard page
per `SetupStep` registered by an installed module, in their registered order, then **Finish**.
Everything is saved in one transaction. **Skip for now** closes setup without saving; the same
details can be entered later in the normal screens.

Only a signed-in super_admin can open it. While it's pending, the administrator is redirected
here from every panel page; other users aren't affected. When it is finished or skipped, the
marker gets `finish_pending: false` and the administrator lands on the dashboard.

The steps shipped today:

| Key | Module | Page | What it saves |
|---|---|---|---|
| `contacts-your-company` | CRM/Contacts (order 10) | **Your company**: company name, short name, your first and last name (prefilled from the administrator's name, split at the last space), then address, country, state, phone, fax, web address | a public Company, and the administrator's own Contact in it with their e-mail and `user_id`, which is what links the login to a person (`User::contact()`) |
| `regional-settings-defaults` | RegionalSettings (order 20) | **Regional settings**: language, timezone, date format, time format, country, state | the system-wide defaults (the `epesi_regional_settings` row with no user), and the same values as the administrator's own settings. New users start from the defaults (`RegionalSetting::defaults()` / `current()`) |

These run on the request *after* installation on purpose: a module's service provider (which
registers its step) only loads once the module is registered, the same reason FirstRun ran
`post_install` after installing.

## After setup

`/setup` and `/setup/install` redirect to the dashboard, or to `/setup/finish` while that is
pending. Running the installer again fails with "This system is already set up".

**When is a system "installed"?** `App\Support\Setup\SetupState::isInstalled()`: the marker
file exists, **or** the `users` table has any row. The fallback keeps installations that
predate the wizard, or were built with `php artisan migrate --seed` or `import:legacy`, from
ever seeing it. The answer is cached per process once true.

## Scripted installs

For hosting panels and automation, `epesi:install` can do everything without a browser:

```bash
php artisan epesi:install --no-interaction \
    --db-connection=mysql --db-host=127.0.0.1 --db-database=epesi \
    --db-username=epesi --db-password=secret \
    --app-url=https://crm.example.com \
    --admin-name="Jan Kowalski" --admin-email=jan@example.com --admin-password=... \
    --profile=crm --mail=sendmail
```

`--admin-name` and `--admin-password` are required with `--admin-email`. `--demo` loads the
demo data; `--roundcube` also downloads Roundcube; `--mail` takes `sendmail`, `smtp` or `log` (SMTP details aren't options — set them
in `.env`). The module pages are still pending afterwards: the administrator sees them on
first sign-in.

## Security

Until setup is done, whoever opens `/setup` could make themselves the administrator, as in
Epesi. With the database chosen in the browser, they could also point epesi at a database of
their own. So the wizard's first page asks for a **one-time setup code**
(`App\Support\Setup\SetupCode`):

- It is generated the first time the wizard (or `epesi:install`) needs it: 12 characters
  without look-alikes (no 0/O, 1/I/L), as `XXXX-XXXX-XXXX`, in `storage/app/setup-code.txt`
  (`config('setup.code_path')`). Only someone who can read the server's files can get it.
  `epesi:install` prints it as well.
- It is compared with `hash_equals`, ignoring case, spaces and dashes.
- Once given, the session remembers it (an HMAC of the code under `APP_KEY`), so it is asked
  once: on the database pages or on FirstRun's, whichever comes first. Both submit actions
  (`connectDatabase`, `install`) check it again, so skipping the step in a crafted request
  doesn't get past it.
- The installer deletes the file once the system is installed.

`SETUP_TOKEN` in `.env`, if set, replaces the generated code with one you chose. It is
compared exactly as written. It has no effect once installed.

The administrator's password is stored hashed like any other user's. SMTP credentials go
into `.env` as plain text, like every other secret there.

## When something fails

- **A requirement check fails**: install the missing PHP extension (XAMPP: remove the `;` in
  front of `extension=intl` / `extension=zip` in `php.ini` and restart Apache) or fix the
  folder permission. Then reload the wizard, or run `epesi:install` again.
- **The first request shows "epesi can't start its setup yet"**: `FirstBoot` couldn't write
  `.env` or found `storage/` or `bootstrap/cache/` read-only. The page lists which.
- **The database connection fails**: the wizard, or `epesi:install`, shows the database's own
  error and writes nothing to `.env`.
- **Creating the tables fails part-way**: the wizard shows the error; the connection is
  already saved, so the database page comes back filled in. Fix the cause (on MySQL, drop any
  half-created table) and click **Create the tables** again.
- **Installation fails part-way** (a module migration, a lost connection): the wizard shows the
  error and stays on the page. Clicking **Install** again is safe: modules already registered
  are skipped, and nothing counts as installed until the administrator exists, which happens
  last. On MySQL, a module migration that failed after creating a table leaves that table
  behind; drop it before retrying (see the MySQL notes in `CLAUDE.md`).
- **Setup went through but mail settings weren't saved**: a warning after installation lists
  the `MAIL_*` keys to put in `.env` by hand.
- **Starting over on a test server**: `php artisan migrate:fresh --force`, delete the marker
  file, then open `/setup` again. To go back to the database pages too, drop all tables (or
  point `.env` at an empty database). A new setup code is written when one is next needed.

## Adding a setup page to a module

Implement `App\Support\Setup\SetupStep`:

```php
use App\Models\User;
use App\Support\Setup\SetupStep;
use Filament\Forms\Components\TextInput;

class InvoicingSetupStep implements SetupStep
{
    public function label(): string
    {
        return 'Invoicing';
    }

    public function description(): ?string
    {
        return 'Numbering for new invoices.';
    }

    public function schema(): array
    {
        return [
            TextInput::make('prefix')->required()->maxLength(8),
        ];
    }

    public function defaults(): array
    {
        return ['prefix' => 'INV-'];
    }

    public function handle(array $data, User $admin): void
    {
        // $data holds this step's fields only: ['prefix' => '...'].
    }
}
```

and register it from the module's service provider `boot()`:

```php
SetupSteps::register('invoicing-numbering', InvoicingSetupStep::class, order: 30);
```

- **The key** becomes the step's form state path, so it can't contain a dot (a dot would nest
  it; `register()` throws). Use dashes.
- **Order**: lower comes first (Contacts uses 10, RegionalSettings 20); equal orders keep
  registration order.
- **`schema()`** can use any Filament form components, including shared ones: the Regional
  Settings step reuses `RegionalSettings::formComponents()` from the user settings page, and
  "Your company" reuses `AddressFields::country()` / `zone()`.
- **`handle()`** runs inside the page's transaction, as the administrator (`auth()->user()`),
  on the request after installation, so the module's own models, policies and morph aliases
  are loaded.
- The step only appears on installations that have the module installed. A module installed
  *later* from Administration → Modules doesn't show its page; like FirstRun, these pages run
  once, at setup.

## Adding or changing a setup type

Edit `config/setup.php`:

```php
'profiles' => [
    'crm' => [
        'label' => 'CRM installation',
        'description' => '...',
        'modules' => ['Epesi/RegionalSettings', 'Epesi/Attachments', /* ... */],
    ],
],
'default_profile' => 'crm',
```

Modules are listed by **path** under `modules/` (`Epesi/Mail`), not by id. Core modules never
need listing. Requirements are resolved automatically, so listing a module that requires
another is enough.

## Files

| File | What it does |
|---|---|
| `public/index.php`, `app/Support/Setup/FirstBoot.php` | `.env` and the application key on the first request |
| `.htaccess` | rewrites into `public/` when unpacked into a web root, keeping everything else unreachable |
| `app/Console/Commands/EpesiInstall.php` | the server stage on the command line, and scripted installs |
| `app/Console/Commands/PackageRelease.php` | `epesi:package`, the release zip |
| `config/setup.php` | profiles, default profile, setup code path, `SETUP_TOKEN`, marker path |
| `app/Support/Setup/SetupCode.php` | the one-time setup code |
| `app/Services/Setup/Requirements.php`, `DatabaseSetup.php` | the server check and the database connection, for the wizard and the command |
| `app/Services/Setup/RoundcubeSetup.php`, `app/Filament/Actions/InstallRoundcubeAction.php` | the Webmail question: module + download, and the button that offers it later |
| `app/Services/Setup/ModuleLoader.php` | loads modules installed during the current request |
| `app/Providers/Filament/SetupPanelProvider.php` | the `/setup` panel and its routes |
| `app/Filament/Setup/Pages/InstallWizard.php` | `/setup/install` |
| `app/Filament/Setup/Pages/FinishSetup.php` | `/setup/finish` |
| `app/Services/Setup/Installer.php`, `InstallOptions.php` | performs the installation |
| `app/Services/Setup/ModulePlan.php` | which modules, in what order |
| `app/Support/Setup/SetupState.php` | installed? finish pending? the marker file |
| `app/Support/Setup/SetupStep.php`, `SetupSteps.php` | the module page contract and registry |
| `app/Support/Setup/EnvFile.php` | writes keys into `.env`, keeping the rest of the file |
| `app/Http/Middleware/RedirectToSetup.php` | sends requests to the wizard until done |
| `database/seeders/DemoDataSeeder.php` | the optional demo data |
| `modules/Epesi/CRM/Contacts/src/Setup/YourCompanyStep.php` | "Your company" |
| `modules/Epesi/RegionalSettings/src/Setup/RegionalSettingsDefaultsStep.php` | "Regional settings" |
| `tests/Feature/SetupTest.php` | the tests |

## Testing

`tests/Feature/SetupTest.php` covers the redirects on an empty database, a full install from
the wizard (modules, administrator, `.env` contents), validation, the setup code and
`SETUP_TOKEN`, the database pages on a database without tables, `DatabaseSetup`, the `.env`
that `FirstBoot` writes, the finish pages (company, contact, regional defaults), skipping, the
module order, and the headless command.

Two things to keep when writing more tests:

- Point `setup.marker_path`, `setup.code_path` and the environment path (`$this->app->useEnvironmentPath()`) at a
  scratch directory, or the installer writes the checkout's real `.env` and marker file.
- `epesi:install` switches the default database connection. Give it a connection of its own,
  and set `database.default` back before the test ends. Otherwise `RefreshDatabase` rolls back
  the wrong connection and the in-memory database keeps an open transaction into the next
  test class.
