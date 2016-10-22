<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

ModuleManager::create_load_priority_array();

$mod = 'Utils_Menu';
DB::Execute('DELETE FROM modules WHERE name=%s',array($mod));
