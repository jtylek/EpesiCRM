<?php
/**
 * Autocomplete - update suggestbox
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-libs
 * @subpackage QuickForm
 */
if(!isset($_GET['key']) || !isset($_GET['cid'])  || !is_numeric($_GET['cid']))
	die('<ul><li class="Informal">Error: Invalid request</li></ul>');
	
define('CID',$_GET['cid']); 
require_once('../../../../../include.php');
ModuleManager::load_modules();

$params = $_SESSION['client']['quickform']['autocomplete'][$_GET['key']];
$string = $_POST[$params['field']];
$callback = $params['callback'];

if (is_callable($callback)) {
	array_unshift($params['args'], $string);
	print(call_user_func_array($callback, $params['args']));
} else
	print('<ul><li>Error: method ('.print_r($callback,true).') not callable</li></ul>');
?>