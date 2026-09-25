<?php

use App\Support\Version;

return [

    /*
     * Version this core app reports to a module's "epesi_core" manifest
     * constraint — the Laravel-side equivalent of Epesi's
     * CompatibilityCheck::system_check() floor. From the VERSION file, the
     * one place a release sets it (see App\Support\Version).
     */
    'core_version' => Version::current(),

    /*
     * Where installed modules live: one <Vendor>/<Name>/ directory each.
     */
    'path' => base_path('modules'),

    /*
     * Recovery switch. A module whose service provider throws takes the whole
     * application down with it, artisan included — MODULES_LOAD=false boots
     * with every module skipped so `module:disable` can be run.
     */
    'load' => (bool) env('MODULES_LOAD', true),

    /*
     * Load every module found under `path` from its module.json, ignoring the
     * `modules` table and its cache. For the test suite only (set in
     * phpunit.xml): tests boot against an empty in-memory database.
     */
    'from_manifests' => (bool) env('MODULES_FROM_MANIFESTS', false),

    /*
     * PSR-4 prefixes registered on every request whatever the `modules` table
     * says, for modules the core app is itself built on (the RecordBrowser
     * engine: core resources extend its base pages and core models use its
     * traits). ModuleRegistry returns an empty list when the database is
     * unreachable or mid-migrate, and without this the class-not-found that
     * follows would be a blank page instead of whatever error was actually
     * happening. Their service providers and Filament plugins still come from
     * the registry like everyone else's — this is autoloading only.
     */
    'core_namespaces' => [
        'Epesi\\Modules\\RecordBrowser\\' => 'Epesi/RecordBrowser',

        // The CRM recordsets. They are modules, but the core app still names
        // their models directly (User::contact(), the legacy importers, the
        // Calendar page) and AppServiceProvider touches them during register(),
        // before the registry has been consulted — so their PSR-4 prefixes
        // cannot wait for the `modules` table either.
        'Epesi\\Modules\\CRM\\Contacts\\' => 'Epesi/CRM/Contacts',
        'Epesi\\Modules\\CRM\\Companies\\' => 'Epesi/CRM/Companies',
        'Epesi\\Modules\\CRM\\Tasks\\' => 'Epesi/CRM/Tasks',
        'Epesi\\Modules\\CRM\\Meetings\\' => 'Epesi/CRM/Meetings',
        'Epesi\\Modules\\CRM\\PhoneCalls\\' => 'Epesi/CRM/PhoneCalls',
    ],

    /*
     * Zips are extracted here first and only moved into place once every
     * validation has passed, so a rejected archive never touches modules/.
     */
    'staging_path' => storage_path('app/private/modules-staging'),

    /*
     * Gates the Admin GUI's upload-and-install action only: an uploaded module
     * runs with the application's own privileges, so that path stays closed
     * unless an operator opens it. `php artisan module:install` is unaffected —
     * anyone who can run it can already write to modules/ directly — and so is
     * enabling/disabling something already installed.
     */
    'install_enabled' => (bool) env('MODULES_INSTALL_ENABLED', false),

    'zip' => [
        'max_size' => 32 * 1024 * 1024,
        'max_uncompressed_size' => 128 * 1024 * 1024,
        'max_entries' => 3000,

        'allowed_extensions' => [
            'php', 'json', 'md', 'txt', 'yml', 'yaml', 'xml', 'csv',
            'css', 'js', 'map',
            'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico',
            'woff', 'woff2', 'ttf', 'otf', 'eot',
        ],

        /*
         * Extensionless files allowed by exact basename — everything else
         * without an extension is rejected.
         */
        'allowed_filenames' => ['LICENSE', 'CHANGELOG', 'README'],
    ],

];
