# Installing Epesi

Epesi runs on PHP 8.2+ with MySQL/MariaDB. The release zip already contains everything
(no Composer, Node or command line needed).

1. Unpack the zip into a folder in your web root, e.g. `C:\xampp\htdocs\epesi`
   (or upload and unpack it on your hosting account).
2. Create an empty database, e.g. in phpMyAdmin.
3. Open the site in a browser (`http://localhost/epesi/`).
4. The setup wizard asks for a one-time setup code: open `storage/app/setup-code.txt`
   in the epesi folder and copy it in.
5. Follow the wizard: it checks the server (PHP version, extensions, writable folders),
   asks for the database, creates the tables, then asks whether to download the Roundcube
   webmail, whether to load demo data, and for the administrator account.

If the wizard reports a problem, fix what it names and reload the page. It continues where
it stopped.

## "Access forbidden" (403) when opening the folder

Epesi serves itself through the `.htaccess` file in its folder, which rewrites requests into
`public/`. Check that:

- `.htaccess` was extracted (it is a hidden file; some tools skip it, so look for it next
  to `artisan`).
- Apache has `mod_rewrite` loaded (`LoadModule rewrite_module ...` not commented out in
  `httpd.conf`).
- The web root allows overrides: `AllowOverride All` for the `htdocs` directory.

On Debian/Ubuntu both are off by default: run `sudo a2enmod rewrite`, set `AllowOverride All`
for the web root's `<Directory>` and restart Apache. The web server's user (`www-data`,
`apache`) also needs to read the folder (and every parent folder, e.g. `/home/you/`) and write
to `storage/` and `bootstrap/cache/`; on SELinux systems they need the `httpd_sys_content_t`
and `httpd_sys_rw_content_t` labels. The Apache error log names the actual cause.

Restart Apache after changing its configuration. As a test, `http://localhost/epesi/public/`
should show the setup wizard even when the rewrite doesn't work. Best of all is a
(sub)domain or virtual host whose document root is epesi's `public/` folder.

## nginx

nginx ignores `.htaccess`, so epesi's `public/` folder must be the site's document root
(on aaPanel: Website → Settings → Site directory → Running directory `/public`). Then add the
rewrite rule that sends everything which isn't a real file to `index.php`:

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

`config/nginx.conf.example` in the zip is a complete server block. Without that rule the setup page
loads but stays empty: the Livewire script is served by a route, not from disk, and returns 404.
Open the site at its root (`http://crm.example.com/`), with no `/public` in the address.

Also allow PHP's `exec`, `proc_open`, `putenv` and `symlink` (on aaPanel they are in the PHP
version's "Disabled functions" list): the Roundcube download needs them.

## Scheduled tasks (optional but recommended)

Have the server's cron (or Windows Task Scheduler) run this every minute:

    php cron.php

## Updating

Unpack a newer release over the existing folder (keep `.env` and `storage/`), then open
Administration → Database update.

## More

Project home: <https://epesicrm.com> — license: MIT, see LICENSE.
