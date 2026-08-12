<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// Custom translation overrides used to live at modules/<Module>/lang/<code>_custom.php
// (see Base_LangCommon::append_custom()) - moved to data/Base_Lang/custom/<module>/<code>.php
// so per-instance edits never touch the modules/ tree. Migrate whatever any
// installed module already wrote there, then remove the old file.
global $custom_translations;
$custom_translations_backup = $custom_translations;

$modules = ModuleManager::get_load_priority_array();
if ($modules) foreach ($modules as $row) {
	$module = $row['name'];
	$dir = 'modules/'.str_replace('_','/',$module).'/lang';
	if (!is_dir($dir)) continue;

	foreach ((glob($dir.'/*_custom.php') ?: array()) as $old_file) {
		$lang = basename($old_file, '_custom.php');
		$custom_translations = array();
		include($old_file);
		if ($custom_translations) {
			Base_LangCommon::append_custom($module, $lang, $custom_translations);
		}
		unlink($old_file);
	}
}

$custom_translations = $custom_translations_backup;

?>
