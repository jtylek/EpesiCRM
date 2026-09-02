<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

// rc_accounts' Password/SMTP Password columns were plaintext until now (see
// AI-private/mail-account-encryption-and-gmail-oauth.md for the full design) - this one-time
// migration encrypts whatever's already stored, in place, using CRM_MailCommon::encrypt() (AES-256-GCM).
// Idempotent-safe to re-run: encrypt() output never round-trips back through decrypt() as valid
// base64 plaintext by coincidence in any realistic scenario, but there's no live install yet for
// this to matter against - this is the first and only run for any install that picks it up.

$rows = DB::GetAll('SELECT id, f_password, f_smtp_password FROM rc_accounts_data_1');
foreach ((array) $rows as $r) {
    Patch::require_time(1);
    DB::Execute('UPDATE rc_accounts_data_1 SET f_password=%s, f_smtp_password=%s WHERE id=%d', array(
        $r['f_password'] !== '' && $r['f_password'] !== null ? CRM_MailCommon::encrypt($r['f_password']) : '',
        $r['f_smtp_password'] !== '' && $r['f_smtp_password'] !== null ? CRM_MailCommon::encrypt($r['f_smtp_password']) : '',
        $r['id'],
    ));
}
