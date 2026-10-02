# Start a clean installation

Use these steps to restart the browser installation from an empty database. This removes
application data; only do it when the database can be discarded. For the full wizard flow, see
[Setup-wizard.md](Setup-wizard.md).

## Reset

1. Back up anything that must be kept. In the configured database, remove all Epesi tables,
   including `users`, `modules`, and `migrations`, or point `.env` at a new, empty database.
   The database account needs permission to create and alter tables.
2. Remove the install marker. The default path is `storage/app/epesi-installed.json`; if
   `SETUP_MARKER_PATH` is set in `.env`, remove the file at that configured path instead.
3. Remove the module registry cache at `bootstrap/cache/epesi-modules.php`. It can still list
   modules from the database that was cleared; the setup wizard will register modules again.

From PowerShell, the default files can be removed with:

```powershell
Remove-Item storage/app/epesi-installed.json -Force -ErrorAction SilentlyContinue
Remove-Item bootstrap/cache/epesi-modules.php -Force -ErrorAction SilentlyContinue
```

## Setup code and browser session

The setup code is not required to be removed. If `storage/app/setup-code.txt` exists and a new
code is wanted, delete it; the next setup request generates another. `SETUP_CODE_PATH` can
override that path. If `SETUP_TOKEN` is set in `.env`, the wizard uses that value instead of a
generated file.

Use a private browser window or clear the site's session cookies to test the setup-code prompt
again. A session that has already verified the same configured `SETUP_TOKEN` remains verified.

## Keep

Keep `.env` and `APP_KEY` so the application retains its database connection and encrypted
session compatibility. Keep the module source directories, `vendor/`, and `public/build/` too;
the wizard runs the module migrations and does not need Composer or a release unpack to be
repeated. Do not delete `storage/` wholesale: it contains the marker and code files above, but
may also contain logs, uploads, and other application data.

After resetting, open `/setup/install` and complete the wizard. If setup failed partway through
and you need a genuinely clean retry, clear the database and these install-state files before
trying again.