<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

DB::Execute('UPDATE base_dashboard_applets SET module_name=%s WHERE module_name=%s',array('CRM_Mail','CRM_Roundcube'));
DB::Execute('UPDATE base_dashboard_default_applets SET module_name=%s WHERE module_name=%s',array('CRM_Mail','CRM_Roundcube'));
