<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage tooltip
 */
if(!isset($_POST['tab']) || !isset($_POST['r_id']) || !isset($_POST['id']) || !isset($_POST['cid']))
	die('Invalid request'.print_r($_POST,true));

define('JS_OUTPUT',1);
define('CID',$_POST['cid']); 
define('READ_ONLY_SESSION',1); 
require_once('../../../include.php');
ModuleManager::load_modules();

$tab = json_decode($_POST['tab']);
$r_id = json_decode($_POST['r_id']);
$id = json_decode($_POST['id']);

print(Utils_RecordBrowserCommon::get_edit_details($tab, $r_id, $id));
?>