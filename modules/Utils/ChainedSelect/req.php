<?php
/**
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-Utils
 * @subpackage ChainedSelect
 */
if(!isset($_POST['values']) || !isset($_POST['dest_id']) || !isset($_POST['cid']))
	die('alert(\'Invalid request\')');
	
define('JS_OUTPUT',1);
define('CID',$_POST['cid']); 
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

$_POST['values'] = json_decode($_POST['values']);
$_POST['parameters'] = json_decode($_POST['parameters']);

foreach($_POST['values'] as $k=>$v) { //verify post
    if(!is_string($v)) return;
}

if (isset($_POST['defaults'])) $_POST['defaults'] = json_decode($_POST['defaults']);
else $_POST['defaults'] = null;
$_REQUEST['values'] = $_GET['values'] = $_POST['values'];
$_REQUEST['defaults'] = $_GET['defaults'] = $_POST['defaults'];
$_REQUEST['parameters'] = $_GET['parameters'] = $_POST['parameters'];

if (!isset($_SESSION['client']['utils_chainedselect'][$_POST['dest_id']])) {
	// bug tracking
	error_log(print_r($_POST,true)."\n-----------------------------------------\n".print_r($_SESSION['client'],true)."\n==============================================\n",3,'data/chainselect.log');
} else {
	require_once($_SESSION['client']['utils_chainedselect'][$_POST['dest_id']]);
}
?>