<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

// 20260629_mail_attachments_to_filestorage.php called Utils_FileStorageCommon::write_content()
// without a $created_on argument, so every attachment it moved before its 2026-09-08 fix (see
// that file's docblock addendum) got utils_filestorage.created_on = time() - the moment the patch
// ran, not when the e-mail was archived. On an install where that patch migrates years of
// accumulated mail in one run, the Files history screen ("Created on") shows the entire archive
// as created within the same few seconds, which is backwards and confusing.
//
// This backfills those already-migrated rows with the mail's real archived-on date
// (rc_mails_data_1.f_date, set by archive_message() in CRM_MailCommon - same ADOdb 'T'/DATETIME
// column type as utils_filestorage.created_on, so no reformatting is needed).
//
// Idempotent: only rewrites rows whose stored created_on doesn't already match the mail's date, so
// a fresh install (where the parent patch already wrote the right date) or a re-run finds nothing
// left to do.

$rows = DB::GetAll(
    'SELECT fs.id AS file_id, fs.created_on AS current_created_on, m.f_date AS mail_date
     FROM rc_mails_attachments a
     JOIN utils_filestorage fs ON fs.id = a.file_id
     JOIN rc_mails_data_1 m ON m.id = a.mail_id
     WHERE a.file_id IS NOT NULL AND m.f_date IS NOT NULL'
);

foreach ($rows as $r) {
    Patch::require_time(1);
    if ($r['mail_date'] === $r['current_created_on']) {
        continue;
    }
    DB::Execute('UPDATE utils_filestorage SET created_on=%T WHERE id=%d', array($r['mail_date'], $r['file_id']));
}
