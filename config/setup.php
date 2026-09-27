<?php

return [

    /*
     * Until setup is done, whoever opens /setup could make themselves the
     * administrator, so the wizard first asks for a one-time code. It is
     * generated into this file on the server (and printed by
     * `php artisan epesi:install`); the file is deleted once setup is done.
     * See App\Support\Setup\SetupCode.
     */
    'code_path' => env('SETUP_CODE_PATH', storage_path('app/setup-code.txt')),

    /*
     * When set, the wizard asks for this value instead of the generated
     * code — for a deployment that chooses its own.
     */
    'token' => env('SETUP_TOKEN'),

    /*
     * Written once setup has run; its presence is how every later request
     * knows the system is installed without asking the database.
     */
    'marker_path' => env('SETUP_MARKER_PATH', storage_path('app/epesi-installed.json')),

];
