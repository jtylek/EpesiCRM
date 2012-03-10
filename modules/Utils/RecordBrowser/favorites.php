<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage recordbrowser
 */
if (!isset($_POST['id']) || !isset($_POST['state']) || !isset($_POST['element']) || !isset($_POST['tab']) || !isset($_POST['cid']))
	die('Invalid request: '.print_r($_POST,true));

define('JS_OUTPUT',1);
define('CID',$_POST['cid']); 
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

$id = json_decode($_POST['id']);
$tab = json_decode($_POST['tab']);
$state = json_decode($_POST['state']);
$element = json_decode($_POST['element']);

if (!Acl::is_user()) die('alert("Unauthorized access");');

Utils_RecordBrowserCommon::set_favs($tab, $id, $state);
print('$("'.$element.'").innerHTML="'.Epesi::escapeJS(Utils_RecordBrowserCommon::get_fav_button_tags($tab, $id, $state)).'";');

?>
