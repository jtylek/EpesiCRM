<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

// Archived e-mail attachments now live in the central Utils_FileStorage (deduplicated,
// content-addressed) instead of raw files under data/CRM_Mail/attachments/. This patch:
//   1) adds rc_mails_attachments.file_id (existing installs; idempotent),
//   2) backfills file_id for legacy rows by copying the on-disk file into Utils_FileStorage.
// Legacy files are LEFT in place — physical cleanup of data/CRM_Mail/attachments/ is a
// separate, deliberate step (pending decision). Idempotent: content-addressing means the
// same bytes yield the same stored file, and only rows still missing file_id are processed.

PatchUtil::db_add_column('rc_mails_attachments', 'file_id', 'I8');

$rows = DB::GetAll('SELECT mail_id, mime_id, name FROM rc_mails_attachments WHERE file_id IS NULL');
foreach ($rows as $r) {
    $path = DATA_DIR . '/CRM_Mail/attachments/' . $r['mail_id'] . '/' . $r['mime_id'];
    if (!file_exists($path)) continue; // nothing on disk to migrate
    $content = file_get_contents($path);
    $file_id = Utils_FileStorageCommon::add_data_from_content($content, $r['name']);
    DB::Execute('UPDATE rc_mails_attachments SET file_id=%d WHERE mail_id=%d AND mime_id=%s',
        array($file_id, $r['mail_id'], $r['mime_id']));
}
