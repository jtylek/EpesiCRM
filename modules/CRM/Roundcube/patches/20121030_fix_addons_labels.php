<?php

Utils_RecordBrowserCommon::delete_addon('contact', 'CRM/Roundcube', 'addon');
Utils_RecordBrowserCommon::delete_addon('company', 'CRM/Roundcube', 'addon');
Utils_RecordBrowserCommon::delete_addon('contact', 'CRM/Roundcube', 'mail_addresses_addon');
Utils_RecordBrowserCommon::delete_addon('company', 'CRM/Roundcube', 'mail_addresses_addon');

Utils_RecordBrowserCommon::new_addon('contact', 'CRM/Roundcube', 'addon', _M('E-mails'));
Utils_RecordBrowserCommon::new_addon('company', 'CRM/Roundcube', 'addon', _M('E-mails'));
Utils_RecordBrowserCommon::new_addon('contact', 'CRM/Roundcube', 'mail_addresses_addon', _M('E-mail addresses'));
Utils_RecordBrowserCommon::new_addon('company', 'CRM/Roundcube', 'mail_addresses_addon', _M('E-mail addresses'));

?>
