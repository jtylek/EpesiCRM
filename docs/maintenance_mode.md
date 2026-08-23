Maintenance mode
================

There are some situation, where we want to prevent access to our installation,
like update process or any other situation, when users should not change
or view anything.

Users should not be able to change anything between data backup and update
process, because this data may be lost in case of the backup restore due to
errors in the update process.


How it works
------------

Maintenance mode is set for all clients except the one, that has turned it on.
It doesn't mean user. Client means browser that has been used to turn it on.
Client is recognized by a cookie set in the browser.

You can login as super admin, turn it on, then logout and login as any other
user.

When maintenance mode is turned on, then EPESI title will contain text
`(Maintenance mode)`. Of course only for the allowed client.


Turn on/off
-----------

You can manage maintenance mode in the */admin* tools.

If you can't access your installation or someone has left maintenance mode
turned on, but it's not required anymore, then you should delete file
`EPESI_INSTALLATION/data/maintenance_mode.php` on your server.


Custom message
--------------

You can change message that will be shown to users in the file
`EPESI_INSTALLATION/data/maintenance_mode.php`. Please read Technical details
for more information.


Technical details
-----------------

When maintenance mode is on, then all scripts that include *include.php*
file will be unavailable for any other clients than that one, which turned it on.

Turning on creates file `EPESI_INSTALLATION/data/maintenance_mode.php`
with special random key that is stored in the cookie and it's used to determine
allowed client.

Example file looks like this:

    <?php
    // by admin on 2014-06-26 13:08:39
    $maintenance_mode_key = 'kaq0s15tvdxfychr';
    $maintenance_mode_message = NULL;


EPESI during load will check for this special file. If it exists, it will be
loaded. If variable `$maintenance_mode_key` doesn't evaluate to false, then
maintenance mode is turned on.

If `$maintenance_mode_message` is NULL then default message will be used.
It's hardcoded in file *include/maintenance_mode.php*.
You can change it to simple string or any valid HTML like here:

    <?php
    // by admin on 2014-06-26 13:08:39
    $maintenance_mode_key = 'kaq0s15tvdxfychr';
    $maintenance_mode_message =
    <<<HTML
    <html>
     <head>
      <title>Maintenance mode</title>
     </head>
    <body>
     <h1>Maintenance Mode</h1>
     <p>Please wait</p>
     </body>
    </html>
    HTML;

Code above uses [heredoc][1] format of PHP string.

Maintenance mode can't use translations mechanism, because it's loaded earlier.

[1]: http://www.php.net/manual/en/language.types.string.php#language.types.string.syntax.heredoc
