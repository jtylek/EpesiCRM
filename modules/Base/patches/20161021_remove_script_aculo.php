<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

ModuleManager::create_load_priority_array();

$mod = 'Tests_Colorpicker';
DB::Execute('DELETE FROM modules WHERE name=%s',array($mod));
$mod = 'Libs_ScriptAculoUs';
DB::Execute('DELETE FROM modules WHERE name=%s',array($mod));
