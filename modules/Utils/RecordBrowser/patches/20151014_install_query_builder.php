<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

$module_name = 'Utils/QueryBuilder';
if (ModuleManager::is_installed($module_name) < 0) {
    ModuleManager::install($module_name);
}
