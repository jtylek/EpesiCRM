<?php

return [

    /*
     * A public demo: visitors pick an account on the login page instead of
     * typing a password, the Administration panel and the setup wizard are
     * gone, and whatever could harm the next visitor says "Unavailable in
     * demo mode". Epesi's DEMO_MODE. See App\Support\Demo.
     */
    'enabled' => (bool) env('DEMO_MODE', false),

    /*
     * The accounts the login page offers, by e-mail — Epesi's $demo_users.
     * DemoDataSeeder creates both. Only these can be chosen, and never one
     * with super_admin.
     */
    'users' => [
        'manager@example.com' => ['label' => 'Manager', 'description' => 'sees and edits every record'],
        'employee@example.com' => ['label' => 'Employee', 'description' => 'sees public records and their own'],
    ],

    /*
     * `php artisan demo:reset` puts the demo data back every day at this
     * time, from the scheduler.
     */
    'reset_at' => env('DEMO_RESET_AT', '03:00'),

    'timezone' => env('DEMO_TIMEZONE', 'Europe/Warsaw'),

    /*
     * Tables the reset saves before it empties the database and puts back
     * afterwards: the login audit is who used the demo and from where.
     */
    'keep_tables' => ['login_audits'],

    /*
     * The administrator the reset creates, with a new random password each
     * time: the demo needs one to own the demo data, and nobody signs in as it.
     */
    'admin_email' => env('DEMO_ADMIN_EMAIL', 'admin@example.com'),

    /*
     * A Google Analytics 4 measurement ID ("G-…") for the demo's pages
     * (App\Filament\Support\DemoAnalytics); the tag always loads, but only
     * tracks after the visitor accepts the cookie bar. Empty: no analytics.
     * Never used outside demo mode.
     */
    'analytics_id' => env('DEMO_ANALYTICS_ID'),

];
