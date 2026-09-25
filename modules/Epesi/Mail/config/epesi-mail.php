<?php

return [
    /*
     * Check the IMAP server's TLS certificate. Turn off only for a server with
     * a self-signed certificate you trust (Epesi always connected with
     * `novalidate-cert`).
     */
    'imap_validate_cert' => (bool) env('MAIL_IMAP_VALIDATE_CERT', true),

    /*
     * How often `mail:fetch` runs from the scheduler, as a cron expression.
     * Requires `php artisan schedule:run` in cron, as Epesi needed cron.php.
     */
    'fetch_schedule' => env('MAIL_FETCH_SCHEDULE', '*/5 * * * *'),

    /*
     * Appended to every message sent from the CRM, after the account's own
     * signature — Epesi's crm_mail_global_signature variable. Empty for none.
     */
    'global_signature' => env('MAIL_GLOBAL_SIGNATURE', ''),

    /*
     * The legacy Epesi install's data/ directory, for `import:legacy mail`:
     * archived attachments live there (data/Utils_FileStorage, or
     * data/CRM_Mail/attachments on an older install), and so does the key the
     * account passwords were encrypted with (data/CRM_Mail/encryption.key).
     */
    'legacy_data_dir' => env('LEGACY_DATA_DIR'),
];
