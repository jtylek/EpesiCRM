<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

$mod = 'Base_Notify';
if (ModuleManager::is_installed($mod) < 0) {
    ModuleManager::install($mod, 0);
}
