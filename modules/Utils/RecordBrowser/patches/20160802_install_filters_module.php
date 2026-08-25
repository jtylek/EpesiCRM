<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

if (!Utils_RecordBrowser_FiltersInstall::is_installed()) {
    ModuleManager::install(Utils_RecordBrowser_FiltersInstall::module_name());
}

DB::Execute('UPDATE 
				base_user_settings 
			SET 
				module=%s 
			WHERE 
				module=%s 
				AND 
				variable ' . DB::like() . ' %s', array('Utils_RecordBrowser_Filters', 'Utils_RecordBrowser', '%_filters'));
