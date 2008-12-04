<?php
/**
 * Lang class.
 *
 * This class provides translations manipulation.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage lang
 */

define('_VALID_ACCESS',1);
require_once('../../../include/magicquotes.php');

/**
 * This class provides inline translation method.
 */
header("Content-type: text/javascript");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past

if(!isset($_POST['parent']) || !isset($_POST['oryg']) || !isset($_POST['trans']))
	die('Invalid request');
$parent = $_POST['parent'];
$trans = $_POST['trans'];
$oryg = $_POST['oryg'];
define('JS_OUTPUT',1);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Acl::check('Administration','Modules') || !Base_MaintenanceModeCommon::get_mode()) return;

Base_LangCommon::load();
global $translations;
$translations[$parent][$oryg]=$trans;
Base_LangCommon::save();
?>
