<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// 'References' shipped as a sized text field - VARCHAR(16384), set here and by
// CRM/Roundcube/patches/20130617_add_thread_view4.php. That fits a legacy
// utf8mb3 column at 3 bytes per character (16384 * 3 = 49152), but not utf8mb4
// at 4: 16384 * 4 = 65536, one byte over MySQL's 65535 row limit. A fresh
// install against a utf8mb4 database - the default for years now - therefore
// failed to create rc_mails_data_1 at all, taking the whole CRM_Mail install
// down with it. Existing installs never hit this: their column predates the
// utf8mb4 switch and quietly kept the 3-byte charset even after the table
// around it was converted.
//
// Shortening the column is not a fix - the other VARCHARs in this recordset
// (f_to 4096, f_subject 256, f_from 128, f_message_id 128) already push the row
// over the limit, so even VARCHAR(16383) fails with ERROR 1118 "Row size too
// large". MailInstall.php now declares the field as 'long text' (TEXT, i.e.
// ADOdb 'X'), which is what Body and Headers Data already use.
//
// This patch converges installs created before that change, so old and new
// installs share one schema and an existing install can later be converted to
// utf8mb4 without tripping over this column. Converting VARCHAR(16384) to TEXT
// preserves the stored values (TEXT holds 65535 bytes, the column held 49152).

PatchUtil::db_alter_column('rc_mails_data_1', 'f_references', 'X');

// Keep the RecordBrowser field metadata in step with the column, or this
// install keeps describing the field as text/16384 while a fresh one calls it
// 'long text'. Existing 'long text' fields in this recordset store an empty
// param, not NULL - match that.
DB::Execute('UPDATE rc_mails_field SET type=%s, param=%s WHERE field=%s',
    array('long text', '', 'References'));

Cache::clear();
