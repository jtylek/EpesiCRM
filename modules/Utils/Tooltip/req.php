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

$tooltip_settings = $_SESSION['client']['utils_tooltip']['callbacks'][$_POST['tooltip_id']];
$callback = $tooltip_settings['callback'];
$args = $tooltip_settings['args'];
$safe_html = !empty($tooltip_settings['safe_html']);
$keep_table = !empty($tooltip_settings['keep_table']);

$content = call_user_func_array($callback, $args);
// Callbacks (format_info_tooltip() etc.) return HTML; the adminlte client
// (theme_adminltedark/tooltip.js) by default only ever displays plain text,
// so convert here once rather than relying on the client to strip markup
// itself - naive DOM textContent extraction collapsed block/row boundaries
// into a single run-on line instead of one "Label: value" per line. Callers
// that opted into $safe_html via ajax_open_tag_attrs() get to_safe_html()'s
// bold-label-preserving mode instead, since tooltip.js renders their
// response via innerHTML; $keep_table is a further opt-in on top of that,
// only for callbacks whose content is a real <table> that needs its columns
// to stay aligned (e.g. Watchdog's changes-list tooltip) rather than
// flattened to "Label: value" lines.
if (Base_ThemeCommon::is_adminlte_family()) {
	print($safe_html ? Utils_TooltipCommon::to_safe_html($content, $keep_table) : Utils_TooltipCommon::to_plain_text($content));
} else {
	print($content);
}
?>