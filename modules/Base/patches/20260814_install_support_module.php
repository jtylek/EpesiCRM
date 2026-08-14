<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

if (ModuleManager::is_installed('Base_Support')>=0) return;

ModuleManager::install('Base_Support');

?>
