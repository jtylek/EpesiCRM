<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

/******* Helpers *******/

function replace_in_db($table, $field, $search, $replace)
{
    DB::Execute("UPDATE {$table} SET {$field}=REPLACE({$field}, " . DB::qstr($search) . ", " . DB::qstr($replace) . ")");
}

function replace_to_mail_in($table, $field)
{
    replace_in_db($table, $field, 'CRM_Roundcube', 'CRM_Mail');
}

/**** End of helpers ****/


if (!DB::GetOne('SELECT 1 FROM modules WHERE name=%s', array('CRM_Mail'))) {
    DB::Execute('INSERT INTO modules(name,version,state) VALUES(%s,0,0)', array('CRM_Mail'));
}
ModuleManager::create_data_dir('CRM_Mail');
@file_put_contents(DATA_DIR . '/CRM_Mail/.htaccess', "deny from all\n");

if (!file_exists(DATA_DIR . '/CRM_Mail/attachments') && file_exists(DATA_DIR . '/CRM_Roundcube/attachments')) {
    rename(DATA_DIR . '/CRM_Roundcube/attachments', DATA_DIR . '/CRM_Mail/attachments');
}

Base_ThemeCommon::install_default_theme('CRM_Mail');

if (!Variable::get('crm_mail_global_signature', false)) {
    Variable::set('crm_mail_global_signature', Variable::get('crm_roundcube_global_signature', false));
    Variable::delete('crm_roundcube_global_signature', false);
}
Variable::set('crm_mail_default_client', 'CRM_Roundcube');

if (!Utils_CommonDataCommon::get_id('CRM/Mail')) {
    Utils_CommonDataCommon::rename_key('CRM', 'Roundcube', 'Mail');
} elseif (!Utils_CommonDataCommon::get_id('CRM/Mail/Security')) {
    Utils_CommonDataCommon::new_array('CRM/Mail/Security', array('tls' => _M('TLS'), 'ssl' => _M('SSL')), true, true);
}
Utils_CommonDataCommon::get_id('CRM/Roundcube/Security', true); // clear cache to avoid deleting moved tree element
Utils_CommonDataCommon::remove('CRM/Roundcube/Security');
$parent = Utils_CommonDataCommon::get_array('CRM/Roundcube', 'value', false, true);
if (empty($parent)) {
    Utils_CommonDataCommon::remove('CRM/Roundcube');
}
replace_in_db('rc_accounts_field', 'param', 'CRM/Roundcube', 'CRM/Mail');

replace_to_mail_in('recordbrowser_processing_methods', 'func');
replace_to_mail_in('rc_related_callback', 'callback');
replace_to_mail_in('rc_accounts_callback', 'callback');
replace_to_mail_in('rc_multiple_emails_callback', 'callback');
replace_to_mail_in('rc_mail_threads_callback', 'callback');
replace_to_mail_in('rc_mails_callback', 'callback');
replace_to_mail_in('rc_mails_field', 'param');

replace_in_db('recordbrowser_table_properties', 'tpl', 'CRM/Roundcube', 'CRM/Mail');
replace_in_db('recordbrowser_table_properties', 'icon', 'CRM/Roundcube', 'CRM/Mail');
replace_to_mail_in('utils_watchdog_category', 'callback');

replace_to_mail_in('recordbrowser_addon', 'module');

ModuleManager::create_load_priority_array();
ModuleManager::create_common_cache();
Cache::clear();
