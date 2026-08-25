Update manual
=============
version: 2014-06-26


How to perform update
---------------------

 1. Open your EPESI and login as super administrator
 2. Open */admin* tools (http[s]://EPESI_address/admin/)
 3. Turn on *maintenance mode* (see `maintenance_mode.md` file)
 4. Backup your data - data dir, custom modifications, database
 5. Update EPESI files - extract release package
 6. Refresh EPESI - you should see an update page
 7. Click *Update!* and do not close browser's window

Maintenance mode will be turned on during the update process (step 7).
You can omit 1-3 steps if you're sure that any user won't access installation.


How my browser drives update?
-----------------------------

Almost every server has a time limit for the script execution. Sometimes PHP
itself limits this time, but sometimes HTTP server forces PHP to stop after
some time. It's a bad thing that may happen during update, because it may
break EPESI.

Due to this limits we have developed mechanism to perform update in chunks.
Every chunk of update should not last more than about 30 seconds.

You browser reloads page to proceed to the next chunk. Javascript is required,
but it's not a problem since you use EPESI, that requires javascript also.


What if I'll close the browser during update?
---------------------------------------------

It depends on the HTTP server. If your browser will terminate http connection,
because you've closed window/tab or forced stop, then server may continue
script execution or force it to stop.

If your server will stop the update process and it will occur in the *bad*
moment, then your installation may be broken in some way.

If your server will run the script until the end then it should be fine.

### How will I know is it broken or not?

Try to rerun update process. If it'll finish without errors, then probably it's
fine.


What if I'll lost internet connection?
--------------------------------------

In this case your browser won't terminate connection so HTTP server should
finish execution of the update chunk.

Just run update again when you'll have internet connection and it'll run
from the place where it's stopped.

For more information read **What if I'll close the browser during update?**.


What if error occurs?
---------------------

If you'll get error instead of progress window - don't panic (unless you
don't have a backup).

At first please read error message. If you know how to solve this issue,
then fix it. File permissions, some code mistakes or incompatibilities - almost
all issues can be fixed during the update.

Restart update process.

If you've got error that is not recoverable, then you have to restore backup
before running update process again.

Please read detailed description below to get better understanding how patches
work.


Update process detailed description
-----------------------------------

Every request will check for the possible update. EPESI stores it's version
number in the database, and in the file `include/version.php`. If version
in the file is greater than installed (in database), then EPESI needs update
and you'll be redirected to the `update.php` script.

Only super admin can perform update process.

You can perform update by browser or by executing `php -f update.php` from
the console. Remember to run script as a proper user.

Update process detailed steps:

1. Turn on maintenance mode with access for current client
2. (only MySQL) Make sure that all tables are InnoDB
3. Apply patches (details below)
4. Update theme (.tpl) files
5. Update translations
6. Recreate modules loading priority
7. Store new version number in the database
8. Turn off maintenance mode

### Applying patches

Patches are applied one by one in the specific order - always the same.

When running from console, then patches are run without any time limits.
If update is driven by browser, then patches execution is limited to 30 seconds.
After this time execution will be stopped.

During execution patches can store their progress into `/data/patch_<md5>`
directory. This progress is loaded, when patch is ran again. If patch has been
applied, then it's state directory is removed and patch won't run next time,
because it's been marked as applied.

If you want to delete patch state, then read it's md5
from `/data/patches_log.txt` file and remove specific directory according to the
md5 sum. However only one patch should run at once until it'll be finished,
so there should not be more than one `/data/patch_<md5>` directory.
