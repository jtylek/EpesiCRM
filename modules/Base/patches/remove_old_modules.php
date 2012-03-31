<?php

$modules = array('Apps_Forum', 'Apps_Gallery', 'Apps_StaticPage', 'Apps_TwisterGame', 'Base_ModuleManager', 'Libs_Lytebox', 'Tests_BookmarkBrowser', 'Utils_BookmarkBrowser', 'Tests_Lytebox', 'Tools_FontSize', 'Utils_Gallery', 'Utils_BookmarkBrowser');
foreach($modules as $m) {
	if(DB::GetOne('SELECT 1 FROM modules WHERE name=%s',array($m)) && !is_dir('modules/'.str_replace('_','/',$m))) {
		DB::Execute('DELETE FROM modules WHERE name=%s',array($m));
		Base_ThemeCommon::uninstall_default_theme($m);
		ModuleManager::remove_data_dir($m);
	}
}

ModuleManager::install('Utils_RecordBrowser_RecordPickerFS', 0, false);
ModuleManager::install('Utils_RecordBrowser_RecordPicker');

?>
