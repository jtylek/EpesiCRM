<?php

define('CID',false);
require_once('../../../../include.php');
ModuleManager::load_modules();

$lang = Base_LangCommon::get_lang_code();

function filename($lang) {
	return 'modules/Base/EssClient/tos/'.$lang.'_privacy.html';
}

if (!file_exists(filename($lang))) $lang = 'en';
$message = file_get_contents(filename($lang));

Utils_FrontPageCommon::display(__('Privacy Policy'), $message);

?>
