<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

Utils_RecordBrowserCommon::new_addon('contact', 'Apps/ActivityReport', 'contact_addon', array('Apps_ActivityReportCommon', 'contact_addon_label'));