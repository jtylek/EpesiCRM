<?php
/**
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license Commercial
 * @version 1.9.0
 * @package epesi-utils
 * @subpackage recordbrowser
 */
if (!isset($_POST['tab']) || !isset($_POST['visible']) || !isset($_POST['cid']))
	die('Invalid request');

define('JS_OUTPUT',1);
define('CID',$_POST['cid']); 
define('READ_ONLY_SESSION',true);
require_once('../../../../include.php');
ModuleManager::load_modules();

if (!Acl::is_user()) die('');

$tab = json_decode($_POST['tab']);
$visible = json_decode($_POST['visible']);

if (!is_numeric($visible) || !is_string($tab)) 
	die('Invalid request');

Utils_RecordBrowser_FiltersCommon::set_filters_visibility($tab, $visible)

?>
