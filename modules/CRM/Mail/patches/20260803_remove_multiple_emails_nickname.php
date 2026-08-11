<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

// rc_multiple_emails' "Nickname" field (CRM_Contacts/CRM_Companies' "E-mail addresses" tab)
// was never actually used for anything beyond itself - unused per request. Mirrors
// RecordBrowser_0.php::delete_page()'s own field-removal steps (shift position/processing_order,
// drop the _field row) plus the physical column, which delete_page() leaves in place for
// admin-added fields but this one - a fixed, install-defined field - can safely drop outright.

DB::StartTrans();
$p = DB::GetOne('SELECT position FROM rc_multiple_emails_field WHERE field=%s', array('Nickname'));
$po = DB::GetOne('SELECT processing_order FROM rc_multiple_emails_field WHERE field=%s', array('Nickname'));
DB::Execute('UPDATE rc_multiple_emails_field SET position = position-1 WHERE position > %d', array($p));
DB::Execute('UPDATE rc_multiple_emails_field SET processing_order = processing_order-1 WHERE processing_order > %d', array($po));
DB::Execute('DELETE FROM rc_multiple_emails_field WHERE field=%s', array('Nickname'));
DB::CompleteTrans();

DB::Execute('DELETE FROM rc_multiple_emails_callback WHERE field=%s', array('Nickname'));

PatchUtil::db_drop_column('rc_multiple_emails_data_1', 'f_nickname');
