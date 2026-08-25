<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

Utils_RecordBrowserCommon::set_description_callback('contact', array('CRM_ContactsCommon','contact_format_default'));
Utils_RecordBrowserCommon::set_description_callback('company', array('CRM_ContactsCommon','company_format_default'));
