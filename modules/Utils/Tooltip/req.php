<?php
/**
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-utils
 * @subpackage tooltip
 */
if(!isset($_POST['tooltip_id']) || !isset($_POST['cid']))
	die('Invalid request');

define('JS_OUTPUT',1);
define('CID',$_POST['cid']); 
define('READ_ONLY_SESSION',1); 
require_once('../../../include.php');
ModuleManager::load_modules();

if (!isset($_SESSION['client']['utils_tooltip']['callbacks'][$_POST['tooltip_id']]))
	die('Invalid tooltip - too many tabs open?');

$callback = $_SESSION['client']['utils_tooltip']['callbacks'][$_POST['tooltip_id']]['callback'];
$args = $_SESSION['client']['utils_tooltip']['callbacks'][$_POST['tooltip_id']]['args'];

print(call_user_func_array($callback, $args));
?>