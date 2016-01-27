<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

$callback = 'Utils_RecordBrowserCommon::display_linked_field_label';

Utils_RecordBrowserCommon::display_callback_cache('contact');
$cache = & Utils_RecordBrowserCommon::$display_callback_table['contact'];

$field = 'First Name';
if ($cache[$field] == 'CRM_ContactsCommon::display_fname') {
    Utils_RecordBrowserCommon::set_display_callback('contact', $field, $callback);
    $cache[$field] = $callback;
}
$field = 'Last Name';
if ($cache[$field] == 'CRM_ContactsCommon::display_lname') {
    Utils_RecordBrowserCommon::set_display_callback('contact', $field, $callback);
    $cache[$field] = $callback;
}

Utils_RecordBrowserCommon::display_callback_cache('company');
$cache = & Utils_RecordBrowserCommon::$display_callback_table['company'];
$field = 'Company Name';
if ($cache[$field] == 'CRM_ContactsCommon::display_cname') {
    Utils_RecordBrowserCommon::set_display_callback('company', $field, $callback);
    $cache[$field] = $callback;
}
