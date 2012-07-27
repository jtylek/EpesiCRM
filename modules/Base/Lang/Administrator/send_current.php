<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-lang
 * @subpackage timesheet
 */
if(!isset($_POST['cid']))
	die('alert(\'Invalid request\')');

define('JS_OUTPUT',1);
define('CID',$_POST['cid']); 
define('READ_ONLY_SESSION',true);
require_once('../../../../include.php');
ModuleManager::load_modules();

global $custom_translations;

$langs = Base_LangCommon::get_installed_langs();

foreach ($langs as $l) {
	$ts = Base_LangCommon::get_langpack($l, 'custom');
	foreach ($ts as $o=>$t) {
		if (!$t) continue;
		Base_Lang_AdministratorCommon::send_translation($l, $o, $t);
	}
}

?>