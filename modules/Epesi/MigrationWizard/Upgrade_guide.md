# Upgrading Epesi 1.9.1 to Epesi 3

This guide is for the administrator of a site that runs Epesi 1.9.1. Epesi 3 is installed
over the old files, in the same folder, with the same database and the same address. Your records
(companies, contacts, tasks, meetings, phone calls, notes, mail) are moved across by a migration
tool that ships with Epesi 3.

Read all of it before you start. Steps 1 to 4 are done while the old site still exists, and the
backup in step 2 is the only complete way back.

**What you need:** access to your hosting control panel (to change the PHP version, upload files and
make backups). The migration can be run from the browser (step 6.2) or, as an alternative, from the
command line (step 6.3: SSH or a "Terminal" in your control panel). The browser page was written for
administrators who have no command line, but it has so far been tested only by automated tests, not in a
real browser on a real host: if something on it does not work, the command line does the same work.

## 1. Check that the old site works on PHP 7.4

Open the site in the browser and log in as an administrator. It must work normally on PHP 7.4, and
Epesi must not ask you to update anything. If it does not work now, fix that first (or restore it):
the migration does not repair a broken site.

**Uninstall modules you no longer use, if you can.** While the old site still works, open
*Administration → Modules* and uninstall the modules (Premium ones especially) that nobody uses. Each
uninstalled module removes its tables, so the database is smaller, the scan report is shorter, and
fewer modules need to be migrated or reported as "Will not be migrated". Do this **before** the
backup in step 2, so the backup is of the cleaned-up site. Only uninstall what you are sure you do
not need: uninstalling deletes that module's data.

## 2. Make a full backup (mandatory)

> **Do not skip this step.** If the migration fails, the way back is restoring this backup.
> There is no other complete way back. Nothing you do later replaces it.

1. Make a full backup of **both**:
   - the **database**, and
   - the **whole Epesi folder**, every file and subfolder, hidden files like `.htaccess` included.

   With **Softaculous**, make a *full* backup (files and database) and check that it contains
   both; this also works for large sites. Without it: export the database with phpMyAdmin and download
   the folder with your control panel's file manager (cPanel, Plesk, DirectAdmin). If these time out
   on a large site, use SSH, or your hosting provider's backup tool.
2. Keep the backup somewhere safe, **outside** the Epesi folder, and check that the files open and are
   not empty. **Keep your backups outside the Epesi folder:** a backup or a copy of the site left inside it
   makes the final cleanup refuse (it would have to archive it), and exposes it to the web.

The migration tool makes **no backup of its own** and does not ask for the old site's maintenance
mode: once Epesi 3 is unpacked over the old folder (step 5) the old site no longer runs, so nobody can
change its data. This backup is the only one; in step 6 you confirm it by typing `backed up`.

## 3. Switch PHP to version 8.3 or newer

In your control panel, set the site's PHP version to 8.3 or newer. From now on the old Epesi
no longer runs, which is expected: the old site stays unavailable until the migration is finished.

## 4. Delete the old `vendor` folder

Delete the folder `vendor` inside the Epesi folder.

**Do this now, before step 5.** If you delete it after uploading Epesi 3, you remove Epesi 3's own
files and it will not start. Deleting it is safe: your backup from step 2 holds it.

If you forgot this step, nothing is lost and nothing is damaged: when you open the site, the migration
wizard's first screen notices the old packages still inside `vendor` and stops there, before scanning
anything, naming exactly which folders to remove. Delete `vendor`, upload and extract the Epesi 3 zip
again (step 5), then reload the page to try again.

## 5. Upload Epesi 3 over the old folder

Upload the Epesi 3 zip and extract it **into the same folder**, replacing files when asked (index.php,
.htaccess and cron.php are replaced, which is expected). Do not delete `data/`, it holds your files
and the old settings. Do not unpack into a new folder.

If you see a page titled "Epesi: server check" that says Epesi cannot run on this server, the site's
PHP version is too old or a PHP extension is missing. The page names the problem: set PHP to 8.3 or newer
(step 3) and reload.

## 6. Run the migration

Open the site's address in the browser: Epesi 3 recognises the old installation and shows the
migration page. Then work through these parts in order.

### 6.1 In the browser: check

The page "epesi migration wizard" shows the detected Epesi version when it opens. Press **Scan my epesi installation** (a spinner says "Scanning your data..."). The scan only reads; nothing changes, and there is no setup code to
enter. It shows the legacy version, whether it is approved, and one list of the modules that hold data (Epesi, Premium and Custom): for each, its recordsets with the number of active records and its plain tables with their rows, and whether it will be migrated or kept untouched. Modules without data are not listed, and neither are system modules (settings, search index, Watchdog, Dashboard, ACL and similar) that are never imported or need no migration (see "What you and your users must recreate"). A last line says how many database tables belong to no listed module. If the scan fails, a short message with a **Scan again** link appears. The complete report is saved on the server in `storage/app/migration-wizard/assessment.json`. Use the link at the bottom of the page to continue to the migration.

### 6.2 In the browser: run it

Open the **migration progress page** (the assessment page links to it; its address ends in
`/setup/migrate/progress`). It needs no setup code.

The page shows where the migration stands (which step it is on, which confirmations are given, what
was imported), and it works through the same steps as the command line, with the same checks. In
order:

1. **Confirm your step 2 backup:** type `backed up` and press the button. **Nothing is changed before
   you do.** The tool does not make a backup; this is the one you made yourself.
2. After the migration is verified, the wizard removes every old table except those of Premium and
   Custom modules, and the junk files in `data/` (cache, logs, temporary files, sessions, saved error
   reports). Your backup holds them. Premium and Custom tables and the other folders in `data/` are kept.
   Discarding data by name is possible only on the command line.
3. Press **Continue**. The page works in short steps of about twenty seconds and asks for the next
   step by itself every few seconds; you can close the page and come back later, and press Continue
   again: nothing is lost, it goes on where it was. A long import simply takes many steps.
4. When it asks, choose the **legacy administrator** who will own the installation and set a **new
   password** (old passwords are not carried over), then Continue.
5. When it says the migration is verified, you are done, or you can run the **optional cleanup**
   (see 6.3, point 5: the same switch `MIGRATION_WIZARD_CLEANUP=true` in `.env`; the page has a box
   to run the cleanup with Continue).

While a step runs, the migration is locked: if the command line, or another browser tab, is already
running it, the page says so and tries again by itself. A step that was cut off (the browser closed, the
server stopped it) leaves no lock behind, so you do not have to delete anything.

If the page says **"Migration stopped. Fix the problem and run it again to continue"**, fix the
problem it names (no disk space, a timeout, a lost connection...) and press Continue. If it shows
**"Migration failed and cannot continue"**, it offers nothing but that message: copy the reason and
go to "Restoring after a failed migration" below.

A few steps cannot be cut into short pieces and may take longer than twenty seconds in one go: creating the
Epesi 3 tables, importing one list of records (for example all companies), the check at the end, and the
cleanup. On a host that cuts web requests after a minute or so, use the command line for those, or ask
your hosting provider to raise the limit; the work is safe to repeat.

### 6.3 On the command line (alternative to 6.2)

Run these in the Epesi folder, in order, instead of 6.2. `php` must be version 8.3 or newer (check with `php -v`).

1. Confirm your step 2 backup. The tool refuses to change anything until you do:

       php artisan epesi:migrate-legacy --confirm-backup

   Type `backed up` when asked (or add `--yes` if you really did make it).
2. Say what happens to the data Epesi 3 does not migrate. To keep it all (recommended):

       php artisan epesi:migrate-legacy --core-only --preserve=@unknown

3. Start (and later continue) the migration:

       php artisan epesi:migrate-legacy --resume

   It stops when it needs an administrator and lists the legacy administrators with their numbers.
   Pick one, and run it again with that number. You are asked for a **new password** (old passwords
   are not carried over):

       php artisan epesi:migrate-legacy --resume --admin-id=NUMBER

4. When it finishes verifying, look at what is left and at the result:

       php artisan epesi:migrate-legacy --status

5. **Optional: remove the old files and tables.** Cleanup is off until you switch it on by adding the
   line `MIGRATION_WIZARD_CLEANUP=true` to the file `.env` in the Epesi folder. It needs only the
   `backed up` confirmation every migration already has. Then run:

       php artisan epesi:migrate-legacy --resume --cleanup

   Cleanup removes the old Epesi files, the old `vendor` files and the migrated old modules. It
   keeps your hosting's own files, everything it did not migrate (see below), everything Epesi
   3 needs, and any backup file (.zip, .tar, .gz, .sql, .bak and the like) it finds outside `data/`:
   those stay exactly where they are and are listed. A list of everything it removed is written to `storage/app/migration-wizard/` in the Epesi folder. A record table is
   removed only when the number of records migrated matches the number in the old table exactly;
   tables whose record counts do not match are kept and listed.

### If it stops

If the tool says **"Migration stopped. Fix the problem and run it again to continue"**, something
you can fix happened: no disk space, a timeout, a lost connection, another run still active, or a
choice it needs from you. Fix it and run `--resume` again; nothing is lost and it continues where it
was.

If it says **"Migration failed and cannot continue"**, do not try again: copy the reason that is
shown (`php artisan epesi:migrate-legacy --status` shows it again) and go to "Restoring after a
failed migration" below.

## What stays after the migration

The migration moves your records and files, and leaves the rest where it is, for you or a developer
to deal with later. Nothing that was not migrated is deleted:

- **`data/` folders** that were not migrated, the old `data/config.php` included.
- **`modules/` folders** of old modules that were not migrated, including Premium and Custom modules.
  They are not loaded by Epesi 3. A rewritten module takes care of its own old files when it is
  installed.
- **Database tables** that were not migrated, kept under their old names with the prefix `legacy_`.
- **Files that no record uses** inside the old mail and file folders. `--status` shows how many
  and how large; the full list is in `storage/app/migration-wizard/leftover-files.txt`.

`--status` lists, for each module that was not migrated, its code folder, its `legacy_` tables and its
data folders. Two things to know when you look at the old tables: SQL written for the old site does
not run as written, because every table name now starts with `legacy_`; and each migrated record has a
`legacy_id` that holds the id of the row it came from.

## Record history is migrated

The history of a record (who changed which field, and when) is migrated together with the record. The
importers for companies, contacts, tasks, meetings and phone calls read the old edit history of each
record and rebuild it as the History tab of the new record. Two limits: the old history stores only the
value from before each change, so the importer rebuilds the later values from the record itself, and
changes to the links between records are not replayed into the History (the links themselves are
migrated).

## What you and your users must recreate

These are **not** migrated, and the migration page does not list them:

- **Watchdog** (the subscriptions that notify a user about changes to a record): users subscribe
  again in Epesi 3.
- **Dashboard** (each user's dashboard tabs and applets): users set up their dashboard again.
- **ACL** (the old access rules and permissions): the administrator sets up users, roles and
  permissions in Epesi 3.

These were kept by the old webmail and are also **not** migrated:

- mail **signatures**,
- **sender names**,
- **address books** in the webmail.

The mail accounts themselves are migrated; users add the webmail items again in Epesi 3.

## The old web server

The old site's address may keep answering from an old web server or an old PHP version and show PHP
errors after the files were replaced. That does not stop the migration and needs no action: Epesi 3
is served with the new PHP version.

## Restoring after a failed migration

If the migration failed and cannot continue, or the result is not what you want, restore the backup
from step 2.

1. **Copy the failure reason** shown by the tool (`php artisan epesi:migrate-legacy --status`)
   before you restore: restoring wipes the site's logs and state. Keep it for support.
2. **With Softaculous:** restore the full backup from Softaculous. Tick **Restore Directory**,
   **Restore Database** and **Delete existing files/folders** (the wording can differ by host
   and version). Without the delete option the restore keeps newer files, and Epesi 3's files stay
   behind.
3. **Without Softaculous (manual restore):**
   1. **Empty the Epesi folder first.** Restoring over the top leaves Epesi 3 files behind. Do **not**
      delete the files and folders that belong to your hosting account rather than to Epesi:
      - `.well-known/`: renewal of the site's SSL certificate,
      - `cgi-bin/`: scripts of your hosting account,
      - `.user.ini` and `php.ini`: your PHP settings,
      - `.htpasswd` and `.htpasswds/`: password protection,
      - `error_log`: your hosting's error log,
      - `.ftpquota`, `.cpanel/` and `.private/`: control panel data,
      - `softperms.txt`, `softsql.sql`, `softver.txt`: files of the Softaculous installer,
      - `stats/`: your hosting's statistics,
      - `.htaccess.cpanel.bak`: a backup made by the control panel.

      If in doubt about a file you do not recognise, **move it aside rather than deleting it.**
   2. **Empty the database first** (in phpMyAdmin select all tables and drop them), then import the
      database from your backup. Importing over the top does not remove Epesi 3's tables.
   3. Copy the folder from your backup back into the Epesi folder.
4. If the restore does not work for some reason (for example the site does not run as Epesi 1.9.1
   afterwards, or the restore stops with an error), the cause can be tables left over from the
   migration or a table that already exists. In that case drop **all** tables in the database
   (phpMyAdmin: select all tables, drop), empty the folder except the hosting files above, and
   restore again.
5. **Switch PHP back to 7.4.** Then open the site: it should work as before.

If you did not use any Premium or Custom module, the old Epesi 1.9.1 files can also be downloaded
again from <https://sourceforge.net/projects/epesi/files/v1.9.1/>. That download has no `data/` folder
and no database, so those must still come from your backup.
