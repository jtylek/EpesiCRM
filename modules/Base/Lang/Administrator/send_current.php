<?php
/**
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-lang
 * @subpackage timesheet
 */
if(!isset($_POST['cid']) || !isset($_POST['lang']))
	die('alert(\'Invalid request\')');

define('JS_OUTPUT',1);
define('CID',$_POST['cid']); 
define('READ_ONLY_SESSION',true);
require_once('../../../../include.php');
ModuleManager::load_modules();

if (!Base_AclCommon::i_am_admin()) {
    die('');
}

$lang = $_POST['lang'];
Base_Lang_AdministratorCommon::send_lang($lang);

?>