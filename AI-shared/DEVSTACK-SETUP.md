# Running Epesi Laravel in PHP Windows DevStack

These instructions are for a source checkout at:

`C:\PHPDevStack\www\epesi`

DevStack uses PHP 8.5, Apache on port `8080`, and MariaDB on port `3307` by
default.

## 1. Start DevStack services

Open the PHP Windows DevStack control panel and start Apache and MariaDB. The
project cannot connect to the database while MariaDB is stopped.

Open the project directory in PowerShell:

```powershell
Set-Location C:\PHPDevStack\www\epesi
$env:Path = "C:\PHPDevStack\runtime\php;$env:Path"
```

The `Path` change is important when another PHP installation, such as XAMPP,
is also installed. It makes the commands use DevStack's PHP 8.5.

Check it with:

```powershell
php -v
```

## 2. Install Composer dependencies

The lock file works on PHP 8.3 to 8.5:

```powershell
composer install --prefer-dist --optimize-autoloader
```

**Never regenerate the lock for your own PHP version.** `composer.json` pins
`config.platform.php` to `8.3.0`, epesi's minimum (`bootstrap/requirements.php`),
so `composer update` resolves for 8.3 even under PHP 8.5. Without that pin, an
update run on DevStack's 8.5 picked Symfony 8.1 (PHP 8.4+ only) and openspout
4.32 into the committed lock on 2026-10-14, and every install on an older PHP,
production included, failed. The range is 8.3 to 8.5 because no openspout
release (Filament's export dependency) supports both 8.2 and 8.5: 4.28 stops at
8.4, 4.31 starts at 8.3. Do not use `--ignore-platform-reqs` as the normal
solution.

Composer may print deprecation notices under PHP 8.5 because the installed
Composer 2.7 package itself contains older PHP signatures. Those notices are
from Composer and do not mean that dependency installation failed. The
`ekmungai/eloquent-ifrs` package can also emit PHP 8.5 deprecation notices at
runtime; see `AI-shared/PHP-8.5-compatibility.md`.

## 3. Configure the environment

Create the environment file if Composer did not create it:

```powershell
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
```

The first browser visit can generate the application key, but it is also safe
to run:

```powershell
php artisan key:generate
```

For the DevStack MariaDB instance, set these values in `.env` before running
database migrations or the Epesi setup wizard:

```dotenv
APP_URL=http://127.0.0.1:8080/epesi
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=epesi
DB_USERNAME=root
DB_PASSWORD=your-DevStack-root-password
```

Create the `epesi` database in phpMyAdmin, or with the MariaDB client, before
continuing. The DevStack root password is available from the control panel's
**Set root password** action or with `scripts/show-database-password.ps1`.

## 4. Build the frontend

Install Node dependencies and create the production Vite bundle:

```powershell
npm install
npm run build
```

The successful build creates `public/build/manifest.json`. Laravel and
Filament use that manifest; if it is missing, the setup page will show a
friendly explanation instead of exposing a Laravel HTTP 500.

## 5. Open Epesi

Visit:

`http://127.0.0.1:8080/epesi/setup/install`

The setup wizard checks the PHP extensions, writable directories, and database
connection. Complete the wizard and create the first administrator account.

## 6. Troubleshooting

- Missing `vendor/autoload.php`: run Composer from the Epesi directory and
  ensure the command uses `C:\PHPDevStack\runtime\php\php.exe`.
- Missing `public/build/manifest.json`: run `npm install` followed by
  `npm run build`.
- Database connection refused: start MariaDB and verify that `.env` uses port
  `3307` (or the port configured in DevStack's **Port settings**).
- A blank or generic 500 page: inspect
  `C:\PHPDevStack\logs\php-error.log` and
  `storage\logs\laravel.log`, then reload after fixing the first reported
  error.
- For writable-directory failures, ensure `storage/` and `bootstrap/cache/`
  are writable by Apache. The optional `modules/` and `public/` directories
  may also need to be writable for module installation and uploads.

## 7. Updating the checkout

Keep local `.env` and `storage/` data. After pulling source changes, repeat:

```powershell
composer install --prefer-dist --optimize-autoloader
npm install
npm run build
```

Restart Apache after changing PHP settings or environment configuration. MariaDB
does not need to restart for PHP or frontend changes.
