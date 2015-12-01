<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');
Utils_RecordBrowserCommon::set_display_callback('rc_mails','Thread',array('CRM_RoundcubeCommon','display_mail_thread'));
Utils_RecordBrowserCommon::set_QFfield_callback('rc_mails','Thread',array('CRM_RoundcubeCommon','QFfield_mail_thread'));
Utils_RecordBrowserCommon::new_addon('rc_mail_threads', CRM_Roundcube::module_name(), 'thread_addon', _M('E-mails'));
?>