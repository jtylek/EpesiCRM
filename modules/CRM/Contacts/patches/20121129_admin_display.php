<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

Utils_RecordBrowserCommon::set_display_callback('contact', 'Admin', array('CRM_ContactsCommon', 'display_admin'));

?>
