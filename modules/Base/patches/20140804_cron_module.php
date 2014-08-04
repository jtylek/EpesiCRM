<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');
if (ModuleManager::is_installed('Base_Cron')>=0) return;

ModuleManager::install('Base_Cron');

?>