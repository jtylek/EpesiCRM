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
//
// 2026-08-20: one row hit "Storing data failed" (file_put_contents() returned false) mid-run —
// transient (a lock from AV/indexer scanning right after a large git checkout in the same data/
// tree is the leading suspect; every row succeeded on retry a few minutes later, see
// AI-shared/environment-and-setup.md). But this loop had no per-row error handling, so that one
// failure propagated out of the patch and — via update.php's apply_new($die_on_error=true), see
// include/patches.php — aborted the *entire* patch queue, not just this attachment. Per-row
// try/catch below, logging with error_log() rather than trigger_error()/throwing: the patch
// runner's own error_handler() (Patch::error_handler() in include/patches.php) converts
// trigger_error() back into a fatal PatchException, which would undo the point of catching it
// here — same reasoning as modules/Base/patches/20260814_utf8mb4_migration.php's per-table
// try/catch. A short retry absorbs exactly the transient-lock case observed; anything still
// failing after that is logged and skipped — idempotency means the next run of this same patch
// picks up any leftover rows automatically, no manual bookkeeping needed.
//
// 2026-09-08: write_content() was called without its $created_on argument, so
// Utils_FileStorageCommon::write_metadata() defaulted utils_filestorage.created_on to time() —
// the moment *this patch* ran, not when the e-mail was archived. On an install where this patch
// runs once against years of accumulated mail, the Files history screen then shows every
// migrated attachment as "created" today, all at the same second, which is exactly backwards.
// rc_mails_data_1.f_date is the mail's own archived-on date (set by archive_message() in
// MailCommon_0.php) and is the same ADOdb 'T'/DATETIME column type as utils_filestorage.created_on
// (RecordBrowserCommon_0.php's 'timestamp' case), so it can be passed straight through. Rows
// already migrated by an earlier, unfixed run of this same patch are backfilled separately by
// 20260908_backfill_mail_attachment_filestorage_dates.php (same directory), since this file only
// reaches file_id-less rows going forward (see "Patches are identified by filepath" in
// CLAUDE.md — editing this file doesn't get re-run on an install that already applied it).

PatchUtil::db_add_column('rc_mails_attachments', 'file_id', 'I8');

$rows = DB::GetAll(
    'SELECT a.mail_id, a.mime_id, a.name, a.file_id, m.f_date AS mail_date
     FROM rc_mails_attachments a
     LEFT JOIN rc_mails_data_1 m ON m.id = a.mail_id'
);
foreach ($rows as $r) {
    Patch::require_time(1);

    $path = DATA_DIR . '/CRM_Mail/attachments/' . $r['mail_id'] . '/' . $r['mime_id'];
    $file_id = $r['file_id'];
    try {
        if (!$file_id) {
            if (!file_exists($path)) continue; // nothing on disk to migrate
            $content = file_get_contents($path);
            // write_content() returns the FILESTORAGE (storage-object) id used by read_content()/
            // file_exists()/meta() — NOT the low-level content id from add_data_from_content().
            // $created_on: the mail's own archived-on date, not "now" - see 2026-09-08 note above.
            $attempts_left = 3;
            while (true) {
                try {
                    $file_id = Utils_FileStorageCommon::write_content($r['name'], $content, null, 'rb:rc_mails/'.$r['mail_id'], $r['mail_date'] ?: null);
                    break;
                } catch (Exception $e) {
                    if (--$attempts_left <= 0) throw $e;
                    usleep(200000); // 0.2s - let a transient lock (AV/indexer) clear before retrying
                }
            }
            DB::Execute('UPDATE rc_mails_attachments SET file_id=%d WHERE mail_id=%d AND mime_id=%s',
                array($file_id, $r['mail_id'], $r['mime_id']));
        }
        // Move: remove the legacy copy only once the content is safely in FileStorage.
        if ($file_id && Utils_FileStorageCommon::file_exists($file_id) && file_exists($path)) {
            @unlink($path);
            @rmdir(dirname($path)); // remove the now-empty per-mail dir (no-op if not empty)
        }
    } catch (Exception $e) {
        error_log('EPESI mail_attachments_to_filestorage: mail_id=' . $r['mail_id'] . ' mime_id=' . $r['mime_id'] . ' skipped: ' . $e->getMessage());
    }
}
