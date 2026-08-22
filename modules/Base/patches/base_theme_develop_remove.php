<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');
if (ModuleManager::is_installed('Base_Theme_Administrator')==-1) return;

@DB::DropTable('base_theme_themeup');

?>