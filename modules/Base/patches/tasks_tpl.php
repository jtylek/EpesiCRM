<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

if (ModuleManager::is_installed('CRM_Tasks')==-1) return;

Utils_RecordBrowserCommon::set_tpl('task', '');

?>