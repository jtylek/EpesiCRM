<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

Utils_RecordBrowserCommon::set_display_callback('crm_meeting', 'Date', array('CRM_MeetingCommon', 'display_date'));
?>