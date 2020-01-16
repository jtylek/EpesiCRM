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
define('READ_ONLY_SESSION',true);
require_once('../../../../../include.php');
ModuleManager::load_modules();

if (!isset($_SESSION['client']['quickform'])) {
	print('<ul><li>Session expired, please reload the page</li></ul>');
	return;
}
if (!isset($_SESSION['client']['quickform']['autocomplete'][$_GET['key']])) {
    die('<ul><li style="font-weight: bold;text-align:center;">'.__('Search disabled in grid view').'</li></ul>');
}
$params = $_SESSION['client']['quickform']['autocomplete'][$_GET['key']];
$string = $_POST[$params['field']];
$callback = $params['callback'];

if (is_callable($callback)) {
/*	if (strlen($string)<2)
		print('<ul><li class="informal">'.__('Minimum %d letters are required.',array(2)).'</li></ul>');
	else {*/
		array_unshift($params['args'], $string);
		print(call_user_func_array($callback, $params['args']));
//	}
} else
	print('<ul><li>Error: method ('.print_r($callback,true).') not callable</li></ul>');
?>