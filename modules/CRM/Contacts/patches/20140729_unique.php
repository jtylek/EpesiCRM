<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

Utils_RecordBrowserCommon::set_QFfield_callback('company', 'Tax ID', array('CRM_ContactsCommon', 'QFfield_tax_id'));
Utils_RecordBrowserCommon::set_QFfield_callback('company', 'Company Name', array('CRM_ContactsCommon', 'QFfield_cname'));
