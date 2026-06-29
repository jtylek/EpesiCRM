<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

// Archived e-mail attachments now live in the central Utils_FileStorage (deduplicated,
// content-addressed) instead of raw files under data/CRM_Mail/attachments/. This patch MOVES
// them (Jasiek's decision, 2026-06-29):
//   1) adds rc_mails_attachments.file_id (existing installs; idempotent),
//   2) stores each legacy on-disk file into Utils_FileStorage (backfills file_id),
//   3) only AFTER the content is confirmed in FileStorage, DELETES the legacy file (and the
//      now-empty per-mail directory).
// Verify-before-delete makes it safe; idempotent — re-running migrates any leftover rows and
// deletes any straggler legacy files. Iterates all rows (covers rows already migrated-but-kept).

PatchUtil::db_add_column('rc_mails_attachments', 'file_id', 'I8');

$rows = DB::GetAll('SELECT mail_id, mime_id, name, file_id FROM rc_mails_attachments');
foreach ($rows as $r) {
    $path = DATA_DIR . '/CRM_Mail/attachments/' . $r['mail_id'] . '/' . $r['mime_id'];
    $file_id = $r['file_id'];
    if (!$file_id) {
        if (!file_exists($path)) continue; // nothing on disk to migrate
        $content = file_get_contents($path);
        $file_id = Utils_FileStorageCommon::add_data_from_content($content, $r['name']);
        DB::Execute('UPDATE rc_mails_attachments SET file_id=%d WHERE mail_id=%d AND mime_id=%s',
            array($file_id, $r['mail_id'], $r['mime_id']));
    }
    // Move: remove the legacy copy only once the content is safely in FileStorage.
    if ($file_id && Utils_FileStorageCommon::file_exists($file_id) && file_exists($path)) {
        @unlink($path);
        @rmdir(dirname($path)); // remove the now-empty per-mail dir (no-op if not empty)
    }
}
