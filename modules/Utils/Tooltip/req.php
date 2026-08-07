<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
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

$content = call_user_func_array($callback, $args);
// Callbacks (format_info_tooltip() etc.) return HTML; the adminlte client
// (theme_adminltedark/tooltip.js) only ever displays plain text, so convert
// here once rather than relying on the client to strip markup itself -
// naive DOM textContent extraction collapsed block/row boundaries into a
// single run-on line instead of one "Label: value" per line.
print(Base_ThemeCommon::is_adminlte_family() ? Utils_TooltipCommon::to_plain_text($content) : $content);
?>