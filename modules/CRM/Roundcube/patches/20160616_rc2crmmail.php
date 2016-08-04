<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

DB::Execute('INSERT INTO modules(name,version,state) VALUES(%s,0,0)',array('CRM_Mail'));
ModuleManager::create_data_dir('CRM_Mail');
@file_put_contents(DATA_DIR.'/CRM_Mail/.htaccess',"deny from all\n");
rename(DATA_DIR.'/CRM_Roundcube/attachments',DATA_DIR.'/CRM_Mail/attachments');

Base_ThemeCommon::install_default_theme('CRM_Mail');

Variable::set('crm_mail_global_signature',Variable::get('crm_roundcube_global_signature',false));
Variable::delete('crm_roundcube_global_signature',false);

Utils_CommonDataCommon::new_array('CRM/Mail/Security', array('tls'=>_M('TLS'),'ssl'=>_M('SSL')),true,true);
DB::Execute('UPDATE rc_accounts_field SET param=%s WHERE param=%s',array('CRM/Mail/Security','CRM/Roundcube/Security'));

DB::Execute('UPDATE recordbrowser_processing_methods SET func=%s WHERE func=%s',array('CRM_MailCommon::processing_related','CRM_RoundcubeCommon::processing_related'));
DB::Execute('UPDATE recordbrowser_processing_methods SET func=%s WHERE func=%s',array('CRM_MailCommon::submit_account','CRM_RoundcubeCommon::submit_account'));
DB::Execute('UPDATE recordbrowser_processing_methods SET func=%s WHERE func=%s',array('CRM_MailCommon::submit_mail','CRM_RoundcubeCommon::submit_mail'));

DB::Execute('UPDATE rc_related_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::display_recordset','CRM_RoundcubeCommon::display_recordset'));
DB::Execute('UPDATE rc_related_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::QFfield_recordset','CRM_RoundcubeCommon::QFfield_recordset'));

DB::Execute('UPDATE rc_accounts_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::display_epesi_user','CRM_RoundcubeCommon::display_epesi_user'));
DB::Execute('UPDATE rc_accounts_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::QFfield_epesi_user','CRM_RoundcubeCommon::QFfield_epesi_user'));
DB::Execute('UPDATE rc_accounts_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::QFfield_account_name','CRM_RoundcubeCommon::QFfield_account_name'));
DB::Execute('UPDATE rc_accounts_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::display_password','CRM_RoundcubeCommon::display_password'));
DB::Execute('UPDATE rc_accounts_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::QFfield_password','CRM_RoundcubeCommon::QFfield_password'));
DB::Execute('UPDATE rc_accounts_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::QFfield_security','CRM_RoundcubeCommon::QFfield_security'));
DB::Execute('UPDATE rc_accounts_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::QFfield_smtp_auth','CRM_RoundcubeCommon::QFfield_smtp_auth'));
DB::Execute('UPDATE rc_accounts_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::QFfield_smtp_login','CRM_RoundcubeCommon::QFfield_smtp_login'));
DB::Execute('UPDATE rc_accounts_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::QFfield_smtp_password','CRM_RoundcubeCommon::QFfield_smtp_password'));
DB::Execute('UPDATE rc_accounts_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::QFfield_smtp_security','CRM_RoundcubeCommon::QFfield_smtp_security'));
DB::Execute('UPDATE rc_accounts_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::QFfield_default_account','CRM_RoundcubeCommon::QFfield_default_account'));

DB::Execute('UPDATE rc_multiple_emails_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::QFfield_nickname','CRM_RoundcubeCommon::QFfield_nickname'));

DB::Execute('UPDATE rc_mail_threads_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::display_subject','CRM_RoundcubeCommon::display_subject'));
DB::Execute('UPDATE rc_mail_threads_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::display_thread_count','CRM_RoundcubeCommon::display_thread_count'));
DB::Execute('UPDATE rc_mail_threads_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::QFfield_thread_count','CRM_RoundcubeCommon::QFfield_thread_count'));
DB::Execute('UPDATE rc_mail_threads_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::display_thread_attachments','CRM_RoundcubeCommon::display_thread_attachments'));
DB::Execute('UPDATE rc_mail_threads_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::QFfield_thread_attachments','CRM_RoundcubeCommon::QFfield_thread_attachments'));

DB::Execute('UPDATE rc_mails_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::display_subject','CRM_RoundcubeCommon::display_subject'));
DB::Execute('UPDATE rc_mails_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::QFfield_related','CRM_RoundcubeCommon::QFfield_related'));
DB::Execute('UPDATE rc_mails_field SET param=%s WHERE field=%s',array('__RECORDSETS__::;CRM_RoundcubeCommon::related_crits','Related'));
DB::Execute('UPDATE rc_mails_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::display_attachments','CRM_RoundcubeCommon::display_attachments'));
DB::Execute('UPDATE rc_mails_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::QFfield_attachments','CRM_RoundcubeCommon::QFfield_attachments'));
DB::Execute('UPDATE rc_mails_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::QFfield_body','CRM_RoundcubeCommon::QFfield_body'));
DB::Execute('UPDATE rc_mails_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::display_mail_thread','CRM_RoundcubeCommon::display_mail_thread'));
DB::Execute('UPDATE rc_mails_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::QFfield_mail_thread','CRM_RoundcubeCommon::QFfield_mail_thread'));
DB::Execute('UPDATE rc_mails_callback SET callback=%s WHERE callback=%s',array('CRM_MailCommon::QFfield_hidden','CRM_RoundcubeCommon::QFfield_hidden'));
Utils_RecordBrowserCommon::set_tpl('rc_mails', Base_ThemeCommon::get_template_filename(CRM_MailInstall::module_name(), 'mails'));
Utils_WatchdogCommon::unregister_category('rc_mails');
Utils_RecordBrowserCommon::enable_watchdog('rc_mails', array('CRM_MailCommon', 'watchdog_label'));

Utils_RecordBrowserCommon::set_icon('rc_multiple_emails', Base_ThemeCommon::get_template_filename(CRM_MailInstall::module_name(), 'icon.png'));

Variable::set('crm_mail_default_client','CRM_Roundcube');

Utils_RecordBrowserCommon::delete_addon('rc_mail_threads', CRM_RoundcubeInstall::module_name(), 'thread_addon');
Utils_RecordBrowserCommon::new_addon('rc_mail_threads', CRM_MailInstall::module_name(), 'thread_addon', _M('E-mails'));
Utils_RecordBrowserCommon::delete_addon('rc_mails', CRM_RoundcubeInstall::module_name(), 'mail_body_addon');
Utils_RecordBrowserCommon::delete_addon('rc_mails', CRM_RoundcubeInstall::module_name(), 'attachments_addon');
Utils_RecordBrowserCommon::delete_addon('rc_mails', CRM_RoundcubeInstall::module_name(), 'mail_headers_addon');
Utils_RecordBrowserCommon::new_addon('rc_mails', CRM_MailInstall::module_name(), 'mail_body_addon', _M('Body'));
Utils_RecordBrowserCommon::new_addon('rc_mails', CRM_MailInstall::module_name(), 'attachments_addon', _M('Attachments'));
Utils_RecordBrowserCommon::new_addon('rc_mails', CRM_MailInstall::module_name(), 'mail_headers_addon', _M('Headers'));
Utils_RecordBrowserCommon::delete_addon('contact', CRM_RoundcubeInstall::module_name(), 'addon');
Utils_RecordBrowserCommon::delete_addon('company', CRM_RoundcubeInstall::module_name(), 'addon');
Utils_RecordBrowserCommon::new_addon('contact', CRM_MailInstall::module_name(), 'addon', _M('E-mails'));
Utils_RecordBrowserCommon::new_addon('company', CRM_MailInstall::module_name(), 'addon', _M('E-mails'));
Utils_RecordBrowserCommon::delete_addon('contact', CRM_RoundcubeInstall::module_name(), 'mail_addresses_addon');
Utils_RecordBrowserCommon::delete_addon('company', CRM_RoundcubeInstall::module_name(), 'mail_addresses_addon');
Utils_RecordBrowserCommon::new_addon('contact', CRM_MailInstall::module_name(), 'mail_addresses_addon', _M('E-mail addresses'));
Utils_RecordBrowserCommon::new_addon('company', CRM_MailInstall::module_name(), 'mail_addresses_addon', _M('E-mail addresses'));

ModuleManager::create_load_priority_array();
ModuleManager::create_common_cache();
