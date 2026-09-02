<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

// rc_accounts' Password field was declared 'required'=>true in the schema, which
// RecordBrowser_0.php's generic form-building loop turns into an unconditional
// addRule(...,'required') + placeholder="Field required" AFTER calling the field's own
// QFfield_callback - silently overriding CRM_MailCommon::QFfield_password()'s own edit-mode
// logic (blank means "keep current password", not required) every time. See
// AI-private/mail-account-encryption-and-gmail-oauth.md and MailInstall.php's own comment on
// this field for the full story. Fresh installs no longer declare 'required' on this field at
// all (MailInstall.php); this patch clears it for whatever's already stored from before.

DB::Execute('UPDATE rc_accounts_field SET required=0 WHERE field=%s', array('Password'));
