<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

// Body/Attachments/Headers used to be separate addon tabs below the main e-mail record view
// (MailInstall.php's new_addon calls). The AdminLTE-dark "View e-mail" template
// (theme_adminltedark/mails.tpl) now renders Body and Attachments inline on the main record
// instead - Headers dropped outright, no replacement needed since Cc is parsed straight out of
// the raw headers for display (see MailCommon_0.php::get_cc_html()). MailInstall.php's install()
// no longer registers any of the three, but existing installs already have all three rows from
// their original install - this mirrors that removal for them.

Utils_RecordBrowserCommon::delete_addon('rc_mails', CRM_MailInstall::module_name(), 'mail_body_addon');
Utils_RecordBrowserCommon::delete_addon('rc_mails', CRM_MailInstall::module_name(), 'attachments_addon');
Utils_RecordBrowserCommon::delete_addon('rc_mails', CRM_MailInstall::module_name(), 'mail_headers_addon');
