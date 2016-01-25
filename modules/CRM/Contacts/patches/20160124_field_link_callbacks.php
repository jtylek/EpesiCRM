<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

Utils_RecordBrowserCommon::set_display_callback('contact', 'First Name', 'Utils_RecordBrowserCommon::display_linked_field_label');
Utils_RecordBrowserCommon::set_display_callback('contact', 'Last Name', 'Utils_RecordBrowserCommon::display_linked_field_label');
Utils_RecordBrowserCommon::set_display_callback('company', 'Company Name', 'Utils_RecordBrowserCommon::display_linked_field_label');
