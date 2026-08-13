<?php

define('CID',false);
require_once('../../../../include.php');
ModuleManager::load_modules();

$lang = Base_LangCommon::get_lang_code();

function filename($lang) {
	return 'modules/Base/EssClient/tos/'.$lang.'_tos.html';
}

if (!file_exists(filename($lang))) $lang = 'en';
$message = file_get_contents(filename($lang));
// {{CURRENT_YEAR}} in the content file - this is a plain HTML file read
// straight in, never parsed by Smarty, so the copyright end-year can't be
// a template tag; substituted here instead so it never needs a manual edit.
$message = str_replace('{{CURRENT_YEAR}}', date('Y'), $message);

Utils_FrontPageCommon::display(__('Terms of Service'), $message);

?>
