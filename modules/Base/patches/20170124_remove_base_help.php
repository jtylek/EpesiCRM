<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

ModuleManager::create_load_priority_array();

$mod = 'Base_Help';
Base_ThemeCommon::uninstall_default_theme($mod);
DB::Execute('DELETE FROM modules WHERE name=%s',array($mod));
