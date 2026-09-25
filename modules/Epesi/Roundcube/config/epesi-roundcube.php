<?php

return [
    /*
     * The Roundcube release `php artisan roundcube:install` downloads — the
     * "complete" tarball, which bundles Roundcube's own dependencies. The
     * checksum is the one published on https://roundcube.net/download/;
     * upgrading means changing both and running the command again.
     */
    'release' => [
        'version' => '1.7.4',
        'sha256' => '2c6c878f0093f1bf7fb6086781d2dd9269d652c016b86939c157c5f1729139a2',
        'url' => 'https://github.com/roundcube/roundcubemail/releases/download/{version}/roundcubemail-{version}-complete.tar.gz',
    ],

    /*
     * Where Roundcube is installed, and where its public_html is linked into
     * the web root. Roundcube runs as its own application, served straight by
     * the web server, so the link must be a real directory under public/.
     */
    'path' => storage_path('roundcube'),

    'public_path' => public_path('roundcube'),

    /* Roundcube's tables share the app's database, as they did in Epesi. */
    'table_prefix' => 'rc_',

    /* Seconds a Mailbox page's login ticket stays valid. */
    'ticket_ttl' => 60,
];
