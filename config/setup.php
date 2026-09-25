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

    /*
     * The "Setup type" choices — Epesi's modules/FirstRun/distros.ini. Core
     * modules ("core": true in module.json: the record engine, CommonData and
     * the five CRM recordsets) are always installed; these are what comes on
     * top. Paths are relative to modules/, and a module missing from disk is
     * skipped, as FirstRun did. Required modules are pulled in automatically.
     */
    'profiles' => [
        'crm' => [
            'label' => 'CRM installation',
            'description' => 'Contacts, companies, tasks, meetings, phone calls and the calendar, plus notes and files on every record, watching records, follow-ups, reminders, e-mail archiving and the shoutbox.',
            'modules' => [
                'Epesi/RegionalSettings',
                'Epesi/Attachments',
                'Epesi/Watchdog',
                'Epesi/Followup',
                'Epesi/Reminders',
                'Epesi/Mail',
                'Epesi/Shoutbox',
            ],
        ],
        'core' => [
            'label' => 'Core only',
            'description' => 'Contacts, companies, tasks, meetings, phone calls and the calendar. More modules can be enabled later under Administration → Modules.',
            'modules' => [
                'Epesi/RegionalSettings',
            ],
        ],
    ],

    'default_profile' => 'crm',

];
